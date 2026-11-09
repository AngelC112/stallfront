<?php

namespace Tests\Feature;

use App\Mail\CustomerMessageMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerEmailTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create(['role' => User::ROLE_SELLER]);
    }

    public function test_seller_sends_separate_email_with_attachments_to_each_selected_customer(): void
    {
        Mail::fake();
        $first = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'anna@example.com']);
        $second = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'marco@example.com']);
        User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'escluso@example.com']);

        $this->actingAs($this->seller())
            ->post(route('admin.customer-emails.store'), [
                'recipients' => [$first->id, $second->id],
                'subject' => 'Novità al mercato',
                'body' => 'Sabato abbiamo le fragole.',
                'attachments' => [UploadedFile::fake()->create('volantino.pdf', 200, 'application/pdf')],
            ])
            ->assertRedirect(route('admin.email-log.index'))
            ->assertSessionHas('success', 'Email inviata a 2 clienti.');

        Mail::assertSent(CustomerMessageMail::class, 2);
        Mail::assertSent(CustomerMessageMail::class, fn (CustomerMessageMail $mail): bool => $mail->hasTo('anna@example.com')
            && ! $mail->hasTo('marco@example.com')
            && $mail->mailSubject === 'Novità al mercato'
            && count($mail->attachments()) === 1
            && $mail->files[0]['name'] === 'volantino.pdf');
        Mail::assertNotSent(CustomerMessageMail::class, fn (CustomerMessageMail $mail): bool => $mail->hasTo('escluso@example.com'));
        $this->assertTrue(ActivityLog::where('action', 'customer_email.sent')->exists());
    }

    public function test_only_verified_customers_can_be_recipients(): void
    {
        Mail::fake();
        $unverified = User::factory()->unverified()->create(['role' => User::ROLE_CUSTOMER]);
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);

        $this->actingAs($this->seller())
            ->post(route('admin.customer-emails.store'), [
                'recipients' => [$unverified->id, $collaborator->id],
                'subject' => 'Prova',
                'body' => 'Testo',
            ])
            ->assertSessionHasErrors(['recipients.0', 'recipients.1']);

        $this->actingAs($this->seller())
            ->post(route('admin.customer-emails.store'), ['subject' => 'Prova', 'body' => 'Testo'])
            ->assertSessionHasErrors('recipients');

        Mail::assertNothingSent();
    }

    public function test_recipient_search_returns_only_verified_customers(): void
    {
        User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Anna Rossi', 'email' => 'anna@example.com']);
        User::factory()->unverified()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Anna Bianchi', 'email' => 'anna.b@example.com']);
        User::factory()->create(['role' => User::ROLE_COLLABORATOR, 'name' => 'Anna Staff', 'email' => 'anna.staff@example.com']);

        $response = $this->actingAs($this->seller())
            ->getJson(route('admin.customer-emails.recipients', ['q' => 'anna']))
            ->assertOk();

        $this->assertSame(['anna@example.com'], array_column($response->json(), 'email'));
    }

    public function test_compose_page_preselects_customer_from_profile_link(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'anna@example.com']);
        $seller = $this->seller();

        $this->actingAs($seller)
            ->get(route('admin.customers.show', $customer))
            ->assertSee(route('admin.customer-emails.create', ['to' => [$customer->id]]), false);

        $this->actingAs($seller)
            ->get(route('admin.customer-emails.create', ['to' => [$customer->id]]))
            ->assertOk()
            ->assertSee('anna@example.com');
    }

    public function test_compose_returns_to_the_page_it_was_opened_from(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $seller = $this->seller();
        $customerPage = '/admin/customers/'.$customer->id;

        $this->actingAs($seller)
            ->get(route('admin.customer-emails.create', ['to' => [$customer->id], 'back' => $customerPage]))
            ->assertOk()
            ->assertSee('href="'.$customerPage.'"', false)
            ->assertSee('Scheda cliente');

        $this->actingAs($seller)
            ->post(route('admin.customer-emails.store'), [
                'recipients' => [$customer->id],
                'subject' => 'Ciao',
                'body' => 'Testo',
                'back' => $customerPage,
            ])
            ->assertRedirect($customerPage);

        $this->actingAs($seller)
            ->post(route('admin.customer-emails.store'), [
                'recipients' => [$customer->id],
                'subject' => 'Ciao',
                'body' => 'Testo',
                'back' => 'https://evil.example/',
            ])
            ->assertRedirect(route('admin.email-log.index'));
    }

    public function test_collaborator_cannot_write_to_customers(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);

        $this->actingAs($collaborator)->get(route('admin.customer-emails.create'))->assertForbidden();
        $this->actingAs($collaborator)->post(route('admin.customer-emails.store'), [])->assertForbidden();
    }
}
