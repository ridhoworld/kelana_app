<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'day_id',
        'title',
        'category',
        'start_time',
        'end_time',
        'location',
        'estimated_cost',
        'booking_ref',
        'notes',
        'position',
        'is_done',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'integer',
            'position' => 'integer',
            'is_done' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ItineraryDay::class, 'day_id');
    }
}
