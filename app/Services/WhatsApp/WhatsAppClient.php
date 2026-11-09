<?php

namespace App\Services\WhatsApp;

/**
 * Invio di messaggi WhatsApp di testo. Usato solo per rispondere al cliente che verifica il cellulare.
 */
interface WhatsAppClient
{
    /**
     * @param  string  $to  numero in formato internazionale senza "+", es. 393331234567
     */
    public function sendText(string $to, string $body): void;

    /**
     * Vero se mancano le chiavi dell'API: i messaggi vanno nel log e si può simulare la verifica.
     */
    public function isTestMode(): bool;
}
