<style>[x-cloak] { display: none !important; }</style>

<nav x-data="{ open: false }" class="border-b border-gray-100 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                {{-- Denominazione del negozio --}}
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('home') }}" class="text-xl font-bold uppercase text-brand-600">
                        {{ \App\Models\Setting::get('store.name', config('app.name')) }}
                    </a>
                </div>

                {{-- Link desktop --}}
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
                        {{ __('Vetrina') }}
                    </x-nav-link>

                    @auth
                        @if (auth()->user()->hasPermission(\App\Support\Permission::DASHBOARD_VIEW))
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                                {{ __('Pannello') }}
                            </x-nav-link>
                        @endif

                        <x-nav-link :href="route('account.orders.index')" :active="request()->routeIs('account.orders.*')">
                            {{ __('I miei ordini') }}
                        </x-nav-link>

                        <x-nav-link :href="route('account.edit')" :active="request()->routeIs('account.edit', 'account.update', 'account.password.*')">
                            {{ __('Il mio account') }}
                        </x-nav-link>

                        <x-nav-link :href="route('account.notifications.edit')" :active="request()->routeIs('account.notifications.*')">
                            {{ __('Notifiche') }}
                        </x-nav-link>
                    @endauth
                </div>
            </div>

            {{-- Parte destra desktop --}}
            <div class="hidden sm:ms-6 sm:flex sm:items-center">
                @auth
                    <span class="me-4 text-sm text-gray-600">{{ auth()->user()->fullName() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">
                            {{ __('Esci') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ __('Accedi') }}
                    </a>
                    <a href="{{ route('register') }}"
                       class="ms-4 rounded-xl bg-gray-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700">
                        {{ __('Registrati') }}
                    </a>
                @endauth
            </div>

            {{-- Pulsante menu mobile --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = !open"
                        class="inline-flex items-center justify-center rounded-xl p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                        x-bind:aria-expanded="open"
                        aria-label="{{ __('Apri menu') }}">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Menu mobile --}}
    <div x-show="open" x-cloak class="sm:hidden">
        <div class="space-y-1 border-t border-gray-200 px-4 pb-3 pt-2">
            <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')">
                {{ __('Vetrina') }}
            </x-responsive-nav-link>

            @auth
                @if (auth()->user()->hasPermission(\App\Support\Permission::DASHBOARD_VIEW))
                    <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                        {{ __('Pannello') }}
                    </x-responsive-nav-link>
                @endif

                <x-responsive-nav-link :href="route('account.orders.index')" :active="request()->routeIs('account.orders.*')">
                    {{ __('I miei ordini') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('account.edit')" :active="request()->routeIs('account.edit', 'account.update', 'account.password.*')">
                    {{ __('Il mio account') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('account.notifications.edit')" :active="request()->routeIs('account.notifications.*')">
                    {{ __('Notifiche') }}
                </x-responsive-nav-link>
            @endauth
        </div>

        <div class="border-t border-gray-200 px-4 pb-3 pt-2">
            @auth
                <div class="mb-2 text-sm text-gray-600">{{ auth()->user()->fullName() }}</div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">
                        {{ __('Esci') }}
                    </button>
                </form>
            @else
                <div class="flex gap-4">
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Accedi') }}</a>
                    <a href="{{ route('register') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Registrati') }}</a>
                </div>
            @endauth
        </div>
    </div>
</nav>