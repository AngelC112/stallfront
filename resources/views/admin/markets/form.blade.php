@extends('layouts.admin')

@section('page-title', $market->exists ? __('Modifica mercato') : __('Nuovo mercato'))

@section('content')
    <form method="POST" action="{{ $market->exists ? route('admin.markets.update', ['market' => $market]) : route('admin.markets.store') }}" class="mx-auto max-w-3xl space-y-5">
        @csrf
        @if ($market->exists)
            @method('PUT')
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">{{ __('Controlla i dati inseriti:') }}</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <h2 class="text-lg font-bold text-gray-900 mb-6">{{ __('Dove e quando') }}</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Nome del mercato') }}</label>
                    <input type="text" name="name" value="{{ old('name', $market->name) }}" required maxlength="160" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-petrol focus:ring-petrol">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Luogo') }}</label>
                    <input type="text" name="location" value="{{ old('location', $market->location) }}" required maxlength="255" placeholder="{{ __('Indirizzo o punto di riferimento') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-petrol focus:ring-petrol">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Giorno settimanale') }}</label>
                    <select name="weekday" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-petrol focus:ring-petrol">
                        <option value="">{{ __('Seleziona un giorno') }}</option>
                        @foreach ([1 => __('Lunedì'), 2 => __('Martedì'), 3 => __('Mercoledì'), 4 => __('Giovedì'), 5 => __('Venerdì'), 6 => __('Sabato'), 7 => __('Domenica')] as $weekday => $label)
                            <option value="{{ $weekday }}" @selected((string) old('weekday', $market->weekday) === (string) $weekday)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Anticipo minimo per ordinare') }}</label>
                    <div class="flex items-center gap-3">
                        <input type="number" name="minimum_order_days" min="0" max="60" value="{{ old('minimum_order_days', $market->minimum_order_days ?? 3) }}" required class="block w-24 rounded-xl border-gray-300 shadow-sm focus:border-petrol focus:ring-petrol">
                        <span class="text-sm font-medium text-gray-500">{{ __('giorni') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <h2 class="text-lg font-bold text-gray-900 mb-6">{{ __('Orari limite') }}</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Ordine entro') }}</label>
                    <input type="time" name="order_cutoff_time" value="{{ old('order_cutoff_time', $market->order_cutoff_time ? substr($market->order_cutoff_time, 0, 5) : '') }}" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-petrol focus:ring-petrol">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Ritiro entro') }}</label>
                    <input type="time" name="pickup_cutoff_time" value="{{ old('pickup_cutoff_time', $market->pickup_cutoff_time ? substr($market->pickup_cutoff_time, 0, 5) : '') }}" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-petrol focus:ring-petrol">
                </div>
            </div>
            <p class="mt-4 text-sm text-gray-500 italic">{{ __('L’orario limite per ordinare determina fino a quando il cliente può scegliere una data di ritiro.') }}</p>
        </section>

        <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $market->is_active ?? true)) class="h-5 w-5 rounded border-gray-300 text-petrol focus:ring-petrol">
                <div>
                    <span class="block text-base font-bold text-gray-900">{{ __('Mercato attivo') }}</span>
                    <span class="block text-sm text-gray-500">{{ __('Solo i mercati attivi compariranno come date selezionabili nel checkout.') }}</span>
                </div>
            </div>
        </section>

        @if ($market->exists && auth()->user()->hasPermission(\App\Support\Permission::CLOSURES_HOLIDAYS_MANAGE))
            <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
                <h2 class="text-lg font-bold text-gray-900">{{ __('Ferie e chiusure') }}</h2>
                <p class="mt-2 text-sm text-gray-600">{{ __('Le chiusure vengono gestite in una pagina dedicata, con riepilogo degli ordini coinvolti prima della conferma.') }}</p>
                <a href="{{ route('admin.holidays.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-900">{{ __('Vai a ferie e chiusure →') }}</a>
            </section>
        @endif

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end mt-8">
            <a href="{{ route('admin.markets.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">{{ __('Annulla') }}</a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-petrol px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-petrol-dark transition-colors">{{ __('Salva mercato') }}</button>
        </div>
    </form>
@endsection