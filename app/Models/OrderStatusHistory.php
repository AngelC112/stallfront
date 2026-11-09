<?php

namespace App\Models;

use App\Services\OrderStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'actor_type',
        'user_id',
        'reason',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actorLabel(): string
    {
        return match ($this->actor_type) {
            OrderStatusService::ACTOR_SYSTEM => __('Sistema'),
            OrderStatusService::ACTOR_CUSTOMER => __('Cliente'),
            default => $this->user?->fullName() ?: __('Venditore'),
        };
    }
}
