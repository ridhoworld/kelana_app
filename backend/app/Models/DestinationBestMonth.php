<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationBestMonth extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'destination_id',
        'month',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
