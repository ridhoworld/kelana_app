<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Trip extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'timezone',
        'start_date',
        'end_date',
        'budget_amount',
        'budget_currency',
        'status',
        'is_template',
        'source_trip_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'budget_amount' => 'integer',
            'is_template' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'source_trip_id');
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'trip_destinations')
            ->withPivot(['position', 'arrival_date', 'departure_date'])
            ->orderBy('trip_destinations.position');
    }

    public function days(): HasMany
    {
        return $this->hasMany(ItineraryDay::class)->orderBy('day_number');
    }

    public function itineraryItems(): HasManyThrough
    {
        return $this->hasManyThrough(ItineraryItem::class, ItineraryDay::class, 'trip_id', 'day_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->orderByDesc('paid_at');
    }

    public function packingItems(): HasMany
    {
        return $this->hasMany(PackingItem::class)->orderBy('category')->orderBy('position');
    }
}
