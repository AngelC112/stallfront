<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailChangeRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            $this->completePendingChanges($user->id);

            return redirect()->route('verification.confirmed');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // La modifica è completata: il link di revoca del vecchio indirizzo
        // da questo momento non è più utilizzabile.
        $this->completePendingChanges($user->id);

        return redirect()->route('verification.confirmed');
    }

    protected function completePendingChanges(int $userId): void
    {
        EmailChangeRequest::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }
}
