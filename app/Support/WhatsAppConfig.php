<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Chiavi WhatsApp salvate dal pannello (token e secret cifrati con APP_KEY).
 * Se un valore non è impostato dal pannello resta quello delle variabili d'ambiente.
 */
final class WhatsAppConfig
{
    private const SECRETS = ['token', 'app_secret'];

    public const FIELDS = ['business_number', 'phone_number_id', 'verify_token', 'token', 'app_secret'];

    public static function stored(string $field): string
    {
        $raw = (string) Setting::get('whatsapp.'.$field, '');

        if ($raw === '' || ! in_array($field, self::SECRETS, true)) {
            return $raw;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (Throwable) {
            return '';
        }
    }

    public static function store(string $field, string $value): void
    {
        $raw = $value !== '' && in_array($field, self::SECRETS, true) ? Crypt::encryptString($value) : $value;

        Setting::set('whatsapp.'.$field, $raw);
    }

    /**
     * Sovrascrive config('services.whatsapp') con i valori salvati dal pannello.
     */
    public static function apply(): void
    {
        foreach (self::FIELDS as $field) {
            $value = self::stored($field);

            if ($value !== '') {
                config(['services.whatsapp.'.$field => $value]);
            }
        }
    }
}
