/*
 * Notifiche del browser: registrazione del service worker, attivazione su richiesta del cliente
 * e controllo del permesso a ogni apertura del sito (se è stato revocato, il server torna all'email).
 */
const ENDPOINT_KEY = 'push_endpoint';

function config() {
    const meta = document.querySelector('meta[name="push-config"]');

    if (!meta) {
        return null;
    }

    try {
        return JSON.parse(meta.content);
    } catch (e) {
        return null;
    }
}

function storage(action, value) {
    try {
        if (action === 'get') {
            return localStorage.getItem(ENDPOINT_KEY);
        }
        if (action === 'set') {
            localStorage.setItem(ENDPOINT_KEY, value);
        }
        if (action === 'remove') {
            localStorage.removeItem(ENDPOINT_KEY);
        }
    } catch (e) {
        return null;
    }

    return null;
}

function isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function isIos() {
    return /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

function post(url, body, csrf) {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    });
}

/**
 * Stato di questo dispositivo, per la pagina delle preferenze.
 * @returns {Promise<'unsupported'|'ios-install'|'denied'|'active'|'inactive'>}
 */
async function deviceState() {
    if (isIos() && !isStandalone()) {
        return 'ios-install';
    }
    if (!isSupported()) {
        return 'unsupported';
    }
    if (Notification.permission === 'denied') {
        return 'denied';
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const subscription = await registration.pushManager.getSubscription();

    return Notification.permission === 'granted' && subscription ? 'active' : 'inactive';
}

/**
 * Chiede il permesso (va chiamata dal tocco su un pulsante) e salva l'iscrizione sul server.
 */
async function enable() {
    const settings = config();

    if (!settings || !settings.vapidKey || !isSupported()) {
        throw new Error('unsupported');
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error(permission === 'denied' ? 'denied' : 'dismissed');
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;

    const subscription = (await registration.pushManager.getSubscription())
        || (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(settings.vapidKey),
        }));

    const json = subscription.toJSON();
    const response = await post(settings.storeUrl, {
        endpoint: json.endpoint,
        keys: json.keys,
        contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
    }, settings.csrf);

    if (!response.ok) {
        throw new Error('server');
    }

    storage('set', json.endpoint);
}

/**
 * All'apertura del sito: se questo dispositivo era iscritto ma il permesso o l'iscrizione non ci sono più,
 * avvisa il server, che disattiva l'iscrizione e riporta le preferenze su email.
 */
async function sync() {
    const settings = config();
    const savedEndpoint = storage('get');

    if (!settings || !settings.loggedIn || !savedEndpoint || !isSupported()) {
        return;
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const subscription = await registration.pushManager.getSubscription();
    const stillValid = Notification.permission === 'granted' && subscription && subscription.endpoint === savedEndpoint;

    if (stillValid) {
        return;
    }

    storage('remove');
    await post(settings.revokedUrl, { endpoint: savedEndpoint }, settings.csrf);
}

window.VetrinaPush = { deviceState, enable, isIos, isStandalone };

document.addEventListener('DOMContentLoaded', () => {
    sync().catch(() => {});
});
