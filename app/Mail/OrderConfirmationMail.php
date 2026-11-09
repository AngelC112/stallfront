<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\OrderQrCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Message;
use Illuminate\Mail\TextMessage;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Riferimento all’immagine del QR già allegata, così le versioni HTML e testo non la allegano due volte.
     */
    private ?string $qrSource = null;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Conferma ordine :number', ['number' => $this->order->order_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-confirmation',
            with: [
                'order' => $this->order,
                'showQr' => $this->order->fulfillment_method === Order::FULFILLMENT_PICKUP,
                'qrSource' => fn (Message|TextMessage $message): ?string => $this->qrSource($message),
            ],
        );
    }

    /**
     * Allega il QR come immagine interna all’email e ne restituisce il riferimento "cid:".
     * La versione solo testo non allega nulla: le basta sapere che il QR c’è, per mostrare il codice.
     */
    private function qrSource(Message|TextMessage $message): ?string
    {
        if ($message instanceof TextMessage) {
            return function_exists('imagecreate') ? ($this->qrSource ?? 'cid:qr') : null;
        }

        if ($this->qrSource === null) {
            $png = OrderQrCode::png($this->order->order_number);

            if ($png === null) {
                return null;
            }

            $this->qrSource = $message->embedData($png, 'qr-'.$this->order->order_number.'.png', 'image/png');
        }

        return $this->qrSource;
    }
}
