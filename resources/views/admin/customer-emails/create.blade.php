@extends('layouts.admin')

@section('page-title', __('Scrivi email ai clienti'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <a href="{{ $backUrl ?? route('admin.email-log.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-700 hover:text-brand-900">← {{ $backUrl ? \App\Support\ReturnUrl::label($backUrl) : __('Email inviate') }}</a>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4">
                <ul class="list-disc pl-5 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
                @if ($errors->has('attachments') || $errors->has('attachments.*') || old('subject'))
                    <p class="mt-2 text-sm text-red-700">{{ __('Per sicurezza il browser non conserva gli allegati: selezionali di nuovo.') }}</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('admin.customer-emails.store') }}" enctype="multipart/form-data"
              x-data="recipientPicker(@js($selected), @js(route('admin.customer-emails.recipients')), {{ $maxRecipients }})"
              @submit="sending = true"
              class="space-y-5 rounded-xl bg-white p-5 border border-gray-200 sm:p-6">
            @csrf
            @if ($backUrl)
                <input type="hidden" name="back" value="{{ $backUrl }}">
            @endif

            {{-- Destinatari: solo clienti registrati con email verificata --}}
            <div>
                <label for="recipient-search" class="block text-sm font-medium text-gray-900">A</label>
                <div class="relative mt-1">
                    <div class="flex min-h-11 flex-wrap items-center gap-2 rounded-xl border border-gray-300 px-2 py-1.5 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                        <template x-for="recipient in selected" :key="recipient.id">
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-100 py-1 pl-3 pr-1 text-sm text-brand-900">
                                <span x-text="recipient.name + ' <' + recipient.email + '>'"></span>
                                <input type="hidden" name="recipients[]" :value="recipient.id">
                                <button type="button" @click="remove(recipient.id)" class="rounded-full px-2 text-brand-700 hover:bg-brand-200" :aria-label="@js(__('Rimuovi :email')).replace(':email', recipient.email)">×</button>
                            </span>
                        </template>
                        <input id="recipient-search" type="text" x-model="term" autocomplete="off"
                               @input.debounce.250ms="search()" @focus="search()" @keydown.enter.prevent="pickFirst()"
                               @keydown.escape="results = []" @click.outside="results = []"
                               :placeholder="selected.length ? @js(__('Aggiungi un altro cliente…')) : @js(__('Cerca un cliente per nome o email…'))"
                               class="min-w-[12rem] flex-1 border-0 p-1 text-sm focus:ring-0">
                    </div>

                    <ul x-show="results.length" x-cloak class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                        <template x-for="customer in results" :key="customer.id">
                            <li>
                                <button type="button" @click="add(customer)" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50">
                                    <span class="font-medium text-gray-900" x-text="customer.name"></span>
                                    <span class="text-gray-500" x-text="customer.email"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p x-show="noResults" x-cloak class="mt-1 text-sm text-gray-500">{{ __('Nessun cliente registrato trovato.') }}</p>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('Solo clienti registrati con email verificata, massimo :max_recipients. Ogni cliente riceve un\'email separata e non vede gli altri destinatari.', ['max_recipients' => $maxRecipients]) }}
                </p>
            </div>

            <div>
                <label for="subject" class="block text-sm font-medium text-gray-900">{{ __('Oggetto') }}</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required maxlength="190"
                       class="mt-1 block min-h-11 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-900">{{ __('Testo') }}</label>
                <textarea name="body" id="body" rows="12" required maxlength="20000"
                          class="mt-1 block w-full rounded-xl border-gray-300 text-sm">{{ old('body') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">{{ __('I link che iniziano con https:// diventano cliccabili.') }}</p>
            </div>

            <div>
                <label for="attachments" class="block text-sm font-medium text-gray-900">{{ __('Allegati') }}</label>
                <input type="file" name="attachments[]" id="attachments" multiple
                       class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-500">{{ __('Fino a :max_attachments file, massimo 10 MB ciascuno e 20 MB in totale.', ['max_attachments' => $maxAttachments]) }}</p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <span x-show="sending" x-cloak class="text-sm text-gray-500">{{ __('Invio in corso, non chiudere la pagina…') }}</span>
                <button type="submit" :disabled="sending || !selected.length"
                        class="min-h-11 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Invia email') }}
                </button>
            </div>
        </form>
    </div>

    <script>
        function recipientPicker(initial, searchUrl, maxRecipients) {
            return {
                selected: initial,
                results: [],
                term: '',
                noResults: false,
                sending: false,
                async search() {
                    const params = new URLSearchParams({ q: this.term });
                    const response = await fetch(searchUrl + '?' + params, { headers: { Accept: 'application/json' } });
                    const customers = response.ok ? await response.json() : [];
                    const chosen = this.selected.map((recipient) => recipient.id);
                    this.results = customers.filter((customer) => !chosen.includes(customer.id));
                    this.noResults = this.term !== '' && this.results.length === 0;
                },
                add(customer) {
                    if (this.selected.length >= maxRecipients || this.selected.some((recipient) => recipient.id === customer.id)) {
                        return;
                    }
                    this.selected.push(customer);
                    this.term = '';
                    this.results = [];
                    this.noResults = false;
                },
                pickFirst() {
                    if (this.results.length) {
                        this.add(this.results[0]);
                    }
                },
                remove(id) {
                    this.selected = this.selected.filter((recipient) => recipient.id !== id);
                },
            };
        }
    </script>
@endsection
