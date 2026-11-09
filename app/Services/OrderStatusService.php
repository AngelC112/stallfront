<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public const ACTOR_STAFF = 'staff';

    public const ACTOR_CUSTOMER = 'customer';

    public const ACTOR_SYSTEM = 'system';

    /** Stati in cui la data di ritiro si cambia senza cambiare stato. */
    public const DATE_CHANGE_STATUSES = [
        Order::STATUS_RECEIVED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
    ];

    private const CANCEL_ACTORS = [self::ACTOR_STAFF, self::ACTOR_CUSTOMER, self::ACTOR_SYSTEM];

    /**
     * Transizioni ammesse: stato di partenza => [stato di arrivo => attori].
     *
     * @return array<string, array<string, array<int, string>>>
     */
    private function transitions(): array
    {
        $staff = [self::ACTOR_STAFF];
        $system = [self::ACTOR_SYSTEM];
        $customer = [self::ACTOR_CUSTOMER];

        return [
            Order::STATUS_RECEIVED => [
                Order::STATUS_PREPARING => $staff,
                Order::STATUS_TO_RESCHEDULE => $system,
                Order::STATUS_CANCELLED => self::CANCEL_ACTORS,
            ],
            Order::STATUS_PREPARING => [
                Order::STATUS_READY => $staff,
                Order::STATUS_TO_RESCHEDULE => $system,
                Order::STATUS_CANCELLED => self::CANCEL_ACTORS,
            ],
            Order::STATUS_READY => [
                Order::STATUS_COLLECTED => $staff,
                Order::STATUS_NOT_COLLECTED => $staff,
                Order::STATUS_OUT_FOR_DELIVERY => $staff,
                Order::STATUS_TO_RESCHEDULE => $system,
                Order::STATUS_CANCELLED => self::CANCEL_ACTORS,
            ],
            Order::STATUS_OUT_FOR_DELIVERY => [
                Order::STATUS_DELIVERED => $staff,
                Order::STATUS_NOT_DELIVERED => $staff,
            ],
            Order::STATUS_NOT_COLLECTED => [
                Order::STATUS_READY => $staff,
                Order::STATUS_RESCHEDULED => $customer,
                Order::STATUS_CANCELLED => self::CANCEL_ACTORS,
            ],
            Order::STATUS_NOT_DELIVERED => [
                Order::STATUS_READY => $staff,
                Order::STATUS_CANCELLED => $staff,
            ],
            Order::STATUS_RESCHEDULED => [
                Order::STATUS_READY => $staff,
                Order::STATUS_CANCELLED => self::CANCEL_ACTORS,
            ],
            Order::STATUS_TO_RESCHEDULE => [
                Order::STATUS_RECEIVED => $customer,
                Order::STATUS_CANCELLED => self::CANCEL_ACTORS,
            ],
        ];
    }

    /**
     * Stati raggiungibili da un ordine, filtrati per tipo di consegna e attore.
     *
     * @return array<int, string>
     */
    public function allowedTargets(Order $order, string $actor): array
    {
        $targets = [];

        foreach ($this->transitions()[$order->status] ?? [] as $to => $actors) {
            if (in_array($actor, $actors, true) && $this->fitsFulfillment($order, $to)) {
                $targets[] = $to;
            }
        }

        return $targets;
    }

    public function canTransition(Order $order, string $to, string $actor): bool
    {
        return in_array($to, $this->allowedTargets($order, $actor), true);
    }

    /**
     * Cambia lo stato di un ordine, registra lo storico e applica gli effetti collaterali.
     *
     * @param  array<string, mixed>  $attributes  Campi extra da salvare insieme (es. nuovo mercato e data).
     */
    public function transition(
        Order $order,
        string $to,
        string $actor,
        ?User $user = null,
        ?string $reason = null,
        array $attributes = [],
    ): Order {
        return DB::transaction(function () use ($order, $to, $actor, $user, $reason, $attributes): Order {
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if (! $this->canTransition($locked, $to, $actor)) {
                throw ValidationException::withMessages([
                    'status' => __('Questo cambio di stato non è consentito.'),
                ]);
            }

            $from = $locked->status;

            if ($to === Order::STATUS_CANCELLED) {
                $this->restoreStock($locked, $user);
            }

            $changes = array_merge($attributes, ['status' => $to]);

            if (in_array($to, [Order::STATUS_COLLECTED, Order::STATUS_DELIVERED], true)) {
                $changes['payment_status'] = 'paid';
            }

            $locked->forceFill($changes)->save();

            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actor,
                'user_id' => $user?->id,
                'reason' => $reason,
            ]);

            app(NotificationService::class)->orderStatusChanged($locked, $to, $actor, $reason);

            if (in_array($to, [Order::STATUS_NOT_COLLECTED, Order::STATUS_NOT_DELIVERED], true)) {
                app(NoShowService::class)->recordNoShow($locked);
            }

            app(ActivityLogger::class)->log('order.status_changed', $locked, [
                'from' => $from,
                'to' => $to,
                'actor' => $actor,
            ]);

            return $locked;
        });
    }

    /**
     * Sposta un ordine aperto su un altro mercato e data senza cambiare stato.
     *
     * @param  array<string, mixed>  $attributes  Mercato e data di ritiro.
     */
    public function changePickup(Order $order, string $actor, ?User $user, string $reason, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $actor, $user, $reason, $attributes): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, self::DATE_CHANGE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'pickup_option' => __('Per questo ordine non si può cambiare la data.'),
                ]);
            }

            $locked->forceFill($attributes)->save();

            $locked->statusHistories()->create([
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'actor_type' => $actor,
                'user_id' => $user?->id,
                'reason' => $reason,
            ]);

            app(ActivityLogger::class)->log('order.pickup_changed', $locked, $attributes);

            return $locked;
        });
    }

    /**
     * Stati che venditore o collaboratore possono impostare, con l’etichetta dell’azione.
     * Oltre alla tabella delle transizioni si applicano i permessi del collaboratore.
     *
     * @return array<string, string>
     */
    public function staffOptions(User $user, Order $order): array
    {
        $labels = [
            Order::STATUS_PREPARING => __('In preparazione'),
            Order::STATUS_READY => __('Evaso'),
            Order::STATUS_COLLECTED => __('Segna come ritirato'),
            Order::STATUS_NOT_COLLECTED => __('Segna come non ritirato'),
            Order::STATUS_OUT_FOR_DELIVERY => __('In consegna'),
            Order::STATUS_DELIVERED => __('Consegnato'),
            Order::STATUS_NOT_DELIVERED => __('Non consegnato'),
            Order::STATUS_CANCELLED => __('Annulla ordine'),
        ];

        $noShowOrder = in_array($order->status, [
            Order::STATUS_NOT_COLLECTED,
            Order::STATUS_NOT_DELIVERED,
            Order::STATUS_RESCHEDULED,
        ], true);

        $options = [];

        foreach ($this->allowedTargets($order, self::ACTOR_STAFF) as $target) {
            if ($target === Order::STATUS_CANCELLED && ! $user->hasPermission(Permission::ORDERS_CANCEL)) {
                continue;
            }

            if ($target === Order::STATUS_READY && $noShowOrder && ! $user->hasPermission(Permission::ORDERS_NO_SHOW_DECIDE)) {
                continue;
            }

            $options[$target] = $labels[$target] ?? $target;
        }

        return $options;
    }

    /**
     * Passo successivo del flusso normale, se l’utente può eseguirlo con un solo tocco.
     *
     * @return array{0: string, 1: string}|null [stato, etichetta]
     */
    public function nextStep(User $user, Order $order): ?array
    {
        $isDelivery = $order->fulfillment_method === Order::FULFILLMENT_DELIVERY;

        $next = match ($order->status) {
            Order::STATUS_RECEIVED => Order::STATUS_PREPARING,
            Order::STATUS_PREPARING => Order::STATUS_READY,
            Order::STATUS_READY => $isDelivery ? Order::STATUS_OUT_FOR_DELIVERY : Order::STATUS_COLLECTED,
            Order::STATUS_OUT_FOR_DELIVERY => Order::STATUS_DELIVERED,
            default => null,
        };

        $options = $this->staffOptions($user, $order);

        return $next !== null && isset($options[$next]) ? [$next, $options[$next]] : null;
    }

    /**
     * Registra lo stato iniziale di un ordine appena creato.
     */
    public function recordCreation(Order $order, ?User $user = null): void
    {
        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => $order->status,
            'actor_type' => self::ACTOR_CUSTOMER,
            'user_id' => $user?->id,
        ]);
    }

    private function fitsFulfillment(Order $order, string $to): bool
    {
        $isDelivery = $order->fulfillment_method === Order::FULFILLMENT_DELIVERY;

        return match ($to) {
            Order::STATUS_COLLECTED, Order::STATUS_NOT_COLLECTED, Order::STATUS_RESCHEDULED => ! $isDelivery,
            Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED, Order::STATUS_NOT_DELIVERED => $isDelivery,
            default => true,
        };
    }

    private function restoreStock(Order $order, ?User $user): void
    {
        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::withTrashed()->lockForUpdate()->find($item->product_id);

            if (! $product || ! $product->track_stock) {
                continue;
            }

            $variant = $item->variant_id
                ? ProductVariant::withTrashed()->lockForUpdate()->find($item->variant_id)
                : null;

            if ($variant) {
                $variant->increment('stock', $item->quantity);
            } else {
                $product->increment('stock', $item->quantity);
            }

            StockMovement::create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'user_id' => $user?->id,
                'delta' => $item->quantity,
                'reason' => StockMovement::REASON_ORDER_CANCEL,
                'note' => __('Annullamento ordine :number', ['number' => $order->order_number]),
            ]);
        }
    }
}
