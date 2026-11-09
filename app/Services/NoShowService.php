<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderBlockNotice;
use App\Models\Setting;
use App\Models\User;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\DB;

class NoShowService
{
    public const BLOCK_REASON = 'Cliente bloccato per mancati ritiri';

    public const DEFAULT_THRESHOLD = 2;

    /**
     * Conta un mancato ritiro (una sola volta per ordine) e blocca il cliente alla soglia.
     */
    public function recordNoShow(Order $order): void
    {
        if ($order->no_show_counted_at !== null || ! $order->user_id) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $customer = User::query()->lockForUpdate()->find($order->user_id);

            if (! $customer || ! $customer->isCustomer()) {
                return;
            }

            $order->forceFill(['no_show_counted_at' => now()])->save();
            $customer->increment('no_show_count');

            if (! $customer->isBlocked() && $customer->no_show_count >= $this->threshold()) {
                $this->blockCustomer($customer);
            }
        });
    }

    public function threshold(): int
    {
        return max(1, (int) Setting::get('orders.no_show_block_threshold', self::DEFAULT_THRESHOLD));
    }

    /**
     * Blocca il cliente (email e cellulare) e annulla i suoi ordini aperti.
     * Per quelli già in preparazione o evasi crea la segnalazione per il venditore.
     */
    public function blockCustomer(User $customer): void
    {
        app(BlocklistService::class)->blockUser($customer);
        app(NotificationService::class)->queueForUser(NotificationEvent::ACCOUNT_BLOCKED, $customer);

        $statusService = app(OrderStatusService::class);

        $openOrders = Order::query()
            ->where('user_id', $customer->id)
            ->whereIn('status', [Order::STATUS_RECEIVED, Order::STATUS_PREPARING, Order::STATUS_READY])
            ->get();

        foreach ($openOrders as $openOrder) {
            $previousStatus = $openOrder->status;

            $statusService->transition(
                $openOrder,
                Order::STATUS_CANCELLED,
                OrderStatusService::ACTOR_SYSTEM,
                null,
                self::BLOCK_REASON,
            );

            if (in_array($previousStatus, [Order::STATUS_PREPARING, Order::STATUS_READY], true)) {
                OrderBlockNotice::create([
                    'order_id' => $openOrder->id,
                    'previous_status' => $previousStatus,
                    'reason' => self::BLOCK_REASON,
                ]);
            }
        }
    }

    public function markAllSeen(User $user): int
    {
        return OrderBlockNotice::query()->unseen()->update([
            'seen_at' => now(),
            'seen_by' => $user->id,
        ]);
    }
}
