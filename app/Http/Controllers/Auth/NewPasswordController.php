<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Models\Setting;
use App\Rules\StrongPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'request' => $request,
            'minPassword' => (int) Setting::get('security.password_min_length', 8),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $min = (int) Setting::get('security.password_min_length', 8);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword($min)],
        ], [
            'email.required' => __('L\'email è obbligatoria.'),
            'email.email' => __('Inserisci un indirizzo email valido.'),
            'password.required' => __('La password è obbligatoria.'),
            'password.confirmed' => __('Le due password non coincidono.'),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Come da specifiche: dopo il cambio le altre sessioni vengono chiuse.
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();

                // Email di avviso del cambio password.
                Mail::to($user->email)->locale($user->preferredLocale())->send(new PasswordChangedMail($user));

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            throw ValidationException::withErrors([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
