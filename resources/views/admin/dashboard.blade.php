@extends('layouts.admin')

@section('page-title', __('Dashboard'))

@php
    $euro = fn (int $cents): string => \App\Support\Format::money($cents);
    $cards = [
        ['label' => __('Ordini da evadere'), 'value' => $stats['toFulfill'], 'href' => route('admin.orders.index'), 'cta' => __('Vai agli ordini'), 'tone' => 'text-gray-900'],
        ['label' => __('Pronti per il ritiro'), 'value' => $stats['ready'], 'href' => route('admin.orders.index', ['status' => 'ready']), 'cta' => __('Vedi gli evasi'), 'tone' => 'text-green-700'],
        ['label' => __('Incassi previsti'), 'value' => $euro($stats['expectedRevenueCents']), 'href' => route('admin.orders.index'), 'cta' => __('Su ordini aperti'), 'tone' => 'text-gray-900'],
        ['label' => __('Incassato oggi'), 'value' => $euro($stats['collectedTodayCents']), 'href' => route('admin.orders.index', ['status' => 'all']), 'cta' => __('Vedi tutti'), 'tone' => 'text-green-700'],
        ['label' => __('Non ritirati'), 'value' => $stats['noShows'], 'href' => route('admin.orders.index', ['status' => 'not_collected']), 'cta' => __('Gestisci'), 'tone' => 'text-red-700'],
        ['label' => __('Clienti con mancati ritiri'), 'value' => $stats['customersWithNoShows'], 'href' => null, 'cta' => null, 'tone' => 'text-amber-700'],
        ['label' => __('Clienti bloccati'), 'value' => $stats['blockedCustomers'], 'href' => null, 'cta' => null, 'tone' => 'text-red-700'],
        ['label' => __('Clienti ricorrenti'), 'value' => $stats['returningCustomers']['count'], 'href' => null, 'cta' => null, 'tone' => 'text-gray-900'],
    ];
@endphp

@section('content')
<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($cards as $card)
        <div class="overflow-hidden rounded-xl bg-white shadow">
            <div class="p-5">
                <p class="truncate text-sm font-medium text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-bold {{ $card['tone'] }}">{{ $card['value'] }}</p>
            </div>
            @if ($card['href'])
                <div class="bg-gray-50 px-5 py-3 text-sm">
                    <a href="{{ $card['href'] }}" class="inline-flex min-h-11 items-center font-medium text-brand-700 hover:text-brand-900">{{ $card['cta'] }} →</a>
                </div>
            @endif
        </div>
    @endforeach
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold text-gray-900">{{ __('Prodotti in esaurimento') }}</h3>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @forelse ($stats['lowStock'] as $product)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="{{ route('admin.products.edit', $product) }}" class="min-w-0 truncate font-medium text-gray-900 hover:text-brand-700">{{ $product->name }}</a>
                    <span class="shrink-0 rounded bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $product->available_stock }} {{ __('pz') }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('Nessun prodotto sotto soglia.') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold text-gray-900">{{ __('Più venduti (ultimi :best_sellers_days giorni)', ['best_sellers_days' => $stats['bestSellersDays']]) }}</h3>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @forelse ($stats['bestSellers'] as $row)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="min-w-0 truncate text-gray-900">{{ $row->product_name }}</span>
                    <span class="shrink-0 font-bold text-gray-900">{{ $row->quantity }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('Ancora nessuna vendita.') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold text-gray-900">{{ __('Clienti ricorrenti') }}</h3>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @forelse ($stats['returningCustomers']['top'] as $row)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="min-w-0 truncate text-gray-900">{{ $row->customer_name }}</span>
                    <span class="shrink-0 font-bold text-gray-900">{{ $row->orders }} {{ __('ordini') }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('Nessun cliente ha ancora ordinato più volte.') }}</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
