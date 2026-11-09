<?php

/*
 * StallFront web installer, for shared hostings without Docker.
 *
 * public/index.php loads this file while the application has no configuration
 * (no APP_KEY). It checks the requirements, tests the database connection,
 * writes the .env file, runs the migrations and then sends the visitor to the
 * first-run page that creates the seller account. It never runs again once the
 * .env file exists: to reinstall, delete .env.
 */

declare(strict_types=1);

$base = dirname(__DIR__);
$envDir = stallfront_data_dir() ?? $base;
$envFile = $envDir.'/.env';

$lang = str_starts_with(strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 'it') ? 'it' : 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'it'], true)) {
    $lang = $_GET['lang'];
}

$T = [
    'en' => [
        'title' => 'StallFront installation',
        'intro' => 'Enter the database and email details. The tables are created automatically; then you create the seller account.',
        'requirements' => 'Requirements',
        'missing_vendor' => 'The PHP dependencies are missing (vendor folder). Upload the release zip, which already contains them, or run "composer install --no-dev".',
        'php' => 'PHP version :v (8.3 or later required)',
        'ext' => 'PHP extension :e',
        'writable' => 'Folder :f writable',
        'db' => 'Database',
        'db_driver' => 'Type',
        'db_host' => 'Host',
        'db_port' => 'Port (empty = default)',
        'db_name' => 'Database name',
        'db_user' => 'User',
        'db_pass' => 'Password',
        'site' => 'Site',
        'app_url' => 'Site address',
        'app_url_hint' => 'Used in email links. Detected automatically: change it only if it is wrong.',
        'language' => 'Default language',
        'timezone' => 'Time zone',
        'cron' => 'Scheduled tasks',
        'cron_yes' => 'My hosting has a cron job that runs "php artisan schedule:run" every minute',
        'cron_no' => 'No cron: run the tasks while people use the site',
        'mail' => 'Email',
        'mail_sendmail' => 'Hosting\'s built-in email (PHP mail / sendmail)',
        'mail_smtp' => 'SMTP server',
        'mail_log' => 'Do not send (write emails to the log)',
        'mail_from' => 'Sender address',
        'mail_from_hint' => 'Use an address of your domain, otherwise many providers reject the messages.',
        'smtp_host' => 'SMTP server',
        'smtp_port' => 'Port',
        'smtp_enc' => 'Encryption',
        'smtp_user' => 'Username',
        'smtp_pass' => 'Password',
        'install' => 'Install',
        'err_db' => 'Database connection failed: :e',
        'err_write' => 'Cannot write :f. Make the folder writable and try again.',
        'err_migrate' => 'Creating the tables failed: :e',
        'err_required' => 'Fill in the database host, name and user.',
        'err_from' => 'Enter a valid sender address.',
        'err_smtp' => 'Enter the SMTP server.',
        'done' => 'Installation completed. Now create the seller account.',
        'continue' => 'Continue',
    ],
    'it' => [
        'title' => 'Installazione di StallFront',
        'intro' => 'Inserisci i dati del database e dell\'email. Le tabelle vengono create in automatico; poi crei l\'account del venditore.',
        'requirements' => 'Requisiti',
        'missing_vendor' => 'Mancano le dipendenze PHP (cartella vendor). Carica lo zip della release, che le contiene già, oppure esegui "composer install --no-dev".',
        'php' => 'Versione PHP :v (serve 8.3 o successiva)',
        'ext' => 'Estensione PHP :e',
        'writable' => 'Cartella :f scrivibile',
        'db' => 'Database',
        'db_driver' => 'Tipo',
        'db_host' => 'Host',
        'db_port' => 'Porta (vuoto = predefinita)',
        'db_name' => 'Nome del database',
        'db_user' => 'Utente',
        'db_pass' => 'Password',
        'site' => 'Sito',
        'app_url' => 'Indirizzo del sito',
        'app_url_hint' => 'Usato nei link delle email. È rilevato in automatico: cambialo solo se è sbagliato.',
        'language' => 'Lingua predefinita',
        'timezone' => 'Fuso orario',
        'cron' => 'Attività programmate',
        'cron_yes' => 'Il mio hosting ha un cron che esegue "php artisan schedule:run" ogni minuto',
        'cron_no' => 'Niente cron: esegui le attività mentre le persone usano il sito',
        'mail' => 'Email',
        'mail_sendmail' => 'Email integrata dell\'hosting (PHP mail / sendmail)',
        'mail_smtp' => 'Server SMTP',
        'mail_log' => 'Non inviare (scrivi le email nel log)',
        'mail_from' => 'Indirizzo del mittente',
        'mail_from_hint' => 'Usa un indirizzo del tuo dominio, altrimenti molti provider rifiutano i messaggi.',
        'smtp_host' => 'Server SMTP',
        'smtp_port' => 'Porta',
        'smtp_enc' => 'Cifratura',
        'smtp_user' => 'Utente',
        'smtp_pass' => 'Password',
        'install' => 'Installa',
        'err_db' => 'Connessione al database non riuscita: :e',
        'err_write' => 'Impossibile scrivere :f. Rendi la cartella scrivibile e riprova.',
        'err_migrate' => 'Creazione delle tabelle non riuscita: :e',
        'err_required' => 'Compila host, nome del database e utente.',
        'err_from' => 'Inserisci un indirizzo mittente valido.',
        'err_smtp' => 'Indica il server SMTP.',
        'done' => 'Installazione completata. Ora crea l\'account del venditore.',
        'continue' => 'Continua',
    ],
][$lang];

$t = static fn (string $key, array $replace = []): string => strtr($T[$key], array_combine(
    array_map(static fn ($k) => ':'.$k, array_keys($replace)),
    array_values($replace),
) ?: []);
$h = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// ── Requirements ─────────────────────────────────────────────
$checks = [
    [$t('php', ['v' => PHP_VERSION]), version_compare(PHP_VERSION, '8.3.0', '>=')],
];
foreach (['pdo', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'ctype', 'json', 'intl'] as $ext) {
    $checks[] = [$t('ext', ['e' => $ext]), extension_loaded($ext)];
}
$checks[] = [$t('ext', ['e' => 'gd / imagick']), extension_loaded('gd') || extension_loaded('imagick')];
$checks[] = [$t('ext', ['e' => 'pdo_mysql / pdo_pgsql']), extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql')];
foreach ([$envDir, $base.'/storage', $base.'/bootstrap/cache'] as $dir) {
    $checks[] = [$t('writable', ['f' => str_replace($base, '', $dir) ?: '/']), is_dir($dir) && is_writable($dir)];
}
$vendorOk = is_file($base.'/vendor/autoload.php');
$allOk = $vendorOk && ! in_array(false, array_column($checks, 1), true);

// ── Defaults ─────────────────────────────────────────────────
$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$path = preg_replace('#/public$#', '', $path);
$detectedUrl = ($https ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').$path;
$host = parse_url($detectedUrl, PHP_URL_HOST) ?: 'example.com';

$old = $_POST + [
    'db_driver' => extension_loaded('pdo_mysql') ? 'mysql' : 'pgsql',
    'db_host' => 'localhost', 'db_port' => '', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'app_url' => $detectedUrl, 'locale' => $lang, 'timezone' => date_default_timezone_get() ?: 'UTC',
    'scheduler' => 'web', 'mailer' => 'sendmail', 'mail_from' => 'noreply@'.preg_replace('/^www\./', '', $host),
    'smtp_host' => '', 'smtp_port' => '587', 'smtp_enc' => 'tls', 'smtp_user' => '', 'smtp_pass' => '',
];

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    $driver = in_array($old['db_driver'], ['mysql', 'pgsql'], true) ? $old['db_driver'] : 'mysql';
    $port = preg_replace('/\D/', '', (string) $old['db_port']) ?: ($driver === 'pgsql' ? '5432' : '3306');

    if (trim((string) $old['db_host']) === '' || trim((string) $old['db_name']) === '' || trim((string) $old['db_user']) === '') {
        $errors[] = $t('err_required');
    }
    if (! filter_var($old['mail_from'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = $t('err_from');
    }
    if ($old['mailer'] === 'smtp' && trim((string) $old['smtp_host']) === '') {
        $errors[] = $t('err_smtp');
    }

    if (! $errors) {
        try {
            $dsn = $driver === 'pgsql'
                ? "pgsql:host={$old['db_host']};port={$port};dbname={$old['db_name']}"
                : "mysql:host={$old['db_host']};port={$port};dbname={$old['db_name']};charset=utf8mb4";
            new PDO($dsn, (string) $old['db_user'], (string) $old['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
        } catch (Throwable $e) {
            $errors[] = $t('err_db', ['e' => $e->getMessage()]);
        }
    }

    if (! $errors) {
        require $base.'/vendor/autoload.php';

        // Uploaded photos: public/storage is a link to storage/app/public, or a real folder where links are not allowed.
        $publicStorage = $base.'/public/storage';
        $publicInPublic = false;
        if (! file_exists($publicStorage)) {
            @mkdir($base.'/storage/app/public', 0775, true);
            if (! @symlink($base.'/storage/app/public', $publicStorage)) {
                @mkdir($publicStorage, 0775, true);
                $publicInPublic = true;
            }
        } elseif (! is_link($publicStorage)) {
            $publicInPublic = true;
        }

        $vapid = ['publicKey' => '', 'privateKey' => ''];
        try {
            $vapid = Minishlink\WebPush\VAPID::createVapidKeys();
        } catch (Throwable) {
            // Browser notifications stay off; the keys can be generated later with "php artisan webpush:vapid".
        }

        $mailer = in_array($old['mailer'], ['sendmail', 'smtp', 'log'], true) ? $old['mailer'] : 'log';
        $sendmailPath = trim((string) ini_get('sendmail_path')) ?: '/usr/sbin/sendmail -t -i';

        $env = [
            'APP_NAME' => 'StallFront',
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim((string) $old['app_url'], '/'),
            'APP_LOCALE' => in_array($old['locale'], ['en', 'it', 'es', 'fr', 'de', 'pt', 'nl', 'pl'], true) ? $old['locale'] : 'en',
            'APP_TIMEZONE' => in_array($old['timezone'], timezone_identifiers_list(), true) ? $old['timezone'] : 'UTC',
            'SCHEDULER_MODE' => $old['scheduler'] === 'cron' ? 'cron' : 'web',
            'LOG_CHANNEL' => 'daily',
            'DB_CONNECTION' => $driver,
            'DB_HOST' => (string) $old['db_host'],
            'DB_PORT' => $port,
            'DB_DATABASE' => (string) $old['db_name'],
            'DB_USERNAME' => (string) $old['db_user'],
            'DB_PASSWORD' => (string) $old['db_pass'],
            'MAIL_MAILER' => $mailer,
            'MAIL_FROM_ADDRESS' => (string) $old['mail_from'],
            'VAPID_PUBLIC_KEY' => $vapid['publicKey'],
            'VAPID_PRIVATE_KEY' => $vapid['privateKey'],
        ];
        if ($mailer === 'sendmail') {
            $env['MAIL_SENDMAIL_PATH'] = $sendmailPath;
        }
        if ($mailer === 'smtp') {
            $env += [
                'MAIL_HOST' => (string) $old['smtp_host'],
                'MAIL_PORT' => preg_replace('/\D/', '', (string) $old['smtp_port']) ?: '587',
                'MAIL_SCHEME' => $old['smtp_enc'] === 'ssl' ? 'smtps' : 'smtp',
                'MAIL_USERNAME' => (string) $old['smtp_user'],
                'MAIL_PASSWORD' => (string) $old['smtp_pass'],
            ];
        }
        if ($publicInPublic) {
            $env['PUBLIC_STORAGE_IN_PUBLIC'] = 'true';
        }

        $content = "# StallFront configuration, written by the installer on ".date('Y-m-d H:i').".\n"
            ."# Environment variables with the same names take precedence.\n";
        foreach ($env as $key => $value) {
            $content .= $key.'='.stallfront_env_value((string) $value)."\n";
        }

        if (@file_put_contents($envFile, $content, LOCK_EX) === false) {
            $errors[] = $t('err_write', ['f' => $envFile]);
        } else {
            @chmod($envFile, 0640);

            try {
                $app = require $base.'/bootstrap/app.php';
                $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
                $kernel->bootstrap();
                if ($kernel->call('migrate', ['--force' => true]) !== 0) {
                    throw new RuntimeException(trim($kernel->output()));
                }
                $done = true;
            } catch (Throwable $e) {
                @rename($envFile, $envFile.'.failed');
                $errors[] = $t('err_migrate', ['e' => $e->getMessage()]);
            }
        }
    }
}

/** Data folder set by the environment (Docker), as in bootstrap/app.php. */
function stallfront_data_dir(): ?string
{
    $dir = $_SERVER['STALLFRONT_DATA_DIR'] ?? $_ENV['STALLFRONT_DATA_DIR'] ?? getenv('STALLFRONT_DATA_DIR');

    return is_string($dir) && $dir !== '' ? $dir : null;
}

/** Value for a .env line, quoted when needed. */
function stallfront_env_value(string $value): string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value)) {
        return $value;
    }

    return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}

$languages = ['en' => 'English', 'it' => 'Italiano', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'pt' => 'Português', 'nl' => 'Nederlands', 'pl' => 'Polski'];
?>
<!DOCTYPE html>
<html lang="<?= $h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $h($t('title')) ?></title>
    <style>
        :root { --brand: #125e5a; --text: #1f2937; --muted: #6b7280; --bg: #f5f7f6; --border: #d1d5db; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: var(--text); background: var(--bg); }
        main { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 24px; margin: 0 0 4px; color: var(--brand); }
        h2 { font-size: 16px; margin: 0 0 12px; }
        section { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px; margin-top: 16px; }
        label { display: block; font-weight: 600; font-size: 14px; margin: 10px 0 4px; }
        input[type=text], input[type=password], input[type=email], input[type=url], select { width: 100%; min-height: 44px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 10px; font: inherit; background: #fff; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .hint { color: var(--muted); font-size: 13px; margin: 4px 0 0; }
        .choice { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; }
        .choice input { margin-top: 4px; }
        ul.checks { list-style: none; padding: 0; margin: 0; }
        ul.checks li { padding: 2px 0; }
        .ok { color: #166534; } .ko { color: #991b1b; font-weight: 600; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 10px 12px; margin-top: 16px; }
        .success { background: #dff0e6; border: 1px solid #9fcfb4; color: #14532d; border-radius: 10px; padding: 12px; margin-top: 16px; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 22px; margin-top: 18px; border: 0; border-radius: 12px; background: var(--brand); color: #fff; font: 600 15px system-ui, sans-serif; text-decoration: none; cursor: pointer; }
        .lang { float: right; font-size: 13px; }
        .lang a { color: var(--brand); }
        @media (max-width: 520px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <p class="lang"><a href="?lang=en">English</a> · <a href="?lang=it">Italiano</a></p>
    <h1><?= $h($t('title')) ?></h1>

    <?php if ($done): ?>
        <div class="success"><?= $h($t('done')) ?></div>
        <a class="button" href="<?= $h(rtrim((string) $old['app_url'], '/').'/setup') ?>"><?= $h($t('continue')) ?></a>
    <?php else: ?>
        <p><?= $h($t('intro')) ?></p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= $h($error) ?></div>
        <?php endforeach; ?>

        <section>
            <h2><?= $h($t('requirements')) ?></h2>
            <?php if (! $vendorOk): ?>
                <div class="error"><?= $h($t('missing_vendor')) ?></div>
            <?php endif; ?>
            <ul class="checks">
                <?php foreach ($checks as [$label, $ok]): ?>
                    <li class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? '✓' : '✗' ?> <?= $h($label) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($allOk): ?>
        <form method="post">
            <section>
                <h2><?= $h($t('db')) ?></h2>
                <label for="db_driver"><?= $h($t('db_driver')) ?></label>
                <select id="db_driver" name="db_driver">
                    <?php if (extension_loaded('pdo_mysql')): ?><option value="mysql" <?= $old['db_driver'] === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option><?php endif; ?>
                    <?php if (extension_loaded('pdo_pgsql')): ?><option value="pgsql" <?= $old['db_driver'] === 'pgsql' ? 'selected' : '' ?>>PostgreSQL</option><?php endif; ?>
                </select>
                <div class="row">
                    <div><label for="db_host"><?= $h($t('db_host')) ?></label><input type="text" id="db_host" name="db_host" value="<?= $h($old['db_host']) ?>" required></div>
                    <div><label for="db_port"><?= $h($t('db_port')) ?></label><input type="text" id="db_port" name="db_port" value="<?= $h($old['db_port']) ?>" inputmode="numeric"></div>
                </div>
                <label for="db_name"><?= $h($t('db_name')) ?></label><input type="text" id="db_name" name="db_name" value="<?= $h($old['db_name']) ?>" required>
                <div class="row">
                    <div><label for="db_user"><?= $h($t('db_user')) ?></label><input type="text" id="db_user" name="db_user" value="<?= $h($old['db_user']) ?>" required autocomplete="off"></div>
                    <div><label for="db_pass"><?= $h($t('db_pass')) ?></label><input type="password" id="db_pass" name="db_pass" value="<?= $h($old['db_pass']) ?>" autocomplete="new-password"></div>
                </div>
            </section>

            <section>
                <h2><?= $h($t('site')) ?></h2>
                <label for="app_url"><?= $h($t('app_url')) ?></label>
                <input type="url" id="app_url" name="app_url" value="<?= $h($old['app_url']) ?>" required>
                <p class="hint"><?= $h($t('app_url_hint')) ?></p>
                <div class="row">
                    <div>
                        <label for="locale"><?= $h($t('language')) ?></label>
                        <select id="locale" name="locale">
                            <?php foreach ($languages as $code => $name): ?>
                                <option value="<?= $h($code) ?>" <?= $old['locale'] === $code ? 'selected' : '' ?>><?= $h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="timezone"><?= $h($t('timezone')) ?></label>
                        <select id="timezone" name="timezone">
                            <?php foreach (timezone_identifiers_list() as $zone): ?>
                                <option value="<?= $h($zone) ?>" <?= $old['timezone'] === $zone ? 'selected' : '' ?>><?= $h($zone) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label><?= $h($t('cron')) ?></label>
                <label class="choice"><input type="radio" name="scheduler" value="web" <?= $old['scheduler'] !== 'cron' ? 'checked' : '' ?>> <?= $h($t('cron_no')) ?></label>
                <label class="choice"><input type="radio" name="scheduler" value="cron" <?= $old['scheduler'] === 'cron' ? 'checked' : '' ?>> <?= $h($t('cron_yes')) ?></label>
            </section>

            <section>
                <h2><?= $h($t('mail')) ?></h2>
                <label class="choice"><input type="radio" name="mailer" value="sendmail" <?= $old['mailer'] === 'sendmail' ? 'checked' : '' ?>> <?= $h($t('mail_sendmail')) ?></label>
                <label class="choice"><input type="radio" name="mailer" value="smtp" <?= $old['mailer'] === 'smtp' ? 'checked' : '' ?>> <?= $h($t('mail_smtp')) ?></label>
                <label class="choice"><input type="radio" name="mailer" value="log" <?= $old['mailer'] === 'log' ? 'checked' : '' ?>> <?= $h($t('mail_log')) ?></label>
                <label for="mail_from"><?= $h($t('mail_from')) ?></label>
                <input type="email" id="mail_from" name="mail_from" value="<?= $h($old['mail_from']) ?>" required>
                <p class="hint"><?= $h($t('mail_from_hint')) ?></p>
                <div class="row">
                    <div><label for="smtp_host"><?= $h($t('smtp_host')) ?></label><input type="text" id="smtp_host" name="smtp_host" value="<?= $h($old['smtp_host']) ?>"></div>
                    <div><label for="smtp_port"><?= $h($t('smtp_port')) ?></label><input type="text" id="smtp_port" name="smtp_port" value="<?= $h($old['smtp_port']) ?>" inputmode="numeric"></div>
                </div>
                <label for="smtp_enc"><?= $h($t('smtp_enc')) ?></label>
                <select id="smtp_enc" name="smtp_enc">
                    <option value="tls" <?= $old['smtp_enc'] !== 'ssl' ? 'selected' : '' ?>>STARTTLS (587)</option>
                    <option value="ssl" <?= $old['smtp_enc'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option>
                </select>
                <div class="row">
                    <div><label for="smtp_user"><?= $h($t('smtp_user')) ?></label><input type="text" id="smtp_user" name="smtp_user" value="<?= $h($old['smtp_user']) ?>" autocomplete="off"></div>
                    <div><label for="smtp_pass"><?= $h($t('smtp_pass')) ?></label><input type="password" id="smtp_pass" name="smtp_pass" value="<?= $h($old['smtp_pass']) ?>" autocomplete="new-password"></div>
                </div>
            </section>

            <button type="submit"><?= $h($t('install')) ?></button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
