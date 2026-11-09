@props(['compact' => false])
@php($current = app()->getLocale())
<form method="POST" action="{{ route('locale.update') }}" {{ $attributes->merge(['class' => 'inline-flex items-center']) }}>
    @csrf
    <label for="locale-switcher-{{ $compact ? 'c' : 'f' }}" class="sr-only">{{ __('Lingua') }}</label>
    <select id="locale-switcher-{{ $compact ? 'c' : 'f' }}" name="locale" onchange="this.form.submit()"
            class="min-h-11 rounded-xl border border-gray-300 bg-white py-1 pl-3 pr-8 text-sm text-gray-800">
        @foreach (\App\Support\Locales::all() as $code => $label)
            <option value="{{ $code }}" @selected($code === $current)>{{ $compact ? strtoupper($code) : $label }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="chip ml-2">{{ __('Cambia') }}</button></noscript>
</form>
