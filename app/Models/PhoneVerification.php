<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'channel',
        'code_hash',
        'expires_at',
        'verified_at',
        'attempts',
        'locked_until',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'locked_until' => 'datetime',
    ];
}
