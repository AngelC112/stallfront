@props(['step' => 1])
@php($steps = [1 => __('Riepilogo'), 2 => __('Dove e quando'), 3 => __('Conferma')])
<header class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="min-w-0 truncate text-xl font-bold uppercase text-brand-600 sm:text-2xl">{{ \App\Models\Setting::get('store.name', config('app.name', 'Vetrina')) }}</a>
        <ol class="ml-auto hidden items-center gap-2 sm:flex lg:mx-auto" aria-label="{{ __('Passaggi dell’ordine') }}">
            @foreach ($steps as $number => $label)
                <li @class([
                    'rounded-full px-3 py-1 text-sm font-semibold',
                    'bg-brand-100 text-brand-700' => $number < $step,
                    'bg-brand-600 text-white' => $number === $step,
                    'bg-gray-100 text-gray-700' => $number > $step,
                ]) @if ($number === $step) aria-current="step" @endif>{{ $number }} {{ $label }}</li>
            @endforeach
        </ol>
    </div>
    {{-- Su telefono: barra di avanzamento come nel mockup --}}
    <div class="mx-auto max-w-7xl px-4 pb-3 sm:hidden">
        <div class="grid grid-cols-3 gap-2" aria-hidden="true">
            @foreach ($steps as $number => $label)
                <span @class(['h-1.5 rounded-full', 'bg-brand-600' => $number <= $step, 'bg-brand-200' => $number > $step])></span>
            @endforeach
        </div>
        <p class="mt-2 text-sm text-gray-500">{{ __('Passo :step di 3 ·', ['step' => $step]) }} {{ $steps[$step] }}</p>
    </div>
</header>
