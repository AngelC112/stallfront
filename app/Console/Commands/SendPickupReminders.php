<?php

namespace App\Console\Commands;

use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Services\NotificationService;
use App\Support\NotificationEvent;
use Illuminate\Console\Command;

class SendPickupReminders extends Command
{
    protected $signature = 'notifications:send-reminders';

    protected $description = 'Queues the reminder for the orders to collect tomorrow';

    public function handle(NotificationService $notifications): int
    {
        $orders = Order::query()
            ->where('fulfillment_method', Order::FULFILLMENT_PICKUP)
            ->whereIn('status', [Order::STATUS_RECEIVED, Order::STATUS_PREPARING, Order::STATUS_READY])
            ->whereDate('pickup_date', now()->addDay()->toDateString())
            ->get();

        $queued = 0;

        foreach ($orders as $order) {
            $alreadyReminded = NotificationQueueItem::query()
                ->where('event', NotificationEvent::PICKUP_REMINDER)
                ->where('order_id', $order->id)
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            if ($notifications->queueForOrder(NotificationEvent::PICKUP_REMINDER, $order)) {
                $queued++;
            }
        }

        $this->info("Promemoria in coda: {$queued}");

        return self::SUCCESS;
    }
}
