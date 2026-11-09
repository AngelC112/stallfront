<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    private const OPEN_FOR_PAYMENT = [
        Order::STATUS_RECEIVED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
    ];

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $since = now()->subDays(30);

        return [
            'toFulfill' => Order::whereIn('status', [Order::STATUS_RECEIVED, Order::STATUS_PREPARING])->count(),
            'ready' => Order::where('status', Order::STATUS_READY)->count(),
            'expectedRevenueCents' => (int) Order::whereIn('status', self::OPEN_FOR_PAYMENT)->sum('total_cents'),
            'collectedTodayCents' => (int) Order::where('payment_status', 'paid')->whereDate('updated_at', today())->sum('total_cents'),
            'noShows' => Order::whereIn('status', [Order::STATUS_NOT_COLLECTED, Order::STATUS_NOT_DELIVERED])->count(),
            'lowStock' => $this->lowStockProducts(),
            'bestSellers' => $this->bestSellers($since),
            'returningCustomers' => $this->returningCustomers(),
            'customersWithNoShows' => User::where('role', User::ROLE_CUSTOMER)->where('no_show_count', '>', 0)->whereNull('blocked_at')->count(),
            'blockedCustomers' => User::where('role', User::ROLE_CUSTOMER)->whereNotNull('blocked_at')->count(),
            'bestSellersDays' => 30,
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    private function lowStockProducts(): Collection
    {
        return Product::query()
            ->where('track_stock', true)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Product $product): bool => $product->isLowStock())
            ->sortBy('stock')
            ->take(8)
            ->values();
    }

    /**
     * @return Collection<int, object{product_name: string, quantity: int}>
     */
    private function bestSellers(\DateTimeInterface $since): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('orders.placed_at', '>=', $since)
            ->selectRaw('order_items.product_name as product_name, SUM(order_items.quantity) as quantity')
            ->groupBy('order_items.product_name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();
    }

    /**
     * Clienti con almeno due ordini non annullati.
     *
     * @return array{count: int, top: Collection<int, object{customer_name: string, orders: int}>}
     */
    private function returningCustomers(): array
    {
        $base = Order::query()
            ->whereNotNull('user_id')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2');

        return [
            'count' => $base->clone()->selectRaw('user_id')->get()->count(),
            'top' => $base->clone()
                ->selectRaw('MAX(customer_name) as customer_name, COUNT(*) as orders')
                ->orderByDesc('orders')
                ->limit(5)
                ->get(),
        ];
    }
}
