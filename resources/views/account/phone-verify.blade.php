<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Verifica il cellulare') }}</h2>
    </x-slot>

    @php($phone = $user->phone)
    <div class="py-8">
        <div class="mx-auto max-w-lg space-y-5 px-4 text-center sm:px-6">
            @if ($errors->any())
                <p class="rounded-xl border border-[#8a2a2a] bg-[#fbe3e3] p-3 text-left text-sm font-semibold text-[#8a2a2a]" role="alert">{{ $errors->first() }}</p>
            @endif

            {{-- Numero verificato --}}
            <section id="verified-panel" @class(['space-y-4', 'hidden' => ! $status['verified']])>
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-brand-600 text-white">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('Numero verificato') }}</h1>
                <p class="text-gray-700"><strong>{{ $phone }}</strong> {{ __('è confermato. Ora il venditore può contattarti e, se abilitata, puoi scegliere la consegna a domicilio.') }}</p>
                <a href="{{ route('account.edit') }}" class="btn-primary min-h-12 w-full text-base">{{ __('Torna al tuo account') }}</a>
            </section>

            @unless ($status['verified'])
                {{-- Messaggio non riconosciuto (mostrato quando aumentano i tentativi falliti) --}}
                <section id="failed-panel" class="hidden space-y-4">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#8a2a2a] text-white">
                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('Messaggio non riconosciuto') }}</h1>
                    <p class="text-gray-700">{{ __('Il messaggio è arrivato da un numero diverso da') }} <strong>{{ $phone }}</strong>{{ __(', oppure il codice è scaduto.') }}</p>
                    <p id="attempts-left" class="text-sm text-gray-500"></p>
                    <form method="POST" action="{{ route('account.phone.verify.start') }}">
                        @csrf
                        <button class="btn-primary min-h-12 w-full text-base">{{ __('Riprova con un nuovo codice') }}</button>
                    </form>
                    <a href="{{ route('account.edit') }}#phone" class="btn-secondary min-h-12 w-full text-base">{{ __('Cambia numero') }}</a>
                    <p class="text-sm text-gray-500">{{ __('Dopo troppi tentativi dovrai attendere :lock_minutes minuti.', ['lock_minutes' => $lockMinutes]) }}</p>
                </section>

                <section id="main-panel" class="space-y-5">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-brand-600 text-white">
                        <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('Verifica il cellulare') }}</h1>
                    <p class="text-gray-700">{{ __('Per confermare che') }} <strong>{{ $phone }}</strong> {{ __('è tuo, invia un messaggio WhatsApp al numero del negozio.') }}</p>

                    @if (! $available)
                        <p class="rounded-xl border border-apricot bg-apricot-light p-4 text-left text-sm text-gray-900">{{ __('La verifica con WhatsApp non è ancora attiva. Riprova più avanti oppure chiedi al venditore di confermare il numero.') }}</p>
                    @elseif ($status['locked_until'])
                        <p class="rounded-xl border border-[#8a2a2a] bg-[#fbe3e3] p-4 text-left text-sm text-[#8a2a2a]">{{ __('Troppi tentativi non riusciti. Riprova dopo le :locked_until.', ['locked_until' => $status['locked_until']]) }}</p>
                    @elseif ($pending)
                        <ol class="space-y-2 rounded-2xl border border-gray-200 bg-white p-5 text-left text-gray-800">
                            <li><strong>1.</strong> {{ __('Tocca «Apri WhatsApp»') }}</li>
                            <li><strong>2.</strong> {{ __('Invia il messaggio già scritto, senza modificarlo') }}</li>
                            <li><strong>3.</strong> {{ __('Torna qui: la pagina si aggiorna da sola') }}</li>
                        </ol>
                        <div class="rounded-2xl border border-[#9fcfb4] bg-[#e4f3ea] p-5 text-left">
                            <p class="text-sm text-gray-600">{{ __('Messaggio da inviare') }}{{ $businessNumber ? ' a '.$businessNumber : '' }}</p>
                            <p class="mt-2 inline-block rounded-xl bg-white px-4 py-2 text-lg tracking-wide text-gray-900">{{ \App\Services\PhoneVerificationService::MESSAGE_PREFIX }} {{ $pending['code'] }}</p>
                        </div>
                        <a href="{{ $pending['url'] }}" target="_blank" rel="noopener" class="btn-primary min-h-12 w-full text-base">{{ __('Apri WhatsApp') }}</a>
                        <p class="text-sm text-gray-500" id="waiting" data-expires="{{ $pending['expires_at'] }}">{{ __('In attesa del tuo messaggio…') }}</p>

                        @if ($canSimulate)
                            <form method="POST" action="{{ route('account.phone.verify.simulate') }}" class="rounded-xl border border-dashed border-apricot bg-apricot-light p-4 text-left text-sm">
                                @csrf
                                <p class="text-gray-900"><strong>{{ __('Modalità prova:') }}</strong> {{ __('il numero WhatsApp del negozio non è ancora collegato. Puoi simulare l’invio del messaggio.') }}</p>
                                <button class="btn-secondary mt-3 w-full">{{ __('Simula l’invio del messaggio') }}</button>
                            </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('account.phone.verify.start') }}">
                            @csrf
                            <button class="btn-primary min-h-12 w-full text-base">{{ __('Verifica con WhatsApp') }}</button>
                        </form>
                        <p class="text-sm text-gray-500">{{ __('Si aprirà WhatsApp con un messaggio già pronto da inviare. Non ti costa nulla.') }}</p>
                    @endif

                    <a href="{{ route('account.edit') }}#phone" class="inline-flex min-h-11 items-center font-bold text-brand-600 hover:underline">{{ __('Cambia numero') }}</a>
                </section>
            @endunless
        </div>
    </div>

    @if (! $status['verified'] && $pending)
        <script>
            (function () {
                var statusUrl = @json(route('account.phone.verify.status'));
                var startAttempts = @json($status['attempts']);
                var waiting = document.getElementById('waiting');
                var expiresAt = new Date(waiting.dataset.expires);

                function show(id) {
                    ['main-panel', 'failed-panel', 'verified-panel'].forEach(function (panel) {
                        document.getElementById(panel).classList.toggle('hidden', panel !== id);
                    });
                }

                function tick() {
                    var minutes = Math.max(0, Math.ceil((expiresAt - new Date()) / 60000));
                    waiting.textContent = minutes > 0
                        ? (minutes === 1 ? @js(__('In attesa del tuo messaggio… il codice scade tra 1 minuto.')) : @js(__('In attesa del tuo messaggio… il codice scade tra :minutes minuti.')).replace(':minutes', minutes))
                        : @js(__('Il codice è scaduto: generane uno nuovo.'));
                }

                function poll() {
                    fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (status) {
                            if (status.verified) {
                                show('verified-panel');
                                return;
                            }
                            if (status.attempts > startAttempts) {
                                document.getElementById('attempts-left').textContent = @js(__('Ti restano :attempts tentativi.')).replace(':attempts', status.attempts_left);
                                show('failed-panel');
                                return;
                            }
                            setTimeout(poll, 3000);
                        })
                        .catch(function () { setTimeout(poll, 5000); });
                }

                tick();
                setInterval(tick, 15000);
                setTimeout(poll, 3000);
            })();
        </script>
    @endif
</x-app-layout>
