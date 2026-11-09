@extends('layouts.admin')

@section('page-title', __('Testi dei messaggi'))

@section('content')
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.notifications.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('‹ Notifiche ai clienti') }}</a>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __('Testi dei messaggi') }}</h1>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] p-3 text-sm font-semibold text-[#14532d]" role="status">{{ session('success') }}</div>
        @endif

        <p class="text-sm text-gray-600">{{ __('Tocca un messaggio per cambiarne oggetto e testo, oppure per non inviarlo più. Nei testi puoi usare segnaposto come') }} <code>{name}</code> o <code>{order_number}</code>. {{ __('Ogni messaggio ha un testo per ciascuna lingua: il cliente lo riceve nella lingua che ha scelto.') }}</p>

        @foreach (['customer' => __('Ai clienti'), 'seller' => __('A te')] as $recipient => $title)
            <section class="space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ $title }}</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($events->where('recipient', $recipient) as $event => $definition)
                        <a href="{{ route('admin.notifications.edit', $event) }}" class="block rounded-2xl border border-gray-200 bg-white p-4 hover:border-brand-300">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-bold text-gray-900">{{ $definition['label'] }}</p>
                                <span @class([
                                    'shrink-0 rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-[#dff0e6] text-[#14532d]' => $definition['is_active'],
                                    'bg-gray-100 text-gray-600' => ! $definition['is_active'],
                                ])>{{ $definition['is_active'] ? __('Attivo') : __('Non inviato') }}</span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-gray-700">{{ $definition['subject'] }}</p>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $definition['body'] }}</p>
                            <span class="mt-2 inline-flex text-sm font-semibold text-brand-600">{{ __('Modifica ›') }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
