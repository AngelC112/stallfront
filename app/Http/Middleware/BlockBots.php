<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-bot leggero: un campo trappola invisibile e un tempo minimo di compilazione.
 */
class BlockBots
{
    public const TRAP_FIELD = 'website';

    public const STARTED_FIELD = 'form_started_at';

    private const MIN_SECONDS = 2;

    public function handle(Request $request, Closure $next): Response
    {
        $filledTrap = $request->filled(self::TRAP_FIELD);
        $startedAt = (int) $request->input(self::STARTED_FIELD, 0);
        $tooFast = $startedAt > 0 && (time() - $startedAt) < self::MIN_SECONDS;

        if ($filledTrap || $tooFast) {
            throw ValidationException::withMessages([
                'email' => __('Richiesta non valida. Riprova tra qualche secondo.'),
            ]);
        }

        return $next($request);
    }
}
