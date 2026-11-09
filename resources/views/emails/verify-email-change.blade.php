<x-mail::message>
# {{ __('Ciao :name,', ['name' => $name]) }}

{{ __('abbiamo ricevuto una richiesta di modifica dell\'email del tuo account su :store verso questo indirizzo. Confermalo per completare la modifica.', ['store' => $store['name']]) }}

{{ __('Il vecchio indirizzo (:email) può revocare la modifica entro :hours ore.', ['email' => $oldEmail, 'hours' => $revokeHours]) }}

<x-mail::button :url="$url">
{{ __('Conferma email') }}
</x-mail::button>

{{ __('Il link scade tra :hours ore. Se non hai richiesto tu questa modifica, ignora questo messaggio.', ['hours' => $hours]) }}

@include('emails._store-footer')
</x-mail::message>
