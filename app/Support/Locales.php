<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Languages offered by the site and the choice of the language of each request.
 */
final class Locales
{
    public const SESSION_KEY = 'locale';

    public const COOKIE = 'locale';

    /**
     * @return array<string, string> code => native name
     */
    public static function all(): array
    {
        return config('app.locales', ['en' => 'English']);
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }

    /**
     * Normalises a code such as "it-IT" or "pt_BR" to a supported language, or null.
     */
    public static function normalize(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        $code = strtolower(substr(str_replace('_', '-', trim($locale)), 0, 2));

        return self::isSupported($code) ? $code : null;
    }

    /**
     * Default language when nothing else applies.
     */
    public static function fallback(): string
    {
        return self::normalize(config('app.locale')) ?? 'en';
    }

    /**
     * Language of the request: the user's choice, then the visitor's choice
     * (session or cookie), then the best match for the browser languages.
     */
    public static function forRequest(Request $request): string
    {
        $user = $request->user();

        if ($user && ($locale = self::normalize($user->locale))) {
            return $locale;
        }

        if ($request->hasSession() && ($locale = self::normalize($request->session()->get(self::SESSION_KEY)))) {
            return $locale;
        }

        if ($locale = self::normalize($request->cookie(self::COOKIE))) {
            return $locale;
        }

        return self::fromBrowser($request) ?? self::fallback();
    }

    /**
     * First supported language in the Accept-Language header, in the order of preference of the browser.
     */
    public static function fromBrowser(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            if ($locale = self::normalize($language)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Value for the lang attribute of the html element.
     */
    public static function htmlLang(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }
}
