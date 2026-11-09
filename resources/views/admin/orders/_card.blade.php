@php($cancelled = $order->status === 'cancelled')
<div class="flex flex-col justify-between rounded-xl border p-4 transition-colors {{ $cancelled ? 'border-red-200 bg-red-50' : ($order->isDueToday() ? 'border-amber-400 bg-amber-50' : 'border-gray-100 bg-white hover:border-brand-200') }}">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-3">
            <input type="checkbox" form="bulk-form" name="order_ids[]" value="{{ $order->id }}" aria-label="Seleziona ordine {{ $order->order_number }}" class="mt-1 h-5 w-5 shrink-0 rounded border-gray-300 text-brand-600 print:hidden">
            <div class="min-w-0">
                <p class="truncate font-bold text-gray-900">{{ $order->customer_name }}</p>
                <p class="text-xs text-gray-500">#{{ $order->order_number }} · {{ $order->statusLabel() }}</p>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap justify-end gap-1">
            @if ($order->isDueToday())
                <span class="rounded bg-amber-500 px-2 py-1 text-[10px] font-bold uppercase text-white">{{ __('Da preparare oggi') }}</span>
            @endif
            @if ($order->isOffSeason())
                <span class="rounded bg-orange-100 px-2 py-1 text-[10px] font-bold uppercase text-orange-700">{{ __('Fuori stagione') }}</span>
            @endif
            @if ($order->fulfillment_method === 'delivery')
                <span class="rounded bg-sky-100 px-2 py-1 text-[10px] font-bold uppercase text-sky-700">{{ __('Domicilio') }}</span>
            @endif
        </div>
    </div>

    <div class="mb-4 text-sm text-gray-600">
        @foreach ($order->items as $item)
            <div class="flex justify-between gap-3 py-1">
                <span>{{ $item->quantity }}x {{ $item->product_name }} {{ $item->variant_label ? "({$item->variant_label})" : '' }}</span>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
        <p class="text-sm font-bold text-gray-900">{{ __('Da incassare:') }} {{ \App\Support\Format::money($order->total_cents) }}</p>
        <div class="flex flex-wrap items-center gap-3 text-xs font-semibold print:hidden">
            @if ($order->telUrl())
                <a href="{{ $order->telUrl() }}" class="inline-flex min-h-11 items-center text-gray-700 hover:text-gray-900">{{ __('Chiama') }}</a>
            @endif
            @if ($order->whatsappUrl())
                <a href="{{ $order->whatsappUrl() }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-green-700 hover:text-green-900">{{ __('WhatsApp') }}</a>
            @endif
            <a href="{{ route('admin.orders.show', $order) }}" data-order-detail class="inline-flex min-h-11 items-center text-brand-600 hover:underline">
                {{ __('Dettaglio:notes_count →', ['notes_count' => $canSeeNotes && $order->notes_count ? ' · '.$order->notes_count.' note' : '']) }}
            </a>
        </div>
    </div>

    @isset($nextSteps[$order->id])
        <form method="POST" action="{{ route('admin.orders.status', ['order' => $order]) }}" class="mt-3 print:hidden">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $nextSteps[$order->id][0] }}">
            <button class="min-h-11 w-full rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ $nextSteps[$order->id][1] }}</button>
        </form>
    @endisset
</div>
