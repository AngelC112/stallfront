<x-mail::message>
# {{ __('Ciao :name,', ['name' => $user->fullName()]) }}

{{ __('la tua password è stata modificata correttamente e le altre sessioni aperte sono state chiuse.') }}

{{ __('Se non sei stato tu, reimposta subito la password e contatta il negozio.') }}
</x-mail::message>
