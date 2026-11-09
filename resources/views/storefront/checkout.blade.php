<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Conferma ordine') }} | {{ \App\Models\Setting::get('store.name', config('app.name')) }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-pwa-head />
    </head>
    <body class="bg-canvas text-gray-900 antialiased">
        <x-checkout-steps :step="2" />

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-5 sm:py-10">
            <div class="mb-7">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">{{ __('Ultimo passaggio') }}</p>
                <h1 class="mt-1 text-3xl font-black text-gray-900 sm:text-4xl">{{ __('Dove ritirare o ricevere l’ordine') }}</h1>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">{{ __('Controlla i dati inseriti:') }}</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <form method="POST" action="{{ route('checkout.store') }}" class="space-y-5" x-data="{ fulfillmentMethod: 'pickup' }">
                    @csrf

                    <section class="rounded-xl border border-brand-200 bg-white p-4 sm:p-6">
                        <h2 class="text-lg font-bold text-gray-900">{{ __('Modalità di ricezione') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-brand-200 p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                <input type="radio" name="fulfillment_method" value="pickup" x-model="fulfillmentMethod" checked class="mt-1 border-brand-600 text-brand-600 focus:ring-brand-600">
                                <span>
                                    <span class="block font-semibold text-gray-900">{{ __('Ritiro al mercato') }}</span>
                                    <span class="mt-1 block text-sm text-slate-600">{{ __('Pagamento al ritiro.') }}</span>
                                </span>
                            </label>

                            @if ($canDeliver)
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-brand-200 p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="fulfillment_method" value="delivery" x-model="fulfillmentMethod" class="mt-1 border-brand-600 text-brand-600 focus:ring-brand-600">
                                    <span>
                                        <span class="block font-semibold text-gray-900">{{ __('Consegna a domicilio') }}</span>
                                        <span class="mt-1 block text-sm text-slate-600">{{ __('Pagamento alla consegna.') }}</span>
                                    </span>
                                </label>
                            @endif
                        </div>
                        @error('fulfillment_method')
                            <p class="mt-3 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </section>

                    <section x-show="fulfillmentMethod === 'pickup'" class="rounded-xl border border-brand-200 bg-white p-4 sm:p-6">
                        <h2 class="text-lg font-bold text-gray-900">{{ __('Scegli mercato e data') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Le date mostrate tengono conto dell’anticipo minimo del mercato e dei tempi di preparazione dei prodotti nel carrello.') }}</p>
                        @forelse ($marketDates as $marketDate)
                            <label class="mt-3 flex cursor-pointer gap-3 rounded-xl border border-brand-200 p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                <input type="radio" name="pickup_option" value="{{ $marketDate['market_id'].'|'.$marketDate['pickup_date'] }}" x-bind:required="fulfillmentMethod === 'pickup'" x-bind:disabled="fulfillmentMethod !== 'pickup'" @checked(old('pickup_option') === $marketDate['market_id'].'|'.$marketDate['pickup_date'])" class="mt-1 border-brand-600 text-brand-600 focus:ring-brand-600">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900">{{ $marketDate['market_name'] }} · {{ \Illuminate\Support\Carbon::parse($marketDate['pickup_date'])->isoFormat('L') }}</span>
                                    <span class="mt-1 block text-sm text-slate-600">{{ $marketDate['location'] }} · {{ $marketDate['weekday_label'] }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">Ordina entro {{ $marketDate['deadline']->isoFormat('L LT') }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Al momento non ci sono date di ritiro prenotabili per i prodotti nel carrello. Contatta il negozio per informazioni.</p>
                        @endforelse
                        @if ($unavailableDates->isNotEmpty())
                            <div class="mt-4 space-y-2" aria-label="{{ __('Date non disponibili') }}">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date non disponibili</p>
                                @foreach ($unavailableDates as $unavailable)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-400" aria-disabled="true">
                                        <span>{{ $unavailable['market_name'] }} · {{ \Illuminate\Support\Carbon::parse($unavailable['pickup_date'])->isoFormat('L') }}</span>
                                        <span class="text-xs">{{ $unavailable['reason'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @error('pickup_option')
                            <p class="mt-3 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                        <p class="mt-3 text-sm text-slate-500">Il pagamento avverrà in negozio al momento del ritiro.</p>
                    </section>

                    @if ($canDeliver)
                        <section x-show="fulfillmentMethod === 'delivery'" class="rounded-xl border border-brand-200 bg-white p-4 sm:p-6">
                            <h2 class="text-lg font-bold text-gray-900">Indirizzo di consegna</h2>
                            <label for="delivery-address" class="mt-4 block text-sm font-medium text-slate-700">Indirizzo completo</label>
                            <textarea id="delivery-address" name="delivery_address" rows="3" maxlength="1000" x-bind:required="fulfillmentMethod === 'delivery'" x-bind:disabled="fulfillmentMethod !== 'delivery'" class="mt-1 block w-full rounded-xl border-brand-200 bg-white px-3 py-3 text-base">{{ old('delivery_address', $customer->address) }}</textarea>
                            <label for="delivery-phone" class="mt-4 block text-sm font-medium text-slate-700">Telefono per la consegna</label>
                            <input id="delivery-phone" type="tel" name="delivery_phone" value="{{ old('delivery_phone', $customer->phone) }}" maxlength="30" x-bind:required="fulfillmentMethod === 'delivery'" x-bind:disabled="fulfillmentMethod !== 'delivery'" class="mt-1 block w-full rounded-xl border-brand-200 bg-white px-3 py-3 text-base sm:max-w-sm">
                            <p class="mt-2 text-sm text-slate-500">Il pagamento avverrà alla consegna. Costo: {{ \App\Support\Format::money($deliveryCostCents) }}.</p>
                            @if ($deliveryMinCents > 0)
                                <p class="mt-1 text-sm text-slate-500">Importo minimo per la consegna: {{ \App\Support\Format::money($deliveryMinCents) }}.</p>
                            @endif
                        </section>
                    @endif

                    <section class="rounded-xl border border-brand-200 bg-white p-4 sm:p-6">
                        <label for="customer-note" class="block text-sm font-semibold text-gray-900">Nota per il venditore (facoltativa)</label>
                        <textarea id="customer-note" name="customer_note" rows="3" maxlength="2000" class="mt-2 block w-full rounded-xl border-brand-200 bg-white px-3 py-3 text-base">{{ old('customer_note') }}</textarea>
                    </section>

                    <p class="rounded-xl bg-brand-50 p-3 text-sm text-brand-700">Abbiamo riservato i tuoi prodotti fino alle {{ $reservedUntil->format('H:i') }}. Conferma entro quell’ora per non perderli.</p>

                    <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-base font-bold text-white shadow-sm transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-700 focus-visible:ring-offset-2">
                        Conferma ordine
                    </button>
                </form>

                <aside class="h-fit rounded-xl border border-brand-200 bg-white p-4 sm:p-6 lg:sticky lg:top-6">
                    <h2 class="text-lg font-bold text-gray-900">Riepilogo</h2>
                    <div class="mt-4 divide-y divide-brand-100">
                        @foreach ($items as $item)
                            <div class="flex justify-between gap-4 py-3 text-sm">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $item['product']->name }}</p>
                                    @if ($item['variantLabel'])
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['variantLabel'] }}</p>
                                    @endif
                                    <p class="mt-1 text-slate-500">{{ $item['quantity'] }} × {{ \App\Support\Format::money($item['unitPriceCents']) }}</p>
                                </div>
                                <p class="shrink-0 font-semibold text-gray-900">{{ \App\Support\Format::money($item['lineTotalCents']) }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 border-t border-brand-200 pt-4">
                        <div class="flex justify-between text-base font-black text-gray-900">
                            <span>Prodotti</span>
                            <span>{{ \App\Support\Format::money($subtotalCents) }}</span>
                        </div>
                        @if ($canDeliver && $deliveryCostCents > 0)
                            <p class="mt-2 text-xs text-slate-500">Il costo di consegna si applica solo scegliendo la consegna a domicilio.</p>
                        @endif
                        <p class="mt-3 text-xs leading-5 text-slate-500">Non è previsto alcun pagamento online: pagherai al ritiro o alla consegna.</p>
                    </div>
                </aside>
            </div>
        </main>
        <x-site-footer />
        <x-cookie-banner />
    </body>
</html>