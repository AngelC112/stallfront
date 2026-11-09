<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Text written by the seller for one language (notification messages, legal pages).
 * When a language has no text, the built-in default of that language is used.
 */
class LocalizedText extends Model
{
    protected $fillable = [
        'key',
        'locale',
        'title',
        'body',
    ];

    /**
     * @return array{title: ?string, body: ?string}|null
     */
    public static function find(string $key, string $locale): ?array
    {
        try {
            return Cache::rememberForever(self::cacheKey($key, $locale), function () use ($key, $locale) {
                $row = static::query()->where('key', $key)->where('locale', $locale)->first(['title', 'body']);

                return $row ? ['title' => $row->title, 'body' => $row->body] : null;
            });
        } catch (Throwable) {
            return null;
        }
    }

    public static function put(string $key, string $locale, ?string $title, ?string $body): void
    {
        static::query()->updateOrCreate(['key' => $key, 'locale' => $locale], ['title' => $title, 'body' => $body]);
        Cache::forget(self::cacheKey($key, $locale));
    }

    public static function remove(string $key, string $locale): void
    {
        static::query()->where('key', $key)->where('locale', $locale)->delete();
        Cache::forget(self::cacheKey($key, $locale));
    }

    /**
     * Languages that have a customised text for the key.
     *
     * @return list<string>
     */
    public static function localesFor(string $key): array
    {
        return static::query()->where('key', $key)->pluck('locale')->all();
    }

    private static function cacheKey(string $key, string $locale): string
    {
        return 'localized_text:'.$key.':'.$locale;
    }
}
