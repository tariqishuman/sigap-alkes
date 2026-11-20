/* SIGAP-Alkes Service Worker — Web Push & Notification Actions */

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {
            title: 'SIGAP-Alkes',
            body: event.data ? event.data.text() : 'Ada pembaruan baru di SIGAP-Alkes.',
        };
    }

    const title = data.title || 'SIGAP-Alkes';
    const options = {
        body: data.body || '',
        icon: '/images/brand/logo-mark.svg',
        badge: '/images/brand/logo-mark.svg',
        tag: data.tag || 'sigap-alkes-notification',
        renotify: true,
        requireInteraction: Boolean(data.requireInteraction),
        data: {
            url: data.url || '/admin',
            ackUrl: data.ackUrl || null,
        },
        actions: Array.isArray(data.actions) ? data.actions : [],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const payload = event.notification.data || {};
    const targetUrl = (event.action === 'ack' && payload.ackUrl)
        ? payload.ackUrl
        : (payload.url || '/admin');

    const normalizedTarget = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Focus an existing SIGAP tab only if it is already on the exact target URL.
            // Never navigate an open tab so unsaved form inputs are preserved.
            for (const client of clientList) {
                try {
                    if (new URL(client.url).href === normalizedTarget && 'focus' in client) {
                        return client.focus();
                    }
                } catch (e) {}
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(normalizedTarget);
            }
        })
    );
});
