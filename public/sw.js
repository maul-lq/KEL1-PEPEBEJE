/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : menulis Service Worker ini untuk menerima Web Push event dan memicu notifikasi native OS Desktop bagi pengguna SIM-PBJ PNJ.
 */

/**
 * Handler Event 'install' Service Worker
 *
 * memakai handler ini agar pembaruan Service Worker langsung siap mengambil alih tanpa menunggu seluruh tab ditutup.
 *
 * @param {ExtendableEvent} event - Event siklus instalasi Service Worker dari peramban.
 * @returns {void} Tidak mengembalikan nilai; memanggil self.skipWaiting().
 *
 * Contoh pemanggilan:
 * `// Dipicu otomatis oleh browser saat navigator.serviceWorker.register('/sw.js') dijalankan.`
 */
self.addEventListener('install', function (event) {
    // langsung melompati fase waiting agar perbaikan handler notifikasi langsung aktif di sesi pengguna.
    self.skipWaiting();
});

/**
 * Handler Event 'activate' Service Worker
 *
 * mengklaim seluruh klien/tab terbuka segera setelah aktivasi agar notifikasi langsung bekerja pada kunjungan pertama.
 *
 * @param {ExtendableEvent} event - Event aktivasi Service Worker dari peramban.
 * @returns {void} Tidak mengembalikan nilai; menunggu clients.claim() selesai via waitUntil.
 *
 * Contoh pemanggilan:
 * `// Dipicu otomatis oleh browser tepat setelah Service Worker berstatus activated.`
 */
self.addEventListener('activate', function (event) {
    // mengikat seluruh tab yang sudah terbuka sebelum registrasi selesai supaya pesan notifikasi tidak terlewat.
    event.waitUntil(self.clients.claim());
});

/**
 * Handler Event 'push' untuk Notifikasi Latar Belakang
 *
 * mem-parsing payload JSON/teks dari push server dan merender pop-up notifikasi sistem operasi dengan tautan modul tujuan.
 *
 * @param {PushEvent} event - Event push yang membawa data payload notifikasi dari server.
 * @returns {void} Tidak mengembalikan nilai; menampilkan notifikasi via showNotification().
 *
 * Contoh pemanggilan:
 * `// Dipicu otomatis oleh Push API browser saat server mengirim web push ke subscription aktif.`
 */
self.addEventListener('push', function (event) {
    // menyiapkan payload fallback agar notifikasi tetap informatif meski server mengirim body kosong.
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
            // jatuh ke teks mentah saat payload bukan JSON valid supaya pesan penting tidak hilang diam-diam.
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
        // menyertakan timestamp unik pada tag agar beberapa alert pengadaan yang datang beruntun tidak saling menimpa.
        tag: 'simpbj-notification-' + Date.now(),
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

/**
 * Handler Event 'notificationclick' untuk Navigasi Langsung
 *
 * mengarahkan pengguna ke halaman modul terkait saat notifikasi OS diklik, dengan memprioritaskan tab SIM-PBJ yang sudah terbuka.
 *
 * @param {NotificationEvent} event - Event klik pada notifikasi sistem operasi.
 * @returns {void} Tidak mengembalikan nilai; memfokuskan atau membuka jendela baru via waitUntil.
 *
 * Contoh pemanggilan:
 * `// Dipicu otomatis ketika pengguna mengklik pop-up notifikasi SIM-PBJ di desktop/OS.`
 */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const rawUrl = event.notification.data?.url || '/notifikasi';
    // menormalkan URL relatif terhadap origin aktif agar navigasi lintas sub-rute selalu valid dan aman dari open redirect.
    const resolvedUrl = new URL(rawUrl, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if ('focus' in client) {
                    if ('navigate' in client) {
                        client.navigate(resolvedUrl);
                    }
                    // memakai ulang tab yang sudah terbuka alih-alih membuka tab baru agar ruang kerja pengguna tetap rapi.
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(resolvedUrl);
            }
        })
    );
});
