<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BlocklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateEmailController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $blocklist = app(BlocklistService::class);
        $user = $request->user();

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validator = Validator::make($request->all(), [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'email.required' => __('L\'email è obbligatoria.'),
            'email.email' => __('Inserisci un indirizzo email valido.'),
            'email.unique' => __('Esiste già un utente con questa email.'),
        ]);

        $validator->after(function ($v) use ($request, $blocklist) {
            if ($blocklist->isEmailBlocked($request->input('email'))) {
                $v->errors()->add('email', __('Esiste già un utente con questa email.'));
            }
        });

        $data = $validator->validate();

        if ($data['email'] === $user->email) {
            return redirect()->route('verification.notice');
        }

        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        return redirect()
            ->route('verification.notice')
            ->with('status', 'verification-link-sent');
    }
}
