@extends('layouts.admin')

@section('page-title', __('Ordini annullati per blocco cliente'))

@section('content')
    <div class="space-y-4">
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if ($notices->isNotEmpty())
            <form method="POST" action="{{ route('admin.block-notices.seen-all') }}" class="flex justify-end">
                @csrf
                <button class="min-h-11 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Segna tutti come visti') }}</button>
            </form>
        @endif

        @forelse ($notices as $notice)
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-white p-4">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900">{{ $notice->order->order_number }} · {{ $notice->order->customer_name }}</p>
                    <p class="text-sm text-gray-600">{{ __('Stato precedente:') }} {{ \App\Models\Order::labelFor($notice->previous_status, $notice->order->fulfillment_method) }}</p>
                    <p class="text-sm text-gray-500">{{ $notice->reason }}</p>
                    <p class="mt-1 text-xs text-amber-700">{{ __('I prodotti già preparati vanno rimessi a magazzino.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.orders.show', $notice->order) }}" class="min-h-11 inline-flex items-center text-sm font-semibold text-brand-700 hover:text-brand-900">{{ __('Apri') }}</a>
                    <form method="POST" action="{{ route('admin.block-notices.seen', $notice) }}">
                        @csrf
                        <button class="min-h-11 text-sm font-semibold text-gray-700 hover:text-gray-900">{{ __('Segna come visto') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">{{ __('Nessuna segnalazione.') }}</p>
        @endforelse
    </div>
@endsection
