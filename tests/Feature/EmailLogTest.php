<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\ImapSentFolder;
use App\Support\MimeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_email_is_logged_and_queued_for_sent_folder(): void
    {
        Storage::fake('local');
        config(['mail.sent_copy.enabled' => true]);

        Mail::to('cliente@example.com')->send(new TemplatedMail('Ordine confermato', 'Grazie per il tuo ordine.'));

        $log = EmailLog::query()->sole();

        $this->assertSame(EmailLog::STATUS_SENT, $log->status);
        $this->assertSame('cliente@example.com', $log->to_addresses);
        $this->assertSame('Ordine confermato', $log->subject);
        $this->assertSame(TemplatedMail::class, $log->mailable);
        $this->assertNotNull($log->sent_at);
        $this->assertNotNull($log->message_id);
        Storage::disk('local')->assertExists($log->raw_path);
        $this->assertStringContainsString('Subject: Ordine confermato', Storage::disk('local')->get($log->raw_path));
    }

    public function test_sent_copy_is_not_prepared_when_disabled(): void
    {
        Storage::fake('local');
        config(['mail.sent_copy.enabled' => false]);

        Mail::to('cliente@example.com')->send(new TemplatedMail('Prova', 'Testo'));

        $log = EmailLog::query()->sole();

        $this->assertSame(EmailLog::STATUS_SENT, $log->status);
        $this->assertNull($log->raw_path);
        $this->assertSame('—', $log->sentCopyLabel());
    }

    public function test_command_stores_pending_emails_in_sent_folder(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sent-mail/1.eml', "Subject: Prova\r\n\r\nTesto");
        $log = EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
            'raw_path' => 'sent-mail/1.eml',
        ]);

        $folder = Mockery::mock(ImapSentFolder::class);
        $folder->shouldReceive('connect')->once();
        $folder->shouldReceive('append')->once()->with("Subject: Prova\r\n\r\nTesto", Mockery::any());
        $folder->shouldReceive('disconnect')->once();
        $this->app->instance(ImapSentFolder::class, $folder);

        $this->artisan('mail:store-sent')->assertSuccessful();

        $log->refresh();
        $this->assertNotNull($log->stored_in_sent_at);
        $this->assertNull($log->raw_path);
        $this->assertSame('Salvata', $log->sentCopyLabel());
        Storage::disk('local')->assertMissing('sent-mail/1.eml');
    }

    public function test_command_keeps_email_for_retry_when_imap_is_unreachable(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sent-mail/1.eml', 'Subject: Prova');
        $log = EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
            'raw_path' => 'sent-mail/1.eml',
        ]);

        $folder = Mockery::mock(ImapSentFolder::class);
        $folder->shouldReceive('connect')->once()->andThrow(new RuntimeException('Connessione IMAP non riuscita'));
        $this->app->instance(ImapSentFolder::class, $folder);

        $this->artisan('mail:store-sent')->assertFailed();

        $log->refresh();
        $this->assertNull($log->stored_in_sent_at);
        $this->assertSame(1, $log->store_attempts);
        $this->assertSame('Connessione IMAP non riuscita', $log->store_error);
        $this->assertSame('In attesa', $log->sentCopyLabel());
        Storage::disk('local')->assertExists('sent-mail/1.eml');
    }

    public function test_email_stuck_in_sending_is_shown_as_failed(): void
    {
        $log = EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'status' => EmailLog::STATUS_SENDING,
        ]);
        $log->forceFill(['created_at' => now()->subMinutes(EmailLog::STALE_AFTER_MINUTES + 1)])->save();

        $this->assertTrue($log->isFailed());
        $this->assertSame('Non inviata', $log->statusLabel());
    }

    public function test_only_seller_can_see_email_log(): void
    {
        EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'subject' => 'Ordine confermato',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);

        $this->actingAs($seller)
            ->get(route('admin.email-log.index'))
            ->assertOk()
            ->assertSee('Ordine confermato')
            ->assertSee('cliente@example.com');

        $this->actingAs($collaborator)
            ->get(route('admin.email-log.index'))
            ->assertForbidden();
    }

    public function test_mime_message_reads_text_html_inline_images_and_attachments(): void
    {
        $message = MimeMessage::parse($this->sampleRawEmail());

        $this->assertSame('Conferma ordine è pronto', $message->header('subject'));
        $this->assertStringContainsString('Testo semplice àè', (string) $message->text);
        $this->assertStringContainsString('<p>Ciao àè</p>', (string) $message->html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode('PNGDATA'), (string) $message->htmlWithInlineImages());

        $filenames = array_column($message->attachments, 'filename');
        $this->assertContains('qr.png', $filenames);
        $this->assertContains('ricevuta è.pdf', $filenames);

        $pdf = collect($message->attachments)->firstWhere('filename', 'ricevuta è.pdf');
        $this->assertSame('%PDF-1.4 contenuto', $pdf['content']);
        $this->assertSame('application/pdf', $pdf['content_type']);
    }

    public function test_seller_opens_email_and_downloads_attachment_from_local_copy(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sent-mail/1.eml', $this->sampleRawEmail());
        $log = EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'subject' => 'Conferma ordine è pronto',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
            'raw_path' => 'sent-mail/1.eml',
        ]);
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)
            ->get(route('admin.email-log.show', $log))
            ->assertOk()
            ->assertSee('Conferma ordine è pronto')
            ->assertSee('ricevuta è.pdf')
            ->assertSee('data:image/png;base64', false);

        $pdfIndex = collect(MimeMessage::parse($this->sampleRawEmail())->attachments)->search(fn (array $file): bool => $file['filename'] === 'ricevuta è.pdf');

        $response = $this->actingAs($seller)->get(route('admin.email-log.attachment', [$log, $pdfIndex]));
        $response->assertOk();
        $this->assertSame('%PDF-1.4 contenuto', $response->streamedContent());

        $eml = $this->actingAs($seller)->get(route('admin.email-log.download', $log));
        $eml->assertOk();
        $this->assertStringContainsString('Subject:', $eml->streamedContent());
    }

    public function test_seller_opens_email_fetched_from_sent_folder(): void
    {
        config(['mail.sent_copy.enabled' => true]);
        $log = EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'subject' => 'Conferma ordine è pronto',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
            'message_id' => 'abc123@example.com',
            'stored_in_sent_at' => now(),
        ]);

        $folder = Mockery::mock(ImapSentFolder::class);
        $folder->shouldReceive('connect')->once();
        $folder->shouldReceive('fetchByMessageId')->once()->with('abc123@example.com', Mockery::any())->andReturn($this->sampleRawEmail());
        $folder->shouldReceive('disconnect')->once();
        $this->app->instance(ImapSentFolder::class, $folder);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SELLER]))
            ->get(route('admin.email-log.show', $log))
            ->assertOk()
            ->assertSee('ricevuta è.pdf');
    }

    public function test_show_page_reports_unreachable_mailbox(): void
    {
        config(['mail.sent_copy.enabled' => true]);
        $log = EmailLog::query()->create([
            'to_addresses' => 'cliente@example.com',
            'subject' => 'Ordine',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
            'message_id' => 'abc123@example.com',
        ]);

        $folder = Mockery::mock(ImapSentFolder::class);
        $folder->shouldReceive('connect')->once()->andThrow(new RuntimeException('Connessione IMAP non riuscita'));
        $this->app->instance(ImapSentFolder::class, $folder);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SELLER]))
            ->get(route('admin.email-log.show', $log))
            ->assertOk()
            ->assertSee('Connessione IMAP non riuscita');
    }

    private function sampleRawEmail(): string
    {
        $email = (new Email)
            ->from('vetrina@example.com')
            ->to('cliente@example.com')
            ->subject('Conferma ordine è pronto')
            ->text('Testo semplice àè')
            ->html('<p>Ciao àè</p><img src="cid:qr.png">')
            ->addPart((new DataPart('PNGDATA', 'qr.png', 'image/png'))->asInline())
            ->attach('%PDF-1.4 contenuto', 'ricevuta è.pdf', 'application/pdf');

        return $email->toString();
    }
}
