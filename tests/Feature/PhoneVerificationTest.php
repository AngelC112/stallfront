<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{to: string, body: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.business_number' => '+39 333 0000000']);

        $sent = &$this->sent;
        $this->app->instance(WhatsAppClient::class, new class($sent) implements WhatsAppClient
        {
            public function __construct(private array &$sent) {}

            public function sendText(string $to, string $body): void
            {
                $this->sent[] = ['to' => $to, 'body' => $body];
            }

            public function isTestMode(): bool
            {
                return true;
            }
        });
    }

    public function test_message_from_the_registered_number_verifies_the_phone(): void
    {
        $user = User::factory()->create(['phone' => '3331234567']);
        $code = $this->startVerification($user);

        $this->actingAs($user)->get(route('account.phone.verify'))
            ->assertOk()
            ->assertSee('https://wa.me/393330000000?text=VERIFICA%20'.$code, false)
            ->assertSee('Apri WhatsApp');

        $this->postWebhook('393331234567', 'VERIFICA '.$code)->assertOk();

        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertSame('393331234567', $this->sent[0]['to']);
        $this->assertStringContainsString('Numero verificato', $this->sent[0]['body']);
        $this->actingAs($user->fresh())->getJson(route('account.phone.verify.status'))->assertJsonPath('verified', true);
    }

    public function test_message_from_another_number_is_rejected_and_counts_an_attempt(): void
    {
        $user = User::factory()->create(['phone' => '3331234567']);
        $code = $this->startVerification($user);

        $this->postWebhook('393339999999', 'VERIFICA '.$code);

        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertStringContainsString('non coincide', $this->sent[0]['body']);
        $this->actingAs($user)->getJson(route('account.phone.verify.status'))
            ->assertJsonPath('attempts', 1)
            ->assertJsonPath('attempts_left', 4);
    }

    public function test_too_many_wrong_attempts_lock_the_verification(): void
    {
        $user = User::factory()->create(['phone' => '3331234567']);
        $code = $this->startVerification($user);

        foreach (range(1, 5) as $attempt) {
            $this->postWebhook('39333000000'.$attempt, 'VERIFICA '.$code);
        }

        $this->postWebhook('393331234567', 'VERIFICA '.$code);

        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertStringContainsString('Troppi tentativi', end($this->sent)['body']);
    }

    public function test_expired_or_unknown_code_is_rejected(): void
    {
        $user = User::factory()->create(['phone' => '3331234567']);
        $code = $this->startVerification($user);

        $this->travel(11)->minutes();
        $this->postWebhook('393331234567', 'VERIFICA '.$code);
        $this->postWebhook('393331234567', 'ciao');

        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertStringContainsString('scaduto', $this->sent[0]['body']);
        $this->assertStringContainsString('Messaggio non valido', $this->sent[1]['body']);
    }

    public function test_test_mode_lets_the_customer_simulate_the_message(): void
    {
        $user = User::factory()->create(['phone' => '3331234567']);
        $this->startVerification($user);

        $this->actingAs($user)->get(route('account.phone.verify'))->assertSee('Simula l’invio del messaggio');
        $this->actingAs($user)->post(route('account.phone.verify.simulate'))->assertRedirect(route('account.phone.verify'));

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_webhook_checks_meta_signature_and_answers_the_subscription_challenge(): void
    {
        config(['services.whatsapp.app_secret' => 'segreto', 'services.whatsapp.verify_token' => 'token-verifica']);

        $this->postJson(route('webhooks.whatsapp.receive'), ['entry' => []], ['X-Hub-Signature-256' => 'sha256=sbagliata'])->assertForbidden();

        $body = json_encode(['entry' => []]);
        $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'segreto'),
        ], $body)->assertOk();

        $this->get(route('webhooks.whatsapp.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'token-verifica', 'hub_challenge' => '12345']))
            ->assertOk()
            ->assertSee('12345');
        $this->get(route('webhooks.whatsapp.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'altro', 'hub_challenge' => '1']))
            ->assertForbidden();
    }

    public function test_home_delivery_needs_a_verified_phone(): void
    {
        $user = User::factory()->create(['delivery_enabled' => true, 'phone' => '3331234567']);

        $this->assertFalse($user->hasPhoneVerified());
        $this->actingAs($user)->get(route('account.edit'))->assertSee('Verifica con WhatsApp');
    }

    private function startVerification(User $user): string
    {
        $this->actingAs($user)->post(route('account.phone.verify.start'))->assertRedirect(route('account.phone.verify'));

        return session('phone_verification')['code'];
    }

    private function postWebhook(string $from, string $text): TestResponse
    {
        return $this->postJson(route('webhooks.whatsapp.receive'), [
            'entry' => [['changes' => [['value' => ['messages' => [
                ['from' => $from, 'type' => 'text', 'text' => ['body' => $text]],
            ]]]]]],
        ]);
    }
}
