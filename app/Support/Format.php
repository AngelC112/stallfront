<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * Prices and dates formatted for the current language.
 */
final class Format
{
    /**
     * Amount in cents, e.g. "€ 12,50" in Italian or "€12.50" in English.
     */
    public static function money(int|float|null $cents, ?string $locale = null): string
    {
        $amount = ((float) ($cents ?? 0)) / 100;
        $currency = (string) config('app.currency', 'EUR');
        $locale ??= app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $currency === 'EUR'
            ? '€ '.number_format($amount, 2, ',', '.')
            : $currency.' '.number_format($amount, 2, '.', ',');
    }

    /**
     * Plain number with two decimals in the current language (no currency symbol).
     */
    public static function decimal(int|float|null $cents, ?string $locale = null): string
    {
        $amount = ((float) ($cents ?? 0)) / 100;
        $locale ??= app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
            $formatted = $formatter->format($amount);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($amount, 2, ',', '.');
    }

    /**
     * Short date, e.g. 09/11/2026 in Italian or 11/09/2026 in English.
     */
    public static function date(DateTimeInterface|string|null $date): string
    {
        return $date ? Carbon::parse($date)->locale(app()->getLocale())->isoFormat('L') : '';
    }

    /**
     * Short date and time.
     */
    public static function dateTime(DateTimeInterface|string|null $date): string
    {
        return $date ? Carbon::parse($date)->locale(app()->getLocale())->isoFormat('L LT') : '';
    }

    /**
     * Time of day.
     */
    public static function time(DateTimeInterface|string|null $date): string
    {
        return $date ? Carbon::parse($date)->locale(app()->getLocale())->isoFormat('LT') : '';
    }

    /**
     * Currency symbol for the current language, for form fields.
     */
    public static function currencySymbol(?string $locale = null): string
    {
        $currency = (string) config('app.currency', 'EUR');

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(($locale ?? app()->getLocale()).'@currency='.$currency, NumberFormatter::CURRENCY);

            return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL) ?: $currency;
        }

        return $currency === 'EUR' ? '€' : $currency;
    }
}
