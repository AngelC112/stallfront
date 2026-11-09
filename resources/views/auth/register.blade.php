<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <x-antibot />

        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Crea il tuo account') }}</h1>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="first_name" :value="__('Nome')" />
                <x-text-input id="first_name" name="first_name" type="text"
                              class="mt-1 block w-full"
                              :value="old('first_name')" required autofocus autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="last_name" :value="__('Cognome')" />
                <x-text-input id="last_name" name="last_name" type="text"
                              class="mt-1 block w-full"
                              :value="old('last_name')" required autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email"
                          class="mt-1 block w-full"
                          :value="old('email')" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-locale-select :value="old('locale', app()->getLocale())" />

        <div>
            <x-input-label for="phone" :value="__('Cellulare (facoltativo)')" />
            <x-text-input id="phone" name="phone" type="tel"
                          class="mt-1 block w-full"
                          :value="old('phone')" autocomplete="tel" :placeholder="__('Es. 333 1234567')" />
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Serve per la consegna a domicilio e per essere contattato. Lo verifichiamo con un messaggio WhatsApp.') }}
            </p>
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        {{-- Password con mostra/nascondi e criteri obbligatori --}}
        <div x-data="{ show: false, pwd: '', min: {{ $minPassword }} }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1">
                <x-text-input id="password" name="password"
                              x-bind:type="show ? 'text' : 'password'"
                              class="block w-full pr-24"
                              x-model="pwd"
                              required autocomplete="new-password" />
                <button type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-gray-600 hover:text-gray-900"
                        x-bind:aria-label="show ? @js(__('Nascondi password')) : @js(__('Mostra password'))">
                    <span x-text="show ? @js(__('Nascondi')) : @js(__('Mostra'))"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />

            <x-password-criteria :min="$minPassword" />
        </div>

        {{-- Conferma password con mostra/nascondi --}}
        <div x-data="{ show: false }">
            <x-input-label for="password_confirmation" :value="__('Conferma password')" />
            <div class="relative mt-1">
                <x-text-input id="password_confirmation" name="password_confirmation"
                              x-bind:type="show ? 'text' : 'password'"
                              class="block w-full pr-24"
                              required autocomplete="new-password" />
                <button type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-gray-600 hover:text-gray-900"
                        x-bind:aria-label="show ? @js(__('Nascondi password')) : @js(__('Mostra password'))">
                    <span x-text="show ? @js(__('Nascondi')) : @js(__('Mostra'))"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-address-field :label="__('Indirizzo (facoltativo)')" />

        <div class="flex items-start gap-3">
            <input id="consent" name="consent" type="checkbox" value="1"
                   class="mt-1 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                   @checked(old('consent')) />
            <label for="consent" class="text-sm text-gray-700">
                {{ __('Ho letto la') }} <a href="{{ route('legal.privacy') }}" target="_blank" class="underline hover:text-gray-900">{{ __('Privacy Policy') }}</a>
                e i <a href="{{ route('legal.terms') }}" target="_blank" class="underline hover:text-gray-900">{{ __('Termini di vendita') }}</a>
            </label>
        </div>
        <x-input-error :messages="$errors->get('consent')" />

        <p class="text-sm text-gray-500">
            {{ __('Ti invieremo un link per confermare l\'email.') }}
        </p>

        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Hai già un account?') }}
            </a>
            <x-primary-button>
                {{ __('Registrati') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>