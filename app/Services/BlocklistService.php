<?php

namespace App\Services;

use App\Models\BlockedFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BlocklistService
{
    public function emailHmac(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return hash_hmac('sha256', 'email:'.$normalized, $this->key());
    }

    public function phoneHmac(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', 'phone:'.$normalized, $this->key());
    }

    public function isEmailBlocked(?string $email): bool
    {
        $hmac = $this->emailHmac($email);

        if (! $hmac) {
            return false;
        }

        return BlockedFingerprint::query()
            ->where('email_hmac', $hmac)
            ->exists();
    }

    public function isPhoneBlocked(?string $phone): bool
    {
        $hmac = $this->phoneHmac($phone);

        if (! $hmac) {
            return false;
        }

        return BlockedFingerprint::query()
            ->where('phone_hmac', $hmac)
            ->exists();
    }

    public function blockUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            BlockedFingerprint::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'email_hmac' => $this->emailHmac($user->email),
                    'phone_hmac' => $this->phoneHmac($user->phone),
                    'blocked_at' => now(),
                ]
            );

            $user->forceFill([
                'blocked_at' => now(),
            ])->save();
        });
    }

    public function unblockUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            BlockedFingerprint::query()
                ->where('user_id', $user->id)
                ->delete();

            $user->forceFill([
                'blocked_at' => null,
                'no_show_count' => 0,
            ])->save();
        });
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    protected function key(): string
    {
        $key = config('vetrina.hmac_key') ?: config('app.key');

        return 'vetrina-blocklist::'.$key;
    }
}
