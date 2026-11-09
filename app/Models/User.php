<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_SELLER = 'seller';

    public const ROLE_COLLABORATOR = 'collaborator';

    protected $fillable = [
        'role',
        'name',
        'first_name',
        'last_name',
        'email',
        'locale',
        'password',
        'phone',
        'phone_verified_at',
        'address',
        'street',
        'street_number',
        'postal_code',
        'city',
        'province',
        'latitude',
        'longitude',
        'privacy_accepted_at',
        'terms_accepted_at',
        'delivery_enabled',
        'no_show_count',
        'blocked_at',
        'last_login_at',
        'push_revoked_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'privacy_accepted_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'blocked_at' => 'datetime',
        'last_login_at' => 'datetime',
        'push_revoked_at' => 'datetime',
        'delivery_enabled' => 'boolean',
        'cart' => 'array',
        'password' => 'hashed',
    ];

    /**
     * Language of the emails and notifications sent to the user.
     */
    public function preferredLocale(): string
    {
        return \App\Support\Locales::normalize($this->locale) ?? \App\Support\Locales::fallback();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(CollaboratorPermission::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(PhoneVerification::class);
    }

    public function isSeller(): bool
    {
        return $this->role === self::ROLE_SELLER;
    }

    public function isCollaborator(): bool
    {
        return $this->role === self::ROLE_COLLABORATOR;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function canOrder(): bool
    {
        return ! $this->isBlocked() && $this->hasVerifiedEmail();
    }

    public function hasPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function fullName(): string
    {
        $first = $this->first_name ?: $this->name;
        $last = $this->last_name;

        return trim($first.' '.$last);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSeller()) {
            return true;
        }

        if (! $this->isCollaborator()) {
            return false;
        }

        return $this->permissions()
            ->where('permission', $permission)
            ->exists();
    }
}
