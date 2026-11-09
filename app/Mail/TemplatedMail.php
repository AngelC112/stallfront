<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email generica costruita dai modelli dei messaggi.
 */
class TemplatedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        $escaped = e($this->mailBody);
        $linked = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $escaped);

        return new Content(
            htmlString: '<div style="font-family:sans-serif;font-size:15px;line-height:1.5">'.nl2br($linked).'</div>',
            text: 'emails.templated-text',
            with: ['body' => $this->mailBody],
        );
    }
}
