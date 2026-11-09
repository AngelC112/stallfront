<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Market;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCartSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_the_products_by_name(): void
    {
        $this->makeProduct('Vaso in ceramica');
        $this->makeProduct('Candela profumata');

        $this->get(route('home', ['q' => 'vaso']))
            ->assertOk()
            ->assertSee('Vaso in ceramica')
            ->assertDontSee('Candela profumata')
            ->assertSee('1 risultato per');
    }

    public function test_add_from_grid_stays_on_the_storefront_and_fills_the_cart_summary(): void
    {
        $product = $this->makeProduct('Vaso in ceramica', 1250);

        $this->from(route('home'))
            ->post(route('cart.store', ['product' => $product]), ['quantity' => 1, 'stay' => 1])
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Carrello · 1 prodotto')
            ->assertSee('Vaso in ceramica ×1')
            ->assertSee(\App\Support\Format::money(1250))
            ->assertSee('Vedi carrello');
    }

    public function test_storefront_shows_the_next_pickup(): void
    {
        $this->travelTo(now()->startOfWeek()->setTime(9, 0));
        Market::create([
            'name' => 'Mercato del sabato', 'location' => 'Piazza', 'weekday' => 6, 'minimum_order_days' => 2,
            'order_cutoff_time' => '18:00', 'pickup_cutoff_time' => '13:00', 'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Prossimo ritiro')
            ->assertSee('Mercato del sabato')
            ->assertSee('Ordina entro');
    }

    private function makeProduct(string $name, int $priceCents = 1000): Product
    {
        $category = Category::first() ?? Category::create([
            'name' => 'Decorazioni', 'slug' => 'decorazioni', 'description' => 'x',
            'is_active' => true, 'always_available' => true, 'position' => 1,
        ]);

        return Product::create([
            'category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug(), 'short_description' => 'x', 'description' => 'x',
            'price_cents' => $priceCents, 'vat_included' => true, 'visibility' => Product::VISIBILITY_VISIBLE, 'is_active' => true, 'is_visible' => true,
            'track_stock' => true, 'stock' => 10, 'reserved_stock' => 0, 'has_variants' => false, 'always_available' => true,
            'off_season_fulfillment_days' => 0, 'low_stock_threshold' => 0, 'created_by' => null,
        ]);
    }
}
