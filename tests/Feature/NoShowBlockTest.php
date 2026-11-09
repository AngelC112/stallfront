<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Order;
use App\Models\OrderBlockNotice;
use App\Models\User;
use App\Services\BlocklistService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoShowBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_show_is_counted_once_per_order(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_READY);
        $service = app(OrderStatusService::class);

        $service->transition($order, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF);
        $service->transition($order, Order::STATUS_READY, OrderStatusService::ACTOR_STAFF);
        $service->transition($order, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF);

        $this->assertSame(1, $customer->fresh()->no_show_count);
        $this->assertFalse($customer->fresh()->isBlocked());
    }

    public function test_second_no_show_blocks_customer_and_cancels_open_orders(): void
    {
        $customer = User::factory()->create(['phone' => '3331234567']);
        $first = $this->makeOrder($customer, Order::STATUS_READY);
        $second = $this->makeOrder($customer, Order::STATUS_READY);
        $received = $this->makeOrder($customer, Order::STATUS_RECEIVED);
        $preparing = $this->makeOrder($customer, Order::STATUS_PREPARING);
        $service = app(OrderStatusService::class);

        $service->transition($first, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF);
        $service->transition($second, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF);

        $customer->refresh();
        $this->assertTrue($customer->isBlocked());
        $this->assertSame(Order::STATUS_CANCELLED, $received->fresh()->status);
        $this->assertSame(Order::STATUS_CANCELLED, $preparing->fresh()->status);
        $this->assertSame(Order::STATUS_NOT_COLLECTED, $first->fresh()->status);

        $notices = OrderBlockNotice::all();
        $this->assertCount(1, $notices);
        $this->assertSame($preparing->id, $notices->first()->order_id);
        $this->assertSame(Order::STATUS_PREPARING, $notices->first()->previous_status);

        $blocklist = app(BlocklistService::class);
        $this->assertTrue($blocklist->isEmailBlocked($customer->email));
        $this->assertTrue($blocklist->isPhoneBlocked('3331234567'));
    }

    public function test_blocked_customer_cannot_checkout(): void
    {
        $customer = User::factory()->create(['blocked_at' => now()]);

        $this->actingAs($customer)
            ->get(route('checkout.show'))
            ->assertRedirect(route('cart.index'));

        $this->actingAs($customer)
            ->post(route('checkout.store'), ['fulfillment_method' => 'pickup'])
            ->assertRedirect(route('cart.index'));
    }

    public function test_seller_sees_strip_and_marks_notices_as_seen(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(User::factory()->create(), Order::STATUS_CANCELLED);
        OrderBlockNotice::create(['order_id' => $order->id, 'previous_status' => Order::STATUS_READY, 'reason' => 'Test']);

        $this->actingAs($seller)
            ->get(route('admin.orders.index'))
            ->assertSee('1 ordine annullato per blocco cliente');

        $this->actingAs($seller)->get(route('admin.block-notices.index'))->assertSee($order->order_number);

        $this->actingAs($seller)->post(route('admin.block-notices.seen-all'))->assertRedirect();

        $this->assertSame(0, OrderBlockNotice::unseen()->count());
        $this->actingAs($seller)->get(route('admin.orders.index'))->assertDontSee('per blocco cliente');
    }

    public function test_customer_cannot_see_block_notices(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.block-notices.index'))
            ->assertForbidden();
    }

    private function makeOrder(User $customer, string $status): Order
    {
        $market = Market::first() ?? Market::create([
            'name' => 'Mercato', 'location' => 'Piazza', 'weekday' => 6, 'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00', 'pickup_cutoff_time' => '21:00', 'is_active' => true,
        ]);

        return Order::create([
            'order_number' => 'ORD-T-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'user_id' => $customer->id,
            'market_id' => $market->id,
            'market_name' => $market->name,
            'market_location' => $market->location,
            'customer_name' => $customer->fullName(),
            'customer_email' => $customer->email,
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'unpaid',
            'status' => $status,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ]);
    }
}
