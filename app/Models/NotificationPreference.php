<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Canale scelto dal cliente per un evento "a scelta": email o notifica del browser.
 */
class NotificationPreference extends Model
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_BROWSER = 'browser';

    protected $fillable = [
        'user_id',
        'event',
        'channel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
