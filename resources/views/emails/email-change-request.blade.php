<x-mail::message>
# {{ __('Ciao :name,', ['name' => $name]) }}

{{ __('abbiamo ricevuto una richiesta di modifica dell\'email del tuo account su :store: da :old a :new.', ['store' => $store['name'], 'old' => $oldEmail, 'new' => $newEmail]) }}

{{ __('Se sei stato tu, non devi fare nulla ora: il nuovo indirizzo dovrà essere confermato.') }}

{{ __('Se non sei stato tu, revoca la modifica entro :hours ore:', ['hours' => $hours]) }}

<x-mail::button :url="$revokeUrl">
{{ __('Revoca la modifica') }}
</x-mail::button>

{{ __('Passate le :hours ore, oppure una volta confermato il nuovo indirizzo, questa modifica non potrà più essere revocata da questo link.', ['hours' => $hours]) }}

@include('emails._store-footer')
</x-mail::message>
