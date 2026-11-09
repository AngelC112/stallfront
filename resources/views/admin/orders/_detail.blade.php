@php($inPanel = $inPanel ?? false)
<div class="space-y-4">
    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <section class="rounded-xl bg-white p-4 border border-gray-200 sm:p-6">
        <div class="flex flex-wrap justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ $order->customer_name }}</h2>
                <p class="mt-1 text-sm text-gray-600">#{{ $order->order_number }} · {{ $order->statusLabel() }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $order->customer_email }}{{ $order->customer_phone ? ' · '.$order->customer_phone : '' }}</p>
                <div class="mt-2 flex flex-wrap gap-3 text-sm font-semibold">
                    @if ($order->telUrl())
                        <a href="{{ $order->telUrl() }}" class="inline-flex min-h-11 items-center text-gray-700 hover:text-gray-900">{{ __('Chiama') }}</a>
                    @endif
                    @if ($order->whatsappUrl())
                        <a href="{{ $order->whatsappUrl() }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-green-700 hover:text-green-900">{{ __('WhatsApp') }}</a>
                    @endif
                    {{-- Il venditore scrive dal sito; nel pannello affiancato si torna all'elenco ordini che lo contiene. --}}
                    @if (auth()->user()->isSeller() && $order->user?->isCustomer() && $order->user->hasVerifiedEmail())
                        <a href="{{ route('admin.customer-emails.create', ['to' => [$order->user_id], 'back' => \App\Support\ReturnUrl::of($inPanel ? url()->previous() : request()->fullUrl())]) }}" class="inline-flex min-h-11 items-center text-brand-700 hover:text-brand-900">{{ __('Email') }}</a>
                    @else
                        <a href="mailto:{{ $order->customer_email }}" class="inline-flex min-h-11 items-center text-brand-700 hover:text-brand-900">{{ __('Email') }}</a>
                    @endif
                </div>
            </div>
            <div class="text-sm text-gray-600">
                <p>{{ $order->fulfillmentMethodLabel() }}</p>
                <p class="mt-1">{{ $order->paymentMethodLabel() }}</p>
                @if ($order->pickup_date)
                    <p class="mt-1">{{ __('Ritiro:') }} {{ $order->pickup_date->isoFormat('L') }}</p>
                @endif
                @if ($order->market_name)
                    <p class="mt-1">{{ $order->market_name }} · {{ $order->market_location }}</p>
                @endif
                @if ($order->isOffSeason())
                    <p class="mt-1 font-semibold text-orange-700">{{ __('Fuori stagione · preparazione dal') }} {{ $order->preparation_start_date?->isoFormat('L') }}</p>
                @endif
            </div>
        </div>
        @if ($order->delivery_address)
            <p class="mt-4 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">{{ __('Indirizzo:') }} {{ $order->delivery_address }}</p>
        @endif
        @if ($order->customer_note)
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><span class="font-semibold">{{ __('Nota del cliente:') }}</span> {{ $order->customer_note }}</p>
        @endif
    </section>

    <section class="rounded-xl bg-white p-4 border border-gray-200 sm:p-6">
        <h2 class="font-semibold text-gray-900">{{ __('Prodotti') }}</h2>
        <div class="mt-3 divide-y divide-gray-100">
            @foreach ($order->items as $item)
                <div class="flex justify-between gap-4 py-3 text-sm">
                    <div>
                        <p class="font-medium text-gray-900">{{ $item->product_name }}</p>
                        @if ($item->variant_label)
                            <p class="mt-1 text-gray-500">{{ $item->variant_label }}</p>
                        @endif
                        <p class="mt-1 text-gray-500">{{ $item->quantity }} × {{ \App\Support\Format::money($item->unit_price_cents) }}</p>
                    </div>
                    <p class="font-semibold text-gray-900">{{ \App\Support\Format::money($item->line_total_cents) }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex justify-between border-t border-gray-200 pt-4 font-bold text-gray-900">
            <span>{{ __('Totale da incassare') }}</span>
            <span>{{ \App\Support\Format::money($order->total_cents) }}</span>
        </div>
    </section>

    <section class="rounded-xl bg-white p-4 border border-gray-200 sm:p-6">
        <h2 class="font-semibold text-gray-900">{{ __('Stato:') }} {{ $order->statusLabel() }}</h2>
        @if ($statusOptions)
            <form method="POST" action="{{ route('admin.orders.status', ['order' => $order]) }}" class="mt-4 flex flex-col gap-3">
                @csrf
                @method('PATCH')
                <label class="block text-sm font-medium text-gray-700">
                    {{ __('Aggiorna stato') }}
                    <select name="status" required class="mt-1 block min-h-11 w-full rounded-xl border-gray-300">
                        <option value="">{{ __('Seleziona') }}</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                @if ($needsNewDate && isset($statusOptions['ready']))
                    <label class="block text-sm font-medium text-gray-700">
                        {{ __('Nuovo mercato (per tornare in Evaso)') }}
                        <select name="market_id" class="mt-1 block min-h-11 w-full rounded-xl border-gray-300">
                            <option value="">{{ __('Seleziona') }}</option>
                            @foreach ($markets as $market)
                                <option value="{{ $market->id }}">{{ $market->name }} · {{ $market->weekdayLabel() }}</option>
                            @endforeach
                        </select>
                        @error('market_id')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ __('Nuova data') }}
                        <input type="date" name="pickup_date" class="mt-1 block min-h-11 w-full rounded-xl border-gray-300">
                        @error('pickup_date')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                @endif
                <button class="min-h-11 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Aggiorna') }}</button>
            </form>
        @endif
    </section>

    @if ($canSeeNotes)
        <section class="rounded-xl bg-white p-4 border border-gray-200 sm:p-6">
            <h2 class="font-semibold text-gray-900">{{ __('Note interne') }}</h2>
            <p class="mt-1 text-xs text-gray-500">{{ __('Visibili solo a te e ai collaboratori autorizzati. Il cliente non le vede.') }}</p>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($order->notes as $note)
                    <li class="py-2">
                        <p class="whitespace-pre-line text-gray-900">{{ $note->body }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $note->author?->fullName() ?: __('Utente rimosso') }} · {{ $note->created_at->isoFormat('L LT') }}</p>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('Nessuna nota.') }}</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('admin.orders.notes.store', $order) }}" class="mt-3 space-y-2">
                @csrf
                <label class="block text-sm font-medium text-gray-700">
                    {{ __('Nuova nota') }}
                    <textarea name="body" rows="2" required maxlength="1000" class="mt-1 block w-full rounded-xl border-gray-300"></textarea>
                    @error('body')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <button class="min-h-11 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Aggiungi nota') }}</button>
            </form>
        </section>
    @endif

    <section class="rounded-xl bg-white p-4 border border-gray-200 sm:p-6">
        <h2 class="font-semibold text-gray-900">{{ __('Storico stati') }}</h2>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @forelse ($order->statusHistories as $entry)
                <li class="flex flex-wrap justify-between gap-2 py-2">
                    <span class="text-gray-900">
                        {{ $entry->from_status ? \App\Models\Order::labelFor($entry->from_status, $order->fulfillment_method).' → ' : '' }}<strong>{{ \App\Models\Order::labelFor($entry->to_status, $order->fulfillment_method) }}</strong>
                        @if ($entry->reason)
                            <span class="text-gray-500">· {{ $entry->reason }}</span>
                        @endif
                    </span>
                    <span class="text-gray-500">{{ $entry->actorLabel() }} · {{ $entry->created_at->isoFormat('L LT') }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('Nessun cambio di stato registrato.') }}</li>
            @endforelse
        </ul>
    </section>
</div>
