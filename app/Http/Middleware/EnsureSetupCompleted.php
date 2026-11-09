<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A new installation has no seller yet: every page leads to the first-run
 * page that creates the seller account. After that this check costs nothing.
 */
class EnsureSetupCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::completed() || $request->routeIs('setup.*', 'locale.update')) {
            return $next($request);
        }

        return redirect()->route('setup.show');
    }

    public static function completed(): bool
    {
        if (Cache::get('setup:completed')) {
            return true;
        }

        try {
            $done = User::query()->where('role', User::ROLE_SELLER)->exists();
        } catch (Throwable) {
            return true; // database not reachable: let the normal error page explain it
        }

        if ($done) {
            Cache::forever('setup:completed', true);
        }

        return $done;
    }
}
