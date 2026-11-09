<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_orders_by_code_name_email_and_phone(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(['customer_name' => 'Giulia Verdi', 'customer_email' => 'giulia@example.com', 'customer_phone' => '3331112222']);

        foreach ([$order->order_number, 'Verdi', 'giulia@example', '333 111'] as $term) {
            $this->actingAs($seller)
                ->getJson(route('admin.banco.search', ['q' => $term]))
                ->assertOk()
                ->assertJsonPath('results.0.order_number', $order->order_number);
        }
    }

    public function test_search_payload_has_total_actions_and_internal_notes_for_authorized_staff(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(['status' => Order::STATUS_READY]);
        $order->notes()->create(['user_id' => $seller->id, 'body' => 'Resto da 50']);

        $this->actingAs($seller)
            ->getJson(route('admin.banco.search', ['q' => $order->order_number]))
            ->assertJsonPath('results.0.total', \App\Support\Format::money(1000))
            ->assertJsonPath('results.0.actions', ['collected', 'not_collected'])
            ->assertJsonPath('results.0.internal_notes.0', 'Resto da 50');
    }

    public function test_counter_collaborator_marks_collected_without_order_permissions_and_cannot_see_notes(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'counter.use']);
        $order = $this->makeOrder(['status' => Order::STATUS_READY]);
        $order->notes()->create(['body' => 'Segreta']);

        $this->actingAs($collaborator)
            ->getJson(route('admin.banco.search', ['q' => $order->order_number]))
            ->assertJsonPath('results.0.internal_notes', [])
            ->assertJsonPath('results.0.url', null);

        $this->actingAs($collaborator)
            ->postJson(route('admin.banco.status', $order), ['status' => 'collected'])
            ->assertOk()
            ->assertJsonPath('order.status', 'collected');

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_not_collected_from_counter_counts_a_no_show(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $order = $this->makeOrder(['status' => Order::STATUS_READY]);

        $this->actingAs($seller)->postJson(route('admin.banco.status', $order), ['status' => 'not_collected'])->assertOk();

        $this->assertSame(1, $order->user->fresh()->no_show_count);
    }

    public function test_counter_rejects_other_statuses_and_invalid_transitions(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $ready = $this->makeOrder(['status' => Order::STATUS_READY]);
        $received = $this->makeOrder();

        $this->actingAs($seller)->postJson(route('admin.banco.status', $ready), ['status' => 'cancelled'])->assertStatus(422);
        $this->actingAs($seller)->postJson(route('admin.banco.status', $received), ['status' => 'collected'])->assertStatus(422);
        $this->assertSame(Order::STATUS_RECEIVED, $received->fresh()->status);
    }

    public function test_counter_requires_the_counter_permission(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.market.manage']);
        $order = $this->makeOrder(['status' => Order::STATUS_READY]);

        $this->actingAs($collaborator)->get(route('admin.banco.index'))->assertForbidden();
        $this->actingAs($collaborator)->postJson(route('admin.banco.status', $order), ['status' => 'collected'])->assertForbidden();
    }

    public function test_counter_lists_the_pickups_of_the_chosen_day_including_orders_not_ready_yet(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $day = now()->addDays(3)->toDateString();
        $ready = $this->makeOrder(['status' => Order::STATUS_READY, 'pickup_date' => $day, 'customer_name' => 'Anna Pronta']);
        $received = $this->makeOrder(['pickup_date' => $day, 'customer_name' => 'Bruno Ricevuto']);
        $this->makeOrder(['status' => Order::STATUS_CANCELLED, 'pickup_date' => $day, 'customer_name' => 'Carla Annullata']);
        $this->makeOrder(['status' => Order::STATUS_READY, 'pickup_date' => now()->addDays(4)->toDateString(), 'customer_name' => 'Dario Altro Giorno']);
        $this->makeOrder(['status' => Order::STATUS_READY, 'pickup_date' => $day, 'fulfillment_method' => Order::FULFILLMENT_DELIVERY, 'customer_name' => 'Elena Domicilio']);

        $orders = $this->actingAs($seller)
            ->get(route('admin.banco.index', ['date' => $day]))
            ->assertOk()
            ->viewData('dayOrders');

        $this->assertEqualsCanonicalizing([$ready->id, $received->id], $orders->pluck('id')->all());
        $this->assertSame([], $orders->firstWhere('id', $received->id)['actions']);
    }

    public function test_counter_day_list_defaults_to_today_and_filters_by_market(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $today = $this->makeOrder(['status' => Order::STATUS_READY, 'pickup_date' => today()->toDateString()]);
        $other = Market::create([
            'name' => 'Altro', 'location' => 'Via', 'weekday' => 3, 'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00', 'pickup_cutoff_time' => '21:00', 'is_active' => true,
        ]);
        $this->makeOrder(['status' => Order::STATUS_READY, 'pickup_date' => today()->toDateString(), 'market_id' => $other->id, 'market_name' => 'Altro']);

        $this->actingAs($seller)->get(route('admin.banco.index'))->assertViewHas('dayOrders', fn ($orders): bool => $orders->count() === 2);
        $this->actingAs($seller)
            ->get(route('admin.banco.index', ['market' => $today->market_id]))
            ->assertViewHas('dayOrders', fn ($orders): bool => $orders->pluck('id')->all() === [$today->id]);
    }

    public function test_qr_is_a_real_svg_with_only_the_order_code_and_is_not_public(): void
    {
        $order = $this->makeOrder();

        $this->get(route('qr.image', ['orderNumber' => $order->order_number]))->assertRedirect(route('login'));

        $response = $this->actingAs($order->user)->get(route('qr.image', ['orderNumber' => $order->order_number]));
        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());

        $this->actingAs(User::factory()->create())
            ->get(route('qr.image', ['orderNumber' => $order->order_number]))
            ->assertNotFound();
    }

    public function test_customer_order_page_shows_the_qr_for_open_pickup_orders(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_READY]);

        $this->actingAs($order->user)
            ->get(route('account.orders.show', ['order' => $order->order_number]))
            ->assertSee('Il tuo QR per il ritiro');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): Order
    {
        $customer = User::factory()->create();
        $market = Market::first() ?? Market::create([
            'name' => 'Mercato', 'location' => 'Piazza', 'weekday' => 6, 'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00', 'pickup_cutoff_time' => '21:00', 'is_active' => true,
        ]);

        return Order::create(array_merge([
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
            'status' => Order::STATUS_RECEIVED,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ], $overrides));
    }
}
