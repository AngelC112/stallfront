<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\MarketClosure;
use App\Models\MarketHoliday;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PickupMarketAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MarketClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_dates_are_not_selectable_for_pickup(): void
    {
        $market = $this->makeMarket();
        $product = $this->makeProduct();
        $cart = ['line' => ['product_id' => $product->id, 'quantity' => 1]];
        $service = app(PickupMarketAvailabilityService::class);

        $all = $service->availableDates($cart)->pluck('pickup_date');
        $this->assertGreaterThanOrEqual(3, $all->count());

        MarketClosure::create(['market_id' => $market->id, 'closure_date' => $all[0], 'reason' => 'Maltempo']);
        MarketHoliday::create(['market_id' => null, 'start_date' => $all[1], 'end_date' => $all[1], 'reason' => 'Ferie']);

        $remaining = $service->availableDates($cart)->pluck('pickup_date');

        $this->assertNotContains($all[0], $remaining);
        $this->assertNotContains($all[1], $remaining);
        $this->assertContains($all[2], $remaining);
    }

    public function test_preview_shows_summary_and_changes_nothing(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $market = $this->makeMarket();
        $date = now()->addDays(10)->toDateString();
        $this->makeOrder($market, $date);
        $this->makeOrder($market, $date);

        $this->actingAs($seller)
            ->post(route('admin.holidays.store'), $this->closurePayload($market, $date))
            ->assertOk()
            ->assertSee('Ordini coinvolti')
            ->assertSee('Conferma e avvisa i clienti');

        $this->assertDatabaseCount('market_closures', 0);
        $this->assertSame(2, Order::where('status', Order::STATUS_RECEIVED)->count());
        Mail::assertNothingSent();
    }

    public function test_confirmed_closure_moves_orders_to_reschedule_and_notifies_customers(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $market = $this->makeMarket();
        $date = now()->addDays(10)->toDateString();
        $affected = $this->makeOrder($market, $date);
        $otherDay = $this->makeOrder($market, now()->addDays(17)->toDateString());

        $this->actingAs($seller)
            ->post(route('admin.holidays.store'), $this->closurePayload($market, $date) + ['confirmed' => 1])
            ->assertRedirect(route('admin.holidays.index'));

        $this->assertDatabaseHas('market_closures', ['market_id' => $market->id, 'reason' => 'Maltempo']);
        $this->assertSame(Order::STATUS_TO_RESCHEDULE, $affected->fresh()->status);
        $this->assertSame(Order::STATUS_RECEIVED, $otherDay->fresh()->status);
        $this->assertSame('Maltempo', $affected->statusHistories()->latest('id')->first()->reason);
        $queued = NotificationQueueItem::where('event', 'market_closed')->get();
        $this->assertCount(1, $queued);
        $this->assertSame($affected->customer_email, $queued->first()->recipient_email);
        $this->assertStringContainsString('Maltempo', $queued->first()->body);
    }

    public function test_holiday_for_all_markets_reschedules_orders_in_the_period(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $market = $this->makeMarket();
        $order = $this->makeOrder($market, now()->addDays(10)->toDateString());

        $this->actingAs($seller)
            ->post(route('admin.holidays.store'), [
                'type' => 'holiday',
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(20)->toDateString(),
                'reason' => 'Vacanze',
                'confirmed' => 1,
            ])
            ->assertRedirect(route('admin.holidays.index'));

        $this->assertDatabaseHas('market_holidays', ['market_id' => null, 'reason' => 'Vacanze']);
        $this->assertSame(Order::STATUS_TO_RESCHEDULE, $order->fresh()->status);
    }

    public function test_collaborator_needs_the_matching_permission(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'closures.holidays.manage']);
        $market = $this->makeMarket();

        $this->actingAs($collaborator)
            ->post(route('admin.holidays.store'), $this->closurePayload($market, now()->addDays(10)->toDateString()))
            ->assertForbidden();

        $this->actingAs($collaborator)->get(route('admin.holidays.index'))->assertOk();
    }

    public function test_customer_cannot_open_holidays_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.holidays.index'))
            ->assertForbidden();
    }

    public function test_account_menu_is_not_active_on_orders_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertSeeInOrder(['border-brand-600', 'I miei ordini', 'border-transparent', 'Il mio account'], false);
    }

    /**
     * @return array<string, mixed>
     */
    private function closurePayload(Market $market, string $date): array
    {
        return [
            'type' => 'closure',
            'market_id' => $market->id,
            'start_date' => $date,
            'reason' => 'Maltempo',
        ];
    }

    private function makeMarket(): Market
    {
        return Market::create([
            'name' => 'Mercato del sabato',
            'location' => 'Piazza Centrale',
            'weekday' => 6,
            'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00',
            'pickup_cutoff_time' => '21:00',
            'is_active' => true,
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'name' => 'Vaso', 'slug' => 'vaso', 'short_description' => 'Vaso', 'description' => 'Vaso',
            'price_cents' => 1000, 'vat_included' => true, 'visibility' => Product::VISIBILITY_VISIBLE,
            'is_active' => true, 'is_visible' => true, 'track_stock' => false, 'stock' => 0,
            'reserved_stock' => 0, 'has_variants' => false, 'always_available' => true,
            'off_season_fulfillment_days' => 2, 'low_stock_threshold' => 1, 'created_by' => null,
        ]);
    }

    private function makeOrder(Market $market, string $pickupDate): Order
    {
        $customer = User::factory()->create();

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
            'status' => Order::STATUS_RECEIVED,
            'pickup_date' => $pickupDate,
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ]);
    }
}
