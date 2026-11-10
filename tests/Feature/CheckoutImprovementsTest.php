<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\MarketClosure;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutImprovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_reserves_stock_and_blocks_a_second_buyer(): void
    {
        $this->makeMarket();
        $product = $this->makeProduct(stock: 1);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);
        $this->actingAs($first)->get(route('checkout.show'))->assertOk()->assertSee('Abbiamo riservato i tuoi prodotti');
        $this->assertSame(1, StockReservation::where('user_id', $first->id)->sum('quantity'));

        $this->actingAs($second)->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);
        $this->actingAs($second)->get(route('checkout.show'))->assertRedirect(route('cart.index'));
    }

    public function test_expired_reservation_no_longer_blocks_others(): void
    {
        $this->makeMarket();
        $product = $this->makeProduct(stock: 1);
        $other = User::factory()->create();
        StockReservation::create(['user_id' => $other->id, 'product_id' => $product->id, 'quantity' => 1, 'expires_at' => now()->subMinute()]);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);
        $this->actingAs($buyer)->get(route('checkout.show'))->assertOk();
    }

    public function test_order_confirmation_releases_the_reservation(): void
    {
        $market = $this->makeMarket();
        $product = $this->makeProduct(stock: 3);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);
        $page = $this->actingAs($buyer)->get(route('checkout.show'));
        $date = $page->viewData('marketDates')->first()['pickup_date'];

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'fulfillment_method' => 'pickup',
            'pickup_option' => $market->id.'|'.$date,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, StockReservation::where('user_id', $buyer->id)->count());
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_closed_dates_appear_grey_with_reason(): void
    {
        $market = $this->makeMarket();
        $product = $this->makeProduct(stock: 5);
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);

        $firstDate = $this->actingAs($buyer)->get(route('checkout.show'))->viewData('marketDates')->first()['pickup_date'];
        MarketClosure::create(['market_id' => $market->id, 'closure_date' => $firstDate, 'reason' => 'Maltempo']);

        $this->actingAs($buyer)->get(route('checkout.show'))
            ->assertSee('Date non disponibili')
            ->assertSee('Chiuso: Maltempo');
    }

    public function test_where_we_are_page_lists_markets_with_closures_and_directions(): void
    {
        $market = $this->makeMarket();
        $next = now()->startOfDay();
        while ($next->dayOfWeekIso !== $market->weekday) {
            $next->addDay();
        }
        MarketClosure::create(['market_id' => $market->id, 'closure_date' => $next->toDateString(), 'reason' => 'Festa patronale']);

        $this->get(route('where-we-are'))
            ->assertOk()
            ->assertSee('Mercato del sabato')
            ->assertSee('Chiuso · Festa patronale')
            ->assertSee('Aperto')
            ->assertSee('google.com/maps', false);
    }

    public function test_reorder_puts_available_items_back_in_the_cart(): void
    {
        $available = $this->makeProduct(stock: 5, slug: 'vaso');
        $gone = $this->makeProduct(stock: 5, slug: 'piatto');
        $gone->update(['is_active' => false]);
        $customer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-T-REORDER', 'user_id' => $customer->id, 'customer_name' => 'x', 'customer_email' => $customer->email,
            'fulfillment_method' => 'pickup', 'payment_method' => 'cash_on_pickup', 'payment_status' => 'paid', 'status' => Order::STATUS_COLLECTED,
            'items_subtotal_cents' => 1000, 'delivery_cost_cents' => 0, 'total_cents' => 1000, 'placed_at' => now(),
        ]);
        foreach ([$available, $gone] as $product) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price_cents' => 500, 'line_total_cents' => 1000]);
        }

        $this->actingAs($customer)
            ->post(route('account.orders.reorder', ['order' => $order->order_number]))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', '1 prodotto aggiunto al carrello. 1 non è più disponibile.');

        $this->assertSame(2, $customer->fresh()->cart[$available->id.':0']['quantity']);
    }

    private function makeMarket(): Market
    {
        return Market::create([
            'name' => 'Mercato del sabato', 'location' => 'Piazza Centrale', 'weekday' => 6, 'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00', 'pickup_cutoff_time' => '21:00', 'is_active' => true,
        ]);
    }

    private function makeProduct(int $stock, string $slug = 'vaso'): Product
    {
        return Product::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'short_description' => $slug, 'description' => $slug,
            'price_cents' => 1000, 'vat_included' => true, 'visibility' => Product::VISIBILITY_VISIBLE,
            'is_active' => true, 'is_visible' => true, 'track_stock' => true, 'stock' => $stock, 'reserved_stock' => 0,
            'has_variants' => false, 'always_available' => true, 'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 0, 'created_by' => null,
        ]);
    }
}
