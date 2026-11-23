<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'city',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'category',
        'priority',
        'status',
        'difficulty',
        'cost_min',
        'cost_max',
        'cost_currency',
        'notes',
        'visited_on',
        'cover_url',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'priority' => 'integer',
            'cost_min' => 'integer',
            'cost_max' => 'integer',
            'visited_on' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bestMonths(): HasMany
    {
        return $this->hasMany(DestinationBestMonth::class)->orderBy('month');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DestinationLink::class)->orderByDesc('added_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'destination_tags');
    }

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_destinations')
            ->withPivot(['position', 'arrival_date', 'departure_date'])
            ->orderByDesc('start_date');
    }
}
