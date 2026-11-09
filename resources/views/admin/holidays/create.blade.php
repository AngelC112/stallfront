@extends('layouts.admin')

@section('page-title', __('Nuova chiusura'))

@section('content')
    <form method="POST" action="{{ route('admin.holidays.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="space-y-5 rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <div>
                <label for="type" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Tipo') }}</label>
                <select id="type" name="type" required class="block w-full rounded-xl border-gray-300 shadow-sm">
                    @if (in_array('holiday', $allowedTypes, true))
                        <option value="holiday" @selected(old('type') === 'holiday')>{{ __('Ferie (periodo, un mercato o tutti)') }}</option>
                    @endif
                    @if (in_array('closure', $allowedTypes, true))
                        <option value="closure" @selected(old('type') === 'closure')>{{ __('Chiusura straordinaria (un giorno)') }}</option>
                    @endif
                </select>
            </div>

            <div>
                <label for="market_id" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Mercato') }}</label>
                <select id="market_id" name="market_id" class="block w-full rounded-xl border-gray-300 shadow-sm">
                    <option value="">{{ __('Tutti i mercati (solo ferie)') }}</option>
                    @foreach ($markets as $market)
                        <option value="{{ $market->id }}" @selected((string) old('market_id') === (string) $market->id)>{{ $market->name }} · {{ $market->weekdayLabel() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="start_date" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Dal giorno') }}</label>
                    <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" min="{{ today()->toDateString() }}" required class="block w-full rounded-xl border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="end_date" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Al giorno (solo ferie)') }}</label>
                    <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" min="{{ today()->toDateString() }}" class="block w-full rounded-xl border-gray-300 shadow-sm">
                </div>
            </div>

            <div>
                <label for="reason" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Motivazione') }}</label>
                <input id="reason" type="text" name="reason" value="{{ old('reason') }}" maxlength="255" required placeholder="{{ __('Es. Maltempo, vacanze estive') }}" class="block w-full rounded-xl border-gray-300 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">{{ __('Verrà inviata ai clienti con ordini coinvolti.') }}</p>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.holidays.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Annulla') }}</a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Vedi riepilogo') }}</button>
        </div>
    </form>
@endsection
