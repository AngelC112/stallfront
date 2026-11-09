<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\Setting::get('store.name', config('app.name')) }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-pwa-head />
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-canvas">
            <div class="mb-4 flex w-full justify-end px-4 sm:absolute sm:right-4 sm:top-4 sm:mb-0 sm:w-auto">
                <x-locale-switcher compact />
            </div>
            <div>
                <a href="{{ route('home') }}" class="block text-center text-2xl font-bold uppercase text-brand-600">
					{{ \App\Models\Setting::get('store.name', config('app.name')) }}
				</a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white border border-gray-200 overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>
        <x-site-footer />
        <x-cookie-banner />
    </body>
</html>
