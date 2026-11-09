<?php

namespace App\Http\Controllers;

use App\Mail\EmailChangeRevokedMail;
use App\Models\EmailChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class EmailChangeRevocationController extends Controller
{
    public function show(string $token): View
    {
        $change = $this->find($token);

        return view('auth.email-change-revoke', [
            'state' => $this->state($change),
            'change' => $change,
            'token' => $token,
        ]);
    }

    public function revoke(string $token): RedirectResponse
    {
        $change = $this->find($token);

        if ($this->state($change) !== 'pending') {
            return redirect()->route('email-change.revoke.form', ['token' => $token]);
        }

        DB::transaction(function () use ($change) {
            $change->forceFill(['revoked_at' => now()])->save();

            // L'account torna al vecchio indirizzo, di nuovo verificato.
            $change->user->forceFill([
                'email' => $change->old_email,
                'email_verified_at' => $change->old_verified_at ?? now(),
            ])->save();
        });

        // Avviso a entrambi gli indirizzi, come buona pratica di sicurezza.
        $locale = $change->user?->preferredLocale() ?? app()->getLocale();
        Mail::to($change->old_email)->locale($locale)->send(new EmailChangeRevokedMail($change->old_email, $change->new_email));
        Mail::to($change->new_email)->locale($locale)->send(new EmailChangeRevokedMail($change->old_email, $change->new_email));

        return redirect()->route('email-change.revoke.form', ['token' => $token]);
    }

    protected function find(string $token): ?EmailChangeRequest
    {
        return EmailChangeRequest::query()
            ->where('revoke_token_hash', hash('sha256', $token))
            ->first();
    }

    protected function state(?EmailChangeRequest $change): string
    {
        if (! $change) {
            return 'unknown';
        }

        if ($change->revoked_at) {
            return 'revoked';
        }

        if ($change->completed_at) {
            return 'completed';
        }

        if ($change->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
    }
}
