<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailChangeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'old_email',
        'new_email',
        'old_verified_at',
        'revoke_token_hash',
        'expires_at',
        'revoked_at',
        'completed_at',
    ];

    protected $casts = [
        'old_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNull('completed_at')
            ->where('expires_at', '>', now());
    }
}
