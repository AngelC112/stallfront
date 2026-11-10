@extends('layouts.admin')

@section('page-title', __('Modalità Banco'))

@section('content')
    <div>
        <div class="mx-auto max-w-6xl space-y-6">
            @include('admin.partials.block-notices-strip')

            <h1 class="text-2xl font-bold text-gray-900">{{ __('Modalità banco') }}</h1>

            <div class="space-y-6">
                {{-- In alto: ricerca e QR a sinistra, giorno e contatori a destra (su computer) --}}
                <div class="grid gap-4 lg:grid-cols-2 lg:items-start [&>*]:space-y-4">
                    <div>
                    <div class="space-y-2">
                        <label for="search-input" class="block text-sm text-gray-600">{{ __('Cerca un ordine') }}</label>
                        <input type="search" id="search-input"
                               class="block min-h-14 w-full rounded-xl border-2 border-petrol bg-white px-4 text-base placeholder:text-gray-400 focus:border-petrol focus:ring-petrol"
                               placeholder="{{ __('Codice, nome, email o cellulare') }}" autocomplete="off" autofocus>
                    </div>

                    <button id="scan-btn" type="button"
                            class="flex min-h-14 w-full items-center justify-center gap-3 rounded-xl bg-apricot px-4 text-base font-bold text-gray-900 hover:brightness-95">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" /></svg>
                        <span id="scan-label">{{ __('Scansiona il QR del cliente') }}</span>
                    </button>
                    <p class="text-sm text-gray-500">{{ __('Su computer puoi usare la webcam. La scansione richiede la connessione HTTPS.') }}</p>

                    <div id="scanner" class="hidden space-y-2">
                        <video id="scan-video" class="w-full rounded-xl bg-black" playsinline muted></video>
                        <p id="scan-help" class="text-center text-xs text-gray-500">{{ __('Inquadra il QR dell’ordine e consenti l’uso della fotocamera.') }}</p>
                    </div>
                    </div>

                    <div>

                    <form id="day-form" method="GET" action="{{ route('admin.banco.index') }}" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('admin.banco.index', array_filter(['date' => $date->copy()->subDay()->toDateString(), 'market' => $marketId])) }}"
                               class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-gray-300 text-lg font-semibold text-petrol hover:bg-gray-50" aria-label="{{ __('Giorno precedente') }}">‹</a>
                            <div class="text-center">
                                <p class="text-base font-bold capitalize text-gray-900">{{ $date->translatedFormat('l j F') }}</p>
                                @if ($date->isToday())
                                    <p class="text-xs text-gray-500">{{ __('Oggi') }}</p>
                                @else
                                    <a href="{{ route('admin.banco.index', array_filter(['market' => $marketId])) }}" class="text-xs font-semibold text-petrol hover:underline">{{ __('Torna a oggi') }}</a>
                                @endif
                            </div>
                            <a href="{{ route('admin.banco.index', array_filter(['date' => $date->copy()->addDay()->toDateString(), 'market' => $marketId])) }}"
                               class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-gray-300 text-lg font-semibold text-petrol hover:bg-gray-50" aria-label="{{ __('Giorno successivo') }}">›</a>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" name="date" value="{{ $date->toDateString() }}" aria-label="{{ __('Data') }}"
                                   class="block min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-petrol focus:ring-petrol">
                            <select name="market" aria-label="{{ __('Mercato') }}"
                                    class="block min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-petrol focus:ring-petrol">
                                <option value="">{{ __('Tutti i mercati') }}</option>
                                @foreach ($markets as $market)
                                    <option value="{{ $market->id }}" @selected((int) $marketId === $market->id)>{{ $market->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    <div id="day-counters" class="grid grid-cols-3 gap-2 text-center"></div>
                    </div>
                </div>

                {{-- Sotto, a tutta larghezza: risultati della ricerca o ritiri del giorno --}}
                <div class="space-y-4">
                    <p id="banco-message" class="hidden" role="status"></p>
                    <div id="results" class="space-y-4"></div>
                    <section id="day-view" class="space-y-6">
                        <div id="day-list" class="space-y-6"></div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var searchUrl = @json(route('admin.banco.search'));
        var statusUrl = @json(url('/admin/banco/orders'));
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        var input = document.getElementById('search-input');
        var results = document.getElementById('results');
        var message = document.getElementById('banco-message');
        var scanBtn = document.getElementById('scan-btn');
        var scanLabel = document.getElementById('scan-label');
        var scanner = document.getElementById('scanner');
        var video = document.getElementById('scan-video');
        var scanHelp = document.getElementById('scan-help');
        var dayForm = document.getElementById('day-form');
        var dayView = document.getElementById('day-view');
        var dayList = document.getElementById('day-list');
        var dayCounters = document.getElementById('day-counters');
        var dayOrders = @json($dayOrders);
        var timer = null;
        var stream = null;
        var scanning = false;

        var HANDLED = ['collected', 'not_collected'];
        var BADGES = {
            ready: 'bg-[#dff0e6] text-[#14532d]',
            collected: 'bg-gray-100 text-gray-600',
            not_collected: 'bg-[#fbe3e3] text-[#8a2a2a]',
            received: 'bg-gray-100 text-gray-700',
            preparing: 'bg-apricot-light text-[#7a4a0c]',
            rescheduled: 'bg-[#ede9fb] text-[#3b2f73]'
        };

        dayForm.querySelectorAll('input, select').forEach(function (field) {
            field.addEventListener('change', function () { dayForm.submit(); });
        });

        // Dal dettaglio dell'ordine si torna al banco com'era: stesso giorno, mercato e ricerca.
        function withBack(url) {
            var here = new URL(window.location.href);
            var term = input.value.trim();
            if (term.length >= 2) { here.searchParams.set('q', term); } else { here.searchParams.delete('q'); }
            var target = new URL(url, window.location.origin);
            target.searchParams.set('back', here.pathname + here.search);
            return target.pathname + target.search;
        }

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) { node.className = className; }
            if (text !== undefined && text !== null) { node.textContent = text; }
            return node;
        }

        function showMessage(text, ok) {
            message.textContent = text;
            message.className = 'rounded-xl border p-3 text-sm font-semibold ' + (ok ? 'border-[#9fcfb4] bg-[#dff0e6] text-[#14532d]' : 'border-[#8a2a2a] bg-[#fbe3e3] text-[#8a2a2a]');
        }

        function hideMessage() { message.className = 'hidden'; }

        function renderOrder(order) {
            var card = el('article', 'space-y-4 rounded-2xl border border-gray-200 bg-white p-5');

            var head = el('div', 'flex items-start justify-between gap-3');
            var who = el('div', 'min-w-0');
            who.appendChild(el('p', 'text-xl font-bold text-gray-900', order.customer_name));
            var meta = ['#' + order.order_number, order.pickup_date, order.market].filter(Boolean).join(' · ');
            who.appendChild(el('p', 'text-sm text-gray-500', meta));
            if (order.customer_phone) {
                who.appendChild(el('p', 'text-sm text-gray-500', order.customer_phone));
            }
            head.appendChild(who);
            head.appendChild(el('span', 'shrink-0 rounded-full px-3 py-1 text-xs font-bold ' + (BADGES[order.status] || 'bg-gray-100 text-gray-700'), order.status_label));
            card.appendChild(head);

            var items = el('ul', 'space-y-1 text-base text-gray-900');
            order.items.forEach(function (item) {
                items.appendChild(el('li', '', item.name + (item.variant ? ' ' + item.variant : '') + ' ×' + item.quantity));
            });
            card.appendChild(items);

            if (order.customer_note) {
                var customerNote = el('p', 'rounded-xl border border-gray-200 bg-[#f5f7f6] px-4 py-3 text-sm text-gray-800');
                customerNote.appendChild(el('strong', '', @js(__('Nota del cliente:')) + ' '));
                customerNote.appendChild(document.createTextNode(order.customer_note));
                card.appendChild(customerNote);
            }

            order.internal_notes.forEach(function (note) {
                var internal = el('p', 'rounded-xl border border-apricot bg-apricot-light px-4 py-3 text-sm text-gray-900');
                internal.appendChild(el('strong', '', @js(__('Nota interna'))));
                internal.appendChild(document.createTextNode(' ' + @js(__('(solo staff):')) + ' ' + note));
                card.appendChild(internal);
            });

            if (!order.actions.length && ['received', 'preparing', 'rescheduled'].indexOf(order.status) !== -1) {
                card.appendChild(el('p', 'rounded-xl bg-gray-100 px-4 py-3 text-sm text-gray-700', @js(__('Non ancora pronto: va segnato come evaso prima della consegna.'))));
            }

            var total = el('div', 'flex items-baseline justify-between gap-3');
            total.appendChild(el('span', 'text-sm text-gray-500', order.paid ? @js(__('Pagato')) : @js(__('Da incassare'))));
            total.appendChild(el('span', 'text-3xl font-bold ' + (order.paid ? 'text-gray-400' : 'text-petrol'), order.total));
            card.appendChild(total);

            var actions = el('div', 'flex flex-wrap gap-2');

            if (order.actions.indexOf('collected') !== -1) {
                actions.appendChild(actionButton(order, 'collected', @js(__('Segna come ritirato')), 'min-w-48 flex-[2] bg-petrol text-white hover:bg-petrol-dark'));
            }
            if (order.actions.indexOf('not_collected') !== -1) {
                actions.appendChild(actionButton(order, 'not_collected', @js(__('Non ritirato')), 'flex-1 border-2 border-[#8a2a2a] bg-white text-[#8a2a2a] hover:bg-[#fbe3e3]'));
            }
            if (order.url) {
                var detail = el('a', 'inline-flex min-h-12 flex-1 items-center justify-center whitespace-nowrap rounded-xl border-2 border-petrol px-4 text-sm font-bold text-petrol hover:bg-[#f5f7f6]', @js(__('Dettaglio completo')) + ' ›');
                detail.href = withBack(order.url);
                actions.appendChild(detail);
            }
            if (actions.children.length) {
                card.appendChild(actions);
            }

            return card;
        }

        function actionButton(order, status, label, classes) {
            var button = el('button', 'min-h-12 rounded-xl px-4 text-sm font-bold disabled:opacity-60 ' + classes, label);
            button.type = 'button';
            button.addEventListener('click', function () {
                if (status === 'not_collected' && !confirm(@js(__('Segnare l’ordine come non ritirato? Conta come mancato ritiro.')))) {
                    return;
                }
                button.disabled = true;
                fetch(statusUrl + '/' + order.id + '/status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ status: status })
                })
                    .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            showMessage(result.body.message || @js(__('Operazione non riuscita.')), false);
                            button.disabled = false;
                            return;
                        }
                        showMessage(status === 'collected' ? @js(__('Ordine segnato come ritirato.')) : @js(__('Ordine segnato come non ritirato.')), true);
                        updateDayOrder(result.body.order);
                        if (dayView.classList.contains('hidden')) {
                            results.replaceChildren(renderOrder(result.body.order));
                        } else {
                            renderDay();
                        }
                    })
                    .catch(function () { showMessage(@js(__('Errore di rete. Riprova.')), false); button.disabled = false; });
            });
            return button;
        }

        function emptyBox(text) {
            return el('p', 'rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500', text);
        }

        function search(term) {
            hideMessage();
            return fetch(searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    results.replaceChildren();
                    if (!data.results.length) {
                        results.appendChild(emptyBox(@js(__('Nessun ordine trovato.'))));
                        return;
                    }
                    data.results.forEach(function (order) { results.appendChild(renderOrder(order)); });
                })
                .catch(function () { showMessage(@js(__('Errore di rete. Riprova.')), false); });
        }

        function updateDayOrder(order) {
            dayOrders = dayOrders.map(function (item) { return item.id === order.id ? order : item; });
        }

        function counter(label, value, numberClass) {
            var box = el('div', 'rounded-2xl border border-gray-200 bg-white p-3');
            box.appendChild(el('p', 'text-2xl font-bold ' + numberClass, String(value)));
            box.appendChild(el('p', 'text-xs text-gray-500', label));
            return box;
        }

        // Elenco del giorno: per mercato, prima gli ordini da consegnare, poi quelli già gestiti.
        function renderDay() {
            var open = dayOrders.filter(function (order) { return HANDLED.indexOf(order.status) === -1; });
            var collected = dayOrders.filter(function (order) { return order.status === 'collected'; });
            var notCollected = dayOrders.filter(function (order) { return order.status === 'not_collected'; });

            dayCounters.replaceChildren(
                counter(@js(__('Da consegnare')), open.length, 'text-petrol'),
                counter(@js(__('Ritirati')), collected.length, 'text-gray-500'),
                counter(@js(__('Non ritirati')), notCollected.length, 'text-[#8a2a2a]')
            );
            dayList.replaceChildren();

            if (!dayOrders.length) {
                dayList.appendChild(emptyBox(@js(__('Nessun ritiro previsto per questo giorno.'))));
                return;
            }

            var markets = [];
            dayOrders.forEach(function (order) {
                if (markets.indexOf(order.market) === -1) { markets.push(order.market); }
            });

            markets.forEach(function (market) {
                var orders = dayOrders.filter(function (order) { return order.market === market; });
                var pending = orders.filter(function (order) { return HANDLED.indexOf(order.status) === -1; });
                var handled = orders.filter(function (order) { return HANDLED.indexOf(order.status) !== -1; });
                var section = el('section', 'space-y-3');

                var title = el('h2', 'text-base font-bold text-gray-900', market || @js(__('Senza mercato')));
                title.appendChild(el('span', 'font-normal text-gray-500', ' · ' + @js(__(':count da consegnare')).replace(':count', pending.length)));
                section.appendChild(title);

                if (!pending.length) {
                    section.appendChild(el('p', 'rounded-xl border border-[#9fcfb4] bg-[#dff0e6] px-4 py-3 text-sm text-[#14532d]', @js(__('Tutti gli ordini di questo mercato sono stati gestiti.'))));
                }
                pending.forEach(function (order) { section.appendChild(renderOrder(order)); });

                if (handled.length) {
                    var details = el('details', 'group space-y-3');
                    details.appendChild(el('summary', 'cursor-pointer text-sm font-bold uppercase tracking-wide text-gray-500', @js(__('Già gestiti (:count)')).replace(':count', handled.length)));
                    var list = el('div', 'mt-3 space-y-3 opacity-80');
                    handled.forEach(function (order) { list.appendChild(renderOrder(order)); });
                    details.appendChild(list);
                    section.appendChild(details);
                }

                dayList.appendChild(section);
            });
        }

        function showDay() {
            results.replaceChildren();
            dayView.classList.remove('hidden');
            renderDay();
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            if (term.length < 2) { showDay(); return; }
            dayView.classList.add('hidden');
            timer = setTimeout(function () { search(term); }, 300);
        });

        // Il QR contiene solo il codice ordine; accetta anche il vecchio formato JSON.
        function codeFromQr(raw) {
            try {
                var parsed = JSON.parse(raw);
                if (parsed && parsed.order_number) { return parsed.order_number; }
            } catch (e) { /* testo semplice */ }
            return raw.trim();
        }

        function stopScan() {
            scanning = false;
            if (stream) { stream.getTracks().forEach(function (track) { track.stop(); }); stream = null; }
            scanner.classList.add('hidden');
            scanLabel.textContent = @js(__('Scansiona il QR del cliente'));
        }

        function onCode(raw) {
            stopScan();
            var code = codeFromQr(raw);
            input.value = code;
            dayView.classList.add('hidden');
            search(code);
        }

        function loadJsQr() {
            return new Promise(function (resolve, reject) {
                if (window.jsQR) { resolve(); return; }
                var script = document.createElement('script');
                script.src = @js(asset('js/jsQR-1.4.0.js'));
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        async function startScan() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showMessage(@js(__('La fotocamera non è disponibile: serve una connessione HTTPS.')), false);
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            } catch (e) {
                showMessage(@js(__('Permesso alla fotocamera negato.')), false);
                return;
            }

            video.srcObject = stream;
            await video.play();
            scanner.classList.remove('hidden');
            scanLabel.textContent = @js(__('Chiudi fotocamera'));
            scanning = true;

            var detector = null;
            if ('BarcodeDetector' in window) {
                detector = new BarcodeDetector({ formats: ['qr_code'] });
            } else {
                try { await loadJsQr(); } catch (e) { scanHelp.textContent = @js(__('Lettore QR non disponibile su questo dispositivo: usa la ricerca.')); return; }
            }

            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d', { willReadFrequently: true });

            (async function tick() {
                if (!scanning) { return; }
                if (video.readyState >= 2) {
                    try {
                        if (detector) {
                            var found = await detector.detect(video);
                            if (found.length) { onCode(found[0].rawValue); return; }
                        } else {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);
                            var image = context.getImageData(0, 0, canvas.width, canvas.height);
                            var qr = window.jsQR(image.data, image.width, image.height);
                            if (qr && qr.data) { onCode(qr.data); return; }
                        }
                    } catch (e) { /* frame non leggibile: riprova */ }
                }
                setTimeout(tick, 200);
            })();
        }

        scanBtn.addEventListener('click', function () {
            if (scanning) { stopScan(); } else { startScan(); }
        });

        renderDay();

        // Ritorno dal dettaglio di un ordine: si ripete la ricerca che era aperta.
        var initialTerm = (new URLSearchParams(window.location.search).get('q') || '').trim();
        if (initialTerm.length >= 2) {
            input.value = initialTerm;
            dayView.classList.add('hidden');
            search(initialTerm);
        }
    })();
    </script>
@endsection
