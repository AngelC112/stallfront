<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedFingerprint extends Model
{
    protected $fillable = [
        'user_id',
        'email_hmac',
        'phone_hmac',
        'blocked_at',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
    ];
}
