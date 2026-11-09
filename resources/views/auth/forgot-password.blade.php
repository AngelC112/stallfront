<x-guest-layout>
    <style>[x-cloak] { display: none !important; }</style>

    @if (session('status'))
        {{-- Link inviato: schermata di conferma --}}
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Controlla la tua email') }}</h1>
        </div>

        <p class="mt-4 text-sm text-gray-600">
            {{ __('Se esiste un account con') }}
            <span class="font-medium text-gray-900">{{ session('email_sent_to') }}</span>{{ __(', troverai un link per scegliere una nuova password. Il link scade tra :value ore.', ['value' => (int) \App\Models\Setting::get('security.password_reset_ttl_hours', 2)]) }}
        </p>

        <div class="mt-6 flex flex-col gap-3">
            <x-mailbox-button :email="session('email_sent_to')" />

            <form method="POST" action="{{ route('password.email') }}"
                  x-data="{ left: 60, email: '{{ session('email_sent_to') }}' }"
                  x-init="setInterval(() => left = left > 0 ? left - 1 : 0, 1000)">
                @csrf
                <x-antibot />
                <input type="hidden" name="email" x-bind:value="email" />
                <button type="submit"
                        x-bind:disabled="left > 0"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-text="left > 0 ? @js(__('Invia di nuovo tra :seconds secondi')).replace(':seconds', left) : @js(__('Invia di nuovo il link'))"></span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-500">
                {{ __('Non la trovi? Controlla anche nella cartella spam.') }}
            </p>

            <a href="{{ route('login') }}" class="text-center text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Torna all\'accesso') }}
            </a>
        </div>
    @else
        {{-- Modulo richiesta link --}}
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Password dimenticata') }}</h1>
        </div>

        <p class="mt-4 text-sm text-gray-600">
            {{ __('Inserisci la tua email: ti mandiamo un link per scegliere una nuova password.') }}
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <x-antibot />

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email"
                              class="mt-1 block w-full"
                              :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                    {{ __('Torna all\'accesso') }}
                </a>
                <x-primary-button>{{ __('Invia il link') }}</x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>