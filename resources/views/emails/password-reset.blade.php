<x-mail::message>
# {{ __('Ciao :name,', ['name' => $name]) }}

{{ __('abbiamo ricevuto una richiesta per cambiare la password del tuo account su :store.', ['store' => $store['name']]) }}

<x-mail::button :url="$url">
{{ __('Scegli una nuova password') }}
</x-mail::button>

{{ __('Il link scade tra :hours ore. Se non sei stato tu, ignora questo messaggio: la password attuale resta valida.', ['hours' => $hours]) }}

@include('emails._store-footer')
</x-mail::message>
