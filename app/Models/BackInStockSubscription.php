<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackInStockSubscription extends Model
{
    protected $fillable = [
        'product_id',
        'email',
        'locale',
        'variant_key',
        'variant_label',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
