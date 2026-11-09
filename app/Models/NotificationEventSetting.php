<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventSetting extends Model
{
    protected $primaryKey = 'event';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event',
        'is_active',
        'importance',
        'subject',
        'body',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
