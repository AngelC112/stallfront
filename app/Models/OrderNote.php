<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota interna: visibile solo a venditore e collaboratori autorizzati, mai al cliente.
 */
class OrderNote extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'body',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
