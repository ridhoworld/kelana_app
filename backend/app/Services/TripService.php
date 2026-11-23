<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\ItineraryDay;
use App\Models\Trip;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class TripService
{
    public function createTrip(string $userId, array $data): Trip
    {
        return DB::transaction(function () use ($userId, $data) {
            $trip = Trip::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'budget_amount' => $data['budget_amount'] ?? null,
                'budget_currency' => strtoupper($data['budget_currency'] ?? 'IDR'),
                'status' => $data['status'] ?? 'rencana',
                'is_template' => $data['is_template'] ?? false,
                'source_trip_id' => $data['source_trip_id'] ?? null,
            ]);

            if (! empty($data['destinations'])) {
                $this->syncDestinations($trip, $data['destinations']);
            } elseif (! empty($data['destination_ids'])) {
                $items = collect($data['destination_ids'])
                    ->values()
                    ->map(fn ($id, $idx) => ['destination_id' => $id, 'position' => $idx + 1])
                    ->all();
                $this->syncDestinations($trip, $items);
            }

            $this->generateDays($trip);

            return $trip->load(['destinations', 'days.items', 'expenses', 'packingItems']);
        });
    }

    public function syncDestinations(Trip $trip, array $destinations): void
    {
        $syncData = [];
        $position = 1;

        foreach ($destinations as $item) {
            $destId = is_array($item) ? $item['destination_id'] : $item;

            $owned = Destination::where('id', $destId)
                ->where('user_id', $trip->user_id)
                ->first();

            if (! $owned) {
                continue;
            }

            $syncData[$owned->id] = [
                'position' => is_array($item) && isset($item['position']) ? (int) $item['position'] : $position,
                'arrival_date' => is_array($item) ? ($item['arrival_date'] ?? null) : null,
                'departure_date' => is_array($item) ? ($item['departure_date'] ?? null) : null,
            ];

            if ($owned->status === 'wishlist') {
                $owned->update(['status' => 'direncanakan']);
            }

            $position++;
        }

        $trip->destinations()->sync($syncData);
    }

    public function generateDays(Trip $trip): void
    {
        $period = CarbonPeriod::create(
            Carbon::parse($trip->start_date),
            Carbon::parse($trip->end_date)
        );

        $existingByDate = $trip->days()->get()->keyBy(fn (ItineraryDay $d) => $d->day_date->format('Y-m-d'));
        $validDates = [];
        $dayNumber = 1;

        $firstDestId = $trip->destinations()->first()?->id;

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $validDates[] = $dateStr;

            if ($existingByDate->has($dateStr)) {
                $day = $existingByDate->get($dateStr);
                if ($day->day_number !== $dayNumber) {
                    $day->update(['day_number' => $dayNumber]);
                }
            } else {
                ItineraryDay::create([
                    'trip_id' => $trip->id,
                    'day_number' => $dayNumber,
                    'day_date' => $dateStr,
                    'destination_id' => $firstDestId,
                ]);
            }

            $dayNumber++;
        }

        // Remove days outside new date range
        $trip->days()->whereNotIn('day_date', $validDates)->delete();
    }

    public function duplicateTrip(Trip $source, array $overrides = []): Trip
    {
        return DB::transaction(function () use ($source, $overrides) {
            $source->load(['destinations', 'days.items', 'packingItems']);

            $startDate = Carbon::parse($overrides['start_date'] ?? $source->start_date);
            $daySpan = max(0, Carbon::parse($source->start_date)->diffInDays(Carbon::parse($source->end_date)));
            $endDate = isset($overrides['end_date'])
                ? Carbon::parse($overrides['end_date'])
                : $startDate->copy()->addDays($daySpan);

            $newTrip = Trip::create([
                'user_id' => $source->user_id,
                'name' => $overrides['name'] ?? ($source->name.' (Salinan)'),
                'timezone' => $source->timezone,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'budget_amount' => $source->budget_amount,
                'budget_currency' => $source->budget_currency,
                'status' => 'rencana',
                'is_template' => false,
                'source_trip_id' => $source->id,
            ]);

            $destPayload = $source->destinations->map(fn ($d) => [
                'destination_id' => $d->id,
                'position' => $d->pivot->position,
            ])->all();

            if (! empty($destPayload)) {
                $this->syncDestinations($newTrip, $destPayload);
            }

            $this->generateDays($newTrip);
            $newDaysByNumber = $newTrip->days()->get()->keyBy('day_number');

            foreach ($source->days as $srcDay) {
                $targetDay = $newDaysByNumber->get($srcDay->day_number);
                if (! $targetDay) {
                    continue;
                }

                $targetDay->update([
                    'destination_id' => $srcDay->destination_id,
                    'notes' => $srcDay->notes,
                ]);

                foreach ($srcDay->items as $item) {
                    $targetDay->items()->create([
                        'title' => $item->title,
                        'category' => $item->category,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                        'location' => $item->location,
                        'estimated_cost' => $item->estimated_cost,
                        'booking_ref' => null,
                        'notes' => $item->notes,
                        'position' => $item->position,
                        'is_done' => false,
                    ]);
                }
            }

            foreach ($source->packingItems as $packing) {
                $newTrip->packingItems()->create([
                    'name' => $packing->name,
                    'category' => $packing->category,
                    'quantity' => $packing->quantity,
                    'is_packed' => false,
                    'position' => $packing->position,
                ]);
            }

            return $newTrip->load(['destinations', 'days.items', 'expenses', 'packingItems']);
        });
    }

    public function buildBudgetSummary(Trip $trip): array
    {
        $totalEstimated = (int) $trip->itineraryItems()->sum('estimated_cost');
        $totalSpent = (int) $trip->expenses()->sum('amount');
        $budgetAmount = $trip->budget_amount;
        $remaining = $budgetAmount !== null ? $budgetAmount - $totalSpent : null;
        $variance = $totalEstimated - $totalSpent;

        $packingTotal = $trip->packingItems()->count();
        $packingPacked = $trip->packingItems()->where('is_packed', true)->count();

        return [
            'budget_amount' => $budgetAmount,
            'budget_currency' => $trip->budget_currency,
            'total_estimated' => $totalEstimated,
            'total_spent' => $totalSpent,
            'remaining_budget' => $remaining,
            'estimate_variance' => $variance,
            'packing_total' => $packingTotal,
            'packing_packed' => $packingPacked,
            'packing_unpacked' => $packingTotal - $packingPacked,
        ];
    }
}
