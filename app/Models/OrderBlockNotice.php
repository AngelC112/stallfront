<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderBlockNotice extends Model
{
    protected $fillable = [
        'order_id',
        'previous_status',
        'reason',
        'seen_at',
        'seen_by',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeUnseen(Builder $query): Builder
    {
        return $query->whereNull('seen_at');
    }
}
