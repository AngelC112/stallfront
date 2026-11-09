<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\Permission;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Prepara le viste "ordini da evadere" del venditore: per mercato e data, oppure per prodotto.
 */
class OrderBoardService
{
    public const VIEW_MARKET = 'market';

    public const VIEW_PRODUCT = 'product';

    public const STATUS_ALL = 'all';

    /** Stati ancora da seguire: tutto ciò che non è finale. */
    public const OPEN_STATUSES = [
        Order::STATUS_RECEIVED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_NOT_COLLECTED,
        Order::STATUS_NOT_DELIVERED,
        Order::STATUS_RESCHEDULED,
        Order::STATUS_TO_RESCHEDULE,
    ];

    /** Da preparare: nella vista per prodotto contano solo questi. */
    private const TO_PREPARE = [Order::STATUS_RECEIVED, Order::STATUS_PREPARING];

    /** Già evasi: pronti ma non ancora consegnati al cliente. */
    private const FULFILLED = [Order::STATUS_READY, Order::STATUS_OUT_FOR_DELIVERY];

    /**
     * @param  array{market_id?: mixed, fulfillment?: mixed, off_season?: mixed, status?: mixed}  $filters
     * @return array{groups: Collection<int, array<string, mixed>>, futureOffSeason: array{count: int, firstDate: ?Carbon}}
     */
    public function build(User $user, array $filters, string $view): array
    {
        $orders = $this->query($user, $filters)->get();

        $groups = $this->groupOrders($orders);

        if ($view === self::VIEW_PRODUCT) {
            $groups = $groups->map(fn (array $group): array => $this->withProductLines($group));
        }

        $future = $orders->filter(fn (Order $order): bool => $order->isOffSeason()
            && $order->preparation_start_date?->gt(today())
            && in_array($order->status, self::TO_PREPARE, true));

        return [
            'groups' => $groups->values(),
            'futureOffSeason' => [
                'count' => $future->count(),
                'firstDate' => $future->min('preparation_start_date'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(User $user, array $filters)
    {
        $canPickup = $user->hasPermission(Permission::ORDERS_MARKET_MANAGE);
        $canDelivery = $user->hasPermission(Permission::ORDERS_DELIVERY_MANAGE);

        $query = Order::query()->with(['items', 'user'])->withCount('notes');

        if (! $canPickup) {
            $query->where('fulfillment_method', Order::FULFILLMENT_DELIVERY);
        }

        if (! $canDelivery) {
            $query->where('fulfillment_method', Order::FULFILLMENT_PICKUP);
        }

        if (! empty($filters['market_id'])) {
            $query->where('market_id', $filters['market_id']);
        }

        if (in_array($filters['fulfillment'] ?? null, [Order::FULFILLMENT_PICKUP, Order::FULFILLMENT_DELIVERY], true)) {
            $query->where('fulfillment_method', $filters['fulfillment']);
        }

        if (! empty($filters['off_season'])) {
            $query->where('extra_season_days', '>', 0);
        }

        $status = $filters['status'] ?? null;

        if ($status === self::STATUS_ALL) {
            // nessun filtro sullo stato
        } elseif ($status) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', self::OPEN_STATUSES);
        }

        return $query;
    }

    /**
     * Un gruppo per mercato e data di ritiro, più uno per la consegna a domicilio (sempre in fondo).
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array<string, mixed>>
     */
    private function groupOrders(Collection $orders): Collection
    {
        $groups = $orders->groupBy(fn (Order $order): string => $order->fulfillment_method === Order::FULFILLMENT_DELIVERY
            ? 'delivery'
            : ($order->market_id ?? 0).'|'.($order->pickup_date?->toDateString() ?? ''));

        return $groups
            ->map(function (Collection $items, string $key): array {
                $first = $items->first();
                $isDelivery = $key === 'delivery';

                return [
                    'key' => $key,
                    'is_delivery' => $isDelivery,
                    'date' => $isDelivery ? null : $first->pickup_date,
                    'title' => $isDelivery ? __('Consegna a domicilio') : ($first->market_name ?? __('Senza mercato')),
                    'orders' => $this->sortOrders($items),
                ];
            })
            ->sortBy(fn (array $group): string => ($group['is_delivery'] ? '9' : '0').($group['date']?->toDateString() ?? '0000-00-00').$group['title'])
            ->values();
    }

    /**
     * Prima gli ordini "da preparare oggi", poi quelli da lavorare, in fondo gli evasi e il resto.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Order>
     */
    private function sortOrders(Collection $orders): Collection
    {
        $rank = function (Order $order): int {
            if ($order->isDueToday()) {
                return 0;
            }

            if (in_array($order->status, self::TO_PREPARE, true)) {
                return 1;
            }

            if (in_array($order->status, self::FULFILLED, true)) {
                return 3;
            }

            return 2;
        };

        return $orders
            ->sortBy(fn (Order $order): string => $rank($order).$order->placed_at->format('YmdHis'))
            ->values();
    }

    /**
     * Totale dei pezzi da preparare per prodotto e variante, con gli ordini che li contengono.
     *
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function withProductLines(array $group): array
    {
        $lines = [];

        foreach ($group['orders'] as $order) {
            if (! in_array($order->status, self::TO_PREPARE, true)) {
                continue;
            }

            foreach ($order->items as $item) {
                $key = $item->product_id.'_'.$item->variant_id;

                $lines[$key] ??= [
                    'name' => $item->product_name,
                    'variant' => $item->variant_label,
                    'quantity' => 0,
                    'orders' => collect(),
                ];

                $lines[$key]['quantity'] += $item->quantity;
                $lines[$key]['orders']->push($order);
            }
        }

        $group['lines'] = collect($lines)->sortBy('name')->values();
        $group['fulfilled'] = $group['orders']->filter(fn (Order $order): bool => in_array($order->status, self::FULFILLED, true))->values();

        return $group;
    }
}
