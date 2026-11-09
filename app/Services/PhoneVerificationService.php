<?php

namespace App\Services;

use App\Models\PhoneVerification;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Verifica del cellulare "inversa" via WhatsApp: il cliente invia al numero del negozio
 * un messaggio già scritto con un codice; il webhook controlla codice e numero del mittente
 * (certificato da WhatsApp) e risponde «Numero verificato». Nessun messaggio a pagamento.
 */
class PhoneVerificationService
{
    public const CHANNEL = 'whatsapp';

    public const MESSAGE_PREFIX = 'VERIFICA';

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    private const LOCK_MINUTES = 15;

    public function __construct(private WhatsAppClient $whatsApp) {}

    /**
     * Nuovo codice per l'utente. Restituisce il codice in chiaro (da tenere solo in sessione)
     * e il link che apre WhatsApp con il messaggio già scritto.
     *
     * @return array{code: string, url: string, expires_at: Carbon}
     */
    public function start(User $user): array
    {
        if (! $user->phone) {
            throw ValidationException::withMessages(['phone' => __('Inserisci prima il numero di cellulare.')]);
        }

        if ($user->hasPhoneVerified()) {
            throw ValidationException::withMessages(['phone' => __('Il numero è già verificato.')]);
        }

        $latest = $user->phoneVerifications()->where('channel', self::CHANNEL)->latest('id')->first();

        if ($latest?->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'phone' => __('Troppi tentativi non riusciti. Riprova dopo le :time.', ['time' => \App\Support\Format::time($latest->locked_until)]),
            ]);
        }

        $wait = (int) Setting::get('security.resend_wait_seconds', 60);

        if ($latest && ! $latest->verified_at && $latest->created_at->gt(now()->subSeconds($wait))) {
            throw ValidationException::withMessages([
                'phone' => __('Attendi :seconds secondi prima di generare un nuovo codice.', ['seconds' => $latest->created_at->addSeconds($wait)->diffInSeconds(now(), true)]),
            ]);
        }

        $code = $this->newCode();
        $expiresAt = now()->addMinutes((int) Setting::get('security.whatsapp_code_ttl_minutes', 10));

        $user->phoneVerifications()->create([
            'phone' => self::normalize($user->phone),
            'channel' => self::CHANNEL,
            'code_hash' => self::hashCode($code),
            'expires_at' => $expiresAt,
            'attempts' => $latest && ! $latest->verified_at ? $latest->attempts : 0,
        ]);

        return ['code' => $code, 'url' => $this->whatsAppUrl($code), 'expires_at' => $expiresAt];
    }

    /**
     * Messaggio ricevuto dal webhook. Verifica, risponde al mittente e restituisce l'esito.
     */
    public function handleIncomingMessage(string $from, string $text): bool
    {
        $from = self::normalize($from);

        // Limite per numero mittente: evita che qualcuno provi codici a raffica.
        if (RateLimiter::tooManyAttempts('whatsapp-inbound:'.$from, 10)) {
            return false;
        }

        RateLimiter::hit('whatsapp-inbound:'.$from, 600);

        $code = $this->extractCode($text);

        if ($code === null) {
            $this->reply($from, __('Messaggio non valido. Per verificare il numero usa il pulsante «Verifica con WhatsApp» nella tua area personale.'));

            return false;
        }

        $verification = PhoneVerification::query()
            ->where('channel', self::CHANNEL)
            ->where('code_hash', self::hashCode($code))
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $verification || $verification->expires_at?->isPast()) {
            $this->reply($from, __('Il codice non è valido o è scaduto. Genera un nuovo codice dalla tua area personale e riprova.'));

            return false;
        }

        if ($verification->locked_until?->isFuture()) {
            $this->reply($from, __('Troppi tentativi non riusciti. Riprova dopo le :time.', ['time' => \App\Support\Format::time($verification->locked_until)]));

            return false;
        }

        $user = User::query()->find($verification->user_id);

        if (! $user || self::normalize((string) $user->phone) !== $verification->phone || $from !== $verification->phone) {
            $attempts = $verification->attempts + 1;
            $verification->update([
                'attempts' => $attempts,
                'locked_until' => $attempts >= (int) Setting::get('security.phone_code_max_attempts', 5) ? now()->addMinutes(self::LOCK_MINUTES) : null,
            ]);

            $this->reply($from, __('Il numero da cui scrivi non coincide con quello inserito nel tuo account. Scrivi dal numero indicato oppure cambialo nella tua area personale.'));

            return false;
        }

        $verification->update(['verified_at' => now()]);
        $user->forceFill(['phone_verified_at' => now()])->save();
        app(ActivityLogger::class)->log('phone.verified', $user, ['channel' => self::CHANNEL]);

        $this->reply($from, __('Numero verificato. Grazie! Puoi tornare al sito.'));

        return true;
    }

    public function isTestMode(): bool
    {
        return $this->whatsApp->isTestMode();
    }

    /**
     * Stato dell'ultimo codice, per la pagina che attende il messaggio.
     *
     * @return array{verified: bool, attempts: int, attempts_left: int, locked_until: ?string}
     */
    public function status(User $user): array
    {
        $latest = $user->phoneVerifications()->where('channel', self::CHANNEL)->latest('id')->first();
        $max = (int) Setting::get('security.phone_code_max_attempts', 5);

        return [
            'verified' => $user->hasPhoneVerified(),
            'attempts' => (int) $latest?->attempts,
            'attempts_left' => max(0, $max - (int) $latest?->attempts),
            'locked_until' => $latest?->locked_until?->isFuture() ? $latest->locked_until->format('H:i') : null,
        ];
    }

    public static function lockMinutes(): int
    {
        return self::LOCK_MINUTES;
    }

    /**
     * Numero in formato internazionale senza "+": i cellulari italiani a 10 cifre ricevono il prefisso 39.
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '39'.$digits;
        }

        return $digits;
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', strtoupper($code));
    }

    private function whatsAppUrl(string $code): string
    {
        $number = self::normalize((string) config('services.whatsapp.business_number'));

        return 'https://wa.me/'.$number.'?text='.rawurlencode(self::MESSAGE_PREFIX.' '.$code);
    }

    private function extractCode(string $text): ?string
    {
        $code = '(['.self::CODE_ALPHABET.']{'.self::CODE_LENGTH.'})';
        $text = strtoupper($text);

        // Prima il codice dopo la parola VERIFICA del messaggio già scritto, poi qualunque parola valida.
        if (preg_match('/'.self::MESSAGE_PREFIX.'\W*'.$code.'\b/', $text, $matches) || preg_match('/\b'.$code.'\b/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function newCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    private function reply(string $to, string $body): void
    {
        try {
            $this->whatsApp->sendText($to, $body);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
