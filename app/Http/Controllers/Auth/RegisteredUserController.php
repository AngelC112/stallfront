<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\BlocklistService;
use App\Services\CartPersistenceService;
use App\Support\AddressInput;
use App\Support\Locales;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'minPassword' => (int) Setting::get('security.password_min_length', 8),
        ]);
    }

    public function store(Request $request, CartPersistenceService $cartPersistence): RedirectResponse
    {
        $blocklist = app(BlocklistService::class);

        // Normalizza il cellulare: solo cifre, per evitare duplicati
        // scritti in modi diversi (spazi, trattini, +39).
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => preg_replace('/\D+/', '', $request->input('phone')),
            ]);
        }

        // Email normalizzata in automatico: minuscolo e senza spazi.
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $minPassword = (int) Setting::get('security.password_min_length', 8);

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'digits_between:8,15', 'unique:users,phone'],
            'address_search' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:'.$minPassword, 'confirmed'],
            'consent' => ['required', 'accepted'],
            'locale' => ['required', 'string', Rule::in(array_keys(Locales::all()))],
        ], [
            'first_name.required' => __('Il nome è obbligatorio.'),
            'last_name.required' => __('Il cognome è obbligatorio.'),
            'email.required' => __('L\'email è obbligatoria.'),
            'email.email' => __('Inserisci un indirizzo email valido.'),
            'email.unique' => __('Esiste già un utente con questa email.'),
            'phone.digits_between' => __('Inserisci un numero di cellulare valido.'),
            'phone.unique' => __('Esiste già un utente con questo numero.'),
            'password.required' => __('La password è obbligatoria.'),
            'password.min' => __('La password deve avere almeno :min caratteri.'),
            'password.confirmed' => __('Le due password non coincidono.'),
            'consent.required' => __('Devi leggere e accettare Privacy Policy e Termini di vendita.'),
            'consent.accepted' => __('Devi leggere e accettare Privacy Policy e Termini di vendita.'),
        ]);

        // Controllo anche sulle impronte dei clienti bloccati:
        // la registrazione viene rifiutata se email o cellulare
        // coincidono con quelli di un cliente bloccato.
        $address = [];

        $validator->after(function ($v) use ($request, $blocklist, &$address) {
            try {
                $address = AddressInput::resolve($request);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $v->errors()->add($field, $messages[0]);
                }
            }

            if ($blocklist->isEmailBlocked($request->input('email'))) {
                $v->errors()->add('email', __('Esiste già un utente con questa email.'));
            }

            if ($request->filled('phone') && $blocklist->isPhoneBlocked($request->input('phone'))) {
                $v->errors()->add('phone', __('Esiste già un utente con questo numero.'));
            }
        });

        $data = $validator->validate();

        $user = User::create([
            'role' => User::ROLE_CUSTOMER,
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            ...$address,
            'privacy_accepted_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $cartPersistence->restoreForUser($request);

        return redirect()->route('verification.notice');
    }
}
