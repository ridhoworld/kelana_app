<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'item_id',
        'description',
        'category',
        'amount',
        'currency',
        'paid_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItineraryItem::class, 'item_id');
    }
}
