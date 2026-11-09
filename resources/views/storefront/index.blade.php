<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Models\Setting::get('store.name', config('app.name')) }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-pwa-head />
    </head>
    @php
        $money = fn (int $cents): string => \App\Support\Format::money($cents);
        $hasCart = $cart['count'] > 0;
    @endphp
    <body @class(['bg-canvas text-gray-900 antialiased', 'pb-28 lg:pb-0' => $hasCart])>
        <x-store-header :cart-count="$cartCount" :search="$search" active="home" :show-cart="false" />

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-8">
            <main class="space-y-5">
                @if (session('status'))
                    <p class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] px-4 py-3 text-sm font-semibold text-[#14532d]" role="status">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <p class="rounded-xl border border-[#8a2a2a] bg-[#fbe3e3] px-4 py-3 text-sm font-semibold text-[#8a2a2a]" role="alert">{{ $errors->first() }}</p>
                @endif

                {{-- Prossimo ritiro --}}
                @if ($nextPickup)
                    <section class="rounded-2xl bg-brand-600 px-6 py-5 text-white md:flex md:items-center md:gap-8">
                        <div class="md:flex-1">
                            <p class="text-sm font-bold uppercase tracking-wider text-white/85">{{ __('Prossimo ritiro') }}</p>
                            <p class="mt-1 text-2xl font-bold leading-tight md:text-3xl">{{ ucfirst($nextPickup['pickup_date']->translatedFormat('l j F')) }} · {{ $nextPickup['market_name'] }}</p>
                            <p class="mt-3 border-t border-white/25 pt-3 text-base text-white/90 md:border-0 md:pt-0">
                                {{ __('Ordina entro :deadline, ore :deadline2', ['deadline' => $nextPickup['deadline']->translatedFormat('l j F'), 'deadline2' => $nextPickup['deadline']->format('H:i')]) }}
                            </p>
                        </div>
                        @php
                            $laterPickups = $pickups->filter(fn ($pickup) => $pickup['pickup_date']->greaterThan($nextPickup['pickup_date']))->take(3);
                        @endphp
                        @if ($laterPickups->isNotEmpty())
                            <ul class="mt-4 hidden space-y-1 border-white/25 text-sm text-white/90 md:mt-0 md:block md:border-l md:pl-8">
                                @foreach ($laterPickups as $pickup)
                                    <li>
                                        {{ $loop->first ? __('Poi: ') : '' }}{{ $pickup['pickup_date']->translatedFormat('l j F') }} · {{ $pickup['market_name'] }}
                                        @if ($pickup['closed_reason']) {{ __('· chiuso') }} @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                {{-- Ricerca (su telefono; su computer è nell’intestazione) --}}
                <form method="GET" action="{{ $selectedCategory ? route('storefront.category', ['category' => $selectedCategory]) : route('home') }}" role="search" class="md:hidden">
                    <label for="mobile-search" class="sr-only">{{ __('Cerca un prodotto') }}</label>
                    <input id="mobile-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cerca un prodotto') }}"
                           class="block min-h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-base">
                </form>

                @if ($categories->isNotEmpty())
                    <nav id="categorie" aria-label="{{ __('Categorie prodotto') }}" class="flex gap-2 overflow-x-auto overscroll-x-contain pb-1">
                        <a href="{{ route('home', array_filter(['q' => $search])) }}" @class(['chip min-h-11 shrink-0 text-base', 'chip-active' => ! $selectedCategory]) @if (! $selectedCategory) aria-current="page" @endif>
                            {{ __('Tutti') }}
                        </a>
                        @foreach ($categories as $category)
                            <a href="{{ route('storefront.category', array_filter(['category' => $category, 'q' => $search])) }}"
                               @class(['chip min-h-11 shrink-0 text-base', 'chip-active' => $selectedCategory?->is($category)])
                               @if ($selectedCategory?->is($category)) aria-current="page" @endif>
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </nav>
                @endif

                <section id="catalogo" class="space-y-4">
                    @if ($search !== '')
                        <p class="text-sm text-gray-600">
                            {{ trans_choice(':count risultato per «:search» ·|:count risultati per «:search» ·', $products->total(), ['search' => $search]) }} <a href="{{ $selectedCategory ? route('storefront.category', ['category' => $selectedCategory]) : route('home') }}" class="font-semibold text-brand-600 underline">{{ __('Annulla la ricerca') }}</a>
                        </p>
                    @endif

                    @if ($products->isEmpty())
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
                            @if ($search !== '')
                                {{ __('Nessun prodotto trovato.') }}
                            @else
                                {{ $selectedCategory ? __('Non ci sono prodotti disponibili in questa categoria.') : __('Al momento non ci sono prodotti pubblici disponibili.') }}
                            @endif
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:gap-5 xl:grid-cols-3">
                            @foreach ($products as $product)
                                @php
                                    $cover = $product->coverPhotoUrl();
                                    $state = $productStates[$product->id];
                                    $productUrl = route('storefront.product', ['product' => $product]);
                                @endphp

                                <article class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    <a href="{{ $productUrl }}" aria-label="Apri {{ $product->name }}" class="relative block aspect-[4/3] bg-brand-100">
                                        @if ($cover)
                                            <img src="{{ $cover }}" alt="{{ $product->name }}" @class(['h-full w-full object-cover', 'opacity-60' => $state['soldOut']]) />
                                        @else
                                            <span class="flex h-full items-center justify-center text-sm text-gray-500">{{ __('Foto prodotto') }}</span>
                                        @endif
                                        @if ($state['soldOut'])
                                            <span class="absolute left-2 top-2 rounded-full bg-gray-500 px-3 py-1 text-xs font-bold text-white">{{ __('Esaurito') }}</span>
                                        @elseif ($state['offSeasonDays'] > 0)
                                            <span class="absolute left-2 top-2 rounded-full bg-[#ede9fb] px-3 py-1 text-xs font-bold text-[#3b2f73]">{{ __('Fuori stagione') }}</span>
                                        @elseif ($state['lowStock'])
                                            <span class="absolute left-2 top-2 rounded-full bg-apricot px-3 py-1 text-xs font-bold text-gray-900">{{ __('Ultimi :available_stock pezzi', ['available_stock' => $product->available_stock]) }}</span>
                                        @endif
                                    </a>
                                    <div class="flex flex-1 flex-col p-3 sm:p-4">
                                        <a href="{{ $productUrl }}" class="text-base text-gray-900 hover:text-brand-600 sm:text-lg">{{ $product->name }}</a>
                                        <p class="text-lg font-bold text-brand-600">{{ \App\Support\Format::money($product->price_cents) }}</p>
                                        <p class="mb-3 text-sm text-gray-500">
                                            @if ($state['soldOut'])
                                                {{ __('Torna presto disponibile') }}
                                            @elseif (! $state['orderable'])
                                                {{ __('Non ordinabile in questo periodo') }}
                                            @elseif ($state['offSeasonDays'] > 0)
                                                {{ __('Pronto in :off_season_days giorni', ['off_season_days' => $state['offSeasonDays']]) }}
                                            @elseif ($product->has_variants)
                                                {{ __('Varianti a scelta') }}
                                            @else
                                                {{ __('Disponibile') }}
                                            @endif
                                        </p>

                                        <div class="mt-auto">
                                            @if ($state['soldOut'])
                                                <a href="{{ $productUrl }}" class="btn-secondary w-full text-base">{{ __('Avvisami') }}</a>
                                            @elseif (! $state['orderable'])
                                                <a href="{{ $productUrl }}" class="btn-secondary w-full text-base">{{ __('Vedi') }}</a>
                                            @elseif ($product->has_variants)
                                                <a href="{{ $productUrl }}" class="btn-primary w-full text-base">{{ __('Scegli') }}</a>
                                            @else
                                                <form method="POST" action="{{ route('cart.store', ['product' => $product]) }}">
                                                    @csrf
                                                    <input type="hidden" name="quantity" value="1">
                                                    <input type="hidden" name="stay" value="1">
                                                    <button type="submit" class="btn-primary w-full text-base">{{ __('Aggiungi') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if ($products->hasPages())
                        <div class="mt-6">{{ $products->links() }}</div>
                    @endif
                </section>
            </main>

            {{-- Carrello di lato (computer) --}}
            <aside class="hidden lg:sticky lg:top-6 lg:block" aria-label="{{ __('Carrello') }}">
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="text-lg font-bold text-gray-900">{{ trans_choice('Carrello · :count prodotto|Carrello · :count prodotti', $cart['count']) }}</h2>
                    @if ($hasCart)
                        <ul class="mt-3 divide-y divide-gray-200">
                            @foreach ($cart['items'] as $item)
                                <li class="flex items-start justify-between gap-3 py-3">
                                    <div class="min-w-0">
                                        <p class="text-gray-900">{{ $item['product']->name }} ×{{ $item['quantity'] }}</p>
                                        @if ($item['variantLabel'])
                                            <p class="text-sm text-gray-500">{{ $item['variantLabel'] }}</p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 font-bold text-gray-900">{{ $money($item['lineTotalCents']) }}</p>
                                </li>
                            @endforeach
                        </ul>
                        <div class="flex items-baseline justify-between border-t border-gray-200 pt-4">
                            <span class="text-xl font-bold text-gray-900">{{ __('Totale') }}</span>
                            <span class="text-2xl font-bold text-brand-600">{{ $money($cart['totalCents']) }}</span>
                        </div>
                        <p class="mt-3 text-sm text-gray-500">{{ __('Si paga al ritiro.') }}</p>
                        <a href="{{ route('cart.index') }}" class="mt-4 flex min-h-12 w-full items-center justify-center rounded-xl bg-apricot px-4 text-base font-bold text-gray-900 hover:brightness-95">{{ __('Vedi carrello') }}</a>
                    @else
                        <p class="mt-3 text-sm text-gray-500">{{ __('Il carrello è vuoto. Aggiungi i prodotti che vuoi ritirare.') }}</p>
                    @endif
                </div>
            </aside>
        </div>

        <x-site-footer />

        {{-- Carrello in basso (telefono e tablet) --}}
        @if ($hasCart)
            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white px-4 py-3 lg:hidden">
                <div class="mx-auto flex max-w-3xl items-center justify-between gap-4">
                    <a href="{{ route('cart.index') }}" class="min-w-0">
                        <p class="text-sm text-gray-500">{{ trans_choice('Carrello · :count prodotto|Carrello · :count prodotti', $cart['count']) }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $money($cart['totalCents']) }}</p>
                    </a>
                    <a href="{{ route('cart.index') }}" class="inline-flex min-h-12 shrink-0 items-center rounded-xl bg-apricot px-6 text-base font-bold text-gray-900 hover:brightness-95">{{ __('Vedi carrello') }}</a>
                </div>
            </div>
        @endif

        <x-cookie-banner />
    </body>
</html>
