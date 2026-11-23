<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\DestinationLink;
use App\Models\Tag;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DestinationController extends Controller
{
    private const CATEGORIES = ['alam', 'kota', 'kuliner', 'sejarah', 'pantai', 'budaya', 'lainnya'];
    private const STATUSES = ['wishlist', 'direncanakan', 'dikunjungi'];
    private const DIFFICULTIES = ['mudah', 'sedang', 'sulit'];
    private const LINK_TYPES = ['artikel', 'video', 'info', 'lainnya'];

    public function __construct(private TripService $tripService) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $currentMonth = (int) now()->format('n');

        $query = Destination::where('user_id', $userId)
            ->with(['bestMonths', 'links', 'tags']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', (int) $request->input('priority'));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->string('difficulty'));
        }

        if ($request->filled('month')) {
            $month = (int) $request->input('month');
            $query->whereHas('bestMonths', fn ($q) => $q->where('month', $month));
        }

        if ($request->filled('cost_max')) {
            $costMax = (int) $request->input('cost_max');
            $query->where(function ($q) use ($costMax) {
                $q->whereNull('cost_min')->orWhere('cost_min', '<=', $costMax);
            });
        }

        if ($request->filled('tag')) {
            $tagName = $request->string('tag');
            $query->whereHas('tags', fn ($q) => $q->where('name', $tagName));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('city', 'like', $search)
                    ->orWhere('country', 'like', $search)
                    ->orWhere('notes', 'like', $search);
            });
        }

        $sort = $request->input('sort', 'priority_desc');
        match ($sort) {
            'priority_asc' => $query->orderBy('priority')->orderByDesc('created_at'),
            'name_asc' => $query->orderBy('name'),
            'cost_asc' => $query->orderByRaw('COALESCE(cost_min, 999999999999) ASC'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('priority')->orderByDesc('created_at'),
        };

        $destinations = $query->get()->map(fn (Destination $d) => $this->formatDestination($d, $currentMonth));

        return response()->json([
            'data' => $destinations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateDestination($request);
        $userId = $request->user()->id;

        $destination = DB::transaction(function () use ($validated, $userId) {
            $dest = Destination::create([
                'user_id' => $userId,
                'name' => $validated['name'],
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'],
                'country_code' => isset($validated['country_code']) ? strtoupper($validated['country_code']) : null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'category' => $validated['category'] ?? null,
                'priority' => $validated['priority'] ?? 3,
                'status' => $validated['status'] ?? 'wishlist',
                'difficulty' => $validated['difficulty'] ?? null,
                'cost_min' => $validated['cost_min'] ?? null,
                'cost_max' => $validated['cost_max'] ?? null,
                'cost_currency' => strtoupper($validated['cost_currency'] ?? 'IDR'),
                'notes' => $validated['notes'] ?? null,
                'visited_on' => $validated['visited_on'] ?? null,
                'cover_url' => $validated['cover_url'] ?? null,
            ]);

            if (isset($validated['best_months'])) {
                $this->syncBestMonths($dest, $validated['best_months']);
            }

            if (isset($validated['tags'])) {
                $this->syncTags($dest, $userId, $validated['tags']);
            }

            if (! empty($validated['links'])) {
                foreach ($validated['links'] as $link) {
                    $dest->links()->create([
                        'url' => $link['url'],
                        'label' => $link['label'] ?? null,
                        'link_type' => $link['link_type'] ?? 'artikel',
                    ]);
                }
            }

            return $dest->load(['bestMonths', 'links', 'tags']);
        });

        return response()->json([
            'message' => 'Destinasi berhasil ditambahkan.',
            'data' => $this->formatDestination($destination, (int) now()->format('n')),
        ], 201);
    }

    public function show(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeOwner($request, $destination);
        $destination->load(['bestMonths', 'links', 'tags', 'trips']);

        $data = $this->formatDestination($destination, (int) now()->format('n'));
        $data['trips'] = $destination->trips->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'start_date' => $t->start_date->format('Y-m-d'),
            'end_date' => $t->end_date->format('Y-m-d'),
            'status' => $t->status,
        ])->values();

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeOwner($request, $destination);
        $validated = $this->validateDestination($request, true);
        $userId = $request->user()->id;

        DB::transaction(function () use ($destination, $validated, $userId) {
            $fields = collect($validated)->except(['best_months', 'tags', 'links'])->all();
            if (isset($fields['country_code'])) {
                $fields['country_code'] = strtoupper($fields['country_code']);
            }
            if (isset($fields['cost_currency'])) {
                $fields['cost_currency'] = strtoupper($fields['cost_currency']);
            }

            $destination->update($fields);

            if (array_key_exists('best_months', $validated)) {
                $this->syncBestMonths($destination, $validated['best_months'] ?? []);
            }

            if (array_key_exists('tags', $validated)) {
                $this->syncTags($destination, $userId, $validated['tags'] ?? []);
            }
        });

        $destination->load(['bestMonths', 'links', 'tags']);

        return response()->json([
            'message' => 'Destinasi berhasil diperbarui.',
            'data' => $this->formatDestination($destination, (int) now()->format('n')),
        ]);
    }

    public function destroy(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeOwner($request, $destination);
        $destination->delete();

        return response()->json([
            'message' => 'Destinasi berhasil dihapus.',
        ]);
    }

    public function visit(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeOwner($request, $destination);

        $validated = $request->validate([
            'visited_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $destination->update([
            'status' => 'dikunjungi',
            'visited_on' => $validated['visited_on'],
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $destination->notes,
        ]);

        $destination->load(['bestMonths', 'links', 'tags']);

        return response()->json([
            'message' => 'Destinasi ditandai sudah dikunjungi.',
            'data' => $this->formatDestination($destination, (int) now()->format('n')),
        ]);
    }

    public function addLink(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeOwner($request, $destination);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'label' => ['nullable', 'string', 'max:200'],
            'link_type' => ['nullable', Rule::in(self::LINK_TYPES)],
        ]);

        $link = $destination->links()->create([
            'url' => $validated['url'],
            'label' => $validated['label'] ?? null,
            'link_type' => $validated['link_type'] ?? 'artikel',
        ]);

        return response()->json([
            'message' => 'Tautan referensi ditambahkan.',
            'data' => $link,
        ], 201);
    }

    public function deleteLink(Request $request, Destination $destination, DestinationLink $link): JsonResponse
    {
        $this->authorizeOwner($request, $destination);

        if ($link->destination_id !== $destination->id) {
            abort(404);
        }

        $link->delete();

        return response()->json([
            'message' => 'Tautan referensi dihapus.',
        ]);
    }

    public function createTrip(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeOwner($request, $destination);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'budget_amount' => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $trip = $this->tripService->createTrip($request->user()->id, [
            'name' => $validated['name'] ?? ('Perjalanan ke '.$destination->name),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'budget_amount' => $validated['budget_amount'] ?? $destination->cost_max,
            'budget_currency' => $validated['budget_currency'] ?? $destination->cost_currency,
            'destination_ids' => [$destination->id],
        ]);

        return response()->json([
            'message' => 'Perjalanan berhasil dibuat dari destinasi.',
            'data' => $trip,
        ], 201);
    }

    public function tags(Request $request): JsonResponse
    {
        $tags = Tag::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $tags]);
    }

    private function validateDestination(Request $request, bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => [$req, 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'priority' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'difficulty' => ['nullable', Rule::in(self::DIFFICULTIES)],
            'cost_min' => ['nullable', 'integer', 'min:0'],
            'cost_max' => ['nullable', 'integer', 'min:0', 'gte:cost_min'],
            'cost_currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'visited_on' => ['nullable', 'date', 'required_if:status,dikunjungi'],
            'cover_url' => ['nullable', 'url', 'max:500'],
            'best_months' => ['nullable', 'array'],
            'best_months.*' => ['integer', 'between:1,12'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'links' => ['nullable', 'array'],
            'links.*.url' => ['required', 'url', 'max:2000'],
            'links.*.label' => ['nullable', 'string', 'max:200'],
            'links.*.link_type' => ['nullable', Rule::in(self::LINK_TYPES)],
        ]);
    }

    private function syncBestMonths(Destination $destination, array $months): void
    {
        $destination->bestMonths()->delete();
        $unique = collect($months)->map(fn ($m) => (int) $m)->unique()->filter(fn ($m) => $m >= 1 && $m <= 12);

        foreach ($unique as $month) {
            $destination->bestMonths()->create(['month' => $month]);
        }
    }

    private function syncTags(Destination $destination, string $userId, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->map(function (string $name) use ($userId) {
                return Tag::firstOrCreate([
                    'user_id' => $userId,
                    'name' => $name,
                ])->id;
            })
            ->all();

        $destination->tags()->sync($tagIds);
    }

    private function authorizeOwner(Request $request, Destination $destination): void
    {
        if ($destination->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke destinasi ini.');
        }
    }

    private function formatDestination(Destination $d, int $currentMonth): array
    {
        $months = $d->bestMonths->pluck('month')->values()->all();

        return [
            'id' => $d->id,
            'name' => $d->name,
            'city' => $d->city,
            'country' => $d->country,
            'country_code' => $d->country_code,
            'latitude' => $d->latitude,
            'longitude' => $d->longitude,
            'category' => $d->category,
            'priority' => $d->priority,
            'status' => $d->status,
            'difficulty' => $d->difficulty,
            'cost_min' => $d->cost_min,
            'cost_max' => $d->cost_max,
            'cost_currency' => $d->cost_currency,
            'notes' => $d->notes,
            'visited_on' => $d->visited_on?->format('Y-m-d'),
            'cover_url' => $d->cover_url,
            'best_months' => $months,
            'is_in_season' => in_array($currentMonth, $months, true),
            'tags' => $d->tags->pluck('name')->values()->all(),
            'links' => $d->links->map(fn ($l) => [
                'id' => $l->id,
                'url' => $l->url,
                'label' => $l->label,
                'link_type' => $l->link_type,
                'added_at' => $l->added_at?->toIso8601String(),
            ])->values()->all(),
            'created_at' => $d->created_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }
}
