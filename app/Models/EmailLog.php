<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro delle email inviate dal sito, con lo stato della copia nella cartella "Inviate".
 */
class EmailLog extends Model
{
    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    /** Intestazione usata per collegare l'evento di invio a quello di invio riuscito. */
    public const HEADER = 'X-Vetrina-Email-Log';

    /** Tentativi massimi di copia in "Inviate" (uno al minuto, circa tre ore). */
    public const MAX_STORE_ATTEMPTS = 180;

    /** Minuti dopo i quali un'email rimasta "in invio" è considerata non inviata. */
    public const STALE_AFTER_MINUTES = 10;

    protected $fillable = [
        'mailable',
        'message_id',
        'from_address',
        'to_addresses',
        'cc_addresses',
        'bcc_addresses',
        'subject',
        'status',
        'sent_at',
        'raw_path',
        'stored_in_sent_at',
        'store_attempts',
        'store_error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'stored_in_sent_at' => 'datetime',
        'store_attempts' => 'integer',
    ];

    /**
     * Email inviate la cui copia in "Inviate" è ancora da salvare.
     *
     * @param  Builder<EmailLog>  $query
     * @return Builder<EmailLog>
     */
    public function scopePendingSentCopy(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_SENT)
            ->whereNull('stored_in_sent_at')
            ->whereNotNull('raw_path')
            ->where('store_attempts', '<', self::MAX_STORE_ATTEMPTS);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_SENDING
            && $this->created_at !== null
            && $this->created_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::STATUS_SENT => __('Inviata'),
            $this->isFailed() => __('Non inviata'),
            default => __('In invio'),
        };
    }

    public function sentCopyLabel(): string
    {
        return match (true) {
            $this->stored_in_sent_at !== null => __('Salvata'),
            $this->status !== self::STATUS_SENT || $this->raw_path === null => '—',
            $this->store_attempts >= self::MAX_STORE_ATTEMPTS => __('Non salvata'),
            default => __('In attesa'),
        };
    }
}
