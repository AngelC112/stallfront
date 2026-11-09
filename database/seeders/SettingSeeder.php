<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Sicurezza e accessi
            'security.email_verification_ttl_hours' => 48,
            'security.password_reset_ttl_hours' => 2,
            'security.whatsapp_code_ttl_minutes' => 10,
            'security.whatsapp_code_attempts' => 3,
            'security.whatsapp_resend_wait_seconds' => 60,
            'security.login_max_attempts' => 5,
            'security.password_min_length' => 8,
            'security.email_change_revoke_hours' => 48,

            // Ordini e clienti
            'orders.no_show_block_threshold' => 2,
            'orders.market_default_lead_time_days' => 3,
            'orders.off_season_default_days' => 10,
            'orders.low_stock_threshold' => 3,
            'orders.checkout_reserve_minutes' => 15,
            'orders.push_ack_wait_minutes' => 10,

            // Consegna a domicilio
            'delivery.min_order_cents' => 0,
            'delivery.cost_cents' => 0,
            'delivery.time_hours' => 48,
            'delivery.enabled' => true,

            // Dati negozio
            'store.name' => '[NOME NEGOZIO]',
            'store.vat' => '',
            'store.address' => '',
            'store.email' => '',
            'store.phone' => '',
            'store.whatsapp_number' => '',

            // Feature flags
            'features.phone_verification_mode' => 'manual',
        ];

        foreach ($defaults as $key => $value) {
            if (! Setting::query()->whereKey($key)->exists()) {
                Setting::set($key, $value);
            }
        }
    }
}
