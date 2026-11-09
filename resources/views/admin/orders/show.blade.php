@extends('layouts.admin')

@section('page-title', __('Ordine :number', ['number' => $order->order_number]))

@section('content')
    @php($backUrl = \App\Support\ReturnUrl::from(request(\App\Support\ReturnUrl::PARAMETER)))
    <div class="mx-auto max-w-4xl space-y-5">
        {{-- Aperto da un'altra vista (scheda cliente, banco...): si torna lì invece che all'elenco ordini. --}}
        @if ($backUrl)
            <a href="{{ $backUrl }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-700 hover:text-brand-900">← {{ \App\Support\ReturnUrl::label($backUrl) }}</a>
        @else
            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('← Tutti gli ordini') }}</a>
        @endif

        @include('admin.orders._detail')
    </div>
@endsection
