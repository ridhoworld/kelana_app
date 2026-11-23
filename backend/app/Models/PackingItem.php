<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'name',
        'category',
        'quantity',
        'is_packed',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_packed' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
