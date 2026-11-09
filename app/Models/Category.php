<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Category extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'always_available', // true/false

        // Nuovi campi stagionali
        'season_start_year',   // nullable int
        'season_start_month',  // nullable int
        'season_start_day',    // nullable int
        'season_end_year',     // nullable int
        'season_end_month',    // nullable int
        'season_end_day',      // nullable int

        // Comportamento fuori stagione
        'off_season_behavior', // string: 'longer_lead_time', 'visible_only', 'hidden'
        'off_season_days',     // int: giorni extra di evasione

        'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'always_available' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('position')
            ->orderBy('name');
    }

    public function isInSeason(?Carbon $date = null): bool
    {
        if ($this->always_available) {
            return true;
        }

        if (
            ! $this->season_start_month ||
            ! $this->season_start_day ||
            ! $this->season_end_month ||
            ! $this->season_end_day
        ) {
            return true;
        }

        $date = $date ?: now();

        $current = sprintf('%02d-%02d', $date->month, $date->day);

        $start = sprintf('%02d-%02d', $this->season_start_month, $this->season_start_day);
        $end = sprintf('%02d-%02d', $this->season_end_month, $this->season_end_day);

        if ($start === $end) {
            return $current === $start;
        }

        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        // Periodo a cavallo dell'anno nuovo.
        // Esempio: 1 novembre -> 28 febbraio.
        return $current >= $start || $current <= $end;
    }

    public function seasonLabel(): string
    {
        if ($this->always_available) {
            return __('Sempre disponibile');
        }

        if (
            ! $this->season_start_month ||
            ! $this->season_start_day ||
            ! $this->season_end_month ||
            ! $this->season_end_day
        ) {
            return __('Periodo non definito');
        }

        return sprintf(
            '%02d/%02d → %02d/%02d',
            $this->season_start_day,
            $this->season_start_month,
            $this->season_end_day,
            $this->season_end_month
        );
    }
}
