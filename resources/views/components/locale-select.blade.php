@props(['value' => null, 'name' => 'locale'])
<div {{ $attributes }}>
    <x-input-label :for="$name" :value="__('Lingua')" />
    <select id="{{ $name }}" name="{{ $name }}" required
            class="mt-1 block min-h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-base">
        @foreach (\App\Support\Locales::all() as $code => $label)
            <option value="{{ $code }}" @selected($code === ($value ?: app()->getLocale()))>{{ $label }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-sm text-gray-500">{{ __('La useremo per il sito, le email e le notifiche.') }}</p>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
