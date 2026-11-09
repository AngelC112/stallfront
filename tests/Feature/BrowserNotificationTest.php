<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Market;
use App\Models\NotificationEventSetting;
use App\Models\NotificationPreference;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushService;
use App\Support\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BrowserNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webpush.public_key' => 'chiave-pubblica', 'services.webpush.private_key' => 'chiave-privata']);
        Mail::fake();
    }

    public function test_customer_chooses_browser_only_for_events_left_to_their_choice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.notifications.edit'))
            ->assertOk()
            ->assertSee('Ordine in preparazione')
            ->assertSee('Arrivano sempre per email');

        $this->actingAs($user)->put(route('account.notifications.update'), [
            'channels' => [NotificationEvent::ORDER_PREPARING => 'browser', NotificationEvent::ORDER_READY => 'browser'],
        ])->assertRedirect(route('account.notifications.edit'));

        $this->assertSame('browser', $user->notificationPreferences()->where('event', NotificationEvent::ORDER_PREPARING)->value('channel'));
        $this->assertNull($user->notificationPreferences()->where('event', NotificationEvent::ORDER_READY)->first());
    }

    public function test_browser_choice_is_ignored_without_an_active_device_or_for_always_email_events(): void
    {
        $user = $this->customerWithBrowserFor(NotificationEvent::ORDER_PREPARING, withDevice: false);
        $order = $this->makeOrder($user);
        $notifications = app(NotificationService::class);

        $this->assertSame('email', $notifications->queueForOrder(NotificationEvent::ORDER_PREPARING, $order)->channel);

        PushSubscription::factory()->create(['user_id' => $user->id]);
        $user->notificationPreferences()->create(['event' => NotificationEvent::ORDER_READY, 'channel' => 'browser']);

        $this->assertSame('push', $notifications->queueForOrder(NotificationEvent::ORDER_PREPARING, $order)->channel);
        $this->assertSame('email', $notifications->queueForOrder(NotificationEvent::ORDER_READY, $order)->channel);
    }

    public function test_confirmed_push_does_not_send_the_email(): void
    {
        $this->fakePush(['sent' => 1, 'expired' => 0, 'failed' => 0]);
        $user = $this->customerWithBrowserFor(NotificationEvent::ORDER_PREPARING);
        $item = app(NotificationService::class)->queueForOrder(NotificationEvent::ORDER_PREPARING, $this->makeOrder($user));

        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertSame(NotificationQueueItem::STATUS_AWAITING_ACK, $item->fresh()->status);

        $delivery = PushDelivery::create(['notification_queue_id' => $item->id, 'push_subscription_id' => $user->pushSubscriptions()->value('id'), 'result' => 'sent', 'sent_at' => now()]);
        $this->post(URL::temporarySignedRoute('push.ack', now()->addHour(), ['delivery' => $delivery->id]))->assertNoContent();

        $this->travel(15)->minutes();
        $this->artisan('notifications:dispatch')->assertSuccessful();

        $this->assertSame('push', $item->fresh()->delivered_via);
        $this->assertNotNull($delivery->fresh()->received_at);
        Mail::assertNothingSent();
    }

    public function test_unconfirmed_push_falls_back_to_email_after_the_waiting_time(): void
    {
        $this->fakePush(['sent' => 1, 'expired' => 0, 'failed' => 0]);
        $user = $this->customerWithBrowserFor(NotificationEvent::ORDER_PREPARING);
        $item = app(NotificationService::class)->queueForOrder(NotificationEvent::ORDER_PREPARING, $this->makeOrder($user));

        $this->artisan('notifications:dispatch');
        $this->travel(5)->minutes();
        $this->artisan('notifications:dispatch');
        Mail::assertNothingSent();

        $this->travel(6)->minutes();
        $this->artisan('notifications:dispatch');

        Mail::assertSent(TemplatedMail::class, 1);
        $this->assertSame('email_fallback', $item->fresh()->delivered_via);
    }

    public function test_revoked_permission_sends_email_at_once_and_switches_preferences_back_to_email(): void
    {
        $this->fakePush(['sent' => 0, 'expired' => 1, 'failed' => 0], deactivate: true);
        $user = $this->customerWithBrowserFor(NotificationEvent::ORDER_PREPARING);
        $item = app(NotificationService::class)->queueForOrder(NotificationEvent::ORDER_PREPARING, $this->makeOrder($user));

        $this->artisan('notifications:dispatch');

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail): bool => str_contains($mail->mailBody, 'riattivarle'));
        $this->assertSame('email_fallback', $item->fresh()->delivered_via);
        $this->assertSame('email', $user->notificationPreferences()->value('channel'));
        $this->assertNotNull($user->fresh()->push_revoked_at);

        $this->actingAs($user->fresh())->get(route('account.notifications.edit'))->assertSee('Notifiche del browser disattivate');
    }

    public function test_site_reports_revoked_permission_for_this_device(): void
    {
        $user = $this->customerWithBrowserFor(NotificationEvent::ORDER_PREPARING);
        $subscription = $user->pushSubscriptions()->first();

        $this->actingAs($user)
            ->postJson(route('account.push.revoked'), ['endpoint' => $subscription->endpoint])
            ->assertJsonPath('revertedToEmail', true);

        $this->assertFalse($subscription->fresh()->is_active);
        $this->assertSame('email', $user->notificationPreferences()->value('channel'));
    }

    public function test_device_subscription_is_saved_once_per_endpoint(): void
    {
        $user = User::factory()->create();
        $payload = ['endpoint' => 'https://push.example.test/abc', 'keys' => ['p256dh' => 'chiave', 'auth' => 'segreto']];

        $this->actingAs($user)->postJson(route('account.push.store'), $payload, ['User-Agent' => 'Mozilla/5.0 (iPhone) Safari/604.1'])->assertOk();
        $this->assertSame('Safari su iPhone', PushSubscription::first()->device);

        $this->actingAs($user)->postJson(route('account.push.store'), $payload)->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->actingAs($user)->postJson(route('account.push.store'), ['endpoint' => 'http://insicuro.test', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnprocessable();
    }

    public function test_seller_sets_importance_from_the_toggles(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Avvisi a te')
            ->assertSee('Modifica i testi dei messaggi');

        $this->actingAs($seller)->put(route('admin.notifications.importance'), [
            'importance' => [NotificationEvent::ORDER_READY => 'customer_choice', NotificationEvent::ORDER_PREPARING => 'always_email'],
        ])->assertRedirect(route('admin.notifications.index'));

        $this->assertSame('customer_choice', NotificationEventSetting::find(NotificationEvent::ORDER_READY)->importance);
        $this->assertSame('always_email', NotificationEventSetting::find(NotificationEvent::ORDER_PREPARING)->importance);
        $this->actingAs($seller)->get(route('admin.notifications.texts'))->assertOk()->assertSee('Ordine in preparazione');
    }

    /**
     * @param  array{sent: int, expired: int, failed: int}  $result
     */
    private function fakePush(array $result, bool $deactivate = false): void
    {
        $this->app->instance(WebPushService::class, new class($result, $deactivate) extends WebPushService
        {
            public function __construct(private array $result, private bool $deactivateAll) {}

            public function send(NotificationQueueItem $item): array
            {
                if ($this->deactivateAll) {
                    PushSubscription::query()->where('user_id', $item->user_id)->get()->each(fn ($subscription) => $this->deactivate($subscription, 'expired'));
                }

                return $this->result;
            }
        });
    }

    private function customerWithBrowserFor(string $event, bool $withDevice = true): User
    {
        $user = User::factory()->create();
        $user->notificationPreferences()->create(['event' => $event, 'channel' => NotificationPreference::CHANNEL_BROWSER]);

        if ($withDevice) {
            PushSubscription::factory()->create(['user_id' => $user->id]);
        }

        return $user;
    }

    private function makeOrder(User $customer): Order
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
            'status' => Order::STATUS_PREPARING,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ]);
    }
}
