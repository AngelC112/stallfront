<?php

namespace App\Support;

use Illuminate\Support\Str;

class SlugGenerator
{
    public static function unique(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim($name));

        if ($base === '') {
            $base = 'elemento';
        }

        $slug = $base;
        $suffix = 1;

        while (true) {
            $exists = $modelClass::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->withTrashed()
                ->exists();

            if (! $exists) {
                return $slug;
            }

            $suffix++;
            $slug = $base.'-'.$suffix;
        }
    }
}
