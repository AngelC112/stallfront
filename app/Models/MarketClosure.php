<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'closure_date',
        'reason',
    ];

    protected $casts = [
        'closure_date' => 'date',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
