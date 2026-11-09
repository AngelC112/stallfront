<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('I miei ordini') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            @forelse ($orders as $order)
                <a href="{{ route('account.orders.show', ['order' => $order->order_number]) }}" class="block rounded-xl border border-gray-200 bg-white p-4 transition hover:border-brand-300 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ __('Ordine') }} {{ $order->order_number }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $order->placed_at->isoFormat('L LT') }} · {{ $order->fulfillmentMethodLabel() }}</p>
                        </div>
                        <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700">{{ $order->statusLabel() }}</span>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-4">
                        <span class="text-sm text-gray-600">{{ $order->items_count }} {{ __('articoli ·') }} {{ $order->paymentMethodLabel() }}</span>
                        <span class="font-bold text-gray-900">{{ \App\Support\Format::money($order->total_cents) }}</span>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                    <h3 class="font-semibold text-gray-900">{{ __('Non hai ancora effettuato ordini') }}</h3>
                    <a href="{{ route('home') }}" class="mt-4 inline-flex rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Vai alla vetrina') }}</a>
                </div>
            @endforelse

            @if ($orders->hasPages())
                <div>{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>