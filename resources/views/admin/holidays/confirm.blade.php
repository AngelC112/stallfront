@extends('layouts.admin')

@section('page-title', __('Conferma chiusura'))

@section('content')
    <form method="POST" action="{{ route('admin.holidays.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        <input type="hidden" name="confirmed" value="1">
        @foreach ($input as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <section class="space-y-4 rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <h2 class="text-lg font-bold text-gray-900">{{ $type === 'closure' ? __('Chiusura straordinaria') : __('Ferie') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Mercato') }}</dt><dd class="font-medium text-gray-900">{{ $market?->name ?? __('Tutti i mercati') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Giorni') }}</dt><dd class="font-medium text-gray-900">{{ $start->equalTo($end) ? $start->isoFormat('L') : 'dal '.$start->isoFormat('L').' al '.$end->isoFormat('L') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Motivazione') }}</dt><dd class="font-medium text-gray-900">{{ $input['reason'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Ordini coinvolti') }}</dt><dd class="font-medium text-gray-900">{{ $summary['orders'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Clienti che verranno avvisati') }}</dt><dd class="font-medium text-gray-900">{{ $summary['customers'] }}</dd></div>
            </dl>
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                {{ __('Gli ordini coinvolti passano in «Da riprogrammare» e i messaggi partono subito alla conferma: non si possono richiamare.') }}
            </p>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.holidays.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Annulla') }}</a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-600 px-6 py-2 text-sm font-semibold text-white hover:bg-red-500">{{ __('Conferma e avvisa i clienti') }}</button>
        </div>
    </form>
@endsection
