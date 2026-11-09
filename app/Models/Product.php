<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const VISIBILITY_VISIBLE = 'visible';

    public const VISIBILITY_HIDDEN = 'hidden';

    public const VISIBILITY_COMING = 'coming_soon';

    public const SEASON_FOLLOW = 'follow_category';

    public const SEASON_PURCHASABLE_LONGER = 'purchasable_longer';

    public const SEASON_VISIBLE_NOT_PURCHASABLE = 'visible_not_purchasable';

    public const SEASON_HIDDEN = 'hidden';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price_cents',
        'vat_included',
        'visibility',
        'is_active',
        'is_visible',
        'track_stock',
        'stock',
        'reserved_stock',
        'has_variants',
        'season_behavior',
        'own_fulfillment_days',
        'always_available',
        'off_season_fulfillment_days',
        'low_stock_threshold',
        'created_by',
    ];

    protected $casts = [
        'vat_included' => 'boolean',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'track_stock' => 'boolean',
        'has_variants' => 'boolean',
        'always_available' => 'boolean',
        'price_cents' => 'integer',
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'own_fulfillment_days' => 'integer',
        'off_season_fulfillment_days' => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class);
    }

    public function optionTypes(): HasMany
    {
        return $this->hasMany(ProductOptionType::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function getAvailableStockAttribute(): int
    {
        if ($this->has_variants) {
            $stock = (int) $this->activeVariants()->sum('stock');
            $reserved = (int) $this->activeVariants()->sum('reserved_stock');

            return max(0, $stock - $reserved);
        }

        return max(0, $this->stock - $this->reserved_stock);
    }

    public function hasOptions(): bool
    {
        return $this->optionTypes()->exists();
    }

    public function coverPhoto(): ?ProductPhoto
    {
        return $this->photos()
            ->orderByDesc('is_cover')
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }

    public function coverPhotoUrl(): ?string
    {
        $photo = $this->coverPhoto();

        return $photo?->thumbUrl();
    }

    public function isOrderable(): bool
    {
        if ($this->visibility !== self::VISIBILITY_VISIBLE) {
            return false;
        }

        if (! $this->track_stock) {
            return true;
        }

        return $this->available_stock > 0;
    }

    public function isLowStock(): bool
    {
        if (! $this->track_stock) {
            return false;
        }

        $threshold = $this->low_stock_threshold
            ?: (int) Setting::get('orders.low_stock_threshold', 3);

        return $this->available_stock <= $threshold;
    }

    public function visibilityLabel(): string
    {
        return match ($this->visibility) {
            self::VISIBILITY_HIDDEN => __('Nascosto'),
            self::VISIBILITY_COMING => __('In arrivo'),
            default => __('Visibile'),
        };
    }

    public function seasonBehaviorLabel(): string
    {
        return match ($this->season_behavior) {
            self::SEASON_PURCHASABLE_LONGER => __('Acquistabile con tempi più lunghi'),
            self::SEASON_VISIBLE_NOT_PURCHASABLE => __('Visibile ma non acquistabile'),
            self::SEASON_HIDDEN => __('Nascosto fuori periodo'),
            default => __('Segui la categoria'),
        };
    }
}
