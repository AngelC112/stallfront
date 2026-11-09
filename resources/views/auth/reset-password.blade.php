<x-guest-layout>
    <div class="text-center">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('Scegli una nuova password') }}</h1>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Usa almeno :min_password caratteri, con minuscole, maiuscole, numeri e simboli.', ['min_password' => $minPassword]) }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.store') }}"
          class="mt-6 space-y-4"
          x-data="{ show: false, pwd: '', min: {{ $minPassword }} }">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}" />

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email"
                          class="mt-1 block w-full"
                          :value="old('email', $request->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Nuova password')" />
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

        <div>
            <x-input-label for="password_confirmation" :value="__('Ripeti la password')" />
            <div class="relative mt-1">
                <x-text-input id="password_confirmation" name="password_confirmation"
                              x-bind:type="show ? 'text' : 'password'"
                              class="block w-full pr-24"
                              required autocomplete="new-password" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end">
            <x-primary-button>{{ __('Salva la nuova password') }}</x-primary-button>
        </div>

        <p class="text-sm text-gray-500">
            {{ __('Dopo il salvataggio ti inviamo una email di avviso e le altre sessioni vengono chiuse.') }}
        </p>
    </form>
</x-guest-layout>