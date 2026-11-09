<?php

namespace App\Http\Controllers;

use App\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Pagina "Verifica con WhatsApp" dell'area personale.
 */
class PhoneVerificationController extends Controller
{
    private const SESSION_KEY = 'phone_verification';

    public function __construct(private PhoneVerificationService $verification) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->phone) {
            return redirect()->route('account.edit')->withErrors(['phone' => __('Inserisci prima il numero di cellulare.')]);
        }

        $pending = $request->session()->get(self::SESSION_KEY);
        $hasValidCode = $pending && Carbon::parse($pending['expires_at'])->isFuture() && ! $user->hasPhoneVerified();

        return view('account.phone-verify', [
            'user' => $user,
            'pending' => $hasValidCode ? $pending : null,
            'available' => $this->isAvailable(),
            'canSimulate' => $this->canSimulate(),
            'status' => $this->verification->status($user),
            'businessNumber' => (string) config('services.whatsapp.business_number'),
            'lockMinutes' => PhoneVerificationService::lockMinutes(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless($this->isAvailable(), 404);

        $result = $this->verification->start($request->user());

        $request->session()->put(self::SESSION_KEY, [
            'code' => $result['code'],
            'url' => $result['url'],
            'expires_at' => $result['expires_at']->toIso8601String(),
        ]);

        return redirect()->route('account.phone.verify');
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->verification->status($request->user()));
    }

    /**
     * Solo in modalità prova e fuori produzione: simula il messaggio WhatsApp inviato dal cliente.
     */
    public function simulate(Request $request): RedirectResponse
    {
        abort_unless($this->canSimulate(), 404);

        $pending = $request->session()->get(self::SESSION_KEY);
        abort_unless($pending, 404);

        $this->verification->handleIncomingMessage(
            (string) $request->user()->phone,
            PhoneVerificationService::MESSAGE_PREFIX.' '.$pending['code'],
        );

        return redirect()->route('account.phone.verify');
    }

    /**
     * La verifica è disponibile con le chiavi dell'API, oppure in modalità prova fuori produzione.
     */
    private function isAvailable(): bool
    {
        return ! $this->verification->isTestMode() || $this->canSimulate();
    }

    private function canSimulate(): bool
    {
        return $this->verification->isTestMode() && ! app()->isProduction();
    }
}
