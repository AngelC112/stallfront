<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_NOT_DELIVERED = 'not_delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NOT_COLLECTED = 'not_collected';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUS_TO_RESCHEDULE = 'to_reschedule';

    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_DELIVERY = 'delivery';

    protected $fillable = [
        'order_number',
        'user_id',
        'market_id',
        'market_name',
        'market_location',
        'customer_name',
        'customer_email',
        'customer_phone',
        'fulfillment_method',
        'payment_method',
        'payment_status',
        'status',
        'pickup_date',
        'delivery_address',
        'customer_note',
        'items_subtotal_cents',
        'delivery_cost_cents',
        'total_cents',
        'placed_at',
        'no_show_counted_at',
        'extra_season_days',
        'preparation_start_date',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'placed_at' => 'datetime',
        'no_show_counted_at' => 'datetime',
        'preparation_start_date' => 'date',
        'extra_season_days' => 'integer',
        'items_subtotal_cents' => 'integer',
        'delivery_cost_cents' => 'integer',
        'total_cents' => 'integer',
    ];

    protected static function booted(): void
    {
        // La data di inizio preparazione segue sempre data di ritiro e giorni extra.
        static::saving(function (Order $order): void {
            if ($order->isDirty(['pickup_date', 'extra_season_days']) || ($order->preparation_start_date === null && $order->pickup_date)) {
                $order->preparation_start_date = $order->pickup_date
                    ? $order->pickup_date->copy()->subDays((int) $order->extra_season_days)
                    : null;
            }
        });
    }

    /**
     * Link per chiamare il cliente, solo se ha fornito il numero.
     */
    public function telUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->customer_phone);

        return $digits ? 'tel:'.$digits : null;
    }

    /**
     * Link WhatsApp: un numero italiano di 10 cifre senza prefisso riceve il +39.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->customer_phone);

        if (! $digits) {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '39'.$digits;
        }

        return 'https://wa.me/'.$digits;
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function isOffSeason(): bool
    {
        return $this->extra_season_days > 0;
    }

    /**
     * Ordine fuori stagione da preparare oggi (o in ritardo) e non ancora lavorato fino in fondo.
     */
    public function isDueToday(): bool
    {
        return $this->isOffSeason()
            && $this->preparation_start_date !== null
            && $this->preparation_start_date->lte(today())
            && in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_PREPARING], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::labelFor($this->status, $this->fulfillment_method);
    }

    public static function labelFor(string $status, ?string $fulfillmentMethod): string
    {
        return match ($status) {
            self::STATUS_PREPARING => __('In preparazione'),
            self::STATUS_READY => $fulfillmentMethod === self::FULFILLMENT_DELIVERY ? __('Evaso') : __('Evaso · pronto per il ritiro'),
            self::STATUS_COLLECTED => __('Ritirato'),
            self::STATUS_OUT_FOR_DELIVERY => __('In consegna'),
            self::STATUS_DELIVERED => __('Consegnato'),
            self::STATUS_NOT_DELIVERED => __('Non consegnato'),
            self::STATUS_CANCELLED => __('Annullato'),
            self::STATUS_NOT_COLLECTED => __('Non ritirato'),
            self::STATUS_RESCHEDULED => __('Riprogrammato'),
            self::STATUS_TO_RESCHEDULE => __('Da riprogrammare'),
            default => __('Ricevuto'),
        };
    }

    public function fulfillmentMethodLabel(): string
    {
        if ($this->fulfillment_method === self::FULFILLMENT_DELIVERY) {
            return __('Consegna a domicilio');
        }

        // Ritiro al mercato se è stato selezionato un mercato
        if ($this->market_id) {
            return __('Ritiro al mercato');
        }

        return __('Ritiro in negozio');
    }

    public function paymentMethodLabel(): string
    {
        return $this->payment_method === 'cash_on_delivery'
            ? __('Pagamento alla consegna')
            : __('Pagamento al ritiro');
    }
}
