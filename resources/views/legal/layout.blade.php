<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') | {{ \App\Models\Setting::get('store.name', config('app.name')) }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-pwa-head />
    </head>
    <body class="bg-canvas text-gray-900 antialiased">
        <x-store-header :cart-count="collect(session('cart', []))->sum('quantity')" :active="request()->routeIs('where-we-are') ? 'where' : null" />

        <main class="mx-auto max-w-3xl px-4 py-8 sm:px-5 sm:py-12">
            <article class="space-y-5 rounded-2xl bg-white p-6 text-[15px] leading-relaxed text-gray-800 border border-gray-200 sm:p-10 [&_h1]:text-3xl [&_h1]:font-black [&_h1]:text-gray-900 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-gray-900 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6 [&_a]:underline">
                @yield('content')
                @if (! empty($updated_at))
                    <p class="border-t border-gray-100 pt-4 text-xs text-gray-500">{{ __('Ultimo aggiornamento: :date', ['date' => \App\Support\Format::date($updated_at)]) }}</p>
                @endif
            </article>
        </main>

        <x-site-footer />
        <x-cookie-banner />
    </body>
</html>
