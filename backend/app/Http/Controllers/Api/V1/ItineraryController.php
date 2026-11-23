<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItineraryController extends Controller
{
    private const CATEGORIES = ['aktivitas', 'transportasi', 'penginapan', 'makan'];

    public function updateDay(Request $request, ItineraryDay $day): JsonResponse
    {
        $day->load('trip');
        if ($day->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'destination_id' => ['nullable', 'uuid', 'exists:destinations,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $day->update($validated);

        return response()->json([
            'message' => 'Hari itinerary diperbarui.',
            'data' => $day->fresh(['destination', 'items']),
        ]);
    }

    public function storeItem(Request $request, ItineraryDay $day): JsonResponse
    {
        $day->load('trip');
        if ($day->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'location' => ['nullable', 'string', 'max:300'],
            'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'booking_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxPos = (int) $day->items()->max('position');

        $item = $day->items()->create([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'aktivitas',
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'estimated_cost' => $validated['estimated_cost'] ?? null,
            'booking_ref' => $validated['booking_ref'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'position' => $validated['position'] ?? ($maxPos + 1),
            'is_done' => false,
        ]);

        return response()->json([
            'message' => 'Kegiatan berhasil ditambahkan.',
            'data' => $this->formatItem($item),
        ], 201);
    }

    public function updateItem(Request $request, ItineraryItem $item): JsonResponse
    {
        $item->load('day.trip');
        if ($item->day->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', Rule::in(self::CATEGORIES)],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:300'],
            'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'booking_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Kegiatan berhasil diperbarui.',
            'data' => $this->formatItem($item->fresh()),
        ]);
    }

    public function destroyItem(Request $request, ItineraryItem $item): JsonResponse
    {
        $item->load('day.trip');
        if ($item->day->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Kegiatan berhasil dihapus.',
        ]);
    }

    private function formatItem(ItineraryItem $item): array
    {
        return [
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
        ];
    }
}
