<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webpush:vapid')]
#[Description(__('Genera le chiavi VAPID per le notifiche del browser da copiare nel file .env'))]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];

        // Su Windows OpenSSL può non trovare il suo file di configurazione: si indica quello di PHP.
        $config = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        if (! getenv('OPENSSL_CONF') && is_file($config)) {
            $options['config'] = $config;
        }

        $key = openssl_pkey_new($options);

        if ($key === false) {
            $this->error('Unable to create the keys: '.openssl_error_string());

            return self::FAILURE;
        }

        $ec = openssl_pkey_get_details($key)['ec'];
        $pad = fn (string $bytes): string => str_pad($bytes, 32, "\0", STR_PAD_LEFT);
        $base64Url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $this->info(__('Copia queste righe nel file .env (una volta sola: se le cambi, i clienti devono riattivare le notifiche).'));
        $this->line('VAPID_PUBLIC_KEY='.$base64Url("\x04".$pad($ec['x']).$pad($ec['y'])));
        $this->line('VAPID_PRIVATE_KEY='.$base64Url($pad($ec['d'])));

        return self::SUCCESS;
    }
}
