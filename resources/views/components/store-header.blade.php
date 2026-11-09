@props(['cartCount' => 0, 'search' => '', 'active' => null, 'showCart' => true])
@php($storeName = \App\Models\Setting::get('store.name', config('app.name')))
<header class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-3 sm:gap-4 sm:px-6 lg:gap-8">
        <a href="{{ route('home') }}" class="min-w-0 truncate text-xl font-bold uppercase text-brand-600 sm:text-2xl">{{ $storeName }}</a>

        <nav aria-label="{{ __('Menu principale') }}" class="hidden shrink-0 items-center gap-6 text-base lg:flex">
            <a href="{{ route('home') }}" @class(['font-bold text-brand-600' => $active === 'home', 'text-gray-800 hover:text-brand-600' => $active !== 'home'])>{{ __('Vetrina') }}</a>
            <a href="{{ route('where-we-are') }}" @class(['font-bold text-brand-600' => $active === 'where', 'text-gray-800 hover:text-brand-600' => $active !== 'where'])>{{ __('Dove siamo') }}</a>
            @auth
                <a href="{{ route('account.orders.index') }}" class="text-gray-800 hover:text-brand-600">{{ __('I miei ordini') }}</a>
            @endauth
        </nav>

        <form method="GET" action="{{ route('home') }}" role="search" class="hidden flex-1 md:block">
            <label for="header-search" class="sr-only">{{ __('Cerca un prodotto') }}</label>
            <input id="header-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cerca un prodotto') }}"
                   class="block min-h-11 w-full max-w-xl rounded-xl border border-gray-300 bg-white px-4 text-base">
        </form>

        <div class="ml-auto flex shrink-0 items-center gap-2">
            <x-locale-switcher compact />
            @if ($showCart)
                <a href="{{ route('cart.index') }}" class="chip">{{ __('Carrello ·') }} {{ $cartCount }}</a>
            @endif
            @auth
                @if (auth()->user()->hasPermission(\App\Support\Permission::DASHBOARD_VIEW))
                    <a href="{{ route('admin.dashboard') }}" class="chip hidden sm:inline-flex">{{ __('Pannello') }}</a>
                @endif
                <a href="{{ route('account.edit') }}" class="chip hidden sm:inline-flex">{{ __('Il mio account') }}</a>
                <a href="{{ route('account.edit') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-brand-200 text-brand-700 sm:hidden" aria-label="{{ __('Il mio account') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="chip px-3 sm:px-4">{{ __('Accedi') }}</a>
                <a href="{{ route('register') }}" class="chip chip-active px-3 sm:px-4">{{ __('Registrati') }}</a>
            @endauth
        </div>
    </div>
</header>
