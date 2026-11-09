<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language of the request (user, visitor choice or browser).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locales::forRequest($request);
        app()->setLocale($locale);

        // Accounts created before languages existed adopt the language they are using.
        $user = $request->user();
        if ($user && ! Locales::normalize($user->locale)) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return $next($request);
    }
}
