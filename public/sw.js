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
    let targetUrl = payload.url || '/admin';

    if (event.action === 'ack' && payload.ackUrl) {
        targetUrl = payload.ackUrl;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
        })
    );
});
