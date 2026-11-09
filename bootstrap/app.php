<?php

use App\Http\Middleware\BlockBots;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureSetupCompleted;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RunSchedulerFromWeb;
use App\Http\Middleware\SetLocale;
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
		$middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'antibot' => BlockBots::class,
        ]);

        // Language of every page: the user's choice, the visitor's choice or the browser language.
        $middleware->web(append: [SetLocale::class, EnsureSetupCompleted::class, RunSchedulerFromWeb::class]);

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
