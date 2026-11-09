<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Language selector: remembers the choice of the visitor and, for a
 * signed-in user, saves it in the account.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(Locales::all()))],
        ]);

        $locale = $data['locale'];
        $request->session()->put(Locales::SESSION_KEY, $locale);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        Cookie::queue(Cookie::make(Locales::COOKIE, $locale, 60 * 24 * 365));

        return redirect()->back();
    }
}
