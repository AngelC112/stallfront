<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price_cents',
        'stock',
        'reserved_stock',
        'is_active',
        'photo_id',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(ProductPhoto::class, 'photo_id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_value',
            'variant_id',
            'option_value_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->reserved_stock);
    }

    public function getPriceCentsOrDefaultAttribute(): int
    {
        return $this->price_cents ?? $this->product->price_cents;
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents_or_default / 100;
    }

    public function label(): string
    {
        $values = $this->optionValues
            ->load('optionType')
            ->sortBy('optionType.position')
            ->map(fn (ProductOptionValue $value) => $value->value)
            ->implode(' / ');

        return $values ?: __('Variante');
    }
}
