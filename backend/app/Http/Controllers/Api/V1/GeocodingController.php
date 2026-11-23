<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodingController extends Controller
{
    public function __construct(private GeocodingService $geocoding) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $results = $this->geocoding->search($validated['q'], (int) ($validated['limit'] ?? 6));

        return response()->json([
            'data' => $results,
        ]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->geocoding->reverse(
            (float) $validated['lat'],
            (float) $validated['lng']
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
