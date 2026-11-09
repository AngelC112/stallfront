<?php

namespace App\Support;

final class Permission
{
    public const PRODUCTS_MANAGE = 'products.manage';

    public const PRODUCTS_MANAGE_OTHERS = 'products.manage_others';

    public const CATEGORIES_MANAGE = 'categories.manage';

    public const ORDERS_MARKET_MANAGE = 'orders.market.manage';

    public const ORDERS_DELIVERY_MANAGE = 'orders.delivery.manage';

    public const ORDERS_CANCEL = 'orders.cancel';

    public const CLOSURES_HOLIDAYS_MANAGE = 'closures.holidays.manage';

    public const CLOSURES_EXCEPTION_MANAGE = 'closures.exception.manage';

    public const STOCK_MANAGE = 'stock.manage';

    public const ORDERS_NO_SHOW_DECIDE = 'orders.no_show.decide';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const ORDERS_NOTES_MANAGE = 'orders.notes.manage';

    public const COUNTER_USE = 'counter.use';

    public static function all(): array
    {
        return [
            self::PRODUCTS_MANAGE => __('Aggiungere, modificare ed eliminare prodotti'),
            self::PRODUCTS_MANAGE_OTHERS => __('Modificare o eliminare prodotti creati da altri'),
            self::CATEGORIES_MANAGE => __('Creare, modificare ed eliminare categorie'),

            self::ORDERS_MARKET_MANAGE => __('Evadere gli ordini dei mercati'),
            self::ORDERS_DELIVERY_MANAGE => __('Evadere gli ordini a domicilio'),
            self::ORDERS_CANCEL => __('Annullare l\'ordine di un cliente'),

            self::CLOSURES_HOLIDAYS_MANAGE => __('Modificare le ferie'),
            self::CLOSURES_EXCEPTION_MANAGE => __('Segnalare chiusure straordinarie'),

            self::STOCK_MANAGE => __('Gestire la giacenza'),
            self::ORDERS_NO_SHOW_DECIDE => __('Decidere sugli ordini non ritirati'),

            self::DASHBOARD_VIEW => __('Vedere dashboard, incassi e statistiche'),
            self::SETTINGS_MANAGE => __('Modificare mercati, notifiche e modelli messaggi'),

            self::ORDERS_NOTES_MANAGE => __('Inserire e leggere note interne sugli ordini'),
            self::COUNTER_USE => __('Usare la modalità banco'),
        ];
    }
}
