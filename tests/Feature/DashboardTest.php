<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_orders_revenue_and_best_sellers(): void
    {
        $this->makeOrder(Order::STATUS_RECEIVED, 1000);
        $this->makeOrder(Order::STATUS_PREPARING, 2000);
        $ready = $this->makeOrder(Order::STATUS_READY, 3000);
        $this->makeOrder(Order::STATUS_COLLECTED, 5000, 'paid');
        $this->makeOrder(Order::STATUS_CANCELLED, 9000);
        $this->makeOrder(Order::STATUS_NOT_COLLECTED, 700);

        OrderItem::create(['order_id' => $ready->id, 'product_name' => 'Vaso', 'quantity' => 4, 'unit_price_cents' => 750, 'line_total_cents' => 3000]);

        $stats = app(DashboardService::class)->overview();

        $this->assertSame(2, $stats['toFulfill']);
        $this->assertSame(1, $stats['ready']);
        $this->assertSame(6000, $stats['expectedRevenueCents']);
        $this->assertSame(5000, $stats['collectedTodayCents']);
        $this->assertSame(1, $stats['noShows']);
        $this->assertSame('Vaso', $stats['bestSellers']->first()->product_name);
        $this->assertEquals(4, $stats['bestSellers']->first()->quantity);
    }

    public function test_overview_lists_low_stock_returning_and_blocked_customers(): void
    {
        Product::create([
            'name' => 'Ultimo pezzo', 'slug' => 'ultimo-pezzo', 'short_description' => 'x', 'description' => 'x',
            'price_cents' => 1000, 'vat_included' => true, 'visibility' => Product::VISIBILITY_VISIBLE,
            'is_active' => true, 'is_visible' => true, 'track_stock' => true, 'stock' => 1, 'reserved_stock' => 0,
            'has_variants' => false, 'always_available' => false, 'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3, 'created_by' => null,
        ]);

        $repeat = User::factory()->create();
        $this->makeOrder(Order::STATUS_COLLECTED, 1000, 'paid', $repeat);
        $this->makeOrder(Order::STATUS_RECEIVED, 1000, 'unpaid', $repeat);
        $this->makeOrder(Order::STATUS_RECEIVED, 1000);
        User::factory()->create(['blocked_at' => now()]);
        User::factory()->create(['no_show_count' => 1]);

        $stats = app(DashboardService::class)->overview();

        $this->assertSame('Ultimo pezzo', $stats['lowStock']->first()->name);
        $this->assertSame(1, $stats['returningCustomers']['count']);
        $this->assertSame(1, $stats['blockedCustomers']);
        $this->assertSame(1, $stats['customersWithNoShows']);
    }

    public function test_dashboard_page_requires_the_dashboard_permission(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);

        $this->actingAs($seller)->get(route('admin.dashboard'))->assertOk()->assertSee('Ordini da evadere');
        $this->actingAs($collaborator)->get(route('admin.dashboard'))->assertForbidden();

        $collaborator->permissions()->create(['permission' => 'dashboard.view']);
        $this->actingAs($collaborator)->get(route('admin.dashboard'))->assertOk();
    }

    private function makeOrder(string $status, int $totalCents, string $paymentStatus = 'unpaid', ?User $customer = null): Order
    {
        $customer ??= User::factory()->create();

        return Order::create([
            'order_number' => 'ORD-T-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'user_id' => $customer->id,
            'customer_name' => $customer->fullName(),
            'customer_email' => $customer->email,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'items_subtotal_cents' => $totalCents,
            'delivery_cost_cents' => 0,
            'total_cents' => $totalCents,
            'placed_at' => now(),
        ]);
    }
}
