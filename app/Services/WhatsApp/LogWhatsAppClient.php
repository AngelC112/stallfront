<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Modalità prova, finché non ci sono le chiavi dell'API: i messaggi finiscono nel log.
 */
class LogWhatsAppClient implements WhatsAppClient
{
    public function sendText(string $to, string $body): void
    {
        Log::info('WhatsApp (modalità prova) a '.$to.': '.$body);
    }

    public function isTestMode(): bool
    {
        return true;
    }
}
