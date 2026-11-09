{{--
    Campo indirizzo con completamento automatico (OpenStreetMap).
    Il cliente scrive la via, sceglie un suggerimento e compaiono civico, CAP, comune e provincia.
    Un indirizzo non scelto dall'elenco non viene accettato.
--}}
@props(['user' => null, 'label' => __('Indirizzo'), 'hint' => __('Serve solo per la consegna a domicilio.')])
@php
    $current = [
        'street' => old('street', $user?->street ?? ''),
        'street_number' => old('street_number', $user?->street_number ?? ''),
        'postal_code' => old('postal_code', $user?->postal_code ?? ''),
        'city' => old('city', $user?->city ?? ''),
        'province' => old('province', $user?->province ?? ''),
        'latitude' => old('latitude', $user?->street ? $user->latitude : ''),
        'longitude' => old('longitude', $user?->street ? $user->longitude : ''),
        'token' => old('address_token', ''),
        'search' => old('address_search', $user?->address ?? ''),
    ];
@endphp

<div
    x-data="addressField(@js($current), @js(route('address.suggest')))"
    @click.outside="open = false"
    class="space-y-3"
>
    <div class="relative">
        <x-input-label for="address_search" :value="$label" />
        <x-text-input
            id="address_search" name="address_search" type="text" class="mt-1 block w-full"
            x-model="search" @input.debounce.350ms="lookup()" @focus="open = results.length > 0" @keydown.escape="open = false"
            autocomplete="off" :placeholder="__('Scrivi la via, es. Via Roma 1, Milano')"
            role="combobox" aria-autocomplete="list" x-bind:aria-expanded="open.toString()" aria-controls="address-suggestions"
        />

        <ul x-show="open" x-cloak id="address-suggestions" role="listbox"
            class="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
            <template x-for="(item, index) in results" :key="index">
                <li role="option">
                    <button type="button" @click="choose(item)"
                            class="block min-h-11 w-full px-4 py-2 text-left text-sm text-gray-900 hover:bg-brand-50 focus:bg-brand-50 focus:outline-none"
                            x-text="item.label"></button>
                </li>
            </template>
        </ul>

        <p x-show="loading" x-cloak class="mt-1 text-sm text-gray-500">{{ __('Cerco l’indirizzo…') }}</p>
        <p x-show="notFound" x-cloak class="mt-1 text-sm text-gray-600">{{ __('Nessun indirizzo trovato. Controlla come hai scritto la via e il comune.') }}</p>
        <p x-show="unavailable" x-cloak class="mt-1 text-sm text-[#8a2a2a]">{{ __('Il servizio degli indirizzi non risponde. Puoi lasciare il campo vuoto e inserirlo più tardi dal tuo account.') }}</p>
        @if ($hint)
            <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
        @endif
        <x-input-error :messages="$errors->get('address_search')" class="mt-2" />
    </div>

    <input type="hidden" name="street" x-model="chosen.street">
    <input type="hidden" name="postal_code" x-model="chosen.postal_code">
    <input type="hidden" name="city" x-model="chosen.city">
    <input type="hidden" name="province" x-model="chosen.province">
    <input type="hidden" name="latitude" x-model="chosen.latitude">
    <input type="hidden" name="longitude" x-model="chosen.longitude">
    <input type="hidden" name="address_token" x-model="chosen.token">

    {{-- Compaiono dopo la scelta: civico da completare, il resto è compilato dall'indirizzo --}}
    <div x-show="chosen.street" x-cloak class="grid gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 sm:grid-cols-6">
        <div class="sm:col-span-4">
            <x-input-label for="address_street_view" :value="__('Via')" />
            <input id="address_street_view" type="text" x-bind:value="chosen.street" readonly tabindex="-1" class="mt-1 block min-h-11 w-full rounded-xl border border-gray-200 bg-gray-100 px-3 text-gray-700">
        </div>
        <div class="sm:col-span-2">
            <x-input-label for="street_number" :value="__('Numero civico')" />
            <x-text-input id="street_number" name="street_number" type="text" maxlength="20" x-model="chosen.street_number" class="mt-1 block w-full" :placeholder="__('Es. 12')" autocomplete="off" />
        </div>
        <div class="sm:col-span-2">
            <x-input-label for="address_cap_view" :value="__('CAP')" />
            <input id="address_cap_view" type="text" x-bind:value="chosen.postal_code" readonly tabindex="-1" class="mt-1 block min-h-11 w-full rounded-xl border border-gray-200 bg-gray-100 px-3 text-gray-700" placeholder="—">
        </div>
        <div class="sm:col-span-2">
            <x-input-label for="address_city_view" :value="__('Comune')" />
            <input id="address_city_view" type="text" x-bind:value="chosen.city" readonly tabindex="-1" class="mt-1 block min-h-11 w-full rounded-xl border border-gray-200 bg-gray-100 px-3 text-gray-700">
        </div>
        <div class="sm:col-span-2">
            <x-input-label for="address_province_view" :value="__('Provincia')" />
            <input id="address_province_view" type="text" x-bind:value="chosen.province" readonly tabindex="-1" class="mt-1 block min-h-11 w-full rounded-xl border border-gray-200 bg-gray-100 px-3 text-gray-700" placeholder="—">
        </div>
        <p class="text-sm text-gray-600 sm:col-span-6">{{ __('Via, CAP e comune vengono dall’indirizzo scelto. Per cambiarli, scrivi di nuovo l’indirizzo qui sopra.') }}</p>
    </div>
</div>

@once
        <script>
            document.addEventListener('alpine:init', function () {
                Alpine.data('addressField', function (initial, url) {
                    return {
                        search: initial.search,
                        results: [],
                        open: false,
                        loading: false,
                        notFound: false,
                        unavailable: false,
                        savedSearch: initial.search,
                        chosen: {
                            street: initial.street, street_number: initial.street_number, postal_code: initial.postal_code,
                            city: initial.city, province: initial.province, latitude: initial.latitude,
                            longitude: initial.longitude, token: initial.token
                        },
                        lookup: function () {
                            var self = this;
                            var term = this.search.trim();

                            // Se il testo cambia, l'indirizzo scelto prima non vale più.
                            if (term !== this.savedSearch) {
                                this.chosen = { street: '', street_number: '', postal_code: '', city: '', province: '', latitude: '', longitude: '', token: '' };
                            }

                            this.notFound = false;
                            this.unavailable = false;

                            if (term.length < 4) {
                                this.results = [];
                                this.open = false;
                                return;
                            }

                            this.loading = true;
                            fetch(url + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                                .then(function (response) { return response.ok ? response.json() : { available: false, results: [] }; })
                                .then(function (data) {
                                    if (term !== self.search.trim()) { return; }
                                    self.results = data.results;
                                    self.unavailable = !data.available;
                                    self.notFound = data.available && data.results.length === 0;
                                    self.open = data.results.length > 0;
                                })
                                .catch(function () { self.unavailable = true; })
                                .finally(function () { self.loading = false; });
                        },
                        choose: function (item) {
                            this.chosen = {
                                street: item.street, street_number: item.street_number, postal_code: item.postal_code,
                                city: item.city, province: item.province, latitude: item.latitude,
                                longitude: item.longitude, token: item.token
                            };
                            this.search = item.label;
                            this.savedSearch = item.label;
                            this.open = false;
                            this.results = [];
                            this.$nextTick(function () {
                                var number = document.getElementById('street_number');
                                if (number && !number.value) { number.focus(); }
                            });
                        }
                    };
                });
            });
        </script>
@endonce
