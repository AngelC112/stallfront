<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_order_follows_the_no_show_path_and_records_history(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(Order::STATUS_READY);
        $service = app(OrderStatusService::class);

        $service->transition($order, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF, $seller);
        $service->transition($order, Order::STATUS_RESCHEDULED, OrderStatusService::ACTOR_CUSTOMER);
        $service->transition($order, Order::STATUS_READY, OrderStatusService::ACTOR_STAFF, $seller);

        $order->refresh();
        $this->assertSame(Order::STATUS_READY, $order->status);
        $this->assertSame(
            [Order::STATUS_NOT_COLLECTED, Order::STATUS_RESCHEDULED, Order::STATUS_READY],
            $order->statusHistories->pluck('to_status')->all(),
        );
        $this->assertSame($seller->id, $order->statusHistories->first()->user_id);
        $this->assertSame(OrderStatusService::ACTOR_CUSTOMER, $order->statusHistories[1]->actor_type);
    }

    public function test_system_moves_open_orders_to_reschedule_and_customer_brings_them_back(): void
    {
        $order = $this->makeOrder(Order::STATUS_PREPARING);
        $service = app(OrderStatusService::class);

        $service->transition($order, Order::STATUS_TO_RESCHEDULE, OrderStatusService::ACTOR_SYSTEM, null, 'Maltempo');
        $service->transition($order, Order::STATUS_RECEIVED, OrderStatusService::ACTOR_CUSTOMER);

        $this->assertSame(Order::STATUS_RECEIVED, $order->fresh()->status);
        $this->assertSame('Maltempo', $order->statusHistories()->first()->reason);
    }

    public function test_transitions_not_in_the_table_are_rejected(): void
    {
        $order = $this->makeOrder(Order::STATUS_COLLECTED);

        $this->expectException(ValidationException::class);

        app(OrderStatusService::class)->transition($order, Order::STATUS_PREPARING, OrderStatusService::ACTOR_STAFF);
    }

    public function test_customer_cannot_perform_staff_only_transitions(): void
    {
        $order = $this->makeOrder(Order::STATUS_RECEIVED);

        $this->expectException(ValidationException::class);

        app(OrderStatusService::class)->transition($order, Order::STATUS_PREPARING, OrderStatusService::ACTOR_CUSTOMER);
    }

    public function test_delivery_orders_use_delivery_states_only(): void
    {
        $service = app(OrderStatusService::class);
        $delivery = $this->makeOrder(Order::STATUS_READY, Order::FULFILLMENT_DELIVERY);
        $pickup = $this->makeOrder(Order::STATUS_READY);

        $this->assertContains(Order::STATUS_OUT_FOR_DELIVERY, $service->allowedTargets($delivery, OrderStatusService::ACTOR_STAFF));
        $this->assertNotContains(Order::STATUS_COLLECTED, $service->allowedTargets($delivery, OrderStatusService::ACTOR_STAFF));
        $this->assertNotContains(Order::STATUS_OUT_FOR_DELIVERY, $service->allowedTargets($pickup, OrderStatusService::ACTOR_STAFF));
    }

    public function test_cancelling_restores_stock(): void
    {
        $product = Product::create([
            'name' => 'Vaso', 'slug' => 'vaso', 'short_description' => 'Vaso', 'description' => 'Vaso',
            'price_cents' => 1000, 'vat_included' => true, 'visibility' => Product::VISIBILITY_VISIBLE,
            'is_active' => true, 'is_visible' => true, 'track_stock' => true, 'stock' => 3,
            'reserved_stock' => 0, 'has_variants' => false, 'always_available' => false,
            'off_season_fulfillment_days' => 2, 'low_stock_threshold' => 1, 'created_by' => null,
        ]);
        $order = $this->makeOrder(Order::STATUS_RECEIVED);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Vaso',
            'quantity' => 2, 'unit_price_cents' => 1000, 'line_total_cents' => 2000,
        ]);

        app(OrderStatusService::class)->transition($order, Order::STATUS_CANCELLED, OrderStatusService::ACTOR_CUSTOMER);

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_seller_reschedules_a_no_show_order_with_new_market_and_date(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(Order::STATUS_NOT_COLLECTED);
        $date = now()->addDays(10);
        $market = Market::create([
            'name' => 'Mercato nuovo', 'location' => 'Via Roma', 'weekday' => $date->dayOfWeekIso,
            'minimum_order_days' => 3, 'order_cutoff_time' => '18:00', 'pickup_cutoff_time' => '21:00',
            'is_active' => true,
        ]);

        $this->actingAs($seller)
            ->patch(route('admin.orders.status', $order), [
                'status' => Order::STATUS_READY,
                'market_id' => $market->id,
                'pickup_date' => $date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_READY, $order->status);
        $this->assertSame($market->id, $order->market_id);
        $this->assertSame($date->toDateString(), $order->pickup_date->toDateString());
    }

    public function test_no_show_order_cannot_return_to_ready_without_new_date(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(Order::STATUS_NOT_COLLECTED);

        $this->actingAs($seller)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_READY])
            ->assertSessionHasErrors('market_id');

        $this->assertSame(Order::STATUS_NOT_COLLECTED, $order->fresh()->status);
    }

    public function test_collaborator_without_cancel_permission_cannot_cancel(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.market.manage']);
        $order = $this->makeOrder(Order::STATUS_RECEIVED);

        $this->actingAs($collaborator)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_CANCELLED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Order::STATUS_RECEIVED, $order->fresh()->status);
    }

    private function makeOrder(string $status, string $fulfillment = Order::FULFILLMENT_PICKUP): Order
    {
        $customer = User::factory()->create();

        return Order::create([
            'order_number' => 'ORD-TEST-'.strtoupper(substr(md5((string) microtime(true).random_int(0, 99999)), 0, 6)),
            'user_id' => $customer->id,
            'customer_name' => $customer->fullName(),
            'customer_email' => $customer->email,
            'fulfillment_method' => $fulfillment,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'unpaid',
            'status' => $status,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'items_subtotal_cents' => 2000,
            'delivery_cost_cents' => 0,
            'total_cents' => 2000,
            'placed_at' => now(),
        ]);
    }
}
