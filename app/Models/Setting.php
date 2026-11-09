<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            // Salviamo in cache solo un array con i dati grezzi, non l'oggetto Eloquent
            $data = Cache::rememberForever('setting:'.$key, function () use ($key) {
                $row = static::query()->whereKey($key)->first(['value', 'type']);

                if (! $row) {
                    return null;
                }

                return [
                    'value' => $row->value,
                    'type' => $row->type,
                ];
            });
        } catch (Throwable) {
            return $default;
        }

        if (! $data) {
            return $default;
        }

        return static::cast($data['value'], $data['type']);
    }

    public static function set(string $key, mixed $value): static
    {
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };

        $raw = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $raw,
                'type' => $type,
            ]
        );

        Cache::forget('setting:'.$key);

        return $setting;
    }

    protected static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
