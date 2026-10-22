/**
 * Service Worker Notifikasi SIM-PBJ Politeknik Negeri Jakarta (sw.js)
 *
 * Menangani penerimaan push event dari server dan mengaktifkan notifikasi pop-up
 * native pada tingkat sistem operasi (Windows Action Center, macOS Notification, Linux Desktop).
 * Mendukung aksi klik untuk memfokuskan jendela peramban atau membuka rute terkait.
 */

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

// 1. Menangani event Push dari Web Push Service
self.addEventListener('push', function (event) {
    let payload = {
        title: 'SIM-PBJ Politeknik Negeri Jakarta',
        body: 'Pemberitahuan pengadaan barang dan jasa baru.',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        url: '/notifikasi',
    };

    if (event.data) {
        try {
            const json = event.data.json();
            payload = Object.assign(payload, json);
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge || '/favicon.ico',
        data: {
            url: payload.url || '/notifikasi',
        },
        vibrate: [200, 100, 200],
        tag: 'simpbj-notification-' + Date.now(),
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

// 2. Menangani aksi klik pada notifikasi OS oleh pengguna
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/notifikasi';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // Cari tab SIM-PBJ yang sedang terbuka untuk difokuskan
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            // Jika belum ada tab yang terbuka, buka jendela peramban baru
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
