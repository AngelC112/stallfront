<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use App\Services\PickupMarketAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cancels_own_order_and_history_is_recorded(): void
    {
        Mail::fake();
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_RECEIVED);

        $this->actingAs($customer)
            ->post(route('account.orders.cancel', ['order' => $order->order_number]))
            ->assertRedirect(route('account.orders.show', ['order' => $order->order_number]));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(OrderStatusService::ACTOR_CUSTOMER, $order->statusHistories()->latest('id')->first()->actor_type);
        Mail::assertNothingSent();
    }

    public function test_cancelling_an_order_in_preparation_alerts_the_seller(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_PREPARING);

        $this->actingAs($customer)->post(route('account.orders.cancel', ['order' => $order->order_number]));

        $this->assertTrue(NotificationQueueItem::where('event', 'seller_order_changed')->where('recipient_email', $seller->email)->exists());
    }

    public function test_collected_order_cannot_be_cancelled(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_COLLECTED);

        $this->actingAs($customer)
            ->post(route('account.orders.cancel', ['order' => $order->order_number]))
            ->assertSessionHasErrors('status');

        $this->assertSame(Order::STATUS_COLLECTED, $order->fresh()->status);
    }

    public function test_customer_changes_pickup_date_keeping_status(): void
    {
        Mail::fake();
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_RECEIVED);
        $choice = $this->firstDateOption($order);

        $this->actingAs($customer)
            ->post(route('account.orders.reschedule', ['order' => $order->order_number]), [
                'pickup_option' => $choice['market_id'].'|'.$choice['pickup_date'],
            ])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_RECEIVED, $order->status);
        $this->assertSame($choice['pickup_date'], $order->pickup_date->toDateString());
        $this->assertSame($choice['market_name'], $order->market_name);
    }

    public function test_to_reschedule_order_returns_to_received_with_new_date(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_TO_RESCHEDULE);
        $choice = $this->firstDateOption($order);

        $this->actingAs($customer)->post(route('account.orders.reschedule', ['order' => $order->order_number]), [
            'pickup_option' => $choice['market_id'].'|'.$choice['pickup_date'],
        ]);

        $order->refresh();
        $this->assertSame(Order::STATUS_RECEIVED, $order->status);
        $this->assertSame($choice['pickup_date'], $order->pickup_date->toDateString());
    }

    public function test_not_collected_order_becomes_rescheduled(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_NOT_COLLECTED);
        $choice = $this->firstDateOption($order);

        $this->actingAs($customer)->post(route('account.orders.reschedule', ['order' => $order->order_number]), [
            'pickup_option' => $choice['market_id'].'|'.$choice['pickup_date'],
        ]);

        $this->assertSame(Order::STATUS_RESCHEDULED, $order->fresh()->status);
    }

    public function test_unavailable_date_is_rejected(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, Order::STATUS_RECEIVED);
        $market = Market::first();

        $this->actingAs($customer)
            ->post(route('account.orders.reschedule', ['order' => $order->order_number]), [
                'pickup_option' => $market->id.'|'.now()->subDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('pickup_option');
    }

    public function test_customer_cannot_touch_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner, Order::STATUS_RECEIVED);

        $this->actingAs(User::factory()->create())
            ->post(route('account.orders.cancel', ['order' => $order->order_number]))
            ->assertNotFound();

        $this->assertSame(Order::STATUS_RECEIVED, $order->fresh()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function firstDateOption(Order $order): array
    {
        return app(PickupMarketAvailabilityService::class)
            ->availableDatesForOrder($order->load('items'))
            ->firstOrFail();
    }

    private function makeOrder(User $customer, string $status): Order
    {
        $market = Market::first() ?? Market::create([
            'name' => 'Mercato del sabato',
            'location' => 'Piazza Centrale',
            'weekday' => 6,
            'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00',
            'pickup_cutoff_time' => '21:00',
            'is_active' => true,
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
            'pickup_date' => now()->addDays(14)->toDateString(),
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ]);
    }
}
