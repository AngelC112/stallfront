<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Il mio account') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status') === 'account-updated')
                <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ __('Dati aggiornati.') }}
                </div>
            @endif

            @if (session('status') === 'password-updated')
                <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ __('Password aggiornata.') }}
                </div>
            @endif

            @if (! $user->hasVerifiedEmail())
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    {{ __('La tua email non è ancora confermata: finché non la confermi non puoi ordinare.') }}
                    <a href="{{ route('verification.notice') }}" class="underline">{{ __('Vai alla conferma') }}</a>
                </div>
            @endif

            {{-- Dati personali --}}
            <div class="bg-white p-6 border border-gray-200 sm:rounded-2xl">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Dati personali') }}</h3>

                <form method="POST" action="{{ route('account.update') }}"
                      class="mt-4 space-y-4"
                      x-data="{ showCurrent: false }">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="current_password" :value="__('Password attuale')" />
                        <div class="relative mt-1">
                            <x-text-input id="current_password" name="current_password"
                                          x-bind:type="showCurrent ? 'text' : 'password'"
                                          class="block w-full pr-24"
                                          required autocomplete="current-password" />
                            <button type="button"
                                    @click="showCurrent = !showCurrent"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-gray-600 hover:text-gray-900"
                                    x-bind:aria-label="showCurrent ? @js(__('Nascondi password')) : @js(__('Mostra password'))">
                                <span x-text="showCurrent ? @js(__('Nascondi')) : @js(__('Mostra'))"></span>
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Ti chiediamo la password a ogni salvataggio per proteggere i tuoi dati.') }}
                        </p>
                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="first_name" :value="__('Nome')" />
                            <x-text-input id="first_name" name="first_name" type="text"
                                          class="mt-1 block w-full"
                                          :value="old('first_name', $user->first_name)" required autocomplete="given-name" />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="last_name" :value="__('Cognome')" />
                            <x-text-input id="last_name" name="last_name" type="text"
                                          class="mt-1 block w-full"
                                          :value="old('last_name', $user->last_name)" required autocomplete="family-name" />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email"
                                      class="mt-1 block w-full"
                                      :value="old('email', $user->email)" required autocomplete="email" />
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Se la modifichi, ti invieremo un nuovo link di conferma e finché non confermi non puoi ordinare.') }}
                        </p>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <x-locale-select :value="old('locale', $user->locale)" />

                    <div>
                        <x-input-label for="phone" :value="__('Cellulare')" />
                        <x-text-input id="phone" name="phone" type="tel"
                                      class="mt-1 block w-full"
                                      :value="old('phone', $user->phone)" autocomplete="tel" :placeholder="__('Es. 333 1234567')" />
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Stato attuale:') }}
                            @if ($user->hasPhoneVerified())
                                <span class="font-medium text-green-700">{{ __('verificato') }}</span>
                            @else
                                <span class="font-medium text-amber-700">{{ __('non verificato') }}</span>
                            @endif
                            {{ __('· Serve per la consegna a domicilio e per essere contattato.') }}
                        </p>
                        @if ($user->phone && ! $user->hasPhoneVerified())
                            <a href="{{ route('account.phone.verify') }}" class="btn-secondary mt-2">{{ __('Verifica con WhatsApp') }}</a>
                        @endif
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <x-address-field :user="$user" />

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Salva modifiche') }}</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Cambio password --}}
            <div class="bg-white p-6 border border-gray-200 sm:rounded-2xl">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Cambia password') }}</h3>

                <form method="POST" action="{{ route('account.password.update') }}"
                      class="mt-4 space-y-4"
                      x-data="{ show: false, showCurrent: false, pwd: '' }">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="current_password" :value="__('Password attuale')" />
                        <div class="relative mt-1">
                            <x-text-input id="current_password" name="current_password"
                                          x-bind:type="showCurrent ? 'text' : 'password'"
                                          class="block w-full pr-24"
                                          required autocomplete="current-password" />
                            <button type="button"
                                    @click="showCurrent = !showCurrent"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-gray-600 hover:text-gray-900"
                                    x-bind:aria-label="showCurrent ? @js(__('Nascondi password')) : @js(__('Mostra password'))">
                                <span x-text="showCurrent ? @js(__('Nascondi')) : @js(__('Mostra'))"></span>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" :value="__('Nuova password')" />
                        <div class="relative mt-1">
                            <x-text-input id="password" name="password"
                                          x-bind:type="show ? 'text' : 'password'"
                                          class="block w-full pr-24"
                                          x-model="pwd"
                                          required autocomplete="new-password" />
                            <button type="button"
                                    @click="show = !show"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-gray-600 hover:text-gray-900"
                                    x-bind:aria-label="show ? @js(__('Nascondi password')) : @js(__('Mostra password'))">
                                <span x-text="show ? @js(__('Nascondi')) : @js(__('Mostra'))"></span>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />

                        <x-password-criteria :min="$minPassword" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Conferma nuova password')" />
                        <div class="relative mt-1">
                            <x-text-input id="password_confirmation" name="password_confirmation"
                                          x-bind:type="show ? 'text' : 'password'"
                                          class="block w-full pr-24"
                                          required autocomplete="new-password" />
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Aggiorna password') }}</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- I tuoi dati --}}
            <div class="bg-white p-6 border border-gray-200 sm:rounded-2xl">
                <h3 class="text-lg font-medium text-gray-900">{{ __('I tuoi dati') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Scarica una copia dei tuoi dati personali e dei tuoi ordini.') }}</p>
                <a href="{{ route('account.export') }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Scarica i miei dati') }}</a>
            </div>

            {{-- Eliminazione account (non per il venditore, titolare del negozio) --}}
            @unless (auth()->user()->isSeller())
            <div class="bg-white p-6 border border-gray-200 sm:rounded-2xl">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Elimina account') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Cancella i tuoi dati e tutti i tuoi ordini. Ti invieremo un’email con un link per confermare.') }}</p>

                @if (session('status') === 'account-delete-link-sent')
                    <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ __('Ti abbiamo inviato un’email: apri il link entro 30 minuti per confermare l’eliminazione.') }}</p>
                @endif

                <form method="POST" action="{{ route('account.delete.request') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="delete_current_password" :value="__('Password attuale')" />
                        <x-text-input id="delete_current_password" name="current_password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
                        <x-input-error :messages="$errors->deleteAccount->get('current_password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="delete_confirmation" :value="__('Scrivi ELIMINA per confermare')" />
                        <x-text-input id="delete_confirmation" name="confirmation" type="text" class="mt-1 block w-full" required autocomplete="off" />
                        <x-input-error :messages="$errors->deleteAccount->get('confirmation')" class="mt-2" />
                    </div>
                    <div class="flex justify-end">
                        <x-danger-button>{{ __('Invia il link di conferma') }}</x-danger-button>
                    </div>
                </form>
            </div>
            @endunless
        </div>
    </div>
</x-app-layout>