<?php

namespace App\Support;

final class NotificationEvent
{
    public const RECIPIENT_CUSTOMER = 'customer';

    public const RECIPIENT_SELLER = 'seller';

    public const IMPORTANCE_ALWAYS_EMAIL = 'always_email';

    public const IMPORTANCE_CUSTOMER_CHOICE = 'customer_choice';

    public const ORDER_PREPARING = 'order_preparing';

    public const ORDER_READY = 'order_ready';

    public const ORDER_OUT_FOR_DELIVERY = 'order_out_for_delivery';

    public const ORDER_COLLECTED = 'order_collected';

    public const ORDER_DELIVERED = 'order_delivered';

    public const ORDER_NOT_COLLECTED = 'order_not_collected';

    public const ORDER_NOT_DELIVERED = 'order_not_delivered';

    public const ORDER_RESCHEDULED = 'order_rescheduled';

    public const ORDER_CANCELLED = 'order_cancelled';

    public const MARKET_CLOSED = 'market_closed';

    public const ACCOUNT_BLOCKED = 'account_blocked';

    public const PICKUP_REMINDER = 'pickup_reminder';

    public const SELLER_NEW_ORDER = 'seller_new_order';

    public const SELLER_ORDER_CHANGED = 'seller_order_changed';

    public const SELLER_LOW_STOCK = 'seller_low_stock';

    /**
     * Placeholders available in the message templates, with their description.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return [
            '{name}' => __('Nome del cliente'),
            '{order_number}' => __('Codice ordine'),
            '{date}' => __('Data di ritiro'),
            '{market}' => __('Mercato'),
            '{reason}' => __('Motivazione'),
            '{total}' => __('Totale da pagare'),
            '{details}' => __('Dettaglio su ritiro o consegna'),
            '{product}' => __('Nome del prodotto (avvisi scorte)'),
            '{order_link}' => __('Link alla pagina dell’ordine'),
        ];
    }

    /**
     * Placeholder names used before languages existed (Italian), still accepted in templates.
     *
     * @return array<string, string> english name => italian name
     */
    public static function placeholderAliases(): array
    {
        return [
            'name' => 'nome',
            'order_number' => 'codice_ordine',
            'date' => 'data',
            'market' => 'mercato',
            'reason' => 'motivazione',
            'total' => 'totale',
            'details' => 'dettaglio',
            'product' => 'prodotto',
            'order_link' => 'link_ordine',
        ];
    }

    /**
     * Events with importance, recipient and default texts in the current language.
     *
     * @return array<string, array{label: string, recipient: string, importance: string, subject: string, body: string}>
     */
    public static function all(): array
    {
        $customer = self::RECIPIENT_CUSTOMER;
        $seller = self::RECIPIENT_SELLER;
        $always = self::IMPORTANCE_ALWAYS_EMAIL;
        $choice = self::IMPORTANCE_CUSTOMER_CHOICE;

        return [
            self::ORDER_PREPARING => [
                'label' => __('Ordine in preparazione'),
                'recipient' => $customer,
                'importance' => $choice,
                'subject' => __('Il tuo ordine {order_number} è in preparazione'),
                'body' => __("Ciao {name},\nstiamo preparando il tuo ordine {order_number}.\n\n{order_link}"),
            ],
            self::ORDER_READY => [
                'label' => __('Ordine evaso (pronto)'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Il tuo ordine {order_number} è pronto'),
                'body' => __("Ciao {name},\nil tuo ordine {order_number} è pronto. {details}\nTotale da pagare: {total}.\n\n{order_link}"),
            ],
            self::ORDER_OUT_FOR_DELIVERY => [
                'label' => __('Ordine in consegna'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Il tuo ordine {order_number} è in consegna'),
                'body' => __("Ciao {name},\nil tuo ordine {order_number} è in consegna. Totale da pagare alla consegna: {total}.\n\n{order_link}"),
            ],
            self::ORDER_COLLECTED => [
                'label' => __('Ordine ritirato'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Ordine {order_number} ritirato'),
                'body' => __("Ciao {name},\nconfermiamo il ritiro dell’ordine {order_number}. Grazie!\n\n{order_link}"),
            ],
            self::ORDER_DELIVERED => [
                'label' => __('Ordine consegnato'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Ordine {order_number} consegnato'),
                'body' => __("Ciao {name},\nl’ordine {order_number} è stato consegnato. Grazie!\n\n{order_link}"),
            ],
            self::ORDER_NOT_COLLECTED => [
                'label' => __('Ordine non ritirato'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Ordine {order_number} non ritirato'),
                'body' => __("Ciao {name},\nl’ordine {order_number} non è stato ritirato. Puoi riprogrammare il ritiro o annullarlo dalla pagina dell’ordine.\nAttenzione: troppi mancati ritiri comportano il blocco dell’account.\n\n{order_link}"),
            ],
            self::ORDER_NOT_DELIVERED => [
                'label' => __('Ordine non consegnato'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Ordine {order_number} non consegnato'),
                'body' => __("Ciao {name},\nnon è stato possibile consegnare l’ordine {order_number}. Ti contatteremo per un nuovo tentativo.\n\n{order_link}"),
            ],
            self::ORDER_RESCHEDULED => [
                'label' => __('Ordine riprogrammato'),
                'recipient' => $customer,
                'importance' => $choice,
                'subject' => __('Ordine {order_number} riprogrammato'),
                'body' => __("Ciao {name},\nl’ordine {order_number} è stato riprogrammato: ritiro il {date} presso {market}.\n\n{order_link}"),
            ],
            self::ORDER_CANCELLED => [
                'label' => __('Ordine annullato'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Ordine {order_number} annullato'),
                'body' => __("Ciao {name},\nl’ordine {order_number} è stato annullato.\n{reason}\n\n{order_link}"),
            ],
            self::MARKET_CLOSED => [
                'label' => __('Mercato chiuso'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Mercato chiuso: scegli un’altra data per l’ordine {order_number}'),
                'body' => __("Ciao {name},\nil mercato {market} del {date} è chiuso.\nMotivo: {reason}\n\nScegli un’altra data disponibile oppure annulla l’ordine {order_number}:\n{order_link}"),
            ],
            self::ACCOUNT_BLOCKED => [
                'label' => __('Blocco dell’account'),
                'recipient' => $customer,
                'importance' => $always,
                'subject' => __('Il tuo account è stato bloccato'),
                'body' => __("Ciao {name},\nil tuo account è stato bloccato per mancati ritiri e non puoi più effettuare ordini. Gli ordini ancora aperti sono stati annullati. Per informazioni contatta il venditore."),
            ],
            self::PICKUP_REMINDER => [
                'label' => __('Promemoria del giorno prima'),
                'recipient' => $customer,
                'importance' => $choice,
                'subject' => __('Promemoria: domani ritiri l’ordine {order_number}'),
                'body' => __("Ciao {name},\ndomani ({date}) puoi ritirare l’ordine {order_number} presso {market}. Totale da pagare: {total}.\n\n{order_link}"),
            ],
            self::SELLER_NEW_ORDER => [
                'label' => __('Nuovo ordine'),
                'recipient' => $seller,
                'importance' => $always,
                'subject' => __('Nuovo ordine {order_number}'),
                'body' => __("Nuovo ordine {order_number} di {name}: {market}, ritiro {date}, totale {total}.\n\n{order_link}"),
            ],
            self::SELLER_ORDER_CHANGED => [
                'label' => __('Ordine modificato dal cliente'),
                'recipient' => $seller,
                'importance' => $always,
                'subject' => __('Ordine {order_number} modificato dal cliente'),
                'body' => __("{name} {reason}. L’ordine {order_number} era già in lavorazione.\n\n{order_link}"),
            ],
            self::SELLER_LOW_STOCK => [
                'label' => __('Prodotto in esaurimento'),
                'recipient' => $seller,
                'importance' => $always,
                'subject' => __('Scorta bassa: {product}'),
                'body' => __('Il prodotto {product} sta per esaurirsi.'),
            ],
        ];
    }
}
