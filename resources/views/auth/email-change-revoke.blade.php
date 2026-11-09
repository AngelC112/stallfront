<x-guest-layout>
    @if ($state === 'pending')
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Revoca la modifica dell\'email') }}</h1>
        </div>

        <p class="mt-4 text-sm text-gray-600">
            {{ __('È in corso una modifica dell\'email dell\'account: da') }} <span class="font-medium text-gray-900">{{ $change->old_email }}</span>
            a <span class="font-medium text-gray-900">{{ $change->new_email }}</span>.
        </p>

        <p class="mt-2 text-sm text-gray-600">
            {{ __('Se non l\'hai richiesta tu, revovala ora: l\'account tornerà al vecchio indirizzo e il nuovo non verrà attivato.') }}
        </p>

        <form method="POST" action="{{ route('email-change.revoke', ['token' => $token]) }}" class="mt-6">
            @csrf
            <x-primary-button class="w-full justify-center">
                {{ __('Revoca la modifica') }}
            </x-primary-button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-500">
            {{ __('Se sei stato tu, non fare nulla: questo link scade il :expires_at.', ['expires_at' => $change->expires_at->format('d/m/Y \a\l\l\e H:i')]) }}
        </p>
    @elseif ($state === 'revoked')
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Modifica revocata') }}</h1>
        </div>
        <p class="mt-4 text-sm text-gray-600">
            {{ __('L\'account continua a usare') }} <span class="font-medium text-gray-900">{{ $change->old_email }}</span>.
        </p>
        <div class="mt-6">
            <a href="{{ route('login') }}"
               class="inline-flex w-full items-center justify-center rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                {{ __('Torna all\'accesso') }}
            </a>
        </div>
    @elseif ($state === 'completed')
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Modifica già completata') }}</h1>
        </div>
        <p class="mt-4 text-sm text-gray-600">
            {{ __('Il nuovo indirizzo è già stato confermato, quindi questa modifica non può più essere revocata da qui. Se non la riconosci, contatta subito il negozio.') }}
        </p>
        <div class="mt-6">
            <a href="{{ route('login') }}"
               class="inline-flex w-full items-center justify-center rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                {{ __('Torna all\'accesso') }}
            </a>
        </div>
    @elseif ($state === 'expired')
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Link scaduto') }}</h1>
        </div>
        <p class="mt-4 text-sm text-gray-600">
            {{ __('Il tempo utile per revocare questa modifica è terminato. Se non riconosci la modifica, contatta subito il negozio.') }}
        </p>
        <div class="mt-6">
            <a href="{{ route('login') }}"
               class="inline-flex w-full items-center justify-center rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                {{ __('Torna all\'accesso') }}
            </a>
        </div>
    @else
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Link non valido') }}</h1>
        </div>
        <p class="mt-4 text-sm text-gray-600">
            {{ __('Questo collegamento non corrisponde a nessuna modifica in corso.') }}
        </p>
        <div class="mt-6">
            <a href="{{ route('login') }}"
               class="inline-flex w-full items-center justify-center rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                {{ __('Torna all\'accesso') }}
            </a>
        </div>
    @endif
</x-guest-layout>