<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Order;
use App\Models\User;
use App\Services\BlocklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create(['role' => User::ROLE_SELLER]);
    }

    public function test_customer_list_searches_and_filters(): void
    {
        User::factory()->create(['name' => 'Anna Bianchi', 'email' => 'anna@example.com', 'phone' => '3331110000']);
        User::factory()->create(['name' => 'Luca Neri', 'email' => 'luca@example.com', 'no_show_count' => 1]);
        User::factory()->create(['name' => 'Piero Blu', 'email' => 'piero@example.com', 'blocked_at' => now()]);

        $this->actingAs($this->seller)->get(route('admin.customers.index', ['q' => '333111']))
            ->assertSee('Anna Bianchi')->assertDontSee('Luca Neri');

        $this->actingAs($this->seller)->get(route('admin.customers.index', ['filter' => 'no_shows']))
            ->assertSee('Luca Neri')->assertDontSee('Anna Bianchi');

        $this->actingAs($this->seller)->get(route('admin.customers.index', ['filter' => 'blocked']))
            ->assertSee('Piero Blu')->assertDontSee('Luca Neri');
    }

    public function test_seller_enables_delivery_and_unblocks_customer(): void
    {
        $customer = User::factory()->create(['phone' => '3332223333', 'no_show_count' => 2]);
        app(BlocklistService::class)->blockUser($customer);

        $this->actingAs($this->seller)
            ->patch(route('admin.customers.delivery', $customer), ['delivery_enabled' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue($customer->fresh()->delivery_enabled);

        $this->actingAs($this->seller)->post(route('admin.customers.unblock', $customer))->assertRedirect();

        $customer->refresh();
        $this->assertFalse($customer->isBlocked());
        $this->assertSame(0, $customer->no_show_count);
        $this->assertFalse(app(BlocklistService::class)->isEmailBlocked($customer->email));
    }

    public function test_delivery_needs_a_phone_number(): void
    {
        $customer = User::factory()->create(['phone' => null]);

        $this->actingAs($this->seller)
            ->patch(route('admin.customers.delivery', $customer), ['delivery_enabled' => 1])
            ->assertSessionHasErrors('delivery_enabled');
    }

    public function test_collaborator_can_view_customers_but_not_unblock_or_enable_delivery(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.market.manage']);
        $customer = User::factory()->create(['blocked_at' => now(), 'phone' => '3330000000']);

        $this->actingAs($collaborator)->get(route('admin.customers.show', $customer))->assertOk()->assertDontSee('Sblocca cliente');
        $this->actingAs($collaborator)->post(route('admin.customers.unblock', $customer))->assertForbidden();
        $this->actingAs($collaborator)->patch(route('admin.customers.delivery', $customer), ['delivery_enabled' => 1])->assertForbidden();
        $this->assertTrue($customer->fresh()->isBlocked());
    }

    public function test_customer_orders_link_to_order_detail_only_when_staff_can_open_them(): void
    {
        $customer = User::factory()->create();
        $pickup = $this->makeCustomerOrder($customer, 'ORD-T-PICK1', Order::FULFILLMENT_PICKUP);
        $delivery = $this->makeCustomerOrder($customer, 'ORD-T-HOME1', Order::FULFILLMENT_DELIVERY);

        $customerPage = '/admin/customers/'.$customer->id;

        $this->actingAs($this->seller)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee(route('admin.orders.show', ['order' => $pickup, 'back' => $customerPage]), false)
            ->assertSee(route('admin.orders.show', ['order' => $delivery, 'back' => $customerPage]), false);

        $this->actingAs($this->seller)
            ->get(route('admin.orders.show', ['order' => $pickup, 'back' => $customerPage]))
            ->assertOk()
            ->assertSee('href="'.$customerPage.'"', false)
            ->assertSee('Scheda cliente')
            ->assertDontSee('Tutti gli ordini');

        $this->actingAs($this->seller)
            ->get(route('admin.orders.show', $pickup))
            ->assertOk()
            ->assertSee('Tutti gli ordini')
            ->assertDontSee('Scheda cliente');

        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.market.manage']);

        $this->actingAs($collaborator)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee(route('admin.orders.show', ['order' => $pickup, 'back' => $customerPage]), false)
            ->assertDontSee(route('admin.orders.show', $delivery), false)
            ->assertSee('ORD-T-HOME1');
    }

    public function test_order_detail_returns_to_banco_and_ignores_external_back_links(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeCustomerOrder($customer, 'ORD-T-BANCO', Order::FULFILLMENT_PICKUP);

        $this->actingAs($this->seller)
            ->get(route('admin.orders.show', ['order' => $order, 'back' => '/admin/banco?date=2026-11-07&q=ORD-T']))
            ->assertOk()
            ->assertSee('href="/admin/banco?date=2026-11-07&amp;q=ORD-T"', false)
            ->assertSee('Modalità banco');

        foreach (['https://evil.example/admin', '//evil.example/admin', 'javascript:alert(1)'] as $external) {
            $this->actingAs($this->seller)
                ->get(route('admin.orders.show', ['order' => $order, 'back' => $external]))
                ->assertOk()
                ->assertSee('Tutti gli ordini')
                ->assertDontSee('href="https://evil.example', false)
                ->assertDontSee('href="//evil.example', false)
                ->assertDontSee('href="javascript:', false);
        }
    }

    public function test_order_email_link_opens_in_site_composer_for_seller_only(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeCustomerOrder($customer, 'ORD-T-MAIL1', Order::FULFILLMENT_PICKUP);

        $this->actingAs($this->seller)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.customer-emails.create', ['to' => [$customer->id], 'back' => '/admin/orders/'.$order->id]))
            ->assertDontSee('mailto:'.$customer->email, false);

        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.market.manage']);

        $this->actingAs($collaborator)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('mailto:'.$customer->email, false);
    }

    private function makeCustomerOrder(User $customer, string $number, string $fulfillment): Order
    {
        return Order::create([
            'order_number' => $number,
            'user_id' => $customer->id,
            'customer_name' => $customer->fullName(),
            'customer_email' => $customer->email,
            'fulfillment_method' => $fulfillment,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'unpaid',
            'status' => Order::STATUS_RECEIVED,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'delivery_address' => $fulfillment === Order::FULFILLMENT_DELIVERY ? 'Via Roma 1' : null,
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ]);
    }

    public function test_customers_cannot_open_customer_pages(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_seller_creates_collaborator_with_permissions(): void
    {
        $this->actingAs($this->seller)
            ->post(route('admin.collaborators.store'), [
                'first_name' => 'Marta', 'last_name' => 'Rossi', 'email' => 'marta@example.com',
                'password' => 'Password1!', 'permissions' => ['orders.cancel', 'counter.use'],
            ])
            ->assertRedirect(route('admin.collaborators.index'));

        $collaborator = User::where('email', 'marta@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_COLLABORATOR, $collaborator->role);
        $this->assertTrue($collaborator->hasVerifiedEmail());
        $this->assertTrue($collaborator->hasPermission('orders.cancel'));
        $this->assertFalse($collaborator->hasPermission('products.manage'));
    }

    public function test_existing_customer_becomes_collaborator_after_confirmation(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['email' => 'anna@example.com', 'password' => 'VecchiaPassword1!']);
        $payload = ['first_name' => 'Anna', 'last_name' => 'Bianchi', 'email' => 'Anna@Example.com', 'permissions' => ['counter.use']];

        $this->actingAs($this->seller)
            ->from(route('admin.collaborators.create'))
            ->post(route('admin.collaborators.store'), $payload)
            ->assertRedirect(route('admin.collaborators.create'))
            ->assertSessionHas('existing_customer');
        $this->assertSame(User::ROLE_CUSTOMER, $customer->fresh()->role);

        $this->actingAs($this->seller)
            ->post(route('admin.collaborators.store'), $payload + ['confirm_existing' => '1'])
            ->assertRedirect(route('admin.collaborators.index'));

        $customer->refresh();
        $this->assertSame(User::ROLE_COLLABORATOR, $customer->role);
        $this->assertTrue($customer->hasPermission('counter.use'));
        $this->assertTrue(Hash::check('VecchiaPassword1!', $customer->password));
        Mail::assertSent(TemplatedMail::class);
    }

    public function test_seller_or_existing_collaborator_email_cannot_be_added_again(): void
    {
        User::factory()->create(['email' => 'gia@example.com', 'role' => User::ROLE_COLLABORATOR]);

        $this->actingAs($this->seller)
            ->post(route('admin.collaborators.store'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'gia@example.com', 'confirm_existing' => '1'])
            ->assertSessionHasErrors(['email' => 'Questa persona è già un collaboratore: modifica i suoi permessi dall’elenco.']);

        $this->actingAs($this->seller)
            ->post(route('admin.collaborators.store'), ['first_name' => 'A', 'last_name' => 'B', 'email' => $this->seller->email, 'confirm_existing' => '1'])
            ->assertSessionHasErrors(['email' => 'Questa email è quella del venditore.']);
    }

    public function test_validation_messages_are_in_italian(): void
    {
        $this->actingAs($this->seller)
            ->post(route('admin.collaborators.store'), ['first_name' => '', 'last_name' => 'B', 'email' => 'nuovo@example.com', 'password' => 'Password1!'])
            ->assertSessionHasErrors(['first_name' => 'Il nome è obbligatorio.']);
    }

    public function test_updating_permissions_replaces_the_previous_set(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.cancel']);

        $this->actingAs($this->seller)
            ->put(route('admin.collaborators.update', $collaborator), [
                'first_name' => 'Marta', 'last_name' => 'Rossi', 'permissions' => ['dashboard.view'],
            ])
            ->assertRedirect(route('admin.collaborators.index'));

        $this->assertFalse($collaborator->fresh()->hasPermission('orders.cancel'));
        $this->assertTrue($collaborator->fresh()->hasPermission('dashboard.view'));
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->seller)
            ->post(route('admin.collaborators.store'), [
                'first_name' => 'Marta', 'last_name' => 'Rossi', 'email' => 'marta@example.com',
                'password' => 'Password1!', 'permissions' => ['super.admin'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_only_the_seller_manages_collaborators(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'settings.manage']);

        $this->actingAs($collaborator)->get(route('admin.collaborators.index'))->assertForbidden();
        $this->actingAs($collaborator)->post(route('admin.collaborators.store'), [])->assertForbidden();
    }

    public function test_removing_a_collaborator_deletes_it_but_keeps_order_history(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'orders.cancel']);

        $this->actingAs($this->seller)->delete(route('admin.collaborators.destroy', $collaborator))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $collaborator->id]);
        $this->assertDatabaseMissing('collaborator_permissions', ['user_id' => $collaborator->id]);
        $this->assertSame(0, Order::count());
    }
}
