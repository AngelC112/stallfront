<?php

namespace App\Support;

/**
 * Parametri del portale modificabili dal venditore (sezione 5.11 delle specifiche).
 */
final class PortalSettings
{
    public const TYPE_INT = 'int';

    public const TYPE_BOOL = 'bool';

    public const TYPE_MONEY = 'money';

    public const TYPE_TIME = 'time';

    public const TYPE_TEXT = 'text';

    public const TYPE_CHOICE = 'choice';

    /**
     * @return array<string, array<string, array<string, mixed>>> gruppo => chiave => definizione
     */
    public static function groups(): array
    {
        return [
            __('Sicurezza e accessi') => [
                'security.email_verification_ttl_hours' => ['label' => __('Scadenza link di conferma email'), 'type' => self::TYPE_INT, 'default' => 48, 'min' => 1, 'max' => 720, 'unit' => __('ore')],
                'security.password_reset_ttl_hours' => ['label' => __('Scadenza link di recupero password'), 'type' => self::TYPE_INT, 'default' => 2, 'min' => 1, 'max' => 72, 'unit' => __('ore')],
                'security.email_change_revoke_hours' => ['label' => __('Tempo per annullare un cambio email'), 'type' => self::TYPE_INT, 'default' => 48, 'min' => 1, 'max' => 720, 'unit' => __('ore')],
                'security.whatsapp_code_ttl_minutes' => ['label' => __('Scadenza del codice WhatsApp'), 'type' => self::TYPE_INT, 'default' => 10, 'min' => 1, 'max' => 120, 'unit' => __('minuti')],
                'security.phone_code_max_attempts' => ['label' => __('Tentativi consentiti per il codice'), 'type' => self::TYPE_INT, 'default' => 5, 'min' => 1, 'max' => 20],
                'security.resend_wait_seconds' => ['label' => __('Attesa per il reinvio'), 'type' => self::TYPE_INT, 'default' => 60, 'min' => 10, 'max' => 3600, 'unit' => __('secondi')],
                'security.login_max_attempts' => ['label' => __('Tentativi di accesso errati prima della pausa'), 'type' => self::TYPE_INT, 'default' => 5, 'min' => 1, 'max' => 20],
                'security.password_min_length' => ['label' => __('Lunghezza minima della password'), 'type' => self::TYPE_INT, 'default' => 8, 'min' => 6, 'max' => 64, 'unit' => __('caratteri')],
            ],
            __('Ordini e clienti') => [
                'orders.no_show_block_threshold' => ['label' => __('Mancati ritiri prima del blocco'), 'type' => self::TYPE_INT, 'default' => 2, 'min' => 1, 'max' => 20],
                'orders.market_default_lead_time_days' => ['label' => __('Anticipo minimo dei nuovi mercati'), 'type' => self::TYPE_INT, 'default' => 3, 'min' => 0, 'max' => 60, 'unit' => __('giorni')],
                'orders.off_season_default_days' => ['label' => __('Evasione fuori stagione'), 'type' => self::TYPE_INT, 'default' => 10, 'min' => 0, 'max' => 90, 'unit' => __('giorni')],
                'orders.low_stock_threshold' => ['label' => __('Soglia di scorta bassa'), 'type' => self::TYPE_INT, 'default' => 3, 'min' => 0, 'max' => 1000, 'unit' => __('pezzi')],
                'orders.checkout_reserve_minutes' => ['label' => __('Durata del blocco giacenza al checkout'), 'type' => self::TYPE_INT, 'default' => 15, 'min' => 1, 'max' => 120, 'unit' => __('minuti')],
                'notifications.push_ack_minutes' => ['label' => __('Attesa per la conferma di ricezione delle notifiche del browser'), 'type' => self::TYPE_INT, 'default' => 10, 'min' => 1, 'max' => 120, 'unit' => __('minuti')],
                'notifications.reminder_time' => ['label' => __('Orario del promemoria del giorno prima'), 'type' => self::TYPE_TIME, 'default' => '18:00'],
            ],
            __('Consegna a domicilio') => [
                'delivery.enabled' => ['label' => __('Consegna a domicilio attiva'), 'type' => self::TYPE_BOOL, 'default' => true],
                'delivery.min_order_cents' => ['label' => __('Ordine minimo'), 'type' => self::TYPE_MONEY, 'default' => 0, 'unit' => Format::currencySymbol()],
                'delivery.cost_cents' => ['label' => __('Costo di consegna (0 = gratuita)'), 'type' => self::TYPE_MONEY, 'default' => 0, 'unit' => Format::currencySymbol()],
                'delivery.time_hours' => ['label' => __('Tempo di consegna dopo l’evasione'), 'type' => self::TYPE_CHOICE, 'default' => 24, 'choices' => [24 => __('24 ore'), 48 => __('48 ore')]],
            ],
            __('Dati del negozio') => [
                'store.name' => ['label' => __('Denominazione'), 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 120],
                'store.vat' => ['label' => __('Partita IVA'), 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 32],
                'store.address' => ['label' => __('Indirizzo'), 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 255],
                'store.email' => ['label' => __('Email di contatto'), 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 255, 'email' => true],
                'store.phone' => ['label' => __('Telefono'), 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 40],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>> chiave => definizione
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /**
     * Nome del campo del modulo: i punti nelle chiavi non vanno d’accordo con la notazione degli array.
     */
    public static function fieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
