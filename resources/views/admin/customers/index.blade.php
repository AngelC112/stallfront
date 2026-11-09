@extends('layouts.admin')

@section('page-title', __('Clienti'))

@section('content')
    <div class="space-y-4">
        <form method="GET" class="flex flex-col gap-3 rounded-xl bg-white p-4 border border-gray-200 sm:flex-row sm:items-center">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Nome, email o cellulare') }}" aria-label="{{ __('Cerca cliente') }}" class="block min-h-11 w-full rounded-xl border-gray-300 text-sm sm:max-w-sm">
            <select name="filter" aria-label="{{ __('Filtro') }}" class="min-h-11 rounded-xl border-gray-300 text-sm">
                <option value="">{{ __('Tutti i clienti') }}</option>
                <option value="delivery" @selected(request('filter') === 'delivery')>{{ __('Consegna a domicilio') }}</option>
                <option value="no_shows" @selected(request('filter') === 'no_shows')>{{ __('Con mancati ritiri') }}</option>
                <option value="blocked" @selected(request('filter') === 'blocked')>{{ __('Bloccati') }}</option>
            </select>
            <button class="min-h-11 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Cerca') }}</button>
        </form>

        {{-- Telefono: una riga per cliente con il pulsante Scheda, senza scorrere di lato. --}}
        <ul class="divide-y divide-gray-100 rounded-xl bg-white border border-gray-200 sm:hidden">
            @forelse ($customers as $customer)
                <li class="flex items-center gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-gray-900">{{ $customer->fullName() }}</p>
                        <p class="truncate text-xs text-gray-600">{{ $customer->email }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                            <span>{{ __('Ordini') }} {{ $customer->orders_count }}</span>
                            <span>{{ __('· Mancati ritiri') }} {{ $customer->no_show_count }}</span>
                            @if ($customer->isBlocked())
                                <span class="rounded-full bg-red-100 px-2 py-0.5 font-semibold text-red-800">{{ __('Bloccato') }}</span>
                            @endif
                            @if ($customer->delivery_enabled)
                                <span class="rounded-full bg-sky-100 px-2 py-0.5 font-semibold text-sky-800">{{ __('Domicilio') }}</span>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-gray-300 px-3 text-sm font-semibold text-brand-700 hover:bg-gray-50">{{ __('Scheda →') }}</a>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('Nessun cliente trovato.') }}</li>
            @endforelse
        </ul>

        <div class="hidden overflow-x-auto rounded-xl bg-white border border-gray-200 sm:block">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Cliente') }}</th>
                        <th class="px-4 py-3">{{ __('Contatti') }}</th>
                        <th class="px-4 py-3">{{ __('Ordini') }}</th>
                        <th class="px-4 py-3">{{ __('Mancati ritiri') }}</th>
                        <th class="px-4 py-3">{{ __('Stato') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $customer->fullName() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->email }}@if ($customer->phone)<br>{{ $customer->phone }}@endif</td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->orders_count }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->no_show_count }}</td>
                            <td class="px-4 py-3">
                                @if ($customer->isBlocked())
                                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">{{ __('Bloccato') }}</span>
                                @endif
                                @if ($customer->delivery_enabled)
                                    <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ __('Domicilio') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-900">{{ __('Scheda →') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('Nessun cliente trovato.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $customers->links() }}
    </div>
@endsection
