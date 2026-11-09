<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => __('L\'email è obbligatoria.'),
            'email.email' => __('Inserisci un indirizzo email valido.'),
        ]);

        // Come da specifiche: la risposta è identica
        // sia che l'email esista sia che non esista.
        Password::sendResetLink($request->only('email'));

        return back()->with([
            'status' => __(Password::RESET_LINK_SENT),
            'email_sent_to' => $request->input('email'),
        ]);
    }
}
