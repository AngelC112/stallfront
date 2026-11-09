<?php

namespace App\Support;

/**
 * Link per aprire la casella di posta (non la scrittura di una nuova email)
 * in base al provider dell'indirizzo. Sul telefono il link web viene di norma
 * aperto dall'app del provider, se installata.
 */
class MailboxLink
{
    private const PROVIDERS = [
        'gmail.com' => 'https://mail.google.com/mail/u/0/#inbox',
        'googlemail.com' => 'https://mail.google.com/mail/u/0/#inbox',
        'outlook.com' => 'https://outlook.live.com/mail/0/inbox',
        'outlook.it' => 'https://outlook.live.com/mail/0/inbox',
        'hotmail.com' => 'https://outlook.live.com/mail/0/inbox',
        'hotmail.it' => 'https://outlook.live.com/mail/0/inbox',
        'live.com' => 'https://outlook.live.com/mail/0/inbox',
        'live.it' => 'https://outlook.live.com/mail/0/inbox',
        'msn.com' => 'https://outlook.live.com/mail/0/inbox',
        'yahoo.com' => 'https://mail.yahoo.com/d/folders/1',
        'yahoo.it' => 'https://mail.yahoo.com/d/folders/1',
        'icloud.com' => 'https://www.icloud.com/mail',
        'me.com' => 'https://www.icloud.com/mail',
        'libero.it' => 'https://login.libero.it/',
        'virgilio.it' => 'https://mail.virgilio.it/',
        'tiscali.it' => 'https://mail.tiscali.it/',
        'alice.it' => 'https://mail.tim.it/',
        'tim.it' => 'https://mail.tim.it/',
        'pec.it' => 'https://webmail.pec.it/',
        'proton.me' => 'https://mail.proton.me/',
        'protonmail.com' => 'https://mail.proton.me/',
    ];

    /**
     * Restituisce l'URL della casella, oppure null se il provider non è noto
     * (in quel caso il pulsante non viene mostrato).
     */
    public static function for(?string $email, ?string $userAgent = null): ?string
    {
        $domain = strtolower(substr(strrchr((string) $email, '@') ?: '', 1));

        if (isset(self::PROVIDERS[$domain])) {
            return self::PROVIDERS[$domain];
        }

        // Provider sconosciuto: su iPhone/iPad "message://" apre l'app Mail predefinita.
        if ($userAgent !== null && preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            return 'message://';
        }

        return null;
    }

    /**
     * Link che apre direttamente l'app di posta sul telefono, oppure null (desktop).
     * Android: intent verso l'app email predefinita. iPhone: schema dell'app del provider,
     * "message://" (Mail) se il provider non ha un'app nota.
     */
    public static function appLink(?string $email, ?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        if (preg_match('/Android/i', $userAgent)) {
            return 'intent:#Intent;action=android.intent.action.MAIN;category=android.intent.category.APP_EMAIL;end';
        }

        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $domain = strtolower(substr(strrchr((string) $email, '@') ?: '', 1));

            return match (true) {
                in_array($domain, ['gmail.com', 'googlemail.com'], true) => 'googlegmail://',
                in_array($domain, ['outlook.com', 'outlook.it', 'hotmail.com', 'hotmail.it', 'live.com', 'live.it', 'msn.com'], true) => 'ms-outlook://',
                in_array($domain, ['yahoo.com', 'yahoo.it'], true) => 'ymail://',
                default => 'message://',
            };
        }

        return null;
    }
}
