<x-guest-layout>
    <style>[x-cloak] { display: none !important; }</style>

    <div class="text-center">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('Controlla la tua email') }}</h1>
    </div>

    <p class="mt-4 text-sm text-gray-600">
        {{ __('Abbiamo inviato un link a') }}
        <span class="font-medium text-gray-900">{{ auth()->user()->email }}</span>{{ __('. Aprilo per confermare l\'account.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ __('Ti abbiamo inviato un nuovo link di conferma.') }}
        </div>
    @endif

    <div class="mt-6 flex flex-col gap-3">
        <x-mailbox-button :email="auth()->user()->email" />

        <form method="POST" action="{{ route('verification.send') }}"
              x-data="{ left: 60 }"
              x-init="setInterval(() => left = left > 0 ? left - 1 : 0, 1000)">
            @csrf
            <button type="submit"
                    x-bind:disabled="left > 0"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60">
                <span x-text="left > 0 ? @js(__('Invia di nuovo tra :seconds secondi')).replace(':seconds', left) : @js(__('Invia di nuovo il link'))"></span>
            </button>
        </form>

        <div x-data="{ open: false }">
            <button type="button"
                    @click="open = !open"
                    class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Cambia indirizzo email') }}
            </button>

            <form x-show="open" x-cloak method="POST" action="{{ route('verification.update') }}"
                  class="mt-3 flex items-start gap-2">
                @csrf
                <div class="flex-1">
                    <x-text-input name="email" type="email" required
                                  :value="old('email', auth()->user()->email)"
                                  class="block w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <x-primary-button>{{ __('Salva') }}</x-primary-button>
            </form>
        </div>
    </div>

    <p class="mt-6 text-sm text-gray-500">
        {{ __('Finché non confermi l\'email non puoi ordinare. Non la trovi? Controlla anche nella cartella spam.') }}
    </p>

    <div class="mt-6 flex items-center justify-between">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Esci') }}
            </button>
        </form>
    </div>
</x-guest-layout>