<x-guest-layout>
    <div class="text-center">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('Link scaduto o già usato') }}</h1>
    </div>

    <p class="mt-4 text-sm text-gray-600">
        {{ __('Per sicurezza il link di conferma vale poche ore e una sola volta. Te ne mandiamo uno nuovo.') }}
    </p>

    <div class="mt-6 flex flex-col gap-3">
        @auth
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-primary-button class="w-full justify-center">
                    {{ __('Invia un nuovo link') }}
                </x-primary-button>
            </form>
        @else
            <a href="{{ route('login') }}"
               class="inline-flex items-center justify-center rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                {{ __('Accedi per ricevere un nuovo link') }}
            </a>
        @endauth

        <a href="{{ route('login') }}" class="text-center text-sm text-gray-600 underline hover:text-gray-900">
            {{ __('Torna all\'accesso') }}
        </a>
    </div>
</x-guest-layout>