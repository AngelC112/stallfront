<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductOptionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'option_type_id',
        'value',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function optionType(): BelongsTo
    {
        return $this->belongsTo(ProductOptionType::class, 'option_type_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_option_value',
            'option_value_id',
            'variant_id'
        );
    }
}
