<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStockMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $productName,
        public string $productUrl,
        public ?string $variantLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Articolo di nuovo disponibile'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.back-in-stock',
            with: [
                'productName' => $this->productName,
                'productUrl' => $this->productUrl,
                'variantLabel' => $this->variantLabel,
            ],
        );
    }
}
