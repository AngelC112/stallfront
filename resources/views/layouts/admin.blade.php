<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ \App\Models\Setting::get('store.name', config('app.name')) }} - {{ __('Pannello') }}</title>

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-pwa-head />
    
    <!-- Alpine.js CDN fallback se Vite non carica subito (opzionale, meglio usare npm) -->
    <!-- Assicurati che resources/js/app.js importi Alpine -->
</head>
<body class="font-sans antialiased bg-canvas text-gray-900">

<div x-data="{ sidebarOpen: false }" class="min-h-screen flex flex-col md:flex-row">

    {{-- Sidebar Desktop --}}
    <aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 z-40 bg-brand-600">
        <div class="flex grow flex-col gap-y-5 overflow-y-auto p-4">
            <div class="flex h-16 shrink-0 items-center px-2">
                <a href="{{ route('home') }}" class="text-xl font-bold uppercase leading-tight text-white">
                    {{ \App\Models\Setting::get('store.name', 'Admin') }}
                </a>
            </div>
            
            <nav class="flex flex-1 flex-col">
                <ul role="list" class="flex flex-1 flex-col gap-y-7">
                    <li>
                        <ul role="list" class="-mx-2 space-y-1">
                            
                            {{-- Dashboard --}}
                            <li>
                                <a href="{{ route('admin.dashboard') }}" 
                                   class="{{ request()->routeIs('admin.dashboard') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                    {{ __('Dashboard') }}
                                </a>
                            </li>

                            {{-- Ordini --}}
                            <li>
                                <a href="{{ route('admin.orders.index') }}"
                                   class="{{ request()->routeIs('admin.orders.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>
                                    {{ __('Ordini') }}
                                </a>
                            </li>

                            {{-- Banco --}}
                            @if (auth()->user()->hasPermission(\App\Support\Permission::COUNTER_USE))
                                <li>
                                    <a href="{{ route('admin.banco.index') }}"
                                       class="{{ request()->routeIs('admin.banco.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM13.5 14.25h1.5v1.5h-1.5v-1.5zM16.5 17.25h1.5v1.5h-1.5v-1.5zM19.5 14.25h1.5v1.5h-1.5v-1.5zM16.5 14.25h1.5v1.5h-1.5v-1.5zM13.5 19.5h1.5v1.5h-1.5v-1.5zM19.5 19.5h1.5v1.5h-1.5v-1.5z" /></svg>
                                        {{ __('Banco') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Prodotti --}}
                            <li>
                                <a href="{{ route('admin.products.index') }}" 
                                   class="{{ request()->routeIs('admin.products.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                                    {{ __('Prodotti') }}
                                </a>
                            </li>

                            {{-- Categorie --}}
                            <li>
                                <a href="{{ route('admin.categories.index') }}" 
                                   class="{{ request()->routeIs('admin.categories.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                    {{ __('Categorie') }}
                                </a>
                            </li>
                            
                                     {{-- Mercati --}}
                                     @if (auth()->user()->hasPermission(\App\Support\Permission::SETTINGS_MANAGE))
                                         <li>
                                            <a href="{{ route('admin.markets.index') }}"
                                               class="{{ request()->routeIs('admin.markets.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                                <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M18 18.75V7.5a2.25 2.25 0 00-2.25-2.25H6.25A2.25 2.25 0 004 7.5v11.25m2.25 0H20.25c1.243 0 2.25-1.007 2.25-2.25V15c0-1.243-1.007-2.25-2.25-2.25H2.25c-1.243 0-2.25 1.007-2.25 2.25v3.75c0 1.243 1.007 2.25 2.25 2.25z" /></svg>
                                                {{ __('Mercati') }}
                                            </a>
                                        </li>
                                    @endif

                            {{-- Notifiche --}}
                            @if (auth()->user()->hasPermission(\App\Support\Permission::SETTINGS_MANAGE))
                                <li>
                                    <a href="{{ route('admin.notifications.index') }}"
                                       class="{{ request()->routeIs('admin.notifications.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                                        {{ __('Notifiche') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Ferie e chiusure --}}
                            @if (auth()->user()->hasPermission(\App\Support\Permission::CLOSURES_HOLIDAYS_MANAGE) || auth()->user()->hasPermission(\App\Support\Permission::CLOSURES_EXCEPTION_MANAGE))
                                <li>
                                    <a href="{{ route('admin.holidays.index') }}"
                                       class="{{ request()->routeIs('admin.holidays.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                                        {{ __('Ferie e chiusure') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Clienti --}}
                            <li>
                                <a href="{{ route('admin.customers.index') }}"
                                   class="{{ request()->routeIs('admin.customers.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                                    {{ __('Clienti') }}
                                </a>
                            </li>

                            {{-- Collaboratori --}}
                            @if (auth()->user()->isSeller())
                                <li>
                                    <a href="{{ route('admin.collaborators.index') }}"
                                       class="{{ request()->routeIs('admin.collaborators.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                                        {{ __('Collaboratori') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Impostazioni e registro attività --}}
                            @if (auth()->user()->isSeller())
                                <li>
                                    <a href="{{ route('admin.settings.edit') }}"
                                       class="{{ request()->routeIs('admin.settings.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        {{ __('Impostazioni') }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('admin.legal.index') }}"
                                       class="{{ request()->routeIs('admin.legal.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                        {{ __('Testi legali') }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('admin.activity-log.index') }}"
                                       class="{{ request()->routeIs('admin.activity-log.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        {{ __('Registro attività') }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('admin.email-log.index') }}"
                                       class="{{ request()->routeIs('admin.email-log.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                        {{ __('Email inviate') }}
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                    
					
                     {{-- Footer Sidebar --}}
                     <li class="mt-auto space-y-1">
                        <a href="{{ route('home') }}" class="group flex gap-x-3 rounded-xl p-2 leading-6 font-medium text-white/85 hover:bg-white/10 hover:text-white">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.125 1.125 0 011.592 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                            {{ __('Vetrina') }}
                        </a>
                        <a href="{{ route('account.edit') }}"
                           class="{{ request()->routeIs('account.edit', 'account.update', 'account.password.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10 hover:text-white' }} group flex gap-x-3 rounded-xl p-2 leading-6 font-medium">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            {{ __('Il mio account') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left group flex gap-x-3 rounded-xl p-2 leading-6 font-medium text-[#ffd5d5] hover:bg-white/10">
                                <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                {{ __('Esci') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    {{-- Overlay Mobile --}}
    <div x-show="sidebarOpen" 
         x-transition.opacity.duration.300ms
         @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-black/50 md:hidden" 
         style="display: none;"></div>

    {{-- Sidebar Mobile Drawer --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 z-40 w-64 transform bg-brand-600 shadow-lg transition-transform duration-300 ease-linear md:hidden"
         style="display: none;">
        
        <div class="flex h-full flex-col">
             <div class="flex items-center justify-between border-b border-white/15 px-4 py-3">
                 <span class="text-lg font-bold uppercase text-white">{{ \App\Models\Setting::get('store.name', 'Menu') }}</span>
                 <button @click="sidebarOpen = false" class="rounded-xl p-2 text-white/85 hover:bg-white/10">
                     <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                 </button>
             </div>
             
             <nav class="flex-1 overflow-y-auto p-4">
                 <ul class="space-y-2">
                     <li><a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Dashboard') }}</a></li>
                     <li><a href="{{ route('admin.orders.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.orders.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Ordini') }}</a></li>
                     @if (auth()->user()->hasPermission(\App\Support\Permission::COUNTER_USE))
                         <li><a href="{{ route('admin.banco.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.banco.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Banco') }}</a></li>
                     @endif
                     <li><a href="{{ route('admin.products.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.products.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Prodotti') }}</a></li>
                     <li><a href="{{ route('admin.categories.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.categories.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Categorie') }}</a></li>
                     @if (auth()->user()->hasPermission(\App\Support\Permission::SETTINGS_MANAGE))
                         <li><a href="{{ route('admin.markets.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.markets.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Mercati') }}</a></li>
                     @endif
                     @if (auth()->user()->hasPermission(\App\Support\Permission::CLOSURES_HOLIDAYS_MANAGE) || auth()->user()->hasPermission(\App\Support\Permission::CLOSURES_EXCEPTION_MANAGE))
                         <li><a href="{{ route('admin.holidays.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.holidays.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Ferie e chiusure') }}</a></li>
                     @endif
                     @if (auth()->user()->hasPermission(\App\Support\Permission::SETTINGS_MANAGE))
                         <li><a href="{{ route('admin.notifications.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.notifications.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Notifiche') }}</a></li>
                     @endif
                     <li><a href="{{ route('admin.customers.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.customers.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Clienti') }}</a></li>
                     @if (auth()->user()->isSeller())
                         <li><a href="{{ route('admin.collaborators.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.collaborators.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Collaboratori') }}</a></li>
                     @endif
                     @if (auth()->user()->isSeller())
                         <li><a href="{{ route('admin.settings.edit') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.settings.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Impostazioni') }}</a></li>
                         <li><a href="{{ route('admin.legal.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.legal.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Testi legali') }}</a></li>
                         <li><a href="{{ route('admin.activity-log.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.activity-log.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Registro attività') }}</a></li>
                         <li><a href="{{ route('admin.email-log.index') }}" class="block rounded-xl px-3 py-2 text-base font-medium {{ request()->routeIs('admin.email-log.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/85 hover:bg-white/10' }}">{{ __('Email inviate') }}</a></li>
                     @endif
                     <li><hr class="my-2 border-white/15"></li>
                     <li>
                         <form method="POST" action="{{ route('logout') }}">
                             @csrf
                             <button type="submit" class="w-full text-left block rounded-xl px-3 py-2 text-base font-medium text-[#ffd5d5] hover:bg-white/10">{{ __('Esci') }}</button>
                         </form>
                     </li>
                 </ul>
             </nav>
        </div>
    </div>

    {{-- Main Content Area --}}
    <div class="flex grow flex-col md:pl-64">
        
        {{-- Top Header --}}
        <header class="sticky top-0 z-20 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                {{-- Hamburger Button Mobile --}}
                <button @click="sidebarOpen = true" class="rounded-xl p-2 text-gray-500 hover:bg-gray-100 md:hidden">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
                
                <h1 class="text-xl font-bold leading-6 text-gray-900">
                    @yield('page-title', __('Pannello'))
                </h1>
            </div>

            <div class="flex items-center gap-4">
                <x-locale-switcher compact />
                <span class="hidden text-sm text-gray-500 sm:block">
                    {{ __('Ciao, :name', ['name' => auth()->user()->first_name ?: auth()->user()->name]) }}
                </span>
                <div class="h-8 w-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-medium text-sm">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="grow p-4 pb-32 sm:p-6 sm:pb-32 md:pb-8 lg:p-8">
            @yield('content')
        </main>
        
        {{-- Bottom Nav Mobile (Quick Access) --}}
        <nav class="fixed bottom-0 left-0 right-0 z-30 flex justify-around border-t border-gray-200 bg-white px-2 pb-safe pt-2 md:hidden safe-area-pb">
             <a href="{{ route('admin.dashboard') }}" class="flex flex-col items-center gap-1 p-2 {{ request()->routeIs('admin.dashboard') ? 'text-brand-600' : 'text-gray-500' }}">
                 <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                 <span class="text-xs">{{ __('Home') }}</span>
             </a>
             <a href="{{ route('admin.products.index') }}" class="flex flex-col items-center gap-1 p-2 {{ request()->routeIs('admin.products.*') ? 'text-brand-600' : 'text-gray-500' }}">
                 <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                 <span class="text-xs">{{ __('Prodotti') }}</span>
             </a>
              <a href="{{ route('admin.categories.index') }}" class="flex flex-col items-center gap-1 p-2 {{ request()->routeIs('admin.categories.*') ? 'text-brand-600' : 'text-gray-500' }}">
                 <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                 <span class="text-xs">{{ __('Cat.') }}</span>
             </a>
             <a href="{{ route('home') }}" class="flex flex-col items-center gap-1 p-2 text-gray-500 hover:text-brand-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.125 1.125 0 011.592 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                <span class="text-xs">{{ __('Vetrina') }}</span>
             </a>
			 <a href="{{ route('account.edit') }}" class="flex flex-col items-center gap-1 p-2 {{ request()->routeIs('account.edit', 'account.update', 'account.password.*') ? 'text-brand-600' : 'text-gray-500' }}">
				<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
				</svg>
				<span class="text-xs">{{ __('Account') }}</span>
			</a>
        </nav>
        
    </div>
</div>

<style>
    /* Safe area padding for iOS notch devices */
    .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
    .safe-area-pb { padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem); }
</style>

</body>
</html>