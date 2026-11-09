@props(['current', 'url', 'customized' => []])
{{-- Language tabs for texts that the seller writes in each language. --}}
<nav aria-label="{{ __('Lingua del testo') }}" class="flex gap-2 overflow-x-auto pb-1">
    @foreach (\App\Support\Locales::all() as $code => $label)
        <a href="{{ $url($code) }}" @class(['chip shrink-0', 'chip-active' => $code === $current])>
            {{ $label }}@if (in_array($code, $customized, true)) <span aria-label="{{ __('personalizzato') }}">•</span>@endif
        </a>
    @endforeach
</nav>
