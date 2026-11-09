@extends('layouts.admin')

@section('page-title', isset($category->id) ? __('Modifica Categoria') : __('Nuova Categoria'))

@section('content')
<form method="POST" action="{{ isset($category->id) ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-6 max-w-2xl">
    @csrf
    @isset($category->id) @method('PUT') @endisset

    <div class="bg-white shadow sm:rounded-2xl">
        <div class="border-b border-gray-200 px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Informazioni Base') }}</h3>
            
            <div class="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-6">
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Nome *') }}</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" required class="mt-1 block w-full rounded-xl border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                
                <div class="sm:col-span-6">
                    <label for="description" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Descrizione (facoltativa)') }}</label>
                    <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-xl border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">{{ old('description', $category->description) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Sezione Stagionalità --}}
    <div class="bg-white shadow sm:rounded-2xl" x-data="{ alwaysAvailable: {{ old('always_available', $category->always_available ?? true) ? 'true' : 'false' }} }">
        <div class="border-b border-gray-200 px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Disponibilità e Stagionalità') }}</h3>
            
            <div class="mt-4 space-y-4">
                {{-- Checkbox Sempre Disponibile --}}
                <div class="flex items-start">
                    <div class="flex h-6 items-center">
                        <input id="always_available" name="always_available" type="checkbox" value="1" 
                               x-model="alwaysAvailable"
                               @checked(old('always_available', $category->always_available ?? true)) 
                               class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                    </div>
                    <div class="ml-3 text-sm leading-6">
                        <label for="always_available" class="font-medium text-gray-900 cursor-pointer">{{ __('Sempre disponibile') }}</label>
                        <p class="text-gray-500">{{ __('Se selezionato, ignora il periodo di vendita sottostante.') }}</p>
                    </div>
                </div>

                {{-- Blocchi Data Stagionali (visibili solo se NOT sempre disponibile) --}}
                <div x-show="!alwaysAvailable" x-collapse class="pl-7 border-l-2 border-gray-200 ml-3 space-y-4">
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Inizio Periodo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Inizio Periodo') }}</label>
                            <div class="grid grid-cols-3 gap-2">
                                {{-- Giorno --}}
                                <select name="season_start_day" class="rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm col-span-1">
                                    <option value="">{{ __('Giorn') }}</option>
                                    @for($d=1;$d<=31;$d++)
                                        <option value="{{ $d }}" @selected(old('season_start_day', $category->season_start_day) == $d)>{{ $d }}</option>
                                    @endfor
                                </select>
                                
                                {{-- Mese (ITALIANO) --}}
                                <select name="season_start_month" class="rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm col-span-1">
                                    <option value="">{{ __('Mese') }}</option>
                                    @foreach(range(1,12) as $m)
                                        {{-- Usiamo Carbon per ottenere il nome in Italiano --}}
                                        <option value="{{ $m }}" @selected(old('season_start_month', $category->season_start_month) == $m)>
                                            {{ \Illuminate\Support\Carbon::create(2024, $m, 1)->isoFormat('MMMM') }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Anno (Facoltativo) --}}
                                <input type="number" name="season_start_year" min="2000" max="2100" placeholder="{{ __('Anno*') }}" 
                                       value="{{ old('season_start_year', $category->season_start_year) }}"
                                       class="rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm col-span-1">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Lascia vuoto l\'anno per ripetere ogni anno.') }}</p>
                        </div>

                        {{-- Fine Periodo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fine Periodo') }}</label>
                            <div class="grid grid-cols-3 gap-2">
                                {{-- Giorno --}}
                                <select name="season_end_day" class="rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm col-span-1">
                                    <option value="">{{ __('Giorn') }}</option>
                                    @for($d=1;$d<=31;$d++)
                                        <option value="{{ $d }}" @selected(old('season_end_day', $category->season_end_day) == $d)>{{ $d }}</option>
                                    @endfor
                                </select>
                                
                                {{-- Mese (ITALIANO) --}}
                                <select name="season_end_month" class="rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm col-span-1">
                                    <option value="">{{ __('Mese') }}</option>
                                    @foreach(range(1,12) as $m)
                                        <option value="{{ $m }}" @selected(old('season_end_month', $category->season_end_month) == $m)>
                                            {{ \Illuminate\Support\Carbon::create(2024, $m, 1)->isoFormat('MMMM') }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Anno (Facoltativo) --}}
                                <input type="number" name="season_end_year" min="2000" max="2100" placeholder="{{ __('Anno*') }}" 
                                       value="{{ old('season_end_year', $category->season_end_year) }}"
                                       class="rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm col-span-1">
                            </div>
                        </div>
                    </div>

                    {{-- Comportamento Fuori Stagione --}}
                    <div class="pt-4 border-t border-gray-100">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Comportamento fuori periodo') }}</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="off_season_behavior" value="long" 
                                       @checked(old('off_season_behavior', $category->off_season_behavior ?? 'long') === 'long')
                                       class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                                <span class="ml-2 text-sm text-gray-900 cursor-pointer">{{ __('Acquistabile con tempi più lunghi') }}</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="off_season_behavior" value="visible" 
                                       @checked(old('off_season_behavior', $category->off_season_behavior) === 'visible')
                                       class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                                <span class="ml-2 text-sm text-gray-900 cursor-pointer">{{ __('Visibile ma non acquistabile') }}</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="off_season_behavior" value="hidden" 
                                       @checked(old('off_season_behavior', $category->off_season_behavior) === 'hidden')
                                       class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                                <span class="ml-2 text-sm text-gray-900 cursor-pointer">{{ __('Nascosta ai clienti') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- Giorni Evasione Extra --}}
                    <div id="days-extra-container" class="pt-2" style="display: none;">
                         <label for="off_season_days" class="block text-sm font-medium text-gray-700">{{ __('Giorni extra di preparazione (fuori stagione)') }}</label>
                         <div class="mt-1 flex items-center gap-2">
                             <input type="number" name="off_season_days" id="off_season_days" min="0" max="99" 
                                    value="{{ old('off_season_days', $category->off_season_days ?? 10) }}"
                                    class="block w-24 rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                             <span class="text-sm text-gray-500">{{ __('giorni') }}</span>
                         </div>
                         <p class="mt-1 text-xs text-gray-500">{{ __('Si sommano all\'anticipo minimo del mercato.') }}</p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Stato Attivo --}}
    <div class="bg-white shadow sm:rounded-2xl">
        <div class="px-4 py-5 sm:p-6">
             <div class="flex items-center">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $category->is_active ?? true)) class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                <label for="is_active" class="ml-3 block text-sm font-medium leading-6 text-gray-900 cursor-pointer">{{ __('Categoria Attiva') }}</label>
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ __('Se disattivata, non apparirà nella vetrina clienti nemmeno se in stagione.') }}</p>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.categories.index') }}" class="text-sm font-semibold text-gray-900 hover:text-gray-700">{{ __('Annulla') }}</a>
        <button type="submit" class="rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">{{ __('Salva') }}</button>
    </div>
</form>

{{-- Script per mostrare/nascondere i giorni extra in base alla radio button --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const radios = document.querySelectorAll('input[name="off_season_behavior"]');
        const daysContainer = document.querySelector('[x-show*="off_season_behavior"]'); // Selezione approssimativa per Alpine
        
        // Poiché usiamo Alpine x-show basato su JS puro qui dentro è complicato mischiarlo.
        // Soluzione semplice: listener vanilla JS sul change delle radio.
        
        const checkBehavior = () => {
            const selected = document.querySelector('input[name="off_season_behavior"]:checked');
            const container = document.getElementById('days-extra-container'); // Dobbiamo dare un ID
            
            if(container) {
                if(selected && selected.value === 'long') {
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            }
        };

        radios.forEach(radio => radio.addEventListener('change', checkBehavior));
        checkBehavior(); // Init
    });
</script>
@endsection