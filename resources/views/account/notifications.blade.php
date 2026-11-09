<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Notifiche') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'notifications-updated')
                <p class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] p-3 text-sm font-semibold text-[#14532d]" role="status">{{ __('Preferenze salvate.') }}</p>
            @endif

            {{-- Permesso revocato: le preferenze sono tornate su email --}}
            @if ($revokedNotice)
                <section class="space-y-3 rounded-2xl border border-apricot bg-apricot-light p-5">
                    <h3 class="font-bold text-gray-900">{{ __('Notifiche del browser disattivate') }}</h3>
                    <p class="text-gray-800">{{ __('Da un tuo dispositivo non risulta più il permesso. Da ora ricevi tutti gli avvisi per email.') }}</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a href="#come-ricevere" class="btn-secondary">{{ __('Riattiva le notifiche') }}</a>
                        <form method="POST" action="{{ route('account.notifications.dismiss-revoked') }}">
                            @csrf
                            <button class="inline-flex min-h-11 items-center px-3 text-sm font-semibold text-gray-600 hover:underline">{{ __('Ho capito') }}</button>
                        </form>
                    </div>
                </section>
            @endif

            {{-- Scelta del canale per gli eventi "a scelta" --}}
            <form id="come-ricevere" method="POST" action="{{ route('account.notifications.update') }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                @csrf
                @method('PUT')
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ __('Come vuoi ricevere gli avvisi') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Per questi avvisi puoi scegliere tra email e notifica sul browser. Se una notifica non ti arriva, ti scriviamo comunque per email.') }}</p>
                </div>

                @if ($choiceEvents->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('Al momento tutti gli avvisi arrivano per email.') }}</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($choiceEvents as $row)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="text-gray-900">{{ str_replace(' (venditore)', '', $row['label']) }}</span>
                                <fieldset class="segmented shrink-0 rounded-full">
                                    <legend class="sr-only">{{ __('Canale per') }} {{ $row['label'] }}</legend>
                                    @foreach (['email' => 'Email', 'browser' => 'Browser'] as $value => $label)
                                        <label @class([
                                            'segmented-item cursor-pointer rounded-full has-[:checked]:bg-brand-600 has-[:checked]:font-bold has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500',
                                            'pointer-events-none opacity-40' => $value === 'browser' && ! $pushConfigured,
                                        ])>
                                            <input type="radio" name="channels[{{ $row['event'] }}]" value="{{ $value }}" class="sr-only" @checked($row['channel'] === $value) @disabled($value === 'browser' && ! $pushConfigured)>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </fieldset>
                            </li>
                        @endforeach
                    </ul>
                    @unless ($pushConfigured)
                        <p class="text-sm text-gray-500">{{ __('Le notifiche del browser non sono ancora attive su questo sito.') }}</p>
                    @endunless
                    <div class="flex justify-end">
                        <button class="btn-primary px-8">{{ __('Salva') }}</button>
                    </div>
                @endif

                @if ($emailEvents->isNotEmpty())
                    <p class="rounded-xl bg-canvas p-3 text-sm text-gray-600">
                        {{ __('Arrivano sempre per email: :label, oltre alle email di sicurezza.', ['label' => $emailEvents->pluck('label')->map(fn ($label) => mb_strtolower(str_replace(' (venditore)', '', $label)))->implode(', ')]) }}
                    </p>
                @endif
            </form>

            {{-- Questo dispositivo: lo stato si legge dal browser --}}
            @if ($usesBrowser && $pushConfigured)
                <section id="device-panel" class="rounded-2xl border border-gray-200 bg-white p-5 text-center sm:p-6">
                    <p class="text-sm text-gray-500" data-state="loading">{{ __('Controllo questo dispositivo…') }}</p>

                    <div data-state="inactive" class="hidden space-y-4">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-600 text-white">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">{{ __('Vuoi gli avvisi sul browser?') }}</h3>
                        <p class="text-gray-700">{{ __('Ti avvisiamo su questo dispositivo per: :label.', ['label' => $choiceEvents->where('channel', 'browser')->pluck('label')->map(fn ($label) => mb_strtolower(str_replace(' (venditore)', '', $label)))->implode(', ')]) }}</p>
                        <p class="rounded-xl border border-gray-200 p-3 text-left text-sm text-gray-700">{{ __('Gli avvisi importanti arrivano sempre per email. Se una notifica non ti arriva, ti scriviamo per email. Puoi cambiare idea quando vuoi.') }}</p>
                        <button type="button" data-action="enable" class="btn-primary min-h-12 w-full text-base">{{ __('Attiva le notifiche') }}</button>
                        <p data-error class="hidden text-sm font-semibold text-[#8a2a2a]"></p>
                    </div>

                    <div data-state="active" class="hidden">
                        <p class="font-semibold text-[#14532d]">{{ __('✓ Le notifiche sono attive su questo dispositivo.') }}</p>
                    </div>

                    <div data-state="denied" class="hidden space-y-2 text-left">
                        <h3 class="text-lg font-bold text-gray-900">{{ __('Le notifiche sono bloccate su questo browser') }}</h3>
                        <ol class="list-inside list-decimal space-y-1 text-gray-700">
                            <li>{{ __('Tocca il lucchetto accanto all’indirizzo del sito.') }}</li>
                            <li>{{ __('Alla voce «Notifiche» scegli «Consenti».') }}</li>
                            <li>{{ __('Ricarica la pagina.') }}</li>
                        </ol>
                        <p class="text-sm text-gray-500">{{ __('Nel frattempo ti scriviamo per email.') }}</p>
                    </div>

                    <div data-state="ios-install" class="hidden space-y-4">
                        <img src="{{ asset('icons/icon-192.png') }}" alt="" class="mx-auto h-16 w-16 rounded-2xl">
                        <h3 class="text-xl font-bold text-gray-900">{{ __('Aggiungi il sito alla schermata Home') }}</h3>
                        <p class="text-gray-700">{{ __('Su iPhone gli avvisi funzionano solo dall’icona del sito sulla Home. Sono 3 passi e ci vuole meno di un minuto.') }}</p>
                        <ol class="space-y-3 rounded-2xl border border-gray-200 p-4 text-left text-gray-800">
                            <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 font-bold text-white">1</span> {{ __('Tocca il pulsante Condividi') }}</li>
                            <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 font-bold text-white">2</span> {{ __('Scegli «Aggiungi alla schermata Home»') }}</li>
                            <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 font-bold text-white">3</span> {{ __('Apri il sito dalla nuova icona e tocca «Attiva le notifiche»') }}</li>
                        </ol>
                    </div>

                    <div data-state="unsupported" class="hidden">
                        <p class="text-gray-700">{{ __('Questo browser non supporta le notifiche. Ti scriviamo per email.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('account.notifications.update') }}" data-email-only class="mt-3 hidden">
                        @csrf
                        @method('PUT')
                        <button class="btn-secondary min-h-12 w-full text-base">{{ __('Preferisco l’email') }}</button>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Gli avvisi importanti arrivano comunque per email.') }}</p>
                    </form>

                    {{-- Da computer: QR per proseguire dal telefono --}}
                    <div class="mt-5 hidden items-center gap-4 rounded-xl bg-canvas p-4 text-left md:flex">
                        <div class="h-24 w-24 shrink-0 rounded-lg bg-white p-1">{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(88)->margin(0)->generate(route('account.notifications.edit')) !!}</div>
                        <p class="text-sm text-gray-700">{{ __('Vuoi gli avvisi anche sul telefono? Inquadra il QR e attiva le notifiche da lì.') }}</p>
                    </div>
                </section>
            @endif

            @if ($devices->isNotEmpty())
                <section class="space-y-3">
                    <h3 class="text-lg font-bold text-gray-900">{{ __('I tuoi dispositivi') }}</h3>
                    <ul class="divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white px-5">
                        @foreach ($devices as $device)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="text-gray-900">{{ $device->device ?? __('Dispositivo') }}</span>
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-[#dff0e6] text-[#14532d]' => $device->is_active,
                                    'bg-[#fbe3e3] text-[#8a2a2a]' => ! $device->is_active,
                                ])>{{ $device->is_active ? __('Attivo') : __('Disattivato') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    @if ($usesBrowser && $pushConfigured)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var panel = document.getElementById('device-panel');
                var emailOnly = panel.querySelector('[data-email-only]');

                function show(state) {
                    panel.querySelectorAll('[data-state]').forEach(function (element) {
                        element.classList.toggle('hidden', element.dataset.state !== state);
                    });
                    emailOnly.classList.toggle('hidden', ['inactive', 'ios-install', 'denied'].indexOf(state) === -1);
                }

                if (!window.VetrinaPush) {
                    show('unsupported');
                    return;
                }

                window.VetrinaPush.deviceState().then(show).catch(function () { show('unsupported'); });

                panel.querySelector('[data-action="enable"]').addEventListener('click', function (event) {
                    var button = event.currentTarget;
                    var error = panel.querySelector('[data-error]');
                    button.disabled = true;
                    error.classList.add('hidden');

                    window.VetrinaPush.enable()
                        .then(function () { window.location.reload(); })
                        .catch(function (reason) {
                            button.disabled = false;
                            if (reason && reason.message === 'denied') {
                                show('denied');
                                return;
                            }
                            error.textContent = @js(__('Non è stato possibile attivare le notifiche. Riprova.'));
                            error.classList.remove('hidden');
                        });
                });
            });
        </script>
    @endif
</x-app-layout>
