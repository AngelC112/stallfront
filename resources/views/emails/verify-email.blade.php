<x-mail::message>
# {{ __('Ciao :name,', ['name' => $name]) }}

{{ __('grazie per esserti registrato su :store. Conferma la tua email per attivare l\'account.', ['store' => $store['name']]) }}

<x-mail::button :url="$url">
{{ __('Conferma email') }}
</x-mail::button>

{{ __('Il link scade tra :hours ore. Se non sei stato tu, ignora questo messaggio.', ['hours' => $hours]) }}

@include('emails._store-footer')
</x-mail::message>
