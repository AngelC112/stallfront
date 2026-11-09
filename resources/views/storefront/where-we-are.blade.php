@extends('legal.layout')

@section('title', __('Dove siamo'))

@section('content')
    <h1>{{ __('Dove siamo') }}</h1>
    <p>{{ __('I mercati in cui puoi ritirare i tuoi ordini, con le prossime date.') }}</p>

    @forelse ($markets as $entry)
        @php($market = $entry['market'])
        <section class="rounded-xl border border-gray-200 p-4">
            <h2 class="!mt-0">{{ $market->name }}</h2>
            <p class="text-gray-700">{{ $market->weekdayLabel() }} · {{ $market->location }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ __('Ordina entro le :order_cutoff_time (anticipo :minimum_order_days giorni) · ritiro entro le', ['order_cutoff_time' => substr($market->order_cutoff_time, 0, 5), 'minimum_order_days' => $market->minimum_order_days]) }} {{ substr($market->pickup_cutoff_time, 0, 5) }}</p>
            <p class="mt-2"><a href="{{ $entry['directions_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-semibold text-brand-600 underline">{{ __('Indicazioni stradali') }}</a></p>

            <ul class="mt-2 divide-y divide-gray-100 text-sm !list-none !pl-0">
                @foreach ($entry['dates'] as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span class="font-medium text-gray-900">{{ $item['date']->translatedFormat('l d F Y') }}</span>
                        @if ($item['closed_reason'])
                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">{{ __('Chiuso ·') }} {{ $item['closed_reason'] }}</span>
                        @else
                            <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">{{ __('Aperto') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>{{ __('Al momento non ci sono mercati attivi.') }}</p>
    @endforelse
@endsection
