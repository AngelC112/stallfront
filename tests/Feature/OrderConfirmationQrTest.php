<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class OrderConfirmationQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_confirmation_email_embeds_the_order_qr_once(): void
    {
        $email = $this->sendConfirmation($this->makeOrder(Order::FULFILLMENT_PICKUP));

        $images = array_filter($email->getAttachments(), fn ($part): bool => $part->getMediaType() === 'image');
        $this->assertCount(1, $images);

        $image = array_values($images)[0];
        $this->assertSame('png', $image->getMediaSubtype());
        $this->assertStringStartsWith("\x89PNG", $image->getBody());
        $this->assertStringContainsString('cid:', $email->getHtmlBody());
        $this->assertStringContainsString('Mostra questo QR al banco', $email->getHtmlBody());
        $this->assertStringContainsString('ORD-T-QR123', $email->getTextBody());
        $this->assertStringNotContainsString('<img', $email->getTextBody());
    }

    public function test_delivery_confirmation_email_has_no_qr(): void
    {
        $email = $this->sendConfirmation($this->makeOrder(Order::FULFILLMENT_DELIVERY));

        $this->assertSame([], $email->getAttachments());
        $this->assertStringNotContainsString('Mostra questo QR', $email->getHtmlBody());
    }

    private function sendConfirmation(Order $order): Email
    {
        config(['mail.default' => 'array']);

        Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));

        return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    private function makeOrder(string $fulfillment): Order
    {
        $customer = User::factory()->create();

        return Order::create([
            'order_number' => 'ORD-T-QR123',
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
}
