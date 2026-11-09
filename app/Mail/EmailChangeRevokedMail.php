<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeRevokedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $oldEmail,
        public string $newEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Modifica dell\'email revocata'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.email-change-revoked',
            with: [
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
                'store' => [
                    'name' => Setting::get('store.name', config('app.name')),
                    'address' => Setting::get('store.address', ''),
                    'vat' => Setting::get('store.vat', ''),
                ],
            ],
        );
    }
}
