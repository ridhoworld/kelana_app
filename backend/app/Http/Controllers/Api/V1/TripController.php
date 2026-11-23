<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\TripService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripController extends Controller
{
    private const STATUSES = ['rencana', 'berjalan', 'selesai', 'dibatalkan'];

    public function __construct(private TripService $tripService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Trip::where('user_id', $request->user()->id)
            ->with(['destinations'])
            ->orderByDesc('start_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('templates_only')) {
            $query->where('is_template', true);
        }

        $trips = $query->get()->map(fn (Trip $t) => $this->formatTripListItem($t));

        return response()->json(['data' => $trips]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'is_template' => ['nullable', 'boolean'],
            'destination_ids' => ['nullable', 'array'],
            'destination_ids.*' => ['uuid'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.destination_id' => ['required', 'uuid'],
            'destinations.*.position' => ['nullable', 'integer', 'min:1'],
            'destinations.*.arrival_date' => ['nullable', 'date'],
            'destinations.*.departure_date' => ['nullable', 'date'],
        ]);

        $trip = $this->tripService->createTrip($request->user()->id, $validated);

        return response()->json([
            'message' => 'Perjalanan berhasil dibuat.',
            'data' => $this->formatTripDetail($trip),
        ], 201);
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $this->authorizeOwner($request, $trip);
        $trip->load(['destinations', 'days.items', 'days.destination', 'expenses', 'packingItems']);

        return response()->json([
            'data' => $this->formatTripDetail($trip),
        ]);
    }

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $this->authorizeOwner($request, $trip);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'is_template' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['budget_currency'])) {
            $validated['budget_currency'] = strtoupper($validated['budget_currency']);
        }

        $datesChanged = isset($validated['start_date']) || isset($validated['end_date']);
        $trip->update($validated);

        if ($datesChanged) {
            $this->tripService->generateDays($trip->fresh());
        }

        $trip->load(['destinations', 'days.items', 'days.destination', 'expenses', 'packingItems']);

        return response()->json([
            'message' => 'Perjalanan berhasil diperbarui.',
            'data' => $this->formatTripDetail($trip),
        ]);
    }

    public function destroy(Request $request, Trip $trip): JsonResponse
    {
        $this->authorizeOwner($request, $trip);
        $trip->delete();

        return response()->json([
            'message' => 'Perjalanan berhasil dihapus.',
        ]);
    }

    public function summary(Request $request, Trip $trip): JsonResponse
    {
        $this->authorizeOwner($request, $trip);
        $trip->load(['destinations', 'days.items', 'packingItems']);

        $budget = $this->tripService->buildBudgetSummary($trip);
        $unpackedItems = $trip->packingItems
            ->where('is_packed', false)
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'quantity' => $p->quantity,
            ])
            ->values();

        return response()->json([
            'data' => [
                'trip' => $this->formatTripListItem($trip),
                'budget' => $budget,
                'days_count' => $trip->days->count(),
                'items_count' => $trip->days->sum(fn ($d) => $d->items->count()),
                'items_done_count' => $trip->days->sum(fn ($d) => $d->items->where('is_done', true)->count()),
                'unpacked_items' => $unpackedItems,
            ],
        ]);
    }

    public function duplicate(Request $request, Trip $trip): JsonResponse
    {
        $this->authorizeOwner($request, $trip);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $newTrip = $this->tripService->duplicateTrip($trip, $validated);

        return response()->json([
            'message' => 'Perjalanan berhasil diduplikasi.',
            'data' => $this->formatTripDetail($newTrip),
        ], 201);
    }

    public function syncDestinations(Request $request, Trip $trip): JsonResponse
    {
        $this->authorizeOwner($request, $trip);

        $validated = $request->validate([
            'destinations' => ['required', 'array'],
            'destinations.*.destination_id' => ['required', 'uuid'],
            'destinations.*.position' => ['nullable', 'integer', 'min:1'],
            'destinations.*.arrival_date' => ['nullable', 'date'],
            'destinations.*.departure_date' => ['nullable', 'date'],
        ]);

        $this->tripService->syncDestinations($trip, $validated['destinations']);
        $trip->load(['destinations', 'days.items', 'days.destination', 'expenses', 'packingItems']);

        return response()->json([
            'message' => 'Destinasi perjalanan diperbarui.',
            'data' => $this->formatTripDetail($trip),
        ]);
    }

    private function authorizeOwner(Request $request, Trip $trip): void
    {
        if ($trip->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke perjalanan ini.');
        }
    }

    private function formatTripListItem(Trip $trip): array
    {
        $today = Carbon::today();
        $start = Carbon::parse($trip->start_date);
        $daysUntil = (int) $today->diffInDays($start, false);
        $budget = $this->tripService->buildBudgetSummary($trip);

        return [
            'id' => $trip->id,
            'name' => $trip->name,
            'timezone' => $trip->timezone,
            'start_date' => $trip->start_date->format('Y-m-d'),
            'end_date' => $trip->end_date->format('Y-m-d'),
            'days_until' => $daysUntil,
            'budget_amount' => $trip->budget_amount,
            'budget_currency' => $trip->budget_currency,
            'status' => $trip->status,
            'is_template' => $trip->is_template,
            'destinations' => $trip->destinations->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'city' => $d->city,
                'country' => $d->country,
                'position' => $d->pivot->position,
                'arrival_date' => $d->pivot->arrival_date,
                'departure_date' => $d->pivot->departure_date,
            ])->values()->all(),
            'summary' => $budget,
        ];
    }

    private function formatTripDetail(Trip $trip): array
    {
        $base = $this->formatTripListItem($trip);

        $base['days'] = $trip->days->map(fn ($day) => [
            'id' => $day->id,
            'day_number' => $day->day_number,
            'day_date' => $day->day_date->format('Y-m-d'),
            'destination_id' => $day->destination_id,
            'destination_name' => $day->destination?->name,
            'notes' => $day->notes,
            'items' => $day->items->map(fn ($item) => [
                'id' => $item->id,
                'day_id' => $item->day_id,
                'title' => $item->title,
                'category' => $item->category,
                'start_time' => $item->start_time ? substr($item->start_time, 0, 5) : null,
                'end_time' => $item->end_time ? substr($item->end_time, 0, 5) : null,
                'location' => $item->location,
                'estimated_cost' => $item->estimated_cost,
                'booking_ref' => $item->booking_ref,
                'notes' => $item->notes,
                'position' => $item->position,
                'is_done' => $item->is_done,
            ])->values()->all(),
        ])->values()->all();

        $base['expenses'] = $trip->expenses->map(fn ($e) => [
            'id' => $e->id,
            'trip_id' => $e->trip_id,
            'item_id' => $e->item_id,
            'description' => $e->description,
            'category' => $e->category,
            'amount' => $e->amount,
            'currency' => $e->currency,
            'paid_at' => $e->paid_at?->toIso8601String(),
        ])->values()->all();

        $base['packing_items'] = $trip->packingItems->map(fn ($p) => [
            'id' => $p->id,
            'trip_id' => $p->trip_id,
            'name' => $p->name,
            'category' => $p->category,
            'quantity' => $p->quantity,
            'is_packed' => $p->is_packed,
            'position' => $p->position,
        ])->values()->all();

        return $base;
    }
}
