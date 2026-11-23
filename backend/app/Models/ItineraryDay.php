<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItineraryDay extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'day_number',
        'day_date',
        'destination_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'day_date' => 'date:Y-m-d',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class, 'day_id')->orderBy('position')->orderBy('start_time');
    }
}
