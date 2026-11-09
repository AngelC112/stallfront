<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Mail\PasswordChangedMail;
use App\Mail\TemplatedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\BlocklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_closes_other_sessions_and_sends_warning(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'altra-sessione', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'NuovaPassword1!',
                'password_confirmation' => 'NuovaPassword1!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'altra-sessione']);
        Mail::assertSent(PasswordChangedMail::class);
    }

    public function test_account_deletion_needs_password_and_confirmation_word(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.delete.request'), ['current_password' => 'sbagliata', 'confirmation' => 'elimina'])
            ->assertSessionHasErrorsIn('deleteAccount', ['current_password', 'confirmation']);

        Mail::assertNothingSent();
    }

    public function test_account_deletion_sends_link_then_deletes_data_and_restores_stock(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Vaso', 'slug' => 'vaso', 'short_description' => 'x', 'description' => 'x', 'price_cents' => 1000,
            'vat_included' => true, 'visibility' => Product::VISIBILITY_VISIBLE, 'is_active' => true, 'is_visible' => true,
            'track_stock' => true, 'stock' => 3, 'reserved_stock' => 0, 'has_variants' => false, 'always_available' => true,
            'off_season_fulfillment_days' => 2, 'low_stock_threshold' => 0, 'created_by' => null,
        ]);
        $order = Order::create([
            'order_number' => 'ORD-T-DELETE', 'user_id' => $user->id, 'customer_name' => 'x', 'customer_email' => $user->email,
            'fulfillment_method' => 'pickup', 'payment_method' => 'cash_on_pickup', 'payment_status' => 'unpaid',
            'status' => Order::STATUS_RECEIVED, 'items_subtotal_cents' => 2000, 'delivery_cost_cents' => 0, 'total_cents' => 2000, 'placed_at' => now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Vaso', 'quantity' => 2, 'unit_price_cents' => 1000, 'line_total_cents' => 2000]);

        $this->actingAs($user)
            ->post(route('account.delete.request'), ['current_password' => 'password', 'confirmation' => 'ELIMINA'])
            ->assertSessionHas('status', 'account-delete-link-sent');

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail): bool => str_contains($mail->mailSubject, 'eliminazione'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $url = URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(30), ['user' => $user->id]);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Elimina definitivamente');
        $this->actingAs($user)->post($url)->assertRedirect(route('home'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertGuest();
    }

    public function test_seller_cannot_delete_own_account(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)->get(route('account.edit'))->assertOk()->assertDontSee('Elimina account');
        $this->actingAs($seller)
            ->post(route('account.delete.request'), ['current_password' => 'password', 'confirmation' => 'ELIMINA'])
            ->assertForbidden();

        $url = URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(30), ['user' => $seller->id]);
        $this->actingAs($seller)->post($url)->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $seller->id]);
        Mail::assertNothingSent();
    }

    public function test_unsigned_or_foreign_deletion_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('account.delete.destroy', ['user' => $user->id]))->assertForbidden();

        $foreign = URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(30), ['user' => $other->id]);
        $this->actingAs($user)->post($foreign)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_blocked_fingerprint_survives_account_deletion(): void
    {
        Mail::fake();
        $user = User::factory()->create(['phone' => '3339998888']);
        app(BlocklistService::class)->blockUser($user);

        $url = URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(30), ['user' => $user->id]);
        $this->actingAs($user)->post($url);

        $this->assertTrue(app(BlocklistService::class)->isEmailBlocked($user->email));
        $this->assertTrue(app(BlocklistService::class)->isPhoneBlocked('3339998888'));
    }

    public function test_data_export_downloads_profile_and_orders(): void
    {
        $user = User::factory()->create(['first_name' => 'Anna']);

        $response = $this->actingAs($user)->get(route('account.export'));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $data = json_decode($response->streamedContent(), true);
        $this->assertSame('Anna', $data['profilo']['nome']);
        $this->assertSame([], $data['ordini']);
    }

    public function test_honeypot_and_too_fast_submissions_are_rejected(): void
    {
        $payload = [
            'first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'bot@example.com',
            'password' => 'Password1!', 'password_confirmation' => 'Password1!', 'consent' => '1', 'locale' => 'it',
        ];

        $this->post(route('register'), $payload + ['website' => 'http://spam.example'])->assertSessionHasErrors('email');
        $this->post(route('register'), $payload + ['form_started_at' => time()])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);

        $this->post(route('register'), $payload + ['form_started_at' => time() - 10])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'bot@example.com']);
    }

    public function test_order_numbers_are_random_and_unique(): void
    {
        $this->assertMatchesRegularExpression('/^ORD-\d{6}-[A-HJ-NP-Z2-9]{8}$/', $this->newOrderNumber());
        $this->assertNotSame($this->newOrderNumber(), $this->newOrderNumber());
    }

    private function newOrderNumber(): string
    {
        $controller = app(CheckoutController::class);

        return (fn () => $this->newOrderNumber())->call($controller);
    }
}
