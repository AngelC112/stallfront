@extends('layouts.admin')

@section('page-title', __('Categorie'))

@section('content')
<div class="mb-6 flex justify-end">
    <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">
        {{ __('+ Nuova Categoria') }}
    </a>
</div>

<div class="overflow-x-auto bg-white shadow sm:rounded-2xl">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Nome') }}</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Periodo') }}</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Stato') }}</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Fuori Stagione') }}</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Prodotti') }}</th>
                <th scope="col" class="relative px-6 py-3 text-right"><span class="sr-only">{{ __('Azioni') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @forelse ($categories as $cat)
            <tr class="hover:bg-gray-50">
                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $cat->name }}</td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                    {{ $cat->seasonLabel() }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm">
                    @if(!$cat->is_active)
                        <span class="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold leading-5 text-gray-600">{{ __('Disattivata') }}</span>
                    @elseif($cat->isInSeason())
                        <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">{{ __('In Stagione') }}</span>
                    @else
                        <span class="inline-flex rounded-full bg-yellow-100 px-2 text-xs font-semibold leading-5 text-yellow-800">{{ __('Fuori Stagione') }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                    @if($cat->always_available)
                        -
                    @else
                        @switch($cat->off_season_behavior)
                            @case('long') {{ __('Acquistabile (+:off_season_daysgg)', ['off_season_days' => $cat->off_season_days]) }} @break
                            @case('visible') {{ __('Solo Visibile') }} @break
                            @case('hidden') {{ __('Nascosta') }} @break
                        @endswitch
                    @endif
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $cat->products_count }}</td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                    <a href="{{ route('admin.categories.edit', $cat) }}" class="text-brand-600 hover:text-brand-900 mr-3">{{ __('Modifica') }}</a>
                    <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" class="inline-block" onsubmit="return confirm(@js(__('Eliminare questa categoria? I prodotti collegati resteranno senza categoria.')));">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Elimina') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('Nessuna categoria trovata.') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection