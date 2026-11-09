<x-guest-layout>
    <div class="text-center">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('Email confermata') }}</h1>
    </div>

    <p class="mt-4 text-sm text-gray-600">
        {{ __('Ora puoi ordinare.') }}
    </p>

    {{-- Verifica del cellulare: proposta subito dopo la conferma dell'email, si può rimandare. --}}
    @if (auth()->user()->phone && ! auth()->user()->hasPhoneVerified())
        <div class="mt-6 space-y-3 rounded-2xl border border-gray-200 p-4">
            <p class="text-sm text-gray-700">{{ __('Vuoi verificare anche il cellulare') }} <strong>{{ auth()->user()->phone }}</strong>{{ __('? Serve per la consegna a domicilio e per essere contattato dal venditore. Basta un messaggio WhatsApp.') }}</p>
            <a href="{{ route('account.phone.verify') }}" class="btn-primary w-full">{{ __('Verifica con WhatsApp') }}</a>
        </div>
    @endif

    <div class="mt-4">
        <a href="{{ route('home') }}" class="btn-secondary w-full">
            {{ auth()->user()->phone && ! auth()->user()->hasPhoneVerified() ? __('Più tardi, vai alla vetrina') : __('Vai alla vetrina') }}
        </a>
    </div>
</x-guest-layout>