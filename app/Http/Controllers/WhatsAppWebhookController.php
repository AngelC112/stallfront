<?php

namespace App\Http\Controllers;

use App\Services\PhoneVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook della WhatsApp Cloud API: Meta lo chiama a ogni messaggio ricevuto dal numero del negozio.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Verifica iniziale richiesta da Meta quando si configura il webhook.
     */
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('services.whatsapp.verify_token');

        abort_unless(
            $verifyToken !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200);
    }

    public function receive(Request $request, PhoneVerificationService $verification): Response
    {
        abort_unless($this->hasValidSignature($request), 403);

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['messages'] ?? []) as $message) {
                    if (($message['type'] ?? null) === 'text' && isset($message['from'], $message['text']['body'])) {
                        $verification->handleIncomingMessage((string) $message['from'], (string) $message['text']['body']);
                    }
                }
            }
        }

        // Meta vuole sempre 200, altrimenti ripete l'invio.
        return response('', 200);
    }

    /**
     * Firma HMAC di Meta (intestazione X-Hub-Signature-256) calcolata con l'App Secret.
     * Senza App Secret le richieste si accettano solo in locale e nei test.
     */
    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');

        if ($secret === '') {
            return app()->environment('local', 'testing');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
    }
}
