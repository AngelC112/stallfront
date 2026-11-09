<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationQueueItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_ERROR = 'error';

    /** Inviata come notifica del browser: si attende la conferma di ricezione, poi eventuale email di riserva. */
    public const STATUS_AWAITING_ACK = 'awaiting_ack';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_PUSH = 'push';

    protected $table = 'notification_queue';

    protected $fillable = [
        'event',
        'user_id',
        'order_id',
        'recipient_email',
        'channel',
        'subject',
        'body',
        'url',
        'status',
        'attempts',
        'last_error',
        'sent_at',
        'awaiting_ack_until',
        'delivered_via',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'awaiting_ack_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushDeliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class, 'notification_queue_id');
    }
}
