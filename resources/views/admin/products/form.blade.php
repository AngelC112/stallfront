@extends('layouts.admin')
@section('page-title', isset($product->id) ? __('Modifica Prodotto') : __('Nuovo Prodotto'))
@section('content')
<div x-data="productForm()" class="space-y-6">
    <form method="POST" enctype="multipart/form-data"
          action="{{ isset($product->id) ? route('admin.products.update', $product) : route('admin.products.store') }}"
          @submit.prevent="prepareForSubmit($event)">
        @csrf
        @isset($product->id) @method('PUT') @endisset

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4">
                <h3 class="text-sm font-medium text-red-800">{{ __('Ci sono stati errori:') }}</h3>
                <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- COLONNA SINISTRA --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Info base --}}
                <div class="bg-white shadow sm:rounded-2xl">
                    <div class="border-b border-gray-200 px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Informazioni Prodotto') }}</h3>
                        <div class="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="name" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Nome Prodotto *') }}</label>
                                <input type="text" name="name" id="name" x-model="form.name" required
                                       value="{{ old('name', $product->name ?? '') }}"
                                       class="mt-1 block w-full rounded-xl border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                            </div>
                            <div class="sm:col-span-6">
                                <label for="short_description" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Descrizione Breve') }}</label>
                                <input type="text" name="short_description" id="short_description" x-model="form.short_description" maxlength="255"
                                       value="{{ old('short_description', $product->short_description ?? '') }}"
                                       class="mt-1 block w-full rounded-xl border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                            </div>
                            <div class="sm:col-span-6">
                                <label for="description" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Descrizione Completa') }}</label>
                                <textarea name="description" id="description" rows="5" x-model="form.description"
                                          class="mt-1 block w-full rounded-xl border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">{{ old('description', $product->description ?? '') }}</textarea>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="category_id" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Categoria') }}</label>
                                <select name="category_id" id="category_id" x-model="form.category_id"
                                        class="mt-1 block w-full rounded-xl border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                                    <option value="">{{ __('-- Nessuna --') }}</option>
                                    @foreach($categories as $id => $name)
                                        <option value="{{ $id }}" @selected(old('category_id', $product->category_id) == $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="price_display" class="block text-sm font-medium leading-6 text-gray-900">{{ __('Prezzo Base (€) *') }}</label>
                                <div class="relative mt-1 rounded-xl shadow-sm">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="text-gray-500 sm:text-sm">€</span>
                                    </div>
                                    <input type="number" step="0.01" min="0" id="price_display"
                                           value="{{ old('price_display', $product->exists ? $product->price_cents / 100 : '') }}"
                                           x-model.number="form.priceDisplay" @blur="updatePriceCents()"
                                           class="block w-full rounded-xl border-0 py-1.5 pl-7 pr-12 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6" required>
                                </div>
                                <input type="hidden" name="price_cents" id="price_cents_hidden"
                                       value="{{ old('price_cents', $product->price_cents ?? 0) }}" :value="form.priceCents">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Magazzino --}}
                <div class="bg-white shadow sm:rounded-2xl">
                    <div class="border-b border-gray-200 px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Magazzino e Disponibilità') }}</h3>
                        <div class="mt-4 space-y-4">
                            <div class="flex items-start">
                                <div class="flex h-6 items-center">
                                    <input id="track_stock" name="track_stock" type="checkbox" value="1"
                                           @checked(old('track_stock', $product->track_stock ?? true))
                                           x-model="form.track_stock"
                                           class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                                </div>
                                <div class="ml-3 text-sm leading-6">
                                    <label for="track_stock" class="font-medium text-gray-900 cursor-pointer">{{ __('Monitora Giacenza') }}</label>
                                    <p class="text-gray-500">{{ __('Deseleziona per prodotti "Sempre Disponibili".') }}</p>
                                </div>
                            </div>
                            <div x-show="form.track_stock" class="pl-7 border-l-2 border-gray-200 ml-3 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="stock" class="block text-sm font-medium text-gray-700">{{ __('Quantità Disponibile') }}</label>
                                        <input type="number" name="stock" id="stock" x-model.number="form.stock" min="0"
                                               value="{{ old('stock', $product->stock ?? 0) }}"
                                               class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="low_stock_threshold" class="block text-sm font-medium text-gray-700">{{ __('Soglia Scorta Bassa') }}</label>
                                        <input type="number" name="low_stock_threshold" id="low_stock_threshold" x-model.number="form.low_stock_threshold" min="0"
                                               value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 3) }}"
                                               class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stagione --}}
                <div class="bg-white shadow sm:rounded-2xl">
                    <div class="border-b border-gray-200 px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Stagione solo per questo prodotto') }}</h3>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="season_behavior" class="block text-sm font-medium text-gray-700">{{ __('Comportamento') }}</label>
                                <select name="season_behavior" id="season_behavior" x-model="form.season_behavior"
                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                    <option value="follow_category">{{ __('Segui la categoria') }}</option>
                                    <option value="purchasable_longer">{{ __('Acquistabile con tempi più lunghi') }}</option>
                                    <option value="visible_not_purchasable">{{ __('Visibile ma non acquistabile') }}</option>
                                    <option value="hidden">{{ __('Nascosto fuori periodo') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="own_fulfillment_days" class="block text-sm font-medium text-gray-700">{{ __('Giorni di evasione propri') }}</label>
                                <input type="number" name="own_fulfillment_days" id="own_fulfillment_days" x-model.number="form.own_fulfillment_days" min="0"
                                       value="{{ old('own_fulfillment_days', $product->own_fulfillment_days ?? '') }}"
                                       class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">{{ __('Vuoto = quelli della categoria. Vale solo se il prodotto ha una categoria fuori periodo.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Varianti --}}
                <div class="bg-white shadow sm:rounded-2xl">
                    <div class="border-b border-gray-200 px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Varianti') }}</h3>
                        <div class="mt-4">
                            <div class="flex items-start">
                                <div class="flex h-6 items-center">
                                    <input id="has_variants" name="has_variants" type="checkbox" value="1"
                                           @checked(old('has_variants', $product->has_variants ?? false))
                                           x-model="form.has_variants"
                                           class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600 cursor-pointer">
                                </div>
                                <div class="ml-3 text-sm leading-6">
                                    <label for="has_variants" class="font-medium text-gray-900 cursor-pointer">{{ __('Il prodotto ha varianti (taglie, colori o altre caratteristiche)') }}</label>
                                    <p class="text-gray-500">{{ __('Scegli le caratteristiche che servono a questo prodotto e dai loro il nome che vuoi (per esempio Taglia, Colore, Materiale). Poi aggiungi una riga per ogni variante.') }}</p>
                                </div>
                            </div>

                            <div x-show="form.has_variants" class="mt-6 space-y-6">
                                {{-- Chip caratteristiche --}}
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <template x-for="(type, tIndex) in form.types" :key="tIndex">
                                            <span class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-900">
                                                <input type="text" x-model="type.name" placeholder="{{ __('Nome') }}"
                                                       class="w-24 border-0 bg-transparent p-0 text-sm focus:ring-0">
                                                <button type="button" @click="removeType(tIndex)" class="text-gray-400 hover:text-red-600">{{ __('×') }}</button>
                                                <input type="hidden" :name="'option_types['+tIndex+'][name]'" :value="type.name">
                                            </span>
                                        </template>
                                        <button type="button" @click="addType()"
                                                class="inline-flex items-center rounded-full border border-dashed border-gray-400 px-3 py-1 text-sm font-medium text-gray-700 hover:border-brand-400 hover:text-brand-600">
                                            {{ __('+ Aggiungi caratteristica') }}
                                        </button>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                                        <span>{{ __('Suggerite:') }}</span>
                                        <template x-for="s in [@js(__('Taglia')),@js(__('Colore')),@js(__('Materiale')),@js(__('Misura'))]" :key="s">
                                            <button type="button" @click="addSuggestedType(s)"
                                                    class="rounded-full border border-dashed border-gray-300 px-3 py-1 text-xs hover:border-brand-400 hover:text-brand-600"
                                                    x-text="s"></button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Tabella varianti manuali --}}
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 rounded-xl border border-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Foto') }}</th>
                                                <template x-for="(type, tIndex) in form.types" :key="'th'+tIndex">
                                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500" x-text="type.name || @js(__('Caratt. :number')).replace(':number', tIndex + 1)"></th>
                                                </template>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Prezzo (€)') }}</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Pezzi') }}</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                                                <th class="relative px-3 py-2"><span class="sr-only">{{ __('Azioni') }}</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <template x-for="(variant, vIndex) in form.variants" :key="vIndex">
                                                <tr>
                                                    <td class="px-3 py-2">
														<button type="button" @click="openPhotoPicker(vIndex)"
																class="h-12 w-12 overflow-hidden rounded border border-gray-200 bg-gray-100 hover:border-brand-400">
															<img x-show="variantPhotoUrl(variant)" :src="variantPhotoUrl(variant)" class="h-full w-full object-cover">
															<span x-show="!variantPhotoUrl(variant)" class="flex h-full w-full items-center justify-center text-[10px] text-gray-400">{{ __('Scegli') }}</span>
														</button>
														<input type="hidden" :name="'variants['+vIndex+'][photo_index]'" :value="variant.photoIndex ?? ''">
													</td>
                                                    <template x-for="(type, tIndex) in form.types" :key="'td'+tIndex+vIndex">
                                                        <td class="px-3 py-2">
                                                            <input type="text" list="datalist-type-'+tIndex"
                                                                   :value="variant.parts[tIndex] || ''"
                                                                   @input="variant.parts[tIndex] = $event.target.value; refreshVariantLabel(variant)"
                                                                   class="w-28 rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                                            <datalist :id="'datalist-type-'+tIndex">
                                                                <template x-for="val in type.values" :key="val.value">
                                                                    <option :value="val.value"></option>
                                                                </template>
                                                            </datalist>
                                                            <input type="hidden" :name="'variants['+vIndex+'][parts][]'" :value="variant.parts[tIndex] || ''">
                                                        </td>
                                                    </template>
                                                    <td class="px-3 py-2">
                                                        <input type="number" step="0.01" min="0"
                                                               :value="(form.priceDisplay || 0) + (Number(variant.priceExtra) || 0)"
                                                               @input="variant.priceExtra = (Number($event.target.value) || 0) - (Number(form.priceDisplay) || 0)"
                                                               class="w-24 rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                                               placeholder="{{ __('Come prodotto') }}">
                                                        <input type="hidden" :name="'variants['+vIndex+'][price_extra]'" :value="variant.priceExtra">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="number" min="0" :name="'variants['+vIndex+'][stock]'" x-model.number="variant.stock"
                                                               class="w-16 rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                            <input type="checkbox" x-model="variant.always_available" class="rounded border-gray-300 text-brand-600 focus:ring-brand-600">
                                                            {{ __('Sempre disp.') }}
                                                        </label>
                                                    </td>
                                                    <td class="px-3 py-2 text-right">
                                                        <button type="button" @click="removeVariant(vIndex)" class="cursor-pointer rounded bg-red-500 px-2 py-1 text-xs font-semibold text-white hover:bg-red-600">x</button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="form.variants.length === 0">
                                                <td :colspan="form.types.length + 4" class="px-3 py-4 text-center text-sm italic text-gray-500">
                                                    {{ __('Nessuna variante. Clicca "+ Aggiungi variante" qui sotto.') }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <button type="button" @click="addVariantRow()"
                                        class="w-full rounded-xl border border-dashed border-gray-300 py-3 text-sm font-medium text-gray-700 hover:border-brand-400 hover:text-brand-600">
                                    {{ __('+ Aggiungi variante') }}
                                </button>
                                <p class="text-xs text-gray-500">{{ __('Tocca la miniatura per scegliere, tra le foto caricate, quella della variante. Senza foto resta quella di vetrina.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- COLONNA DESTRA --}}
            <div class="space-y-6">
                {{-- Foto con riordino --}}
                <div class="bg-white shadow sm:rounded-2xl">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Foto di Vetrina') }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Usa le frecce per ordinare. La miniatura serve per l\'elenco; l\'immagine grande si vede solo nella scheda prodotto.') }}</p>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <template x-for="(photo, pIndex) in form.photos" :key="photo.uid">
                                <div class="group relative aspect-square overflow-hidden rounded-xl border border-gray-200 bg-gray-100">
                                    <img x-show="photo.url" :src="photo.url" alt="" class="h-full w-full object-cover">
                                    <div x-show="!photo.url" class="h-full w-full animate-pulse bg-gray-200"></div>

                                    <div class="absolute right-1 top-1 z-10">
                                        <input type="radio" :value="photo.uid" x-model="form.coverUid"
                                               class="h-4 w-4 cursor-pointer border-gray-300 bg-white text-brand-600 opacity-80 border border-gray-200 focus:ring-brand-600 hover:opacity-100"
                                               :title="@js(__('Usa come copertina: foto :number')).replace(':number', pIndex + 1)">
                                    </div>

                                    <div class="absolute bottom-1 left-1 z-10 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button type="button" @click="movePhoto(pIndex, -1)" :disabled="pIndex === 0"
                                                class="rounded bg-black/50 px-1.5 text-white disabled:opacity-30 hover:bg-brand-600" title="{{ __('Sposta a sinistra') }}">&#8249;</button>
                                        <button type="button" @click="movePhoto(pIndex, 1)" :disabled="pIndex === form.photos.length - 1"
                                                class="rounded bg-black/50 px-1.5 text-white disabled:opacity-30 hover:bg-brand-600" title="{{ __('Sposta a destra') }}">&#8250;</button>
                                    </div>

                                    <button type="button" @click="removePhoto(pIndex)"
                                            class="absolute bottom-1 right-1 z-10 rounded-full bg-black/50 p-1 text-white opacity-0 transition-opacity hover:bg-red-600 group-hover:opacity-100"
                                            title="{{ __('Elimina foto') }}">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4">
                            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-8 text-center transition-colors hover:border-brand-400 hover:bg-brand-50/50">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="mt-4 flex text-sm leading-6 text-gray-600">
                                    <span class="relative cursor-pointer rounded-xl font-semibold text-brand-600 hover:text-brand-500">
                                        <span>{{ __('Carica immagini') }}</span>
                                        <input type="file" id="photos_input" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="handleFiles($event)">
                                    </span>
                                    <p class="pl-1">{{ __('o trascina qui') }}</p>
                                </div>
                                <p class="text-xs leading-5 text-gray-600">{{ __('PNG, JPG, WEBP fino a 20MB ciascuna. Vengono ridimensionate automaticamente.') }}</p>
                            </label>
                        </div>

                        <input type="hidden" name="remove_photo_ids" :value="form.removedPhotoIds.join(',')">
                        <input type="hidden" name="photo_order" :value="form.photos.filter(p => p.id).map(p => p.id).join(',')">
                        <input type="hidden" name="cover_photo_index" id="cover_photo_index_hidden" value="">
						<div id="sync_payload"></div>
                    </div>
                </div>

                {{-- Visibilità --}}
                <div class="bg-white shadow sm:rounded-2xl">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">{{ __('Visibilità') }}</h3>
                        <div class="mt-4">
                            <select name="visibility" id="visibility" x-model="form.visibility"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                <option value="visible">{{ __('Visibile') }}</option>
                                <option value="hidden">{{ __('Nascosto') }}</option>
                                <option value="coming_soon">{{ __('In arrivo') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fixed inset-x-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-lg md:sticky md:inset-x-auto md:bottom-4 md:z-auto md:mt-6 md:flex-row md:items-center md:justify-end md:gap-3 md:p-4">
                    <button type="submit" :disabled="loading"
                            class="min-h-11 w-full rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50 md:w-auto md:min-w-40">
                        <span x-show="!loading">{{ __('Salva Prodotto') }}</span>
                        <span x-show="loading">{{ __('Salvataggio...') }}</span>
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="w-full py-1 text-center text-sm font-semibold text-gray-900 underline hover:text-gray-700 md:w-auto md:px-3">{{ __('Annulla e torna alla lista') }}</a>
                </div>
            </div>
        </div>
		{{-- Modale scelta foto variante --}}
		<div x-show="showPhotoPicker" x-cloak
			 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
			 @click.self="closePhotoPicker()">
			<div class="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
				<div class="mb-4 flex items-center justify-between">
					<h3 class="text-lg font-semibold text-gray-900">{{ __('Scegli la foto della variante') }}</h3>
					<button type="button" @click="closePhotoPicker()" class="text-gray-400 hover:text-gray-600">
						<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
					</button>
				</div>
				<div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
					<template x-for="(photo, pIdx) in form.photos" :key="pIdx">
						<button type="button" @click="selectVariantPhoto(pIdx)"
								class="group relative aspect-square overflow-hidden rounded border-2 border-transparent hover:border-brand-500">
							<img :src="photo.url" class="h-full w-full object-cover">
							<span class="absolute bottom-1 left-1 rounded bg-black/50 px-1.5 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100"
								  x-text="@js(__('Foto :number')).replace(':number', pIdx + 1)"></span>
						</button>
					</template>
					<button type="button" @click="selectVariantPhoto(null)"
							class="flex aspect-square items-center justify-center rounded border-2 border-dashed border-gray-300 text-sm text-gray-500 hover:border-brand-400 hover:text-brand-600">
						{{ __('Nessuna') }}<br>{{ __('(usa copertina)') }}
					</button>
				</div>
			</div>
		</div>
    </form>
</div>

<style>
[x-cloak] { display: none !important; }
</style>

<script>
function productForm() {
    return {
        loading: false,
        uidSeq: 0,
		showPhotoPicker: false,
		pickingPhotoForVariant: null,

        form: {
            name: @json(old('name', $product->name ?? '')),
            short_description: @json(old('short_description', $product->short_description ?? '')),
            description: @json(old('description', $product->description ?? '')),
            category_id: @json((string) old('category_id', $product->category_id ?? '')),
            priceDisplay: @json(old('price_display', $product->exists ? $product->price_cents / 100 : 0)),
            priceCents: @json(old('price_cents', $product->price_cents ?? 0)),
            track_stock: @json((bool) old('track_stock', $product->track_stock ?? true)),
            stock: @json(old('stock', $product->stock ?? 0)),
            low_stock_threshold: @json(old('low_stock_threshold', $product->low_stock_threshold ?? 3)),
            visibility: @json(old('visibility', $product->visibility ?? 'visible')),
            season_behavior: @json(old('season_behavior', $product->season_behavior ?? 'follow_category')),
            own_fulfillment_days: @json(old('own_fulfillment_days', $product->own_fulfillment_days ?? '')),
            has_variants: @json((bool) old('has_variants', $product->has_variants ?? false)),

            photos: @json($photosJs ?? []),
            removedPhotoIds: [],
            coverUid: null,
            types: @json($typesJs ?? []),
            variants: @json($variantsJs ?? []),
        },

        init() {
            const cover = this.form.photos.find(p => p.is_cover);
            if (cover) this.form.coverUid = cover.uid;

            this.form.variants.forEach(v => {
                if (v.photoId != null) {
                    const idx = this.form.photos.findIndex(p => p.id === v.photoId);
                    v.photoIndex = idx >= 0 ? idx : null;
                }
                if (!Array.isArray(v.parts)) v.parts = [];
            });
        },

        updatePriceCents() {
            this.form.priceCents = Math.round((Number(this.form.priceDisplay) || 0) * 100);
        },

        handleFiles(event) {
            const files = Array.from(event.target.files || []);
            files.forEach(file => {
                if (!file.type || !file.type.startsWith('image/')) return;
                const uid = 'n' + (++this.uidSeq);
                const url = URL.createObjectURL(file);
                this.form.photos.push({
                    uid, id: null, url, file,
                    position: this.form.photos.length,
                    is_cover: false, objectUrl: url,
                });
                if (!this.form.coverUid) this.form.coverUid = uid;
            });
            event.target.value = '';
        },

        movePhoto(index, dir) {
            const target = index + dir;
            if (target < 0 || target >= this.form.photos.length) return;
            const tmp = this.form.photos[index];
            this.form.photos[index] = this.form.photos[target];
            this.form.photos[target] = tmp;
        },

        removePhoto(index) {
            const photo = this.form.photos[index];
            if (photo?.objectUrl) URL.revokeObjectURL(photo.objectUrl);
            if (photo?.id) this.form.removedPhotoIds.push(photo.id);
            if (this.form.coverUid === photo?.uid) this.form.coverUid = null;
            this.form.photos.splice(index, 1);
        },

        addType() { this.form.types.push({ name: '', values: [], newValue: '' }); },
        addSuggestedType(name) {
            if (this.form.types.some(t => t.name === name)) return;
            this.form.types.push({ name, values: [], newValue: '' });
        },
        removeType(index) {
            this.form.types.splice(index, 1);
            this.form.variants.forEach(v => { v.parts.splice(index, 1); this.refreshVariantLabel(v); });
        },

        addVariantRow() {
            this.form.variants.push({
                id: null, label: '', parts: this.form.types.map(() => ''),
                sku: '', priceExtra: 0, stock: 0, always_available: false, photoIndex: null,
            });
        },
        removeVariant(index) { this.form.variants.splice(index, 1); },
        refreshVariantLabel(v) { v.label = v.parts.filter(Boolean).join(' · '); },

        variantPhotoUrl(variant) {
            if (variant.photoIndex == null) return null;
            return this.form.photos[variant.photoIndex]?.url || null;
        },

        prepareForSubmit(e) {
            const priceHidden = document.getElementById('price_cents_hidden');
            if (priceHidden) priceHidden.value = Math.round((Number(this.form.priceDisplay) || 0) * 100);

            const newFiles = this.form.photos.filter(p => p.file).map(p => p.file);
            const fileInput = document.getElementById('photos_input');
            if (fileInput) {
                const dt = new DataTransfer();
                newFiles.forEach(f => dt.items.add(f));
                fileInput.files = dt.files;
            }

            const coverIndexEl = document.getElementById('cover_photo_index_hidden');
            if (coverIndexEl) {
                coverIndexEl.value = '';
                if (this.form.coverUid) {
                    const idx = this.form.photos.findIndex(p => p.uid === this.form.coverUid);
                    if (idx >= 0) coverIndexEl.value = idx;
                }
            }

            this.collectTypeValuesFromVariants();
			this.syncTypeValuesInputs();

			const form = e.target;
			if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;
			this.loading = true;
			form.submit();
        },
		
		collectTypeValuesFromVariants() {
			this.form.types.forEach((type, tIndex) => {
				const usedValues = new Set();
				this.form.variants.forEach(v => {
					const val = (v.parts[tIndex] || '').trim();
					if (val) usedValues.add(val);
				});
				type.values = Array.from(usedValues).map(v => ({ value: v }));
			});
		},
		syncTypeValuesInputs() {
			const container = document.getElementById('sync_payload');
			if (!container) return;
			container.innerHTML = '';
			this.form.types.forEach((type, tIndex) => {
				(type.values || []).forEach(v => {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'option_types[' + tIndex + '][values][]';
					input.value = v.value;
					container.appendChild(input);
				});
			});
		},
		openPhotoPicker(vIndex) {
			this.pickingPhotoForVariant = vIndex;
			this.showPhotoPicker = true;
		},
		selectVariantPhoto(photoIndex) {
			if (this.pickingPhotoForVariant !== null) {
				this.form.variants[this.pickingPhotoForVariant].photoIndex = photoIndex;
			}
			this.showPhotoPicker = false;
			this.pickingPhotoForVariant = null;
		},
		closePhotoPicker() {
			this.showPhotoPicker = false;
			this.pickingPhotoForVariant = null;
		},
		variantPhotoUrl(variant) {
			if (variant.photoIndex == null) return null;
			return this.form.photos[variant.photoIndex]?.url || null;
		},
    };
}
</script>
@endsection