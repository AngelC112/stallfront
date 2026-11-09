<x-guest-layout>
    <form method="POST" action="{{ route('setup.store') }}" class="space-y-5">
        @csrf

        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Benvenuto') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('Crea l’account del venditore: potrà gestire prodotti, mercati, ordini e impostazioni.') }}</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                {{ __('Controlla i dati inseriti:') }}
            </div>
        @endif

        <div>
            <x-input-label for="store_name" :value="__('Nome del negozio')" />
            <x-text-input id="store_name" name="store_name" type="text" class="mt-1 block w-full"
                          :value="old('store_name')" required autofocus />
            <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="first_name" :value="__('Nome')" />
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
                              :value="old('first_name')" required autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="last_name" :value="__('Cognome')" />
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full"
                              :value="old('last_name')" required autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                          :value="old('email')" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-locale-select :value="old('locale', app()->getLocale())" />

        <div x-data="{ pwd: '' }">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                          x-model="pwd" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
            <x-password-criteria :min="$minPassword" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Conferma password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full"
                          required autocomplete="new-password" />
        </div>

        <x-primary-button class="w-full justify-center">{{ __('Crea l’account e apri il pannello') }}</x-primary-button>
    </form>
</x-guest-layout>
