<?php

use App\Http\Middleware\BlockBots;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Only a reverse proxy may set the X-Forwarded-* headers. Trusting every
        // sender ('*') would let any visitor fake its IP address and so bypass
        // the per-IP rate limits (login attempts included). By default the
        // proxy is expected on the same machine or on a private network
        // (Docker, LAN); a visitor coming straight from the internet has a
        // public address and is never trusted. TRUSTED_PROXIES replaces the
        // list: comma-separated IP addresses or CIDR ranges, or * for all.
        $trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');
        if (! is_string($trustedProxies) || trim($trustedProxies) === '') {
            $trustedProxies = '127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,::1,fc00::/7';
        }
        $middleware->trustProxies(at: trim($trustedProxies) === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'antibot' => BlockBots::class,
        ]);


        // Chiamate esterne senza sessione: webhook di Meta e conferme del service worker (link firmati).
        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
            'push/ack/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (InvalidSignatureException $e, $request) {
            if ($request->routeIs('verification.verify')) {
                return response()->view('auth.link-expired', status: 403);
            }
        });
    })->create();

/*
 * Persistent data folder (Docker: /data). When STALLFRONT_DATA_DIR is set,
 * the configuration (.env) and everything the app writes (uploads, logs,
 * cache, sessions) live there instead of inside the application folder.
 */
$dataDir = $_SERVER['STALLFRONT_DATA_DIR'] ?? $_ENV['STALLFRONT_DATA_DIR'] ?? getenv('STALLFRONT_DATA_DIR');

if (is_string($dataDir) && $dataDir !== '') {
    $app->useEnvironmentPath($dataDir);
    $app->useStoragePath($dataDir.'/storage');
}

return $app;
