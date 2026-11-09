<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Carrello') }} | {{ \App\Models\Setting::get('store.name', config('app.name')) }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-pwa-head />
    </head>
    @php($money = fn (int $cents): string => \App\Support\Format::money($cents))
    <body @class(['bg-canvas text-gray-900 antialiased', 'pb-28 lg:pb-0' => $items->isNotEmpty()])>
        <x-checkout-steps :step="1" />

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start lg:gap-8">
            <main class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ __('Il tuo carrello') }}</h1>
                    <a href="{{ route('home') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('‹ Continua gli acquisti') }}</a>
                </div>

                @if (session('status'))
                    <p class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] px-4 py-3 text-sm font-semibold text-[#14532d]" role="status">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <p class="rounded-xl border border-[#8a2a2a] bg-[#fbe3e3] px-4 py-3 text-sm font-semibold text-[#8a2a2a]" role="alert">{{ $errors->first() }}</p>
                @endif

                @if ($items->isEmpty())
                    <section class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center sm:p-12">
                        <h2 class="text-xl font-bold text-gray-900">{{ __('Il carrello è vuoto') }}</h2>
                        <p class="mt-2 text-sm text-gray-600">{{ __('Sfoglia la vetrina per trovare qualcosa che ti piace.') }}</p>
                        <a href="{{ route('home') }}" class="btn-primary mt-5">{{ __('Vai alla vetrina') }}</a>
                    </section>
                @else
                    @foreach ($items as $item)
                        <article class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 sm:gap-5 sm:p-4">
                            <a href="{{ route('storefront.product', ['product' => $item['product']]) }}" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-brand-100 sm:h-24 sm:w-24">
                                @if ($item['image'])
                                    <img src="{{ $item['image'] }}" alt="{{ $item['product']->name }}" class="h-full w-full object-cover">
                                @endif
                            </a>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('storefront.product', ['product' => $item['product']]) }}" class="text-base font-bold text-gray-900 hover:text-brand-600 sm:text-lg">{{ $item['product']->name }}</a>
                                @if ($item['variantLabel'])
                                    <p class="text-sm text-gray-500">{{ $item['variantLabel'] }}</p>
                                @endif
                                @if ($item['offSeasonDays'] > 0)
                                    <p class="text-sm text-gray-500">{{ __('Fuori stagione: pronto in :off_season_days giorni', ['off_season_days' => $item['offSeasonDays']]) }}</p>
                                @endif
                                <p class="text-sm text-gray-500 sm:hidden">{{ $money($item['lineTotalCents']) }}</p>
                                <form method="POST" action="{{ route('cart.destroy', ['lineKey' => $item['key']]) }}" class="mt-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-gray-500 underline hover:text-[#8a2a2a]">{{ __('Rimuovi') }}</button>
                                </form>
                            </div>

                            <p class="hidden shrink-0 text-lg font-bold text-gray-900 sm:block">{{ $money($item['lineTotalCents']) }}</p>

                            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                                @if ($item['quantity'] > 1)
                                    <form method="POST" action="{{ route('cart.update', ['lineKey' => $item['key']]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $item['quantity'] - 1 }}">
                                        <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-300 text-lg font-bold text-brand-600 hover:bg-brand-50" aria-label="Togli uno: {{ $item['product']->name }}">−</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('cart.destroy', ['lineKey' => $item['key']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-300 text-lg font-bold text-brand-600 hover:bg-brand-50" aria-label="Rimuovi {{ $item['product']->name }}">−</button>
                                    </form>
                                @endif
                                <span class="w-6 text-center text-lg font-bold" aria-label="{{ __('Quantità') }}">{{ $item['quantity'] }}</span>
                                <form method="POST" action="{{ route('cart.update', ['lineKey' => $item['key']]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="quantity" value="{{ $item['quantity'] + 1 }}">
                                    <button type="submit" @disabled($item['availableStock'] !== null && $item['quantity'] >= $item['availableStock'])
                                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-300 text-lg font-bold text-brand-600 hover:bg-brand-50 disabled:opacity-40" aria-label="Aggiungi uno: {{ $item['product']->name }}">+</button>
                                </form>
                            </div>
                        </article>
                    @endforeach

                    @if ($offSeasonDays > 0)
                        <div class="rounded-xl border border-apricot bg-apricot-light px-4 py-3 text-sm text-gray-900">
                            <strong>{{ __('Contiene un prodotto fuori stagione.') }}</strong>
                            {{ __('Sarà pronto in :off_season_days giorni: la prima data di ritiro disponibile sarà dopo il :off_season_days2.', ['off_season_days' => $offSeasonDays, 'off_season_days2' => now()->addDays($offSeasonDays)->translatedFormat('j F')]) }}
                        </div>
                    @endif
                @endif
            </main>

            @if ($items->isNotEmpty())
                <aside class="mt-6 lg:sticky lg:top-6 lg:mt-0" aria-label="{{ __('Riepilogo') }}">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="hidden text-lg font-bold text-gray-900 lg:block">{{ __('Riepilogo') }}</h2>
                        <div class="hidden justify-between border-b border-gray-200 py-3 text-gray-800 lg:flex">
                            <span>{{ __('Prodotti (:cart_count)', ['cart_count' => $cartCount]) }}</span>
                            <span class="font-bold">{{ $money($totalCents) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 lg:pt-4">
                            <span class="text-lg text-gray-900 lg:text-xl lg:font-bold">{{ __('Totale da pagare al ritiro') }}</span>
                            <span class="text-2xl font-bold text-brand-600">{{ $money($totalCents) }}</span>
                        </div>
                        <div class="hidden lg:block">
                            @auth
                                <a href="{{ route('checkout.show') }}" class="btn-primary mt-4 min-h-12 w-full text-base">{{ __('Procedi al checkout') }}</a>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary mt-4 min-h-12 w-full text-base">{{ __('Accedi per ordinare') }}</a>
                            @endauth
                        </div>
                    </div>
                </aside>

                {{-- Pulsante fisso in basso su telefono --}}
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white px-4 py-3 lg:hidden">
                    @auth
                        <a href="{{ route('checkout.show') }}" class="btn-primary min-h-12 w-full text-base">{{ __('Procedi al checkout') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary min-h-12 w-full text-base">{{ __('Accedi per ordinare') }}</a>
                    @endauth
                </div>
            @endif
        </div>

        <x-site-footer />
        <x-cookie-banner />
    </body>
</html>
