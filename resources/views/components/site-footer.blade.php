@php
    $footerName = \App\Models\Setting::get('store.name', config('app.name'));
    $footerAddress = \App\Models\Setting::get('store.address', '');
    $footerVat = \App\Models\Setting::get('store.vat', '');
    $footerEmail = \App\Models\Setting::get('store.email', '');
    $footerPhone = \App\Models\Setting::get('store.phone', '');
@endphp
<footer class="mt-10 border-t border-gray-200 bg-white text-sm text-gray-700">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 md:grid-cols-3 md:gap-10">
        <div class="space-y-1">
            <p class="text-base font-bold uppercase text-brand-600">{{ $footerName }}</p>
            @if ($footerAddress)<p>{{ $footerAddress }}</p>@endif
            @if ($footerVat)<p>{{ __('P.IVA') }} {{ $footerVat }}</p>@endif
            @if ($footerEmail || $footerPhone)
                <p>{{ $footerEmail }}@if ($footerEmail && $footerPhone) · @endif{{ $footerPhone }}</p>
            @endif
        </div>
        <nav aria-label="{{ __('Il negozio') }}" class="flex flex-col">
            <p class="mb-1 font-bold text-gray-900">{{ __('Il negozio') }}</p>
            <a href="{{ route('where-we-are') }}" class="inline-flex min-h-11 items-center hover:text-brand-600 hover:underline md:min-h-9">{{ __('Dove siamo') }}</a>
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-brand-600 hover:underline md:min-h-9">{{ __('Vetrina') }}</a>
        </nav>
        <nav aria-label="{{ __('Informazioni legali') }}" class="flex flex-col">
            <p class="mb-1 font-bold text-gray-900">{{ __('Informazioni legali') }}</p>
            <a href="{{ route('legal.privacy') }}" class="inline-flex min-h-11 items-center hover:text-brand-600 hover:underline md:min-h-9">{{ __('Privacy Policy') }}</a>
            <a href="{{ route('legal.cookies') }}" class="inline-flex min-h-11 items-center hover:text-brand-600 hover:underline md:min-h-9">{{ __('Cookie Policy') }}</a>
            <a href="{{ route('legal.terms') }}" class="inline-flex min-h-11 items-center hover:text-brand-600 hover:underline md:min-h-9">{{ __('Termini di vendita') }}</a>
            <button type="button" onclick="window.openCookieSettings && window.openCookieSettings()" class="inline-flex min-h-11 items-center text-left hover:text-brand-600 hover:underline md:min-h-9">{{ __('Gestisci cookie') }}</button>
        </nav>
    </div>
</footer>
