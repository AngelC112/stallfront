<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro di un invio push a un dispositivo: esito e conferma di ricezione.
 */
class PushDelivery extends Model
{
    public const RESULT_SENT = 'sent';

    public const RESULT_RECEIVED = 'received';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_ERROR = 'error';

    public const RESULT_EMAIL_FALLBACK = 'email_fallback';

    protected $fillable = [
        'notification_queue_id',
        'push_subscription_id',
        'sent_at',
        'received_at',
        'result',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationQueueItem::class, 'notification_queue_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'push_subscription_id');
    }
}
