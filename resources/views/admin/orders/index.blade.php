@extends('layouts.admin')

@section('page-title', __('Ordini da evadere'))

@section('content')
    <div class="space-y-5">
        @include('admin.partials.block-notices-strip')

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        {{-- Interruttore vista e filtri --}}
        @php
            $filterKeys = ['market_id', 'fulfillment', 'off_season'];
            $baseQuery = \Illuminate\Support\Arr::except(request()->query(), $filterKeys);
            $noFilter = ! request('market_id') && ! request('fulfillment') && ! request('off_season');
        @endphp
        <div class="space-y-3">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                <div class="segmented self-start">
                    <a href="{{ route('admin.orders.index', array_merge(request()->query(), ['view' => 'market'])) }}"
                       @class(['segmented-item', 'segmented-item-active' => $viewMode === 'market'])>{{ __('Per mercato e data') }}</a>
                    <a href="{{ route('admin.orders.index', array_merge(request()->query(), ['view' => 'product'])) }}"
                       @class(['segmented-item', 'segmented-item-active' => $viewMode === 'product'])>{{ __('Per prodotto') }}</a>
                </div>

                <nav aria-label="{{ __('Filtri') }}" class="flex gap-2 overflow-x-auto pb-1">
                    <a href="{{ route('admin.orders.index', $baseQuery) }}" @class(['chip shrink-0', 'chip-active' => $noFilter])>{{ __('Tutti') }}</a>
                    @foreach ($markets as $market)
                        <a href="{{ route('admin.orders.index', array_merge($baseQuery, ['market_id' => $market->id])) }}"
                           @class(['chip shrink-0', 'chip-active' => (string) request('market_id') === (string) $market->id])>{{ $market->name }}</a>
                    @endforeach
                    <a href="{{ route('admin.orders.index', array_merge($baseQuery, ['fulfillment' => 'delivery'])) }}"
                       @class(['chip shrink-0', 'chip-active' => request('fulfillment') === 'delivery'])>{{ __('Domicilio') }}</a>
                    <a href="{{ route('admin.orders.index', array_merge($baseQuery, ['off_season' => 1])) }}"
                       @class(['chip shrink-0', 'chip-active' => (bool) request('off_season')])>{{ __('Fuori stagione') }}{{ $futureOffSeason['count'] > 0 ? ' ('.$futureOffSeason['count'].')' : '' }}</a>
                    <a href="{{ route('admin.orders.print', request()->query()) }}" target="_blank" class="chip shrink-0">{{ __('Stampa PDF') }}</a>
                </nav>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                @foreach (request()->except('status', 'page') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <select name="status" aria-label="{{ __('Stato') }}" onchange="this.form.submit()" class="min-h-10 rounded-xl border border-gray-300 bg-white px-3 text-sm">
                    <option value="">{{ __('Ordini aperti') }}</option>
                    <option value="all" @selected(request('status') === 'all')>{{ __('Tutti gli stati') }}</option>
                    @foreach (['received' => __('Ricevuto'), 'preparing' => __('In preparazione'), 'ready' => __('Evaso'), 'out_for_delivery' => __('In consegna'), 'collected' => __('Ritirato'), 'delivered' => __('Consegnato'), 'not_collected' => __('Non ritirato'), 'not_delivered' => __('Non consegnato'), 'rescheduled' => __('Riprogrammato'), 'to_reschedule' => __('Da riprogrammare'), 'cancelled' => __('Annullato')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn-secondary min-h-10">{{ __('Filtra') }}</button></noscript>
            </form>
        </div>

        @if ($futureOffSeason['count'] > 0)
            <div class="rounded-xl border border-violet-300 bg-violet-50 p-3 text-sm text-violet-950">
                {{ trans_choice(':count ordine fuori stagione futuro: la prima preparazione inizia il :date.|:count ordini fuori stagione futuri: la prima preparazione inizia il :date.', $futureOffSeason['count'], ['date' => $futureOffSeason['firstDate']->isoFormat('L')]) }}
                <a href="{{ route('admin.orders.index', array_merge(request()->query(), ['off_season' => 1])) }}" class="font-semibold underline">{{ __('Mostra') }}</a>
            </div>
        @endif

        {{-- Cambio stato multiplo: le caselle delle schede puntano a questo modulo --}}
        <form id="bulk-form" method="POST" action="{{ route('admin.orders.bulk-status') }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 border border-gray-200">
            @csrf
            <span class="text-sm font-medium text-gray-700">{{ __('Ordini selezionati:') }}</span>
            <select name="status" required aria-label="{{ __('Nuovo stato') }}" class="min-h-11 rounded-xl border-gray-300 text-xs">
                <option value="">{{ __('Cambia stato in…') }}</option>
                <option value="preparing">{{ __('In preparazione') }}</option>
                <option value="ready">{{ __('Evaso') }}</option>
                <option value="collected">{{ __('Ritirato') }}</option>
                <option value="out_for_delivery">{{ __('In consegna') }}</option>
                <option value="delivered">{{ __('Consegnato') }}</option>
            </select>
            <button class="min-h-11 rounded-xl border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('Applica') }}</button>
        </form>

        <div class="md:grid md:grid-cols-[minmax(0,1fr)_24rem] md:items-start md:gap-5 xl:grid-cols-[minmax(0,1fr)_30rem]">
            <div class="space-y-8">
                @forelse ($groups as $group)
                    <section class="space-y-3">
                        <h3 class="flex items-center gap-2 px-1 text-sm font-bold uppercase tracking-wider text-gray-700">
                            <span class="h-2 w-2 rounded-full {{ $group['is_delivery'] ? 'bg-sky-500' : 'bg-brand-500' }}"></span>
                            {{ $group['title'] }}
                            @if ($group['date'])
                                <span class="font-normal normal-case text-gray-500">· {{ $group['date']->translatedFormat('l d F') }}</span>
                            @endif
                        </h3>

                        @if ($viewMode === 'market')
                            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                @foreach ($group['orders'] as $order)
                                    @include('admin.orders._card', ['order' => $order])
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-3">
                                @forelse ($group['lines'] as $line)
                                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                        <button type="button" class="flex min-h-11 w-full items-center justify-between gap-3 bg-gray-50 p-4 text-left" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                            <span class="flex items-center gap-3">
                                                <span class="flex h-8 min-w-8 items-center justify-center rounded bg-brand-100 px-2 text-xs font-bold text-brand-700">{{ $line['quantity'] }}</span>
                                                <span class="font-bold text-gray-900">{{ $line['name'] }} {{ $line['variant'] ? "({$line['variant']})" : '' }}</span>
                                            </span>
                                            <span class="text-xs font-semibold text-brand-600">{{ $line['orders']->count() }} {{ __('ordini') }}</span>
                                        </button>
                                        <div class="hidden space-y-1 p-4">
                                            @foreach ($line['orders'] as $lineOrder)
                                                <div class="flex items-center justify-between border-b border-gray-100 py-2 text-xs last:border-0">
                                                    <span class="font-medium text-gray-700">{{ $lineOrder->customer_name }} <span class="text-gray-400">#{{ $lineOrder->order_number }}</span></span>
                                                    <a href="{{ route('admin.orders.show', $lineOrder) }}" data-order-detail class="inline-flex min-h-11 items-center font-semibold text-brand-600 hover:underline">{{ __('Apri →') }}</a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">{{ __('Niente da preparare in questo gruppo.') }}</p>
                                @endforelse

                                @if ($group['fulfilled']->isNotEmpty())
                                    <div class="pt-2">
                                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">{{ __('Già evasi') }}</h4>
                                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                            @foreach ($group['fulfilled'] as $order)
                                                @include('admin.orders._card', ['order' => $order])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </section>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">{{ __('Non ci sono ordini da mostrare.') }}</div>
                @endforelse
            </div>

            {{-- Dettaglio affiancato (tablet e computer) --}}
            <aside id="order-detail-panel" class="sticky top-4 mt-5 hidden max-h-[calc(100vh-2rem)] overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-3 md:mt-0 md:block">
                <p class="p-4 text-center text-sm text-gray-500">{{ __('Tocca «Dettaglio» su un ordine per vederlo qui.') }}</p>
            </aside>
        </div>
    </div>

    <script>
        (function () {
            var panel = document.getElementById('order-detail-panel');
            var wide = window.matchMedia('(min-width: 768px)');

            document.addEventListener('click', function (event) {
                var link = event.target.closest('a[data-order-detail]');

                if (!link || !wide.matches) {
                    return;
                }

                event.preventDefault();
                panel.innerHTML = '<p class="p-4 text-center text-sm text-gray-500">' + @js(__('Caricamento…')) + '</p>';

                fetch(link.href + (link.href.indexOf('?') === -1 ? '?' : '&') + 'partial=1', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (response) { return response.ok ? response.text() : Promise.reject(); })
                    .then(function (html) { panel.innerHTML = html; })
                    .catch(function () { window.location.href = link.href; });
            });
        })();
    </script>
@endsection
