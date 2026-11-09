<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * For hostings without cron (SCHEDULER_MODE=web): after the response has been
 * sent, runs the scheduled tasks at most once a minute. With a real cron job
 * (Docker, or a hosting with cron) this middleware does nothing.
 */
class RunSchedulerFromWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (config('app.scheduler_mode') !== 'web') {
            return;
        }

        try {
            if (! Cache::add('scheduler:web-run', now()->timestamp, 55)) {
                return;
            }

            Artisan::call('schedule:run');
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
