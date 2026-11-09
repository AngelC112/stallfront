<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Ordine') }} {{ $order->order_number }}</h2>
            <a href="{{ route('account.orders.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Tutti i miei ordini') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-500">{{ __('Effettuato il') }} {{ $order->placed_at->isoFormat('L LT') }}</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900">{{ $order->statusLabel() }}</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">{{ $order->fulfillmentMethodLabel() }}</span>
                </div>
                <div class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                    <p class="text-sm text-gray-700">{{ $order->paymentMethodLabel() }}</p>
                    @if ($order->pickup_date)
                        <p class="text-sm text-gray-700">{{ __('Data desiderata per il ritiro:') }} {{ $order->pickup_date->isoFormat('L') }}</p>
                    @endif
                    @if ($order->market_name)
                        <p class="text-sm text-gray-700">{{ $order->market_name }} · {{ $order->market_location }}</p>
                    @endif
                    @if ($order->delivery_address)
                        <p class="text-sm text-gray-700 sm:col-span-2">{{ __('Indirizzo di consegna:') }} {{ $order->delivery_address }}</p>
                    @endif
                </div>
            </section>

            @if (session('success'))
                <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if ($order->status === \App\Models\Order::STATUS_TO_RESCHEDULE)
                <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ __('Il ritiro previsto non è più possibile. Scegli un’altra data oppure annulla l’ordine.') }}
                </div>
            @elseif ($order->status === \App\Models\Order::STATUS_NOT_COLLECTED)
                <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ __('Non hai ritirato questo ordine. Puoi riprogrammarlo su un’altra data oppure annullarlo.') }}
                </div>
            @endif

            @if ($order->fulfillment_method === 'pickup' && in_array($order->status, ['received', 'preparing', 'ready'], true))
                <section class="rounded-xl border border-gray-200 bg-white p-4 text-center sm:p-6">
                    <h3 class="font-semibold text-gray-900">{{ __('Il tuo QR per il ritiro') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Mostralo al banco insieme al codice :order_number.', ['order_number' => $order->order_number]) }}</p>
                    <img src="{{ route('qr.image', ['orderNumber' => $order->order_number]) }}" alt="QR code dell’ordine {{ $order->order_number }}" class="mx-auto mt-3 h-48 w-48">
                </section>
            @endif

            @if ($canChangeDate)
                <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                    <h3 class="font-semibold text-gray-900">{{ __('Cambia data di ritiro') }}</h3>
                    @if ($dateOptions->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">{{ __('Al momento non ci sono altre date disponibili.') }}</p>
                    @else
                        <form method="POST" action="{{ route('account.orders.reschedule', ['order' => $order->order_number]) }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                            @csrf
                            <label class="block flex-1 text-sm font-medium text-gray-700">
                                {{ __('Mercato e data') }}
                                <select name="pickup_option" required class="mt-1 block min-h-11 w-full rounded-xl border-gray-300">
                                    <option value="">{{ __('Seleziona') }}</option>
                                    @foreach ($dateOptions as $option)
                                        <option value="{{ $option['market_id'] }}|{{ $option['pickup_date'] }}">{{ $option['market_name'] }} · {{ \Illuminate\Support\Carbon::parse($option['pickup_date'])->isoFormat('L') }}</option>
                                    @endforeach
                                </select>
                                @error('pickup_option')
                                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                            <button class="min-h-11 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Conferma nuova data') }}</button>
                        </form>
                    @endif
                </section>
            @endif

            <form method="POST" action="{{ route('account.orders.reorder', ['order' => $order->order_number]) }}">
                @csrf
                <button class="min-h-11 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Riordina') }}</button>
            </form>

            @if ($canCancel)
                <form method="POST" action="{{ route('account.orders.cancel', ['order' => $order->order_number]) }}" onsubmit="return confirm(@js(__('Vuoi annullare questo ordine?')))">
                    @csrf
                    <button class="min-h-11 rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">{{ __('Annulla ordine') }}</button>
                </form>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                <h3 class="font-semibold text-gray-900">{{ __('Riepilogo prodotti') }}</h3>
                <div class="mt-3 divide-y divide-gray-100">
                    @foreach ($order->items as $item)
                        <div class="flex justify-between gap-4 py-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ $item->product_name }}</p>
                                @if ($item->variant_label)
                                    <p class="mt-1 text-sm text-gray-500">{{ $item->variant_label }}</p>
                                @endif
                                <p class="mt-1 text-sm text-gray-500">{{ $item->quantity }} × {{ \App\Support\Format::money($item->unit_price_cents) }}</p>
                            </div>
                            <p class="shrink-0 font-semibold text-gray-900">{{ \App\Support\Format::money($item->line_total_cents) }}</p>
                        </div>
                    @endforeach
                </div>
                @if ($order->delivery_cost_cents > 0)
                    <div class="flex justify-between border-t border-gray-100 py-3 text-sm text-gray-600">
                        <span>{{ __('Consegna') }}</span>
                        <span>{{ \App\Support\Format::money($order->delivery_cost_cents) }}</span>
                    </div>
                @endif
                <div class="flex justify-between border-t border-gray-200 pt-4 text-lg font-bold text-gray-900">
                    <span>{{ __('Totale da pagare') }}</span>
                    <span>{{ \App\Support\Format::money($order->total_cents) }}</span>
                </div>
                <p class="mt-3 text-sm text-gray-500">{{ $order->fulfillment_method === 'delivery' ? __('Il pagamento avverrà al momento della consegna.') : __('Il pagamento avverrà al ritiro.') }}</p>
            </section>
        </div>
    </div>
</x-app-layout>