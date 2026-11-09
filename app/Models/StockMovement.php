<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const REASON_MANUAL = 'manual';

    public const REASON_ORDER = 'order';

    public const REASON_ORDER_CANCEL = 'order_cancel';

    public const REASON_RETURN = 'return';

    public const REASON_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'product_id',
        'variant_id',
        'user_id',
        'delta',
        'reason',
        'note',
    ];

    protected $casts = [
        'delta' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
