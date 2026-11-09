@extends('layouts.admin')

@section('page-title', __('Mercati'))

@section('content')
    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('admin.markets.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Nuovo mercato') }}</a>
        </div>

        @if ($errors->has('market'))
            <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first('market') }}</p>
        @endif

        @forelse ($markets as $market)
            <article class="rounded-xl border border-gray-200 bg-white p-4 sm:flex sm:items-center sm:justify-between sm:gap-5 sm:p-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold text-gray-900">{{ $market->name }}</h2>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $market->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $market->is_active ? __('Attivo') : __('Sospeso') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">{{ $market->location }}</p>
                    <p class="mt-2 text-sm text-gray-500">{{ $market->weekdayLabel() }} {{ __('· anticipo :minimum_order_days giorni · ordini entro :order_cutoff_time · ritiro entro', ['minimum_order_days' => $market->minimum_order_days, 'order_cutoff_time' => substr($market->order_cutoff_time, 0, 5)]) }} {{ substr($market->pickup_cutoff_time, 0, 5) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $market->orders_count }} {{ __('ordini collegati') }}</p>
                </div>
                <div class="mt-4 flex shrink-0 items-center gap-4 sm:mt-0">
                    <a href="{{ route('admin.markets.edit', ['market' => $market]) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">{{ __('Modifica') }}</a>
                    @if ($market->orders_count === 0)
                        <form method="POST" action="{{ route('admin.markets.destroy', ['market' => $market]) }}" onsubmit="return confirm(@js(__('Eliminare questo mercato?')))">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm font-semibold text-red-700 hover:text-red-900">{{ __('Elimina') }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">
                {{ __('Nessun mercato configurato. Aggiungi un mercato per rendere disponibili le date di ritiro nel checkout.') }}
            </div>
        @endforelse
    </div>
@endsection