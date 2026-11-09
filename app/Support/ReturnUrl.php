<?php

namespace App\Support;

/**
 * Indirizzo a cui tornare con il link "indietro", passato nel parametro "back".
 *
 * Accetta solo percorsi interni del sito: un link esterno non può essere usato per portare altrove chi clicca.
 */
final class ReturnUrl
{
    public const PARAMETER = 'back';

    public static function from(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 1000) {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($value, '\\') || preg_match('/[\x00-\x1F]/', $value)) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Percorso e query dell'indirizzo, pronti da passare come "back" (es. la pagina che si sta guardando).
     */
    public static function of(string $url): string
    {
        $parts = parse_url($url) ?: [];

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Testo del link "indietro" in base alla pagina di provenienza.
     */
    public static function label(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            str_starts_with($path, '/admin/banco') => __('Modalità banco'),
            (bool) preg_match('~^/admin/customers/\d+~', $path) => __('Scheda cliente'),
            str_starts_with($path, '/admin/customers') => __('Clienti'),
            (bool) preg_match('~^/admin/orders/\d+~', $path) => __('Ordine'),
            str_starts_with($path, '/admin/orders') => __('Tutti gli ordini'),
            str_starts_with($path, '/admin/email-log') => __('Email inviate'),
            default => __('Indietro'),
        };
    }
}
