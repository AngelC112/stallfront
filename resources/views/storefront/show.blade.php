<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $product->name }} | {{ \App\Models\Setting::get('store.name', config('app.name')) }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-pwa-head />
    </head>
    <body class="bg-canvas text-gray-900 antialiased">
        <x-store-header :cart-count="$cartCount" />

        <main class="mx-auto max-w-6xl px-4 py-5 sm:px-5 sm:py-6">
            @if (session('status'))
                <p class="mb-5 rounded-xl bg-emerald-100 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</p>
            @endif

            @php
                $images = $product->photos->sortBy([['is_cover', 'desc'], ['position', 'asc'], ['id', 'asc']])->values();
                $primaryImage = $variants->first()['image'] ?? $images->first()?->url ?? $product->coverPhotoUrl();
                $availableStock = (int) $product->available_stock;
                $lowStockThreshold = (int) ($product->low_stock_threshold ?: 3);
            @endphp

            <div class="mb-5 text-sm text-slate-500">
                <a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('Home') }}</a>
                <span class="mx-2">/</span>
                @if ($product->category?->is_active)
                    <a href="{{ route('storefront.category', ['category' => $product->category]) }}" class="hover:text-slate-700">{{ $product->category->name }}</a>
                @else
                    <span>{{ $product->category?->name ?? __('Catalogo') }}</span>
                @endif
                <span class="mx-2">/</span>
                <span class="text-slate-800">{{ $product->name }}</span>
            </div>

            <div
                class="grid min-w-0 gap-6 lg:grid-cols-[1.08fr_0.92fr] lg:gap-8"
                x-data="{
                    variants: @js($variants),
                    optionGroups: @js($optionGroups),
                    images: @js($images->map(fn ($image) => ['url' => $image->url, 'alt' => $image->alt ?: $product->name])->values()),
                    selectedVariantId: @js($variants->first()['id'] ?? null),
                    selectedOptions: @js($variants->first()['options'] ?? []),
                    selectedImage: @js($primaryImage),
                    lightboxOpen: false,
                    productName: @js($product->name),
                    defaultImage: @js($images->first()?->url ?? $product->coverPhotoUrl()),
                    basePriceCents: @js($product->price_cents),
                    baseAvailableStock: @js($availableStock),
                    trackStock: @js((bool) $product->track_stock),
                    lowStockThreshold: @js($lowStockThreshold),
                    requiresVariant: @js($variants->isNotEmpty()),
                    quantity: 1,
                    selectVariant(id, image) {
                        this.selectedVariantId = id;
                        this.selectedImage = image || this.defaultImage;
                        this.normalizeQuantity();
                    },
                    isOptionAvailable(groupId, valueId) {
                        return this.variants.some((variant) => Number(variant.options[groupId]) === valueId);
                    },
                    selectOption(groupId, valueId) {
                        const candidates = this.variants.filter((variant) => Number(variant.options[groupId]) === valueId);
                        const matchesCurrentOptions = (variant) => Object.entries(this.selectedOptions).every(([selectedGroupId, selectedValueId]) => {
                            return Number(selectedGroupId) === groupId || Number(variant.options[selectedGroupId]) === Number(selectedValueId);
                        });
                        const compatibleVariant = candidates.find(matchesCurrentOptions) || candidates[0];

                        if (!compatibleVariant) return;

                        this.selectedOptions = { ...compatibleVariant.options };
                        this.selectedVariantId = compatibleVariant.id;
                        this.selectedImage = compatibleVariant.image || this.defaultImage;
                        this.normalizeQuantity();
                    },
                    moveImage(direction) {
                        if (this.images.length < 2) return;

                        const currentIndex = this.images.findIndex((image) => image.url === this.selectedImage);
                        const nextIndex = currentIndex === -1
                            ? 0
                            : (currentIndex + direction + this.images.length) % this.images.length;

                        this.selectedImage = this.images[nextIndex].url;
                    },
                    openLightbox() {
                        if (this.selectedImage) this.lightboxOpen = true;
                    },
                    selectedVariant() {
                        return this.variants.find((item) => item.id === this.selectedVariantId) || null;
                    },
                    selectedAvailableStock() {
                        return this.selectedVariant()?.availableStock ?? this.baseAvailableStock;
                    },
                    maximumQuantity() {
                        return this.trackStock ? Math.max(1, Math.min(99, this.selectedAvailableStock())) : 99;
                    },
                    decrementQuantity() {
                        this.quantity = Math.max(1, Number(this.quantity || 1) - 1);
                    },
                    incrementQuantity() {
                        this.quantity = Math.min(this.maximumQuantity(), Number(this.quantity || 1) + 1);
                    },
                    normalizeQuantity() {
                        const enteredQuantity = Number.parseInt(this.quantity, 10) || 1;
                        this.quantity = Math.max(1, Math.min(this.maximumQuantity(), enteredQuantity));
                    },
                    isSoldOut() {
                        return this.trackStock && this.selectedAvailableStock() <= 0;
                    },
                    canAddToCart() {
                        return (!this.requiresVariant || this.selectedVariant()) && !this.isSoldOut();
                    },
                    formatPrice(cents) {
                        return new Intl.NumberFormat(@js(\App\Support\Locales::htmlLang()), { style: 'currency', currency: @js(config('app.currency', 'EUR')) }).format(cents / 100);
                    },
                }"
                @keydown.window.escape="lightboxOpen = false"
                @keydown.window.right.prevent="if (lightboxOpen) moveImage(1)"
                @keydown.window.left.prevent="if (lightboxOpen) moveImage(-1)"
            >
                <div class="min-w-0 rounded-2xl border border-brand-200 bg-brand-100 p-3 sm:rounded-[1.5rem] sm:p-4">
                    @if ($primaryImage)
                        <div class="relative flex aspect-[4/3] max-h-[560px] items-center justify-center overflow-hidden rounded-xl bg-white/40 sm:aspect-[5/4] lg:aspect-[4/3]">
                            <button type="button" @click="openLightbox()" aria-label="{{ __('Apri immagine a tutto schermo') }}" title="{{ __('Apri immagine a tutto schermo') }}" class="flex h-full w-full cursor-zoom-in items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-600">
                                <img x-bind:src="selectedImage" src="{{ $primaryImage }}" x-bind:alt="images.find((image) => image.url === selectedImage)?.alt || productName" class="h-full w-full object-contain" />
                            </button>
                            @if ($images->count() > 1)
                                <button type="button" @click="moveImage(-1)" aria-label="{{ __('Foto precedente') }}" title="{{ __('Foto precedente') }}" class="absolute left-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-2xl text-brand-700 shadow-sm transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-700">←</button>
                                <button type="button" @click="moveImage(1)" aria-label="{{ __('Foto successiva') }}" title="{{ __('Foto successiva') }}" class="absolute right-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-2xl text-brand-700 shadow-sm transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-700">→</button>
                            @endif
                        </div>
                    @else
                        <div class="flex aspect-[4/3] items-center justify-center rounded-xl bg-white/40 text-sm font-medium text-brand-600">
                            {{ __('Nessuna foto disponibile') }}
                        </div>
                    @endif

                    <div x-show="lightboxOpen" x-cloak @click.self="lightboxOpen = false" role="dialog" aria-modal="true" aria-label="{{ __('Immagine prodotto a tutto schermo') }}" class="fixed inset-0 z-50 flex items-center justify-center bg-black/95 p-3 sm:p-6">
                        <button type="button" @click="lightboxOpen = false" aria-label="{{ __('Chiudi immagine') }}" title="{{ __('Chiudi') }}" class="absolute right-4 top-4 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-3xl leading-none text-white hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">{{ __('×') }}</button>
                        @if ($images->count() > 1)
                            <button type="button" @click="moveImage(-1)" aria-label="{{ __('Foto precedente') }}" title="{{ __('Foto precedente') }}" class="absolute left-3 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:left-6">←</button>
                            <button type="button" @click="moveImage(1)" aria-label="{{ __('Foto successiva') }}" title="{{ __('Foto successiva') }}" class="absolute right-3 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:right-6">→</button>
                        @endif
                        <img x-bind:src="selectedImage" src="{{ $primaryImage }}" x-bind:alt="images.find((image) => image.url === selectedImage)?.alt || productName" class="max-h-full max-w-full object-contain" />
                        @if ($images->count() > 1)
                            <p class="absolute bottom-4 rounded-full bg-black/50 px-3 py-1 text-sm font-medium text-white" x-text="(images.findIndex((image) => image.url === selectedImage) + 1) + ' / ' + images.length"></p>
                        @endif
                    </div>

                    @if ($images->count() > 1)
                        <div class="mt-4 flex gap-3 overflow-x-auto pb-1">
                            @foreach ($images as $image)
                                <button
                                    type="button"
                                    @click="selectedImage = '{{ $image->url }}'"
                                    aria-label="Mostra foto {{ $loop->iteration }} di {{ $product->name }}"
                                    class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border bg-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 sm:h-20 sm:w-20"
                                    :class="selectedImage === '{{ $image->url }}' ? 'border-brand-600 ring-2 ring-brand-600/20' : 'border-brand-200'"
                                >
                                    <img src="{{ $image->thumbUrl() }}" alt="" class="h-full w-full object-cover" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="min-w-0 pt-1 sm:pt-2">
                    <h1 class="mt-2 break-words text-3xl font-black text-gray-900 sm:text-4xl lg:text-5xl">{{ $product->name }}</h1>
                    @if ($product->short_description)
                        <p class="mt-3 text-sm text-slate-600 sm:text-base">{{ $product->short_description }}</p>
                    @endif
                    <div class="mt-4 text-3xl font-black text-gray-900 sm:text-4xl"><span x-text="formatPrice(selectedVariant()?.priceCents ?? basePriceCents)">{{ \App\Support\Format::money($product->price_cents) }}</span></div>

                    @if ($product->track_stock)
                        <div class="mt-5 inline-flex rounded-xl bg-red-100 px-3 py-1 text-sm font-semibold text-red-700" x-show="isSoldOut()">{{ __('Esaurito') }}</div>
                        <div class="mt-5 inline-flex rounded-xl bg-[#f3d8a9] px-3 py-1 text-sm font-semibold text-[#5a430d]" x-show="!isSoldOut() && selectedAvailableStock() <= lowStockThreshold">
                            {{ __('Ultimi') }} <span class="mx-1" x-text="selectedAvailableStock()">{{ $availableStock }}</span> {{ __('pezzi') }}
                        </div>
                        <div class="mt-5 inline-flex rounded-xl bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700" x-show="!isSoldOut() && selectedAvailableStock() > lowStockThreshold">{{ __('Disponibile') }}</div>
                    @endif

                    @if ($optionGroups->isNotEmpty())
                        @foreach ($optionGroups as $optionGroup)
                            <div class="mt-6 sm:mt-8">
                                <div class="mb-3 text-base font-semibold text-brand-600 sm:text-lg">{{ $optionGroup['name'] }}</div>
                                <div class="flex flex-wrap gap-2 sm:gap-3">
                                    @foreach ($optionGroup['values'] as $value)
                                        <button
                                            type="button"
                                            @click="selectOption({{ $optionGroup['id'] }}, {{ $value['id'] }})"
                                            :aria-pressed="Number(selectedOptions[{{ $optionGroup['id'] }}]) === {{ $value['id'] }}"
                                            :disabled="!isOptionAvailable({{ $optionGroup['id'] }}, {{ $value['id'] }})"
                                            :class="Number(selectedOptions[{{ $optionGroup['id'] }}]) === {{ $value['id'] }} ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-600/20' : 'border-brand-200 bg-canvas'"
                                            class="rounded-full border px-3 py-2 text-sm font-medium text-gray-800 transition hover:border-brand-600 disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 sm:px-4 sm:text-base"
                                        >
                                            {{ $value['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @elseif ($variants->isNotEmpty())
                        <div class="mt-6 sm:mt-8">
                            <div class="mb-3 text-base font-semibold text-brand-600 sm:text-lg">{{ __('Varianti disponibili') }}</div>
                            <div class="flex flex-wrap gap-2 sm:gap-3">
                                @foreach ($variants as $variant)
                                    <button type="button" @click="selectVariant({{ $variant['id'] }}, $el.dataset.image)" data-image="{{ $variant['image'] }}" :aria-pressed="selectedVariantId === {{ $variant['id'] }}" :class="selectedVariantId === {{ $variant['id'] }} ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-600/20' : 'border-brand-200 bg-canvas'" class="rounded-full border px-3 py-2 text-sm font-medium text-gray-800 sm:px-4 sm:text-base">{{ $variant['label'] }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($product->description)
                        <div class="mt-6 rounded-xl border border-gray-200 bg-canvas p-4 sm:mt-8">
                            <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-500">{{ __('Descrizione') }}</h2>
                            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $product->description }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('cart.store', ['product' => $product]) }}" class="mt-8">
                        @csrf
                        <input type="hidden" name="variant_id" x-bind:value="selectedVariantId || ''">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="w-full sm:w-auto">
                                <label for="product-quantity" class="block text-sm font-medium text-slate-700">{{ __('Quantità') }}</label>
                                <div class="mt-1 inline-flex h-12 items-center rounded-xl border border-brand-200 bg-white">
                                    <button type="button" @click="decrementQuantity()" :disabled="quantity <= 1" aria-label="{{ __('Diminuisci quantità') }}" title="{{ __('Diminuisci quantità') }}" class="inline-flex h-11 w-11 items-center justify-center text-xl font-semibold text-brand-600 hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40">−</button>
                                    <input id="product-quantity" type="number" name="quantity" min="1" x-bind:max="maximumQuantity()" required x-model.number="quantity" @change="normalizeQuantity()" class="h-11 w-16 border-0 bg-transparent px-1 text-center text-base font-semibold text-gray-900 focus:ring-0">
                                    <button type="button" @click="incrementQuantity()" :disabled="quantity >= maximumQuantity()" aria-label="{{ __('Aumenta quantità') }}" title="{{ __('Aumenta quantità') }}" class="inline-flex h-11 w-11 items-center justify-center text-xl font-semibold text-brand-600 hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40">+</button>
                                </div>
                            </div>
                            <button type="submit" x-show="canAddToCart()" class="flex min-h-12 flex-1 items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-base font-bold text-white shadow-sm transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-700 focus-visible:ring-offset-2">
                                {{ __('Aggiungi al carrello') }} · <span class="ml-1" x-text="formatPrice((selectedVariant()?.priceCents ?? basePriceCents) * quantity)">{{ \App\Support\Format::money($product->price_cents) }}</span>
                            </button>
                            @if ($product->track_stock)
                                <p x-show="isSoldOut()" class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ __('Questa variante è esaurita.') }}</p>
                            @endif
                        </div>
                        @error('variant_id')
                            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                        @error('quantity')
                            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </form>

                    @if ($product->track_stock)
                        <form method="POST" action="{{ route('back-in-stock.store', ['product' => $product]) }}" x-show="isSoldOut()" class="mt-4 rounded-xl border border-brand-200 bg-white p-4">
                            @csrf
                            <input type="hidden" name="variant_id" x-bind:value="selectedVariantId || ''">
                            <label for="back-in-stock-email" class="block text-sm font-semibold text-gray-900">{{ __('Vuoi sapere quando torna disponibile?') }}</label>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input id="back-in-stock-email" type="email" name="email" value="@auth{{ auth()->user()->email }}@endauth" required maxlength="255" placeholder="{{ __('La tua email') }}" class="min-w-0 flex-1 rounded-xl border-brand-200 bg-white px-3 py-3 text-base">
                                <button type="submit" class="rounded-xl border border-brand-600 px-4 py-3 text-sm font-bold text-brand-600 hover:bg-brand-50">{{ __('Avvisami') }}</button>
                            </div>
                            @error('email')
                                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                            @error('variant_id')
                                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </form>
                    @endif
                </div>
            </div>
        </main>
        <x-site-footer />
        <x-cookie-banner />
    </body>
</html>
