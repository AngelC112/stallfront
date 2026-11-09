<?php

namespace App\Services;

use App\Models\Market;
use App\Models\MarketClosure;
use App\Models\MarketHoliday;
use App\Models\Order;
use App\Support\NotificationEvent;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

class MarketClosureService
{
    private const DEFAULT_REASON = 'Mercato chiuso';

    public function __construct(
        private OrderStatusService $statusService,
        private NotificationService $notifications,
    ) {}

    /**
     * Restituisce una funzione (id mercato, data Y-m-d) => motivazione di chiusura, oppure null se aperto.
     * Le chiusure vengono lette una sola volta per l’intero intervallo richiesto.
     *
     * @return Closure(int, string): ?string
     */
    public function reasonLookup(CarbonInterface $from, CarbonInterface $to): Closure
    {
        $closures = MarketClosure::query()
            ->whereDate('closure_date', '>=', $from->toDateString())
            ->whereDate('closure_date', '<=', $to->toDateString())
            ->get();

        $holidays = MarketHoliday::query()
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereDate('start_date', '<=', $to->toDateString())
            ->get();

        return function (int $marketId, string $date) use ($closures, $holidays): ?string {
            $closure = $closures->first(
                fn (MarketClosure $item): bool => $item->market_id === $marketId && $item->closure_date->toDateString() === $date
            );

            if ($closure) {
                return $closure->reason ?: __(self::DEFAULT_REASON);
            }

            $holiday = $holidays->first(
                fn (MarketHoliday $item): bool => ($item->market_id === null || $item->market_id === $marketId)
                    && $item->start_date->toDateString() <= $date
                    && $item->end_date->toDateString() >= $date
            );

            return $holiday ? ($holiday->reason ?: __(self::DEFAULT_REASON)) : null;
        };
    }

    /**
     * Ordini di ritiro ancora aperti con data di ritiro nel periodo (e nel mercato, se indicato).
     *
     * @return Collection<int, Order>
     */
    public function affectedOrders(?int $marketId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Order::query()
            ->where('fulfillment_method', Order::FULFILLMENT_PICKUP)
            ->whereIn('status', [Order::STATUS_RECEIVED, Order::STATUS_PREPARING, Order::STATUS_READY])
            ->whereDate('pickup_date', '>=', $start->toDateString())
            ->whereDate('pickup_date', '<=', $end->toDateString())
            ->when($marketId, fn ($query) => $query->where('market_id', $marketId))
            ->get();
    }

    /**
     * Riepilogo mostrato prima della conferma.
     *
     * @return array{orders: int, customers: int}
     */
    public function summary(?int $marketId, CarbonInterface $start, CarbonInterface $end): array
    {
        $orders = $this->affectedOrders($marketId, $start, $end);

        return [
            'orders' => $orders->count(),
            'customers' => $orders->pluck('customer_email')->unique()->count(),
        ];
    }

    /**
     * @return array{orders: int, customers: int}
     */
    public function registerHoliday(?Market $market, CarbonInterface $start, CarbonInterface $end, string $reason): array
    {
        MarketHoliday::create([
            'market_id' => $market?->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $reason,
        ]);

        return $this->rescheduleOrders($market?->id, $start, $end, $reason);
    }

    /**
     * @return array{orders: int, customers: int}
     */
    public function registerClosure(Market $market, CarbonInterface $date, string $reason): array
    {
        MarketClosure::updateOrCreate(
            ['market_id' => $market->id, 'closure_date' => $date->toDateString()],
            ['reason' => $reason],
        );

        return $this->rescheduleOrders($market->id, $date, $date, $reason);
    }

    /**
     * Porta gli ordini coinvolti in "Da riprogrammare" e avvisa ogni cliente con la motivazione.
     *
     * @return array{orders: int, customers: int}
     */
    private function rescheduleOrders(?int $marketId, CarbonInterface $start, CarbonInterface $end, string $reason): array
    {
        $orders = $this->affectedOrders($marketId, $start, $end);

        foreach ($orders as $order) {
            $order = $this->statusService->transition(
                $order,
                Order::STATUS_TO_RESCHEDULE,
                OrderStatusService::ACTOR_SYSTEM,
                null,
                $reason,
            );

            $this->notifications->queueForOrder(NotificationEvent::MARKET_CLOSED, $order, ['motivazione' => $reason]);
        }

        return [
            'orders' => $orders->count(),
            'customers' => $orders->pluck('customer_email')->unique()->count(),
        ];
    }
}
