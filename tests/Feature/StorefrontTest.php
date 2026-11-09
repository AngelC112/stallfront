<?php

namespace Tests\Feature;

use App\Mail\BackInStockMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Category;
use App\Models\CollaboratorPermission;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOptionType;
use App\Models\ProductOptionValue;
use App\Models\ProductPhoto;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackInStockNotifier;
use App\Services\PickupMarketAvailabilityService;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_displays_public_catalog(): void
    {
        $category = Category::create([
            'name' => 'Decorazioni',
            'slug' => 'decorazioni',
            'description' => 'Oggettistica per casa e regalo.',
            'is_active' => true,
            'always_available' => true,
            'position' => 1,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Vaso in ceramica',
            'slug' => 'vaso-in-ceramica',
            'short_description' => 'Vaso elegante e moderno.',
            'description' => 'Vaso in ceramica con finitura naturale, ideale per regali e arredamento.',
            'price_cents' => 2500,
            'vat_included' => true,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => true,
            'stock' => 25,
            'reserved_stock' => 0,
            'has_variants' => false,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Vaso in ceramica');
        $response->assertSee('Decorazioni');
    }

    public function test_panel_and_storefront_shortcuts_follow_user_permissions(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Pannello')
            ->assertSee('Il mio account');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Vetrina');

        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        CollaboratorPermission::create([
            'user_id' => $collaborator->id,
            'permission' => Permission::DASHBOARD_VIEW,
        ]);

        $this->actingAs($collaborator)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Pannello');

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Pannello');
    }

    public function test_category_page_only_displays_products_in_that_category(): void
    {
        $decorations = Category::create([
            'name' => 'Decorazioni',
            'slug' => 'decorazioni',
            'description' => 'Oggetti decorativi.',
            'is_active' => true,
            'always_available' => true,
            'position' => 1,
        ]);

        $accessories = Category::create([
            'name' => 'Accessori',
            'slug' => 'accessori',
            'description' => 'Piccoli accessori.',
            'is_active' => true,
            'always_available' => true,
            'position' => 2,
        ]);

        foreach ([
            [$decorations, 'Candeliere in ceramica', 'candeliere-in-ceramica'],
            [$accessories, 'Portachiavi in legno', 'portachiavi-in-legno'],
        ] as [$category, $name, $slug]) {
            Product::create([
                'category_id' => $category->id,
                'name' => $name,
                'slug' => $slug,
                'short_description' => null,
                'description' => null,
                'price_cents' => 1500,
                'vat_included' => true,
                'is_active' => true,
                'is_visible' => true,
                'track_stock' => true,
                'stock' => 5,
                'reserved_stock' => 0,
                'has_variants' => false,
                'always_available' => true,
                'off_season_fulfillment_days' => 2,
                'low_stock_threshold' => 3,
                'created_by' => null,
            ]);
        }

        $response = $this->get(route('storefront.category', ['category' => $decorations]));

        $response->assertOk();
        $response->assertSee('Candeliere in ceramica');
        $response->assertDontSee('Portachiavi in legno');
        $response->assertSee('overflow-x-auto', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_seller_can_configure_weekly_pickup_market(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)
            ->get(route('admin.markets.index'))
            ->assertOk()
            ->assertSee('Mercati');

        $this->post(route('admin.markets.store'), [
            'name' => 'Mercato del sabato',
            'location' => 'Piazza Centrale',
            'weekday' => 6,
            'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00',
            'pickup_cutoff_time' => '12:00',
            'is_active' => '1',
        ])->assertRedirect(route('admin.markets.index'));

        $this->assertDatabaseHas('markets', [
            'name' => 'Mercato del sabato',
            'weekday' => 6,
            'minimum_order_days' => 3,
            'is_active' => true,
        ]);
    }

    public function test_market_dates_include_off_season_extra_lead_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 12:00:00'));

        try {
            $category = Category::create([
                'name' => 'Decorazioni stagionali',
                'slug' => 'decorazioni-stagionali',
                'description' => null,
                'is_active' => true,
                'always_available' => false,
                'season_start_month' => 4,
                'season_start_day' => 1,
                'season_end_month' => 4,
                'season_end_day' => 30,
                'off_season_behavior' => 'long',
                'off_season_days' => 10,
                'position' => 1,
            ]);

            $product = Product::create([
                'category_id' => $category->id,
                'name' => 'Centrotavola primaverile',
                'slug' => 'centrotavola-primaverile',
                'short_description' => 'Centrotavola.',
                'description' => 'Centrotavola decorativo.',
                'price_cents' => 2000,
                'vat_included' => true,
                'visibility' => Product::VISIBILITY_VISIBLE,
                'season_behavior' => Product::SEASON_FOLLOW,
                'is_active' => true,
                'is_visible' => true,
                'track_stock' => true,
                'stock' => 5,
                'reserved_stock' => 0,
                'has_variants' => false,
                'always_available' => false,
                'off_season_fulfillment_days' => null,
                'low_stock_threshold' => 3,
                'created_by' => null,
            ]);

            Market::create([
                'name' => 'Mercato del sabato',
                'location' => 'Piazza Centrale',
                'weekday' => 6,
                'minimum_order_days' => 3,
                'order_cutoff_time' => '18:00',
                'pickup_cutoff_time' => '21:00',
                'is_active' => true,
            ]);

            $dates = app(PickupMarketAvailabilityService::class)->availableDates([
                "{$product->id}:0" => [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => 1,
                ],
            ]);

            $this->assertNotEmpty($dates);
            $this->assertSame('2026-11-14', $dates->first()['pickup_date']);
            $this->assertSame(10, $dates->first()['extra_lead_days']);
            $this->assertSame('2026-11-01 18:00:00', $dates->first()['deadline']->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_product_detail_page_is_accessible(): void
    {
        $category = Category::create([
            'name' => 'Decorazioni',
            'slug' => 'decorazioni',
            'description' => 'Oggettistica per casa e regalo.',
            'is_active' => true,
            'always_available' => true,
            'position' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Vaso in ceramica',
            'slug' => 'vaso-in-ceramica',
            'short_description' => 'Vaso elegante e moderno.',
            'description' => 'Vaso in ceramica con finitura naturale, ideale per regali e arredamento.',
            'price_cents' => 2500,
            'vat_included' => true,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => true,
            'stock' => 25,
            'reserved_stock' => 0,
            'has_variants' => false,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $response = $this->get('/prodotti/'.$product->slug);

        $response->assertOk();
        $response->assertSee('Vaso in ceramica');
        $response->assertSee('25,00');
        $response->assertDontSee('Colore');
        $response->assertDontSee('Taglia');
    }

    public function test_untracked_product_hides_stock_count_and_can_be_added_to_cart(): void
    {
        $product = Product::create([
            'name' => 'Tazza artigianale',
            'slug' => 'tazza-artigianale',
            'short_description' => 'Tazza in ceramica.',
            'description' => 'Tazza fatta a mano.',
            'price_cents' => 1200,
            'vat_included' => true,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => false,
            'stock' => 0,
            'reserved_stock' => 0,
            'has_variants' => false,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $detailResponse = $this->get(route('storefront.product', ['product' => $product]));

        $detailResponse->assertOk();
        $detailResponse->assertDontSee('0 disponibili');
        $detailResponse->assertSee('Aggiungi al carrello');

        $this->post(route('cart.store', ['product' => $product]), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Tazza artigianale')
            ->assertSee(\App\Support\Format::money(2400));
    }

    public function test_cart_survives_logout_and_next_login(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Portacandele',
            'slug' => 'portacandele',
            'short_description' => 'Portacandele in vetro.',
            'description' => 'Portacandele decorativo.',
            'price_cents' => 1600,
            'vat_included' => true,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => false,
            'stock' => 0,
            'reserved_stock' => 0,
            'has_variants' => false,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $this->actingAs($user)
            ->post(route('cart.store', ['product' => $product]), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->assertSame(2, $user->fresh()->cart["{$product->id}:0"]['quantity']);

        $this->post(route('logout'))->assertRedirect('/');

        $guestProduct = Product::create([
            'name' => 'Piatto decorativo',
            'slug' => 'piatto-decorativo',
            'short_description' => 'Piatto decorativo.',
            'description' => 'Piatto in ceramica.',
            'price_cents' => 500,
            'vat_included' => true,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => false,
            'stock' => 0,
            'reserved_stock' => 0,
            'has_variants' => false,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $this->post(route('cart.store', ['product' => $guestProduct]), ['quantity' => 3])
            ->assertRedirect(route('cart.index'));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Portacandele')
            ->assertSee('Piatto decorativo')
            ->assertSee('value="2"', false)
            ->assertSee('value="3"', false)
            ->assertSee(\App\Support\Format::money(4700));
    }

    public function test_pickup_checkout_creates_order_sends_confirmation_and_decrements_stock(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = $this->createCheckoutProduct('Vaso da tavolo', 'vaso-da-tavolo', 2200, 5, true);
        $pickupDate = now()->addDays(14);
        $market = Market::create([
            'name' => 'Mercato del sabato',
            'location' => 'Piazza Centrale',
            'weekday' => $pickupDate->dayOfWeekIso,
            'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00',
            'pickup_cutoff_time' => '21:00',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('cart.store', ['product' => $product]), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Ritiro al mercato')
            ->assertSee('Mercato del sabato')
            ->assertSee('Piazza Centrale');

        $response = $this->post(route('checkout.store'), [
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'pickup_option' => $market->id.'|'.$pickupDate->toDateString(),
            'customer_note' => 'Confezione regalo.',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('account.orders.show', ['order' => $order->order_number]));
        $this->assertSame(Order::STATUS_RECEIVED, $order->status);
        $this->assertSame('cash_on_pickup', $order->payment_method);
        $this->assertSame($market->id, $order->market_id);
        $this->assertSame('Mercato del sabato', $order->market_name);
        $this->assertSame(4400, $order->total_cents);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertSame('Vaso da tavolo', $order->items()->firstOrFail()->product_name);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => -2,
            'reason' => 'order',
        ]);
        $this->assertSame([], $user->fresh()->cart);

        $this->get(route('account.orders.show', ['order' => $order->order_number]))
            ->assertOk()
            ->assertSee('Pagamento al ritiro')
            ->assertSee(\App\Support\Format::money(4400));

        Mail::assertSent(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail): bool => $mail->order->is($order));
    }

    public function test_delivery_checkout_requires_vendor_enabled_customer(): void
    {
        $user = User::factory()->create(['delivery_enabled' => false]);
        $product = $this->createCheckoutProduct('Vassoio decorativo', 'vassoio-decorativo', 3000, 0, false);

        $this->actingAs($user)
            ->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);

        $this->from(route('checkout.show'))
            ->post(route('checkout.store'), [
                'fulfillment_method' => Order::FULFILLMENT_DELIVERY,
                'delivery_address' => 'Via Roma 10, Torino',
                'delivery_phone' => '3331234567',
            ])
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('fulfillment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_enabled_customer_pays_delivery_cost_at_handoff(): void
    {
        Mail::fake();
        Setting::set('delivery.enabled', true);
        Setting::set('delivery.cost_cents', 600);

        $user = User::factory()->create(['delivery_enabled' => true, 'phone' => '3331234567', 'phone_verified_at' => now()]);
        $product = $this->createCheckoutProduct('Vassoio in legno', 'vassoio-in-legno', 3000, 0, false);

        $this->actingAs($user)
            ->post(route('cart.store', ['product' => $product]), ['quantity' => 1]);

        $this->post(route('checkout.store'), [
            'fulfillment_method' => Order::FULFILLMENT_DELIVERY,
            'delivery_address' => 'Via Roma 10, Torino',
            'delivery_phone' => '3331234567',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('cash_on_delivery', $order->payment_method);
        $this->assertSame(600, $order->delivery_cost_cents);
        $this->assertSame(3600, $order->total_cents);
        Mail::assertSent(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail): bool => $mail->order->is($order));
    }

    public function test_checkout_rejects_cart_quantity_that_exceeds_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->createCheckoutProduct('Ciotola in ceramica', 'ciotola-in-ceramica', 1000, 1, true);
        $pickupDate = now()->addDays(14);
        $market = Market::create([
            'name' => 'Mercato di prova',
            'location' => 'Piazza Centrale',
            'weekday' => $pickupDate->dayOfWeekIso,
            'minimum_order_days' => 3,
            'order_cutoff_time' => '18:00',
            'pickup_cutoff_time' => '21:00',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('cart.store', ['product' => $product]), ['quantity' => 1])
            ->assertRedirect(route('cart.index'));

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Mercato di prova');

        $product->update(['stock' => 0]);

        $this->actingAs($user)
            ->from(route('checkout.show'))
            ->post(route('checkout.store'), [
                'fulfillment_method' => Order::FULFILLMENT_PICKUP,
                'pickup_option' => $market->id.'|'.$pickupDate->toDateString(),
            ])
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('pickup_option');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_cancelling_an_admin_order_restores_stock_only_once(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $product = $this->createCheckoutProduct('Vaso blu', 'vaso-blu', 2400, 3, true);
        $order = Order::create([
            'order_number' => 'ORD-TEST-1001',
            'user_id' => null,
            'customer_name' => 'Cliente di prova',
            'customer_email' => 'cliente@example.test',
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'unpaid',
            'status' => Order::STATUS_RECEIVED,
            'pickup_date' => now()->addDay()->toDateString(),
            'items_subtotal_cents' => 4800,
            'delivery_cost_cents' => 0,
            'total_cents' => 4800,
            'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price_cents' => 2400,
            'line_total_cents' => 4800,
        ]);

        $this->actingAs($seller)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->from(route('admin.orders.show', ['order' => $order]))
            ->patch(route('admin.orders.status', ['order' => $order]), ['status' => Order::STATUS_CANCELLED])
            ->assertRedirect(route('admin.orders.show', ['order' => $order]));

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => 2,
            'reason' => 'order_cancel',
        ]);

        $this->from(route('admin.orders.show', ['order' => $order]))
            ->patch(route('admin.orders.status', ['order' => $order]), ['status' => Order::STATUS_PREPARING])
            ->assertSessionHasErrors('status');

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_restock_notification_only_goes_to_subscriber_for_restocked_variant(): void
    {
        Mail::fake();

        $product = Product::create([
            'name' => 'Candela profumata',
            'slug' => 'candela-profumata',
            'short_description' => 'Candela artigianale.',
            'description' => 'Candela in cera naturale.',
            'price_cents' => 1800,
            'vat_included' => true,
            'visibility' => Product::VISIBILITY_VISIBLE,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => true,
            'stock' => 0,
            'reserved_stock' => 0,
            'has_variants' => true,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $optionType = ProductOptionType::create([
            'product_id' => $product->id,
            'name' => 'Fragranza',
            'position' => 1,
        ]);

        $lavenderValue = ProductOptionValue::create([
            'option_type_id' => $optionType->id,
            'value' => 'Lavanda',
            'position' => 1,
        ]);

        $citrusValue = ProductOptionValue::create([
            'option_type_id' => $optionType->id,
            'value' => 'Agrumi',
            'position' => 2,
        ]);

        $lavenderVariant = ProductVariant::create([
            'product_id' => $product->id,
            'price_cents' => 1800,
            'stock' => 0,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);
        $lavenderVariant->optionValues()->attach($lavenderValue->id);

        $citrusVariant = ProductVariant::create([
            'product_id' => $product->id,
            'price_cents' => 1800,
            'stock' => 0,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);
        $citrusVariant->optionValues()->attach($citrusValue->id);

        $this->from(route('storefront.product', ['product' => $product]))
            ->post(route('back-in-stock.store', ['product' => $product]), [
                'email' => 'lavanda@example.test',
                'variant_id' => $lavenderVariant->id,
            ])->assertRedirect(route('storefront.product', ['product' => $product]));

        $this->from(route('storefront.product', ['product' => $product]))
            ->post(route('back-in-stock.store', ['product' => $product]), [
                'email' => 'agrumi@example.test',
                'variant_id' => $citrusVariant->id,
            ])->assertRedirect(route('storefront.product', ['product' => $product]));

        $this->assertDatabaseHas('back_in_stock_subscriptions', [
            'email' => 'lavanda@example.test',
            'variant_key' => 'fragranza: lavanda',
        ]);

        $lavenderVariant->update(['stock' => 2]);
        $product->refresh()->load('activeVariants.optionValues.optionType');

        $sentCount = app(BackInStockNotifier::class)->notifyAvailable($product);

        $this->assertSame(1, $sentCount);
        Mail::assertSent(BackInStockMail::class, fn (BackInStockMail $mail): bool => $mail->variantLabel === 'Fragranza: Lavanda' && $mail->hasTo('lavanda@example.test'));
        Mail::assertNotSent(BackInStockMail::class, fn (BackInStockMail $mail): bool => $mail->hasTo('agrumi@example.test'));
        $this->assertDatabaseMissing('back_in_stock_subscriptions', ['email' => 'lavanda@example.test']);
        $this->assertDatabaseHas('back_in_stock_subscriptions', ['email' => 'agrumi@example.test']);
    }

    private function createCheckoutProduct(string $name, string $slug, int $priceCents, int $stock, bool $trackStock): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'short_description' => $name,
            'description' => $name,
            'price_cents' => $priceCents,
            'vat_included' => true,
            'visibility' => Product::VISIBILITY_VISIBLE,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => $trackStock,
            'stock' => $stock,
            'reserved_stock' => 0,
            'has_variants' => false,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);
    }

    public function test_product_detail_uses_real_option_data_and_not_mockup_placeholders(): void
    {
        $category = Category::create([
            'name' => 'Decorazioni',
            'slug' => 'decorazioni',
            'description' => 'Oggettistica per casa e regalo.',
            'is_active' => true,
            'always_available' => true,
            'position' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Lampada in vetro',
            'slug' => 'lampada-in-vetro',
            'short_description' => 'Lampada elegante.',
            'description' => 'Lampada in vetro trasparente con struttura in metallo.',
            'price_cents' => 5400,
            'vat_included' => true,
            'is_active' => true,
            'is_visible' => true,
            'track_stock' => true,
            'stock' => 18,
            'reserved_stock' => 0,
            'has_variants' => true,
            'always_available' => true,
            'off_season_fulfillment_days' => 2,
            'low_stock_threshold' => 3,
            'created_by' => null,
        ]);

        $frontPhoto = ProductPhoto::create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/lampada-front.jpg',
            'alt' => 'Lampada vista frontale',
            'is_cover' => true,
            'position' => 1,
        ]);

        $sidePhoto = ProductPhoto::create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/lampada-side.jpg',
            'alt' => 'Lampada vista laterale',
            'is_cover' => false,
            'position' => 2,
        ]);

        $colorType = ProductOptionType::create([
            'product_id' => $product->id,
            'name' => 'Colore',
            'position' => 1,
        ]);

        $greenValue = ProductOptionValue::create(['option_type_id' => $colorType->id, 'value' => 'Verde', 'position' => 1]);
        $naturalValue = ProductOptionValue::create(['option_type_id' => $colorType->id, 'value' => 'Tonalità naturale', 'position' => 2]);

        $sizeType = ProductOptionType::create([
            'product_id' => $product->id,
            'name' => 'Taglia',
            'position' => 2,
        ]);

        $smallValue = ProductOptionValue::create(['option_type_id' => $sizeType->id, 'value' => 'S', 'position' => 1]);
        $mediumValue = ProductOptionValue::create(['option_type_id' => $sizeType->id, 'value' => 'M', 'position' => 2]);

        $greenVariant = ProductVariant::create([
            'product_id' => $product->id,
            'price_cents' => 5400,
            'stock' => 2,
            'reserved_stock' => 0,
            'is_active' => true,
            'photo_id' => $frontPhoto->id,
        ]);
        $greenVariant->optionValues()->attach([$greenValue->id, $smallValue->id]);

        $naturalVariant = ProductVariant::create([
            'product_id' => $product->id,
            'price_cents' => 5900,
            'stock' => 1,
            'reserved_stock' => 0,
            'is_active' => true,
            'photo_id' => $sidePhoto->id,
        ]);
        $naturalVariant->optionValues()->attach([$naturalValue->id, $mediumValue->id]);

        $response = $this->get('/prodotti/'.$product->slug);

        $response->assertOk();
        $response->assertSee('Colore');
        $response->assertSee('Taglia');
        $response->assertSee('Verde');
        $response->assertSee('Tonalità naturale');
        $response->assertSee('lampada-front.jpg');
        $response->assertSee('lampada-side.jpg');
        $response->assertSee('Mostra foto 1 di Lampada in vetro');
        $response->assertSee('Foto precedente', false);
        $response->assertSee('Foto successiva', false);
        $this->assertSame(1, substr_count($response->getContent(), route('storefront.category', ['category' => $category])));
        $response->assertViewHas('variants', fn ($variants): bool => $variants->firstWhere('id', $greenVariant->id)['image'] === $frontPhoto->url
            && $variants->firstWhere('id', $naturalVariant->id)['image'] === $sidePhoto->url
        );
        $response->assertDontSee('Rosso');
        $response->assertDontSee('Ultimi 3 pezzi');
        $response->assertDontSee('[Descrizione del prodotto]');

        $this->from(route('storefront.product', ['product' => $product]))
            ->post(route('cart.store', ['product' => $product]), [
                'variant_id' => $naturalVariant->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('storefront.product', ['product' => $product]))
            ->assertSessionHasErrors('quantity');

        $this->post(route('cart.store', ['product' => $product]), [
            'variant_id' => $naturalVariant->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Colore: Tonalità naturale · Taglia: M')
            ->assertSee(\App\Support\Format::money(5900));
    }
}
