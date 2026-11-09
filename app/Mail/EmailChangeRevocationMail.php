<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeRevocationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $oldEmail,
        public string $newEmail,
        public string $revokeUrl,
        public int $hours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Richiesta di modifica dell\'email del tuo account'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.email-change-request',
            with: [
                'name' => $this->user->fullName(),
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
                'revokeUrl' => $this->revokeUrl,
                'hours' => $this->hours,
                'store' => [
                    'name' => Setting::get('store.name', config('app.name')),
                    'address' => Setting::get('store.address', ''),
                    'vat' => Setting::get('store.vat', ''),
                ],
            ],
        );
    }
}
