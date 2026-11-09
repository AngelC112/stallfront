/*
 * Service worker delle notifiche del browser.
 * Mostra la notifica, conferma la ricezione al server (così non parte l'email di riserva)
 * e al tocco apre la pagina collegata.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: event.data ? event.data.text() : 'Nuova notifica' };
    }

    var show = self.registration.showNotification(data.title || 'Nuova notifica', {
        body: data.body || '',
        tag: data.tag || undefined,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: data.url || '/' }
    });

    var ack = data.ack
        ? fetch(data.ack, { method: 'POST', credentials: 'omit', keepalive: true }).catch(function () {})
        : Promise.resolve();

    event.waitUntil(Promise.all([show, ack]));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            for (var i = 0; i < windows.length; i++) {
                if (windows[i].url === url && 'focus' in windows[i]) {
                    return windows[i].focus();
                }
            }

            return self.clients.openWindow(url);
        })
    );
});
