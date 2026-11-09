@props(['min' => 8])

<div {{ $attributes->merge(['class' => 'mt-2 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm']) }}>
    <p class="font-medium text-gray-700">{{ __('Criteri obbligatori della password') }}</p>
    <ul class="mt-1 space-y-1 text-gray-600">
        <li x-bind:class="pwd.length >= {{ $min }} ? 'text-green-700' : ''">
            <span x-text="pwd.length >= {{ $min }} ? '✓' : '–'"></span>
            {{ __('Almeno :min caratteri', ['min' => $min]) }}
        </li>
        <li x-bind:class="/[a-z]/.test(pwd) ? 'text-green-700' : ''">
            <span x-text="/[a-z]/.test(pwd) ? '✓' : '–'"></span>
            {{ __('Almeno una lettera minuscola') }}
        </li>
        <li x-bind:class="/[A-Z]/.test(pwd) ? 'text-green-700' : ''">
            <span x-text="/[A-Z]/.test(pwd) ? '✓' : '–'"></span>
            {{ __('Almeno una lettera maiuscola') }}
        </li>
        <li x-bind:class="/[0-9]/.test(pwd) ? 'text-green-700' : ''">
            <span x-text="/[0-9]/.test(pwd) ? '✓' : '–'"></span>
            {{ __('Almeno un numero') }}
        </li>
        <li x-bind:class="/[^A-Za-z0-9]/.test(pwd) ? 'text-green-700' : ''">
            <span x-text="/[^A-Za-z0-9]/.test(pwd) ? '✓' : '–'"></span>
            {{ __('Almeno un simbolo (es. ! ? # & €)') }}
        </li>
    </ul>
</div>