<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PackingItem;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackingItemController extends Controller
{
    public function index(Request $request, Trip $trip): JsonResponse
    {
        if ($trip->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'data' => $trip->packingItems()->get(),
        ]);
    }

    public function store(Request $request, Trip $trip): JsonResponse
    {
        if ($trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_packed' => ['nullable', 'boolean'],
        ]);

        $maxPos = (int) $trip->packingItems()->max('position');

        $item = $trip->packingItems()->create([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'umum',
            'quantity' => $validated['quantity'] ?? 1,
            'is_packed' => $validated['is_packed'] ?? false,
            'position' => $maxPos + 1,
        ]);

        return response()->json([
            'message' => 'Barang bawaan ditambahkan.',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, PackingItem $packingItem): JsonResponse
    {
        $packingItem->load('trip');
        if ($packingItem->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', 'string', 'max:50'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'is_packed' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $packingItem->update($validated);

        return response()->json([
            'message' => 'Barang bawaan diperbarui.',
            'data' => $packingItem->fresh(),
        ]);
    }

    public function destroy(Request $request, PackingItem $packingItem): JsonResponse
    {
        $packingItem->load('trip');
        if ($packingItem->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $packingItem->delete();

        return response()->json([
            'message' => 'Barang bawaan dihapus.',
        ]);
    }
}
