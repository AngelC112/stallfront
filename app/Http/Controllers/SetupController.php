<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureSetupCompleted;
use App\Models\Setting;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * First run: creates the seller (administrator) account and the shop name.
 * Available only while no seller exists.
 */
class SetupController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (EnsureSetupCompleted::completed()) {
            return redirect()->route('home');
        }

        return view('setup', [
            'minPassword' => (int) Setting::get('security.password_min_length', 8),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (EnsureSetupCompleted::completed()) {
            return redirect()->route('home');
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $minPassword = (int) Setting::get('security.password_min_length', 8);

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword($minPassword)],
            'locale' => ['required', 'string', Rule::in(array_keys(Locales::all()))],
        ]);

        $user = DB::transaction(function () use ($data): ?User {
            // Two people submitting at the same time: only the first becomes the seller.
            if (User::query()->where('role', User::ROLE_SELLER)->lockForUpdate()->exists()) {
                return null;
            }

            Setting::set('store.name', $data['store_name']);

            return User::forceCreate([
                'role' => User::ROLE_SELLER,
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'locale' => $data['locale'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
                'privacy_accepted_at' => now(),
                'terms_accepted_at' => now(),
            ]);
        });

        if (! $user) {
            return redirect()->route('home');
        }

        Cache::forever('setup:completed', true);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.settings.edit')->with('success', __('Il negozio è pronto. Completa i dati del negozio e aggiungi il primo mercato.'));
    }
}
