@extends('layouts.admin')

@section('page-title', __('Notifiche ai clienti'))

@section('content')
    <form method="POST" action="{{ route('admin.notifications.importance') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Notifiche ai clienti') }}</h1>
            <div class="hidden gap-3 sm:flex">
                <a href="{{ route('admin.notifications.texts') }}" class="btn-secondary">{{ __('Modifica i testi dei messaggi') }}</a>
                <button type="submit" class="btn-primary px-8">{{ __('Salva') }}</button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] p-3 text-sm font-semibold text-[#14532d]" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-[#8a2a2a] bg-[#fbe3e3] p-3 text-sm font-semibold text-[#8a2a2a]" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                <p class="text-sm text-gray-600">{{ __('Per ogni evento scegli se arriva sempre per email o se il cliente può scegliere tra email e browser.') }}</p>

                <ul class="mt-3 divide-y divide-gray-200">
                    {{-- La conferma d'ordine (con il QR) parte sempre per email: non è modificabile. --}}
                    <li class="flex items-center justify-between gap-3 py-3">
                        <span class="text-gray-900">{{ __('Ordine ricevuto') }}</span>
                        <span class="segmented shrink-0 rounded-full opacity-70" title="{{ __('La conferma d’ordine con il QR è sempre inviata per email') }}">
                            <span class="segmented-item segmented-item-active rounded-full font-bold">{{ __('Sempre email') }}</span>
                            <span class="segmented-item rounded-full"><span class="sm:hidden">{{ __('A scelta') }}</span><span class="hidden sm:inline">{{ __('A scelta del cliente') }}</span></span>
                        </span>
                    </li>
                    @foreach ($customerEvents as $event => $definition)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <span @class(['text-gray-900', 'text-gray-400 line-through' => ! $definition['is_active']])>{{ $definition['label'] }}</span>
                            <fieldset class="segmented shrink-0 rounded-full">
                                <legend class="sr-only">{{ __('Importanza di') }} {{ $definition['label'] }}</legend>
                                @foreach (['always_email' => [__('Sempre email'), __('Sempre email')], 'customer_choice' => [__('A scelta'), __('A scelta del cliente')]] as $value => [$short, $long])
                                    <label class="segmented-item cursor-pointer rounded-full has-[:checked]:bg-brand-600 has-[:checked]:font-bold has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500">
                                        <input type="radio" name="importance[{{ $event }}]" value="{{ $value }}" class="sr-only" @checked(old("importance.$event", $definition['importance']) === $value)>
                                        <span class="sm:hidden">{{ $short }}</span><span class="hidden sm:inline">{{ $long }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        </li>
                    @endforeach
                </ul>
            </section>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="text-lg font-bold text-gray-900">{{ __('Avvisi a te') }}</h2>
                    <ul class="mt-2 divide-y divide-gray-200">
                        @foreach ($sellerEvents as $definition)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span @class(['text-gray-900', 'text-gray-400 line-through' => ! $definition['is_active']])>{{ str_replace(' (venditore)', '', $definition['label']) }}</span>
                                <span class="shrink-0 rounded-full bg-[#dff0e6] px-3 py-1 text-xs font-bold text-[#14532d]">{{ $definition['uses_browser'] ? __('Email + Browser') : __('Email') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('account.notifications.edit') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-brand-600 hover:underline">{{ __('Ricevi gli avvisi su questo dispositivo ›') }}</a>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 text-sm">
                    <h2 class="font-bold text-gray-900">{{ __('Notifiche del browser') }}</h2>
                    <p class="mt-1 text-gray-800">{{ __('Se nessun dispositivo conferma la ricezione entro :ack_minutes minuti, il sito invia la stessa notifica per email.', ['ack_minutes' => $ackMinutes]) }}</p>
                    <p class="mt-1 text-gray-500">{{ __('Le email di sicurezza (conferma email, recupero password) sono sempre inviate.') }}</p>
                </section>
            </aside>
        </div>

        {{-- Su telefono i pulsanti stanno in fondo, come nel mockup --}}
        <div class="space-y-3 sm:hidden">
            <a href="{{ route('admin.notifications.texts') }}" class="btn-secondary min-h-12 w-full text-base">{{ __('Modifica i testi dei messaggi') }}</a>
            <button type="submit" class="btn-primary min-h-12 w-full text-base">{{ __('Salva') }}</button>
        </div>
    </form>
@endsection
