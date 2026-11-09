<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create(['role' => User::ROLE_SELLER]);
    }

    public function test_orders_are_grouped_by_market_and_date_with_delivery_separate(): void
    {
        $pickup = $this->makeOrder(['customer_name' => 'Cliente Ritiro']);
        $delivery = $this->makeOrder([
            'customer_name' => 'Cliente Domicilio', 'fulfillment_method' => Order::FULFILLMENT_DELIVERY,
            'market_id' => null, 'market_name' => null, 'pickup_date' => null,
        ]);

        $response = $this->actingAs($this->seller)->get(route('admin.orders.index'));

        $response->assertOk()
            ->assertSeeInOrder(['Mercato Uno', 'Cliente Ritiro', 'Consegna a domicilio', 'Cliente Domicilio']);
    }

    public function test_fulfilled_orders_sink_to_the_bottom_and_due_today_goes_first(): void
    {
        $this->makeOrder(['customer_name' => 'Evaso Cliente', 'status' => Order::STATUS_READY, 'placed_at' => now()->subDays(3)]);
        $this->makeOrder(['customer_name' => 'Normale Cliente', 'placed_at' => now()->subDay()]);
        $this->makeOrder([
            'customer_name' => 'Stagione Cliente', 'extra_season_days' => 10,
            'pickup_date' => now()->addDays(5)->toDateString(), 'placed_at' => now(),
        ]);

        $this->actingAs($this->seller)
            ->get(route('admin.orders.index'))
            ->assertSeeInOrder(['Stagione Cliente', 'Da preparare oggi', 'Normale Cliente', 'Evaso Cliente']);
    }

    public function test_future_off_season_orders_are_flagged_and_filterable(): void
    {
        $this->makeOrder([
            'customer_name' => 'Futuro Cliente', 'extra_season_days' => 10,
            'pickup_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->makeOrder(['customer_name' => 'Altro Cliente']);

        $this->actingAs($this->seller)
            ->get(route('admin.orders.index'))
            ->assertSee('1 ordine fuori stagione futuro');

        $this->actingAs($this->seller)
            ->get(route('admin.orders.index', ['off_season' => 1]))
            ->assertSee('Futuro Cliente')
            ->assertDontSee('Altro Cliente');
    }

    public function test_default_view_hides_final_orders_and_all_filter_shows_them(): void
    {
        $this->makeOrder(['customer_name' => 'Chiuso Cliente', 'status' => Order::STATUS_COLLECTED]);

        $this->actingAs($this->seller)->get(route('admin.orders.index'))->assertDontSee('Chiuso Cliente');
        $this->actingAs($this->seller)->get(route('admin.orders.index', ['status' => 'all']))->assertSee('Chiuso Cliente');
    }

    public function test_product_view_totals_pieces_and_lists_fulfilled_separately(): void
    {
        $first = $this->makeOrder(['customer_name' => 'Primo']);
        $second = $this->makeOrder(['customer_name' => 'Secondo']);
        $done = $this->makeOrder(['customer_name' => 'Terzo Evaso', 'status' => Order::STATUS_READY]);

        foreach ([$first, $second, $done] as $order) {
            OrderItem::create([
                'order_id' => $order->id, 'product_name' => 'Vaso blu', 'quantity' => 2,
                'unit_price_cents' => 500, 'line_total_cents' => 1000,
            ]);
        }

        $response = $this->actingAs($this->seller)->get(route('admin.orders.index', ['view' => 'product']));

        $response->assertOk()
            ->assertSeeInOrder(['>4<', 'Vaso blu', 'Già evasi', 'Terzo Evaso'], false);
    }

    public function test_bulk_status_change_notifies_each_order_and_skips_invalid(): void
    {
        $a = $this->makeOrder();
        $b = $this->makeOrder();
        $collected = $this->makeOrder(['status' => Order::STATUS_COLLECTED]);

        $this->actingAs($this->seller)
            ->post(route('admin.orders.bulk-status'), ['order_ids' => [$a->id, $b->id, $collected->id], 'status' => 'preparing'])
            ->assertSessionHas('success');

        $this->assertSame(Order::STATUS_PREPARING, $a->fresh()->status);
        $this->assertSame(Order::STATUS_PREPARING, $b->fresh()->status);
        $this->assertSame(Order::STATUS_COLLECTED, $collected->fresh()->status);
        $this->assertSame(2, NotificationQueueItem::where('event', 'order_preparing')->count());
    }

    public function test_internal_notes_are_stored_visible_to_staff_and_never_to_customer(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->seller)
            ->post(route('admin.orders.notes.store', $order), ['body' => 'Ritira la zia'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->seller)->get(route('admin.orders.show', $order))->assertSee('Ritira la zia');
        $this->actingAs($order->user)->get(route('account.orders.show', ['order' => $order->order_number]))->assertDontSee('Ritira la zia');
    }

    public function test_collaborator_without_notes_permission_cannot_see_or_add_notes(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.market.manage']);
        $order = $this->makeOrder();
        $order->notes()->create(['user_id' => $this->seller->id, 'body' => 'Segreto']);

        $this->actingAs($collaborator)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Segreto');
        $this->actingAs($collaborator)->post(route('admin.orders.notes.store', $order), ['body' => 'x'])->assertForbidden();
    }

    public function test_detail_partial_returns_fragment_without_layout(): void
    {
        $order = $this->makeOrder(['customer_name' => 'Pannello Cliente']);

        $this->actingAs($this->seller)
            ->get(route('admin.orders.show', ['order' => $order, 'partial' => 1]))
            ->assertOk()
            ->assertSee('Pannello Cliente')
            ->assertDontSee('<html', false);
    }

    public function test_contact_links_use_italian_prefix_for_whatsapp(): void
    {
        $order = $this->makeOrder(['customer_phone' => '333 123 4567']);

        $this->assertSame('tel:3331234567', $order->telUrl());
        $this->assertSame('https://wa.me/393331234567', $order->whatsappUrl());
        $this->assertNull($this->makeOrder(['customer_phone' => null])->whatsappUrl());
    }

    public function test_print_view_renders_for_staff_only(): void
    {
        $this->makeOrder(['customer_name' => 'Stampa Cliente']);

        $this->actingAs($this->seller)->get(route('admin.orders.print'))->assertOk()->assertSee('Stampa Cliente');
        $this->actingAs(User::factory()->create())->get(route('admin.orders.print'))->assertForbidden();
    }

    public function test_preparation_start_date_follows_pickup_date_and_extra_days(): void
    {
        $order = $this->makeOrder(['extra_season_days' => 10, 'pickup_date' => '2026-12-20']);

        $this->assertSame('2026-12-10', $order->preparation_start_date->toDateString());

        $order->update(['pickup_date' => '2026-12-27']);

        $this->assertSame('2026-12-17', $order->fresh()->preparation_start_date->toDateString());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): Order
    {
        $customer = User::factory()->create();
        $market = Market::first() ?? Market::create([
            'name' => 'Mercato Uno', 'location' => 'Piazza', 'weekday' => 6, 'minimum_order_days' => 3,
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
