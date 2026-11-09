<?php

namespace App\Http\Controllers;

use App\Mail\TemplatedMail;
use App\Services\AccountSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Eliminazione dell’account con doppia verifica: password + conferma dal link ricevuto via email.
 * Il venditore non può eliminare il proprio account: è il titolare del negozio.
 */
class AccountDeletionController extends Controller
{
    private const LINK_MINUTES = 30;

    public function export(Request $request, AccountSecurityService $security): StreamedResponse
    {
        $data = $security->export($request->user());

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'i-miei-dati-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Primo passo: password attuale e parola di conferma. Parte l’email con il link.
     */
    public function request(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSeller(), 403);

        $request->validateWithBag('deleteAccount', [
            'current_password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:ELIMINA'],
        ], [
            'current_password.required' => __('Inserisci la password attuale.'),
            'current_password.current_password' => __('La password attuale non è corretta.'),
            'confirmation.in' => __('Scrivi ELIMINA in maiuscolo per confermare.'),
        ]);

        $user = $request->user();
        $url = URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(self::LINK_MINUTES), ['user' => $user->id]);

        Mail::to($user->email)->send(new TemplatedMail(
            __('Conferma l’eliminazione del tuo account'),
            __("Ciao :name,\nhai chiesto di eliminare il tuo account. L’operazione cancella i tuoi dati e tutti i tuoi ordini e non si può annullare.\n\nPer confermare apri questo link entro :minutes minuti:\n:url\n\nSe non sei stato tu, ignora questa email e cambia la password.", ['name' => $user->fullName(), 'minutes' => self::LINK_MINUTES, 'url' => $url]),
        ));

        return back()->with('status', 'account-delete-link-sent');
    }

    /**
     * Secondo passo: la pagina aperta dal link firmato chiede l’ultima conferma.
     */
    public function confirm(Request $request): View
    {
        abort_unless((int) $request->route('user') === $request->user()->id, 403);
        abort_if($request->user()->isSeller(), 403);

        return view('account.delete-confirm');
    }

    public function destroy(Request $request, AccountSecurityService $security): RedirectResponse
    {
        abort_unless((int) $request->route('user') === $request->user()->id, 403);
        abort_if($request->user()->isSeller(), 403);

        $user = $request->user();

        Auth::logout();
        $security->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Il tuo account è stato eliminato.'));
    }
}
