<x-mail::message>
# {{ __('Ciao,') }}

{{ __('la modifica dell\'email dell\'account su :store da :old a :new è stata revocata.', ['store' => $store['name'], 'old' => $oldEmail, 'new' => $newEmail]) }}

{{ __('L\'account continua a usare :email.', ['email' => $oldEmail]) }}

{{ __('Se non hai revocato tu la modifica, contatta subito il negozio.') }}

@include('emails._store-footer')
</x-mail::message>
