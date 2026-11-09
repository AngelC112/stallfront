<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;

/**
 * Client della WhatsApp Cloud API di Meta. Le risposte inviate entro 24 ore dal messaggio
 * del cliente sono messaggi di servizio, senza costo secondo il listino Meta attuale.
 */
class CloudApiWhatsAppClient implements WhatsAppClient
{
    public function __construct(
        private string $token,
        private string $phoneNumberId,
        private string $graphVersion = 'v21.0',
    ) {}

    public function sendText(string $to, string $body): void
    {
        Http::withToken($this->token)
            ->timeout(10)
            ->post("https://graph.facebook.com/{$this->graphVersion}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $body],
            ])
            ->throw();
    }

    public function isTestMode(): bool
    {
        return false;
    }
}
