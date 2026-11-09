<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Market extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'location',
        'weekday',
        'minimum_order_days',
        'order_cutoff_time',
        'pickup_cutoff_time',
        'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'minimum_order_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function closures(): HasMany
    {
        return $this->hasMany(MarketClosure::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(MarketHoliday::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function weekdayLabel(): string
    {
        return [
            1 => __('Lunedì'),
            2 => __('Martedì'),
            3 => __('Mercoledì'),
            4 => __('Giovedì'),
            5 => __('Venerdì'),
            6 => __('Sabato'),
            7 => __('Domenica'),
        ][$this->weekday] ?? __('Giorno non valido');
    }
}
