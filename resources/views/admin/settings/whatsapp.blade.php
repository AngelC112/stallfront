@extends('layouts.admin')

@section('page-title', __('Collegamento WhatsApp Business'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <a href="{{ route('admin.settings.edit') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-900">{{ __('← Impostazioni del portale') }}</a>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border p-4 text-sm {{ $active ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            @if ($active)
                <strong>{{ __('Collegamento attivo.') }}</strong> {{ __('La verifica del cellulare usa la WhatsApp Cloud API.') }}
            @else
                <strong>{{ __('Modalità prova.') }}</strong> {{ __('Senza Token e Phone Number ID i messaggi non vengono inviati davvero e la verifica del cellulare si simula.') }}
            @endif
            @if ($fromEnv)
                <p class="mt-1">{{ __('Alcuni valori arrivano anche dalla configurazione del server; quelli salvati qui hanno la precedenza.') }}</p>
            @endif
        </div>

        <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <h2 class="mb-2 text-lg font-bold text-gray-900">{{ __('Webhook da inserire su Meta') }}</h2>
            <p class="text-sm text-gray-600">{{ __('In Meta for Developers, nella sezione WhatsApp → Configurazione, imposta come URL di callback:') }}</p>
            <p class="mt-2 break-all rounded-lg bg-gray-100 p-3 font-mono text-sm">{{ $webhookUrl }}</p>
            <p class="mt-2 text-sm text-gray-600">{{ __('Come token di verifica usa lo stesso valore scritto qui sotto in «Token di verifica del webhook».') }}</p>
        </section>

        <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}" class="space-y-5 rounded-xl bg-white p-6 border border-gray-200 sm:p-8" autocomplete="off">
            @csrf
            @method('PUT')

            <div>
                <label for="business_number" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Numero WhatsApp del negozio') }}</label>
                <input id="business_number" name="business_number" type="text" inputmode="numeric" value="{{ old('business_number', $values['business_number']) }}" placeholder="{{ __('39333xxxxxxx') }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">{{ __('Con prefisso internazionale, senza + né spazi.') }}</p>
            </div>

            <div>
                <label for="phone_number_id" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Phone Number ID') }}</label>
                <input id="phone_number_id" name="phone_number_id" type="text" inputmode="numeric" value="{{ old('phone_number_id', $values['phone_number_id']) }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
            </div>

            <div>
                <label for="token" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Token di accesso permanente') }}</label>
                <input id="token" name="token" type="password" value="" autocomplete="new-password" placeholder="{{ $hasToken ? __('•••••••• salvato (lascia vuoto per mantenerlo)') : __('Incolla il token') }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                @if ($hasToken)
                    <label class="mt-2 flex min-h-11 items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="clear_token" value="1" class="h-5 w-5 rounded border-gray-300"> {{ __('Rimuovi il token salvato') }}</label>
                @endif
            </div>

            <div>
                <label for="app_secret" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('App Secret') }}</label>
                <input id="app_secret" name="app_secret" type="password" value="" autocomplete="new-password" placeholder="{{ $hasAppSecret ? __('•••••••• salvato (lascia vuoto per mantenerlo)') : __('Incolla l\'App Secret') }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                @if ($hasAppSecret)
                    <label class="mt-2 flex min-h-11 items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="clear_app_secret" value="1" class="h-5 w-5 rounded border-gray-300"> {{ __('Rimuovi l\'App Secret salvato') }}</label>
                @endif
                <p class="mt-1 text-xs text-gray-500">{{ __('Serve a verificare che i messaggi in arrivo vengano davvero da Meta.') }}</p>
            </div>

            <div>
                <label for="verify_token" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Token di verifica del webhook') }}</label>
                <input id="verify_token" name="verify_token" type="text" maxlength="100" value="{{ old('verify_token', $values['verify_token']) }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">{{ __('Una parola d\'ordine a tua scelta: la stessa che scrivi su Meta.') }}</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Salva') }}</button>
            </div>
        </form>
    </div>
@endsection
