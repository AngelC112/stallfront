<x-guest-layout>
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ show: false }">
        @csrf
        <x-antibot />

        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Accedi') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('Entra per ordinare e vedere i tuoi ordini.') }}</p>
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email"
                          class="mt-1 block w-full"
                          :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1">
                <x-text-input id="password" name="password"
                              x-bind:type="show ? 'text' : 'password'"
                              class="block w-full pr-24"
                              required autocomplete="current-password" />
                <button type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-gray-600 hover:text-gray-900"
                        x-bind:aria-label="show ? @js(__('Nascondi password')) : @js(__('Mostra password'))">
                    <span x-text="show ? @js(__('Nascondi')) : @js(__('Mostra'))"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="flex items-center gap-2 text-sm text-gray-700">
                <input id="remember" name="remember" type="checkbox"
                       class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                {{ __('Ricordami') }}
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm text-gray-600 underline hover:text-gray-900">
                    {{ __('Password dimenticata?') }}
                </a>
            @endif
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('register') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Non hai un account?') }}
            </a>
            <x-primary-button>
                {{ __('Accedi') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>