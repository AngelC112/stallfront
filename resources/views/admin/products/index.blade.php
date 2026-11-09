@extends('layouts.admin')

@section('page-title', __('Prodotti'))

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div class="hidden sm:block">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Catalogo Prodotti') }}</h2>
        <p class="text-sm text-gray-500">{{ __('Gestisci articoli, prezzi e disponibilità.') }}</p>
    </div>
    <a href="{{ route('admin.products.create') }}"
       class="inline-flex items-center rounded-xl bg-petrol px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-petrol-dark transition-colors">
        {{ __('+ Nuovo Prodotto') }}
    </a>
</div>

{{-- Ricerca e filtri --}}
<form method="GET" action="{{ route('admin.products.index') }}" class="mb-4 space-y-3">
    @if ($activeFilter !== 'all')
        <input type="hidden" name="filter" value="{{ $activeFilter }}">
    @endif
    <div class="flex gap-2">
        <label for="product-search" class="sr-only">{{ __('Cerca un prodotto') }}</label>
        <input id="product-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cerca per nome o categoria') }}"
               class="min-h-11 w-full rounded-xl border border-gray-300 px-4 text-sm focus:border-petrol focus:ring-petrol">
        <button type="submit" class="min-h-11 shrink-0 rounded-xl border border-petrol px-4 text-sm font-semibold text-petrol hover:bg-petrol/5">{{ __('Cerca') }}</button>
    </div>
    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.products.index', array_filter(['q' => $search, 'filter' => $key !== 'all' ? $key : null])) }}"
               @class(['chip shrink-0', 'chip-active' => $activeFilter === $key])>{{ $label }}</a>
        @endforeach
    </div>
</form>

{{-- MOBILE VIEW (Cards) --}}
<div class="block sm:hidden space-y-4">
    @forelse ($products as $prod)
    <div class="rounded-xl bg-white p-4 border border-gray-100">
        <div class="flex gap-4">
            {{-- Foto --}}
            <div class="h-16 w-16 shrink-0">
                @if($url = $prod->coverPhotoUrl())
                    <img src="{{ $url }}" alt="" class="h-full w-full rounded-xl object-cover bg-gray-100">
                @else
                    <div class="flex h-full w-full items-center justify-center rounded-xl bg-gray-200 text-xs text-gray-400">{{ __('No Img') }}</div>
                @endif
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-gray-900 truncate">{{ $prod->name }}</h3>
                <p class="text-xs text-gray-500 mb-2">{{ $prod->category->name ?? '-' }} • {{ \App\Support\Format::money($prod->price_cents) }}</p>
                
                <div class="flex flex-wrap gap-1">
                    {{-- Stock Tag --}}
                    @if(!$prod->track_stock)
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ __('Senza stock') }}</span>
                    @else
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $prod->isLowStock() ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800' }}">
                            {{ $prod->isLowStock() ? __('Scorta bassa') : __('Disponibile') }} • {{ __(':available_stock pz', ['available_stock' => $prod->available_stock]) }}
                        </span>
                    @endif

                    {{-- Visibility Tag --}}
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $prod->visibility === 'visible' ? 'bg-green-100 text-green-800' : ($prod->visibility === 'coming_soon' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600') }}">
                        {{ $prod->visibilityLabel() }}
                    </span>

                    @if($prod->has_variants)
                        <span class="inline-flex rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-medium text-purple-800">{{ __('Varianti') }}</span>
                    @endif
                </div>
            </div>

            {{-- Azioni Rapide --}}
            <div class="flex flex-col gap-2">
                <a href="{{ route('admin.products.edit', $prod) }}" class="text-center rounded-xl border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('Modifica') }}</a>
                <form action="{{ route('admin.products.destroy', $prod) }}" method="POST" onsubmit="return confirm(@js(__('Eliminare definitivamente questo prodotto?')));">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full text-center rounded-xl border border-transparent px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">{{ __('Elimina') }}</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-10 text-sm text-gray-500">{{ ($search !== '' || $activeFilter !== 'all') ? __('Nessun prodotto trovato con questi filtri.') : __('Nessun prodotto trovato.') }}</div>
    @endforelse
</div>

{{-- DESKTOP VIEW (Table) --}}
<div class="hidden sm:block overflow-x-auto bg-white shadow sm:rounded-2xl">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                <th scope="col" class="px-6 py-3">{{ __('Foto') }}</th>
                <th scope="col" class="px-6 py-3">{{ __('Nome') }}</th>
                <th scope="col" class="px-6 py-3">{{ __('Categoria') }}</th>
                <th scope="col" class="px-6 py-3">{{ __('Prezzo') }}</th>
                <th scope="col" class="px-6 py-3">{{ __('Stock') }}</th>
                <th scope="col" class="px-6 py-3">{{ __('Stato') }}</th>
                <th scope="col" class="relative px-6 py-3 text-right"><span class="sr-only">{{ __('Azioni') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @forelse ($products as $prod)
            <tr class="hover:bg-gray-50">
                <td class="whitespace-nowrap px-6 py-4">
                    <a href="{{ route('admin.products.edit', $prod) }}" class="block">
                        @if($url = $prod->coverPhotoUrl())
                            <img src="{{ $url }}" alt="" class="h-10 w-10 rounded object-cover bg-gray-100">
                        @else
                            <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-200 text-xs text-gray-400">{{ __('No Img') }}</div>
                        @endif
                    </a>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                    <a href="{{ route('admin.products.edit', $prod) }}" class="hover:text-petrol hover:underline">
                        {{ $prod->name }}
                    </a>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $prod->category->name ?? '-' }}</td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ \App\Support\Format::money($prod->price_cents) }}</td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                    @if(!$prod->track_stock)
                        <span class="italic text-gray-400">{{ __('Senza stock') }}</span>
                    @else
                        {{ __(':available_stock pz', ['available_stock' => $prod->available_stock]) }}
                        @if($prod->isLowStock())
                            <span class="ml-1 inline-flex rounded-full bg-orange-100 px-2 text-xs font-semibold leading-5 text-orange-800">{{ __('Low') }}</span>
                        @endif
                    @endif
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm">
                    <div class="flex flex-col gap-1">
                        <span class="inline-flex w-fit rounded-full px-2 text-xs font-semibold leading-5
                            {{ $prod->visibility === 'visible' ? 'bg-green-100 text-green-800' : ($prod->visibility === 'coming_soon' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600') }}">
                            {{ $prod->visibilityLabel() }}
                        </span>
                        @if($prod->has_variants)
                            <span class="inline-flex w-fit rounded-full bg-purple-100 px-2 text-xs font-semibold leading-5 text-purple-800">{{ __('Varianti') }}</span>
                        @endif
                    </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                    <a href="{{ route('admin.products.edit', $prod) }}" class="mr-3 text-petrol hover:text-petrol-dark">{{ __('Modifica') }}</a>
                    <form action="{{ route('admin.products.destroy', $prod) }}" method="POST" class="inline-block"
                          onsubmit="return confirm(@js(__('Eliminare definitivamente questo prodotto?')));">
                        @csrf @method('DELETE')
                        <button type="submit" class="cursor-pointer text-red-600 hover:text-red-900">{{ __('Elimina') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('Nessun prodotto trovato.') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $products->links() }}
</div>
@endsection