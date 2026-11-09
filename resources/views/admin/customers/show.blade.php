@extends('layouts.admin')

@section('page-title', $customer->fullName())

@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <a href="{{ route('admin.customers.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('← Tutti i clienti') }}</a>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-xl bg-white p-5 border border-gray-200 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ $customer->fullName() }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $customer->email }} · {{ $customer->hasVerifiedEmail() ? __('email verificata') : __('email non verificata') }}</p>
                    <p class="text-sm text-gray-600">{{ $customer->phone ?: __('Cellulare non indicato') }}@if ($customer->phone) · {{ $customer->hasPhoneVerified() ? __('cellulare verificato') : __('cellulare non verificato') }}@endif</p>
                    @if ($customer->address)
                        <p class="text-sm text-gray-600">{{ $customer->address }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-3 text-sm font-semibold">
                        @if ($customer->phone)
                            <a href="tel:{{ preg_replace('/\D+/', '', $customer->phone) }}" class="inline-flex min-h-11 items-center text-gray-700 hover:text-gray-900">{{ __('Chiama') }}</a>
                        @endif
                        @if (auth()->user()->isSeller() && $customer->hasVerifiedEmail())
                            <a href="{{ route('admin.customer-emails.create', ['to' => [$customer->id], 'back' => \App\Support\ReturnUrl::of(request()->fullUrl())]) }}" class="inline-flex min-h-11 items-center text-brand-700 hover:text-brand-900">{{ __('Scrivi email') }}</a>
                        @else
                            <a href="mailto:{{ $customer->email }}" class="inline-flex min-h-11 items-center text-brand-700 hover:text-brand-900">{{ __('Email') }}</a>
                        @endif
                    </div>
                </div>
                <div class="text-right text-sm">
                    <p class="font-semibold text-gray-900">{{ __('Mancati ritiri:') }} {{ $customer->no_show_count }}</p>
                    @if ($customer->isBlocked())
                        <p class="mt-1 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">{{ __('Bloccato dal') }} {{ $customer->blocked_at->isoFormat('L') }}</p>
                    @endif
                </div>
            </div>
        </section>

        @if (auth()->user()->isSeller())
            <section class="space-y-4 rounded-xl bg-white p-5 border border-gray-200 sm:p-6">
                <h3 class="font-semibold text-gray-900">{{ __('Azioni del venditore') }}</h3>

                <form method="POST" action="{{ route('admin.customers.delivery', $customer) }}" class="flex flex-wrap items-center justify-between gap-3">
                    @csrf
                    @method('PATCH')
                    <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-gray-800">
                        <input type="checkbox" name="delivery_enabled" value="1" @checked($customer->delivery_enabled) class="h-5 w-5 rounded border-gray-300 text-brand-600">
                        {{ __('Consegna a domicilio abilitata') }}
                    </label>
                    <button class="min-h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Salva') }}</button>
                </form>

                @if ($customer->phone && ! $customer->hasPhoneVerified())
                    <form method="POST" action="{{ route('admin.customers.verify-phone', $customer) }}">
                        @csrf
                        <button class="min-h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Segna cellulare come verificato') }}</button>
                    </form>
                @endif

                @if ($customer->isBlocked())
                    <form method="POST" action="{{ route('admin.customers.unblock', $customer) }}" onsubmit="return confirm(@js(__('Sbloccare questo cliente? Il contatore dei mancati ritiri sarà azzerato.')))">
                        @csrf
                        <button class="min-h-11 rounded-xl bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-500">{{ __('Sblocca cliente') }}</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="rounded-xl bg-white p-5 border border-gray-200 sm:p-6">
            <h3 class="font-semibold text-gray-900">{{ __('Ultimi ordini') }}</h3>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($orders as $order)
                    @php
                        $canOpenOrder = auth()->user()->hasPermission($order->fulfillment_method === \App\Models\Order::FULFILLMENT_DELIVERY
                            ? \App\Support\Permission::ORDERS_DELIVERY_MANAGE
                            : \App\Support\Permission::ORDERS_MARKET_MANAGE);
                    @endphp
                    <li>
                        @if ($canOpenOrder)
                            <a href="{{ route('admin.orders.show', ['order' => $order, 'back' => \App\Support\ReturnUrl::of(request()->fullUrl())]) }}" class="-mx-2 flex min-h-11 flex-wrap items-center justify-between gap-2 rounded-lg px-2 py-2 hover:bg-gray-50">
                                <span class="font-semibold text-brand-700">#{{ $order->order_number }} <span class="font-normal text-gray-900">· {{ $order->statusLabel() }}</span></span>
                                <span class="text-gray-500">{{ $order->placed_at->isoFormat('L') }} {{ __('· :amount →', ['amount' => \App\Support\Format::money($order->total_cents)]) }}</span>
                            </a>
                        @else
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span class="text-gray-900">#{{ $order->order_number }} · {{ $order->statusLabel() }}</span>
                                <span class="text-gray-500">{{ $order->placed_at->isoFormat('L') }} · {{ \App\Support\Format::money($order->total_cents) }}</span>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('Nessun ordine.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
