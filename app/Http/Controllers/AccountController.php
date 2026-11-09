<?php

namespace App\Http\Controllers;

use App\Mail\EmailChangeRevocationMail;
use App\Models\EmailChangeRequest;
use App\Models\Setting;
use App\Rules\StrongPassword;
use App\Services\AccountSecurityService;
use App\Services\BlocklistService;
use App\Support\AddressInput;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'minPassword' => (int) Setting::get('security.password_min_length', 8),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $blocklist = app(BlocklistService::class);

        // Normalizza il cellulare a sole cifre.
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => preg_replace('/\D+/', '', $request->input('phone')),
            ]);
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validator = Validator::make($request->all(), [
            // Conferma dell'identità: senza password attuale corretta
            // nessuna modifica viene salvata.
            'current_password' => ['required', 'current_password'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'nullable', 'string', 'digits_between:8,15',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'address_search' => ['nullable', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(array_keys(Locales::all()))],
        ], [
            'current_password.required' => __('Inserisci la password attuale per confermare le modifiche.'),
            'current_password.current_password' => __('La password attuale non è corretta.'),
            'first_name.required' => __('Il nome è obbligatorio.'),
            'last_name.required' => __('Il cognome è obbligatorio.'),
            'email.required' => __('L\'email è obbligatoria.'),
            'email.email' => __('Inserisci un indirizzo email valido.'),
            'email.unique' => __('Esiste già un utente con questa email.'),
            'phone.digits_between' => __('Inserisci un numero di cellulare valido.'),
            'phone.unique' => __('Esiste già un utente con questo numero.'),
        ]);

        $address = [];

        $validator->after(function ($v) use ($request, $blocklist, $user, &$address) {
            try {
                $address = AddressInput::resolve($request, $user);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $v->errors()->add($field, $messages[0]);
                }
            }

            if (
                $request->input('email') !== $user->email
                && $blocklist->isEmailBlocked($request->input('email'))
            ) {
                $v->errors()->add('email', __('Esiste già un utente con questa email.'));
            }

            if (
                $request->filled('phone')
                && $request->input('phone') !== $user->phone
                && $blocklist->isPhoneBlocked($request->input('phone'))
            ) {
                $v->errors()->add('phone', __('Esiste già un utente con questo numero.'));
            }
        });

        $data = $validator->validate();

        $emailChanged = $data['email'] !== $user->email;
        $phoneChanged = ($data['phone'] ?? null) !== $user->phone;

        $oldEmail = $user->email;
        $oldVerifiedAt = $user->email_verified_at;

        $user->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'locale' => $data['locale'],
            'phone' => $data['phone'] ?? null,
            ...$address,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
        ])->save();

        if ($emailChanged) {
            $revokeHours = (int) Setting::get('security.email_change_revoke_hours', 48);

            $token = bin2hex(random_bytes(32));

            EmailChangeRequest::create([
                'user_id' => $user->id,
                'old_email' => $oldEmail,
                'new_email' => $data['email'],
                'old_verified_at' => $oldVerifiedAt,
                'revoke_token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours($revokeHours),
            ]);

            Mail::to($oldEmail)->send(new EmailChangeRevocationMail(
                $user,
                $oldEmail,
                $data['email'],
                route('email-change.revoke.form', ['token' => $token]),
                $revokeHours,
            ));

            $user->sendEmailVerificationNotification();

            return redirect()
                ->route('verification.notice')
                ->with('status', 'verification-link-sent');
        }

        return redirect()
            ->route('account.edit')
            ->with('status', 'account-updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $min = (int) Setting::get('security.password_min_length', 8);

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword($min)],
        ], [
            'current_password.required' => __('Inserisci la password attuale.'),
            'current_password.current_password' => __('La password attuale non è corretta.'),
            'password.confirmed' => __('Le due password non coincidono.'),
        ]);

        $data = $validator->validate();

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        app(AccountSecurityService::class)->passwordChanged($request->user(), $request->session()->getId());

        return redirect()
            ->route('account.edit')
            ->with('status', 'password-updated');
    }
}
