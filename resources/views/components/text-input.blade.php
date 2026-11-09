@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-11 border border-gray-300 bg-white px-3 focus:border-brand-600 focus:ring-brand-600 rounded-xl']) }}>
