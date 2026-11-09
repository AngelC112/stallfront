<?php

namespace App\Support;

use Closure;

/**
 * Runs code in another language (e.g. a message for a customer who uses a
 * different language from the person who triggered it).
 */
final class Localized
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(?string $locale, Closure $callback): mixed
    {
        $locale = Locales::normalize($locale) ?? Locales::fallback();
        $previous = app()->getLocale();

        if ($locale === $previous) {
            return $callback();
        }

        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
