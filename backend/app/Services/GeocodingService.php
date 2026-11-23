<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function search(string $query, int $limit = 6): array
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $cacheKey = 'geocoding:nominatim:'.md5($normalized.'|'.$limit);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($query, $limit) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'KelanaApp/1.0 (contact@kelana.app)',
                    'Accept-Language' => 'id,en',
                ])
                    ->timeout(8)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $query,
                        'format' => 'json',
                        'addressdetails' => 1,
                        'limit' => $limit,
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json())->map(function (array $item) {
                    $address = $item['address'] ?? [];
                    $city = $address['city']
                        ?? $address['town']
                        ?? $address['village']
                        ?? $address['county']
                        ?? $address['state']
                        ?? null;

                    return [
                        'name' => $item['name'] ?: explode(',', $item['display_name'] ?? '')[0],
                        'display_name' => $item['display_name'] ?? '',
                        'city' => $city,
                        'country' => $address['country'] ?? '',
                        'country_code' => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
                        'latitude' => isset($item['lat']) ? round((float) $item['lat'], 6) : null,
                        'longitude' => isset($item['lon']) ? round((float) $item['lon'], 6) : null,
                    ];
                })->values()->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $latR = round($lat, 5);
        $lngR = round($lng, 5);
        $cacheKey = 'geocoding:nominatim:rev:'.md5($latR.'|'.$lngR);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($latR, $lngR) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'KelanaApp/1.0 (contact@kelana.app)',
                    'Accept-Language' => 'id,en',
                ])
                    ->timeout(8)
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat' => $latR,
                        'lon' => $lngR,
                        'format' => 'json',
                        'addressdetails' => 1,
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $item = $response->json();
                if (! is_array($item) || isset($item['error'])) {
                    return null;
                }

                $address = $item['address'] ?? [];
                $city = $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['county']
                    ?? $address['state']
                    ?? null;

                return [
                    'name' => $item['name'] ?: explode(',', $item['display_name'] ?? '')[0],
                    'display_name' => $item['display_name'] ?? '',
                    'city' => $city,
                    'country' => $address['country'] ?? '',
                    'country_code' => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
                    'latitude' => round($latR, 6),
                    'longitude' => round($lngR, 6),
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
