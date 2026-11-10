<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\LocalizedText;
use App\Models\Market;
use App\Models\NotificationEventSetting;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_status_change_queues_email_with_rendered_placeholders(): void
    {
        $order = $this->makeOrder(Order::STATUS_PREPARING);

        app(OrderStatusService::class)->transition($order, Order::STATUS_READY, OrderStatusService::ACTOR_STAFF);

        $item = NotificationQueueItem::where('event', 'order_ready')->firstOrFail();
        $this->assertSame($order->customer_email, $item->recipient_email);
        $this->assertStringContainsString($order->order_number, $item->subject);
        $this->assertStringNotContainsString('{', $item->body);
        $this->assertSame(NotificationQueueItem::STATUS_PENDING, $item->status);
    }

    public function test_one_notification_per_order_on_bulk_changes(): void
    {
        $service = app(OrderStatusService::class);

        foreach ([$this->makeOrder(Order::STATUS_RECEIVED), $this->makeOrder(Order::STATUS_RECEIVED)] as $order) {
            $service->transition($order, Order::STATUS_PREPARING, OrderStatusService::ACTOR_STAFF);
        }

        $this->assertSame(2, NotificationQueueItem::where('event', 'order_preparing')->count());
    }

    public function test_customer_own_actions_do_not_notify_the_customer(): void
    {
        $order = $this->makeOrder(Order::STATUS_RECEIVED);

        app(OrderStatusService::class)->transition($order, Order::STATUS_CANCELLED, OrderStatusService::ACTOR_CUSTOMER);

        $this->assertSame(0, NotificationQueueItem::count());
    }

    public function test_disabled_event_is_not_queued(): void
    {
        NotificationEventSetting::create(['event' => 'order_preparing', 'is_active' => false, 'importance' => 'customer_choice']);
        $order = $this->makeOrder(Order::STATUS_RECEIVED);

        app(OrderStatusService::class)->transition($order, Order::STATUS_PREPARING, OrderStatusService::ACTOR_STAFF);

        $this->assertSame(0, NotificationQueueItem::count());
    }

    public function test_custom_template_overrides_default(): void
    {
        // The texts written by the seller are stored per language.
        NotificationEventSetting::create(['event' => 'order_preparing', 'is_active' => true, 'importance' => 'customer_choice']);
        LocalizedText::put('notification.order_preparing', 'it', 'Ci siamo {nome}', 'Ordine {codice_ordine} in lavorazione');
        $order = $this->makeOrder(Order::STATUS_RECEIVED);

        app(OrderStatusService::class)->transition($order, Order::STATUS_PREPARING, OrderStatusService::ACTOR_STAFF);

        $item = NotificationQueueItem::firstOrFail();
        $this->assertSame('Ci siamo '.$order->customer_name, $item->subject);
        $this->assertSame('Ordine '.$order->order_number.' in lavorazione', $item->body);
    }

    public function test_dispatch_sends_pending_and_marks_them_sent(): void
    {
        Mail::fake();
        $order = $this->makeOrder(Order::STATUS_RECEIVED);
        app(OrderStatusService::class)->transition($order, Order::STATUS_PREPARING, OrderStatusService::ACTOR_STAFF);

        $this->artisan('notifications:dispatch')->assertSuccessful();

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail): bool => $mail->hasTo($order->customer_email));
        $this->assertSame(NotificationQueueItem::STATUS_SENT, NotificationQueueItem::firstOrFail()->status);
    }

    public function test_failed_delivery_is_retried_then_marked_as_error(): void
    {
        $item = NotificationQueueItem::create([
            'event' => 'order_ready', 'recipient_email' => 'non-valida', 'channel' => 'email',
            'subject' => 'Test', 'body' => 'Test', 'status' => NotificationQueueItem::STATUS_PENDING,
        ]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $service = app(NotificationService::class);
        $service->dispatchPending();
        $this->assertSame(NotificationQueueItem::STATUS_PENDING, $item->fresh()->status);
        $this->assertSame(1, $item->fresh()->attempts);

        $service->dispatchPending();
        $service->dispatchPending();

        $this->assertSame(NotificationQueueItem::STATUS_ERROR, $item->fresh()->status);
        $this->assertSame('SMTP down', $item->fresh()->last_error);
    }

    public function test_reminders_are_queued_once_for_orders_picked_up_tomorrow(): void
    {
        $tomorrow = $this->makeOrder(Order::STATUS_READY, now()->addDay()->toDateString());
        $this->makeOrder(Order::STATUS_READY, now()->addDays(5)->toDateString());

        $this->artisan('notifications:send-reminders')->assertSuccessful();
        $this->artisan('notifications:send-reminders')->assertSuccessful();

        $reminders = NotificationQueueItem::where('event', 'pickup_reminder')->get();
        $this->assertCount(1, $reminders);
        $this->assertSame($tomorrow->id, $reminders->first()->order_id);
    }

    public function test_blocked_customer_gets_block_notice_but_not_cancel_mail_for_block(): void
    {
        $customer = User::factory()->create();
        $first = $this->makeOrder(Order::STATUS_READY, null, $customer);
        $second = $this->makeOrder(Order::STATUS_READY, null, $customer);
        $open = $this->makeOrder(Order::STATUS_RECEIVED, null, $customer);
        $service = app(OrderStatusService::class);

        $service->transition($first, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF);
        $service->transition($second, Order::STATUS_NOT_COLLECTED, OrderStatusService::ACTOR_STAFF);

        $this->assertSame(Order::STATUS_CANCELLED, $open->fresh()->status);
        $this->assertSame(1, NotificationQueueItem::where('event', 'account_blocked')->count());
        $this->assertSame(0, NotificationQueueItem::where('event', 'order_cancelled')->count());
        $this->assertSame(2, NotificationQueueItem::where('event', 'order_not_collected')->count());
    }

    public function test_seller_edits_notification_template(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)->get(route('admin.notifications.index'))->assertOk()->assertSee('Mercato chiuso');

        $this->actingAs($seller)
            ->put(route('admin.notifications.update', 'order_ready'), [
                'locale' => 'it',
                'subject' => 'Pronto {codice_ordine}',
                'body' => 'Ritira {codice_ordine}',
                'importance' => 'customer_choice',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.notifications.edit', ['event' => 'order_ready', 'lang' => 'it']));

        $this->assertSame('customer_choice', NotificationEventSetting::findOrFail('order_ready')->importance);
        $this->assertSame(
            ['title' => 'Pronto {codice_ordine}', 'body' => 'Ritira {codice_ordine}'],
            LocalizedText::find('notification.order_ready', 'it'),
        );
    }

    public function test_collaborator_without_settings_permission_cannot_edit_notifications(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);

        $this->actingAs($collaborator)->get(route('admin.notifications.index'))->assertForbidden();
    }

    private function makeOrder(string $status, ?string $pickupDate = null, ?User $customer = null): Order
    {
        $customer ??= User::factory()->create();
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
            'status' => $status,
            'pickup_date' => $pickupDate ?? now()->addDays(7)->toDateString(),
            'items_subtotal_cents' => 1000,
            'delivery_cost_cents' => 0,
            'total_cents' => 1000,
            'placed_at' => now(),
        ]);
    }
}
