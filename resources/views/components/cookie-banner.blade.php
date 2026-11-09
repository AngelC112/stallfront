{{-- Banner cookie: Rifiuta, Personalizza e Accetta hanno la stessa evidenza. Prima della scelta si usano solo cookie tecnici. --}}
<div id="cookie-banner" role="dialog" aria-modal="false" aria-labelledby="cookie-banner-title" hidden
     class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-300 bg-white p-4 shadow-2xl">
    <div class="mx-auto max-w-3xl space-y-3">
        <h2 id="cookie-banner-title" class="text-base font-bold text-gray-900">{{ __('Cookie') }}</h2>
        <p class="text-sm text-gray-700">
            {{ __('Usiamo cookie tecnici, necessari al funzionamento del sito. Non usiamo altri cookie senza il tuo consenso. Leggi la') }} <a href="{{ route('legal.cookies') }}" class="underline">{{ __('Cookie Policy') }}</a>.
        </p>

        <div id="cookie-preferences" hidden class="space-y-1 rounded-xl bg-gray-50 p-3 text-sm text-gray-800">
            <label class="flex min-h-11 items-center gap-3">
                <input type="checkbox" checked disabled class="h-5 w-5 rounded border-gray-300">
                {{ __('Tecnici (sempre attivi)') }}
            </label>
            <label class="flex min-h-11 items-center gap-3">
                <input type="checkbox" id="cookie-statistics" class="h-5 w-5 rounded border-gray-300 text-brand-600">
                {{ __('Statistiche (al momento non utilizzati)') }}
            </label>
            <label class="flex min-h-11 items-center gap-3">
                <input type="checkbox" id="cookie-marketing" class="h-5 w-5 rounded border-gray-300 text-brand-600">
                {{ __('Marketing (al momento non utilizzati)') }}
            </label>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <button type="button" id="cookie-reject" class="min-h-11 rounded-xl border border-gray-700 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">{{ __('Rifiuta') }}</button>
            <button type="button" id="cookie-customize" class="min-h-11 rounded-xl border border-gray-700 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">{{ __('Personalizza') }}</button>
            <button type="button" id="cookie-accept" class="min-h-11 rounded-xl border border-gray-700 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">{{ __('Accetta') }}</button>
        </div>
    </div>
</div>

<script>
(function () {
    var NAME = 'cookie_consent';
    var banner = document.getElementById('cookie-banner');
    var preferences = document.getElementById('cookie-preferences');
    var customizeButton = document.getElementById('cookie-customize');
    var customizing = false;

    function readConsent() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
        if (!match) { return null; }
        try { return JSON.parse(decodeURIComponent(match[1])); } catch (e) { return null; }
    }

    function saveConsent(statistics, marketing) {
        var value = encodeURIComponent(JSON.stringify({ v: 1, necessary: true, statistics: statistics, marketing: marketing }));
        document.cookie = NAME + '=' + value + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        banner.hidden = true;
        customizing = false;
        preferences.hidden = true;
        document.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: readConsent() }));
    }

    function open() {
        var current = readConsent();
        document.getElementById('cookie-statistics').checked = !!(current && current.statistics);
        document.getElementById('cookie-marketing').checked = !!(current && current.marketing);
        banner.hidden = false;
    }

    document.getElementById('cookie-reject').addEventListener('click', function () { saveConsent(false, false); });
    document.getElementById('cookie-accept').addEventListener('click', function () { saveConsent(true, true); });
    customizeButton.addEventListener('click', function () {
        if (!customizing) {
            customizing = true;
            preferences.hidden = false;
            customizeButton.textContent = @js(__('Salva le mie scelte'));
            return;
        }
        saveConsent(document.getElementById('cookie-statistics').checked, document.getElementById('cookie-marketing').checked);
        customizeButton.textContent = 'Personalizza';
    });

    window.openCookieSettings = function () {
        customizing = false;
        preferences.hidden = true;
        customizeButton.textContent = 'Personalizza';
        open();
    };

    if (!readConsent()) { open(); }
})();
</script>
