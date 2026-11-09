<?php

namespace App\Support;

use App\Models\LocalizedText;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Legal pages (privacy, cookies, terms of sale).
 *
 * Each page has a built-in template for every language (resources/legal/<locale>/<page>.md)
 * that the seller can replace from the panel, language by language. Templates use
 * placeholders such as {store_identity} that are filled in from the shop settings.
 */
final class LegalPages
{
    public const PAGES = ['privacy', 'cookies', 'terms'];

    public static function exists(string $page): bool
    {
        return in_array($page, self::PAGES, true);
    }

    public static function key(string $page): string
    {
        return 'legal.'.$page;
    }

    /**
     * Built-in template (Markdown) of a page in a language, falling back to English.
     */
    public static function template(string $page, string $locale): string
    {
        foreach ([$locale, 'en'] as $code) {
            $path = resource_path('legal/'.$code.'/'.$page.'.md');

            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        return '';
    }

    /**
     * Markdown source in use for a language: the seller's text, otherwise the built-in template.
     *
     * @return array{source: string, customized: bool, updated_at: ?\Illuminate\Support\Carbon}
     */
    public static function source(string $page, string $locale): array
    {
        $custom = LocalizedText::query()->where('key', self::key($page))->where('locale', $locale)->first();

        if ($custom && trim((string) $custom->body) !== '') {
            return ['source' => (string) $custom->body, 'customized' => true, 'updated_at' => $custom->updated_at];
        }

        return ['source' => self::template($page, $locale), 'customized' => false, 'updated_at' => null];
    }

    /**
     * Page ready to show: title and HTML in the current language.
     *
     * @return array{title: string, html: string, updated_at: ?\Illuminate\Support\Carbon}
     */
    public static function render(string $page): array
    {
        $source = self::source($page, app()->getLocale());
        $markdown = strtr($source['source'], self::placeholderValues());

        $title = preg_match('/^#\s+(.+)$/m', $markdown, $match) ? trim($match[1]) : self::label($page);

        return [
            'title' => $title,
            'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'updated_at' => $source['updated_at'],
        ];
    }

    public static function label(string $page): string
    {
        return match ($page) {
            'privacy' => __('Privacy Policy'),
            'cookies' => __('Cookie Policy'),
            'terms' => __('Termini di vendita'),
            default => $page,
        };
    }

    /**
     * @return array<string, string> placeholder => description
     */
    public static function placeholders(): array
    {
        return [
            '{store_identity}' => __('Denominazione, indirizzo, partita IVA e contatti del negozio'),
            '{store_name}' => __('Denominazione'),
            '{store_address}' => __('Indirizzo'),
            '{store_vat}' => __('Partita IVA'),
            '{store_email}' => __('Email di contatto'),
            '{store_phone}' => __('Telefono'),
            '{no_show_threshold}' => __('Mancati ritiri prima del blocco'),
            '{delivery_min}' => __('Ordine minimo per la consegna'),
            '{delivery_cost}' => __('Costo di consegna'),
            '{delivery_hours}' => __('Tempo di consegna in ore'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function placeholderValues(): array
    {
        $name = (string) Setting::get('store.name', config('app.name'));
        $address = (string) Setting::get('store.address', '');
        $vat = (string) Setting::get('store.vat', '');
        $email = (string) Setting::get('store.email', '');
        $phone = (string) Setting::get('store.phone', '');
        $minCents = (int) Setting::get('delivery.min_order_cents', 0);
        $costCents = (int) Setting::get('delivery.cost_cents', 0);

        $identity = $name.($address !== '' ? ', '.$address : '');
        if ($vat !== '') {
            $identity .= ' · '.__('P.IVA :vat', ['vat' => $vat]);
        }
        if ($email !== '') {
            $identity .= ' · '.__('email: :email', ['email' => '<'.$email.'>']);
        }
        if ($phone !== '') {
            $identity .= ' · '.__('tel. :phone', ['phone' => $phone]);
        }

        return [
            '{store_identity}' => $identity,
            '{store_name}' => $name,
            '{store_address}' => $address,
            '{store_vat}' => $vat,
            '{store_email}' => $email !== '' ? '<'.$email.'>' : __('il venditore'),
            '{store_phone}' => $phone,
            '{no_show_threshold}' => (string) (int) Setting::get('orders.no_show_block_threshold', 2),
            '{delivery_min}' => $minCents > 0 ? Format::money($minCents) : __('nessuno'),
            '{delivery_cost}' => $costCents > 0 ? Format::money($costCents) : __('gratuita'),
            '{delivery_hours}' => (string) (int) Setting::get('delivery.time_hours', 24),
        ];
    }
}
