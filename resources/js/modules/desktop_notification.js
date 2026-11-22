/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Modul ini bangun untuk mengelola notifikasi real-time di dalam aplikasi dan
 *             browser (toast antarmuka, polling berkala API notifikasi, kursor ID persisten di
 *             localStorage, serta pendaftaran Service Worker `/sw.js` dengan fallback aman).
 */

import { redirectToLoginOnSessionEnd } from './layout.js';

/**
 * Memeriksa apakah peramban mendukung Web Notification pada konteks asal aman (localhost atau HTTPS).
 *
 * Tujuan: mencegah tombol izin notifikasi tampak aktif namun gagal diam-diam saat halaman dibuka melalui IP LAN HTTP.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: `{boolean}` Bernilai true jika API Notification tersedia dan berjalan di konteks aman.
 * Contoh pemanggilan: `if (isBrowserNotificationSupported()) { ... }`
 */
function isBrowserNotificationSupported() {
    return typeof Notification !== 'undefined' && window.isSecureContext !== false;
}

/**
 * Mengirimkan notifikasi sistem operasi melalui Service Worker beserta fallback otomatis ke Notification API standar.
 *
 * Tujuan: mempertahankan pemakaian ikon/badge `favicon.ico` serta tag unik per ID notifikasi sambil
 * menjamin kegagalan `reg.showNotification()` tetap jatuh ke constructor `Notification` standar.
 * Parameter: `{string} title`, `{string} body`, `{string} [url='/notifikasi']`, `{string|number} [tagSuffix]`.
 * Nilai kembali: `{Promise<void>}` Promise pengiriman notifikasi OS.
 * Contoh pemanggilan: `await displayNativeBrowserNotification('Judul', 'Isi pesan', '/notifikasi', 15);`
 */
async function displayNativeBrowserNotification(title, body, url = '/notifikasi', tagSuffix = '') {
    if (!isBrowserNotificationSupported() || Notification.permission !== 'granted') {
        return;
    }

    const uniqueId = tagSuffix || `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    const targetUrl = url || '/notifikasi';
    const tag = `simpbj-notif-${uniqueId}`;

    const swOptions = {
        body,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: { url: targetUrl },
        tag,
        renotify: true,
    };

    if ('serviceWorker' in navigator) {
        try {
            const registration = (await navigator.serviceWorker.getRegistration('/')) || (await navigator.serviceWorker.ready);
            if (registration && typeof registration.showNotification === 'function') {
                await registration.showNotification(title, swOptions);
                return;
            }
        } catch (swError) {
            // lanjut ke fallback Notification() langsung bila Service Worker menolak opsi ikon/badge atau belum aktif.
        }
    }

    try {
        const notif = new Notification(title, {
            body,
            icon: '/favicon.ico',
            data: { url: targetUrl },
            tag,
        });
        notif.onclick = function () {
            window.focus();
            window.location.href = targetUrl;
        };
    } catch (directError) {
        try {
            // mencoba ulang tanpa ikon apabila sistem operasi menolak ukuran ikon besar pada konstruktor langsung.
            const plainNotif = new Notification(title, { body, tag });
            plainNotif.onclick = function () {
                window.focus();
                window.location.href = targetUrl;
            };
        } catch (fallbackError) {
            console.warn('Gagal menampilkan notifikasi browser OS:', fallbackError);
        }
    }
}

/**
 * Meminta izin notifikasi peramban langsung pada konteks klik pengguna agar diterima oleh Firefox dan Chrome.
 *
 * Tujuan: menjaga keabsahan user-gesture saat memanggil `Notification.requestPermission()`, menyiarkan
 * status izin baru ke seluruh komponen UI, dan mengirim satu notifikasi uji begitu izin diberikan.
 * Parameter: `{Function} [onPermissionResolved]` - Callback opsional penerima nilai permission baru.
 * Nilai kembali: `{Promise<string|null>}` Status izin (`granted`, `denied`, `default`) atau null bila tidak didukung.
 * Contoh pemanggilan: `await requestDirectNotificationPermission((perm) => { this.permission = perm; });`
 */
async function requestDirectNotificationPermission(onPermissionResolved) {
    if (!isBrowserNotificationSupported()) {
        return null;
    }

    try {
        const permission = await Notification.requestPermission();
        if (typeof onPermissionResolved === 'function') {
            onPermissionResolved(permission);
        }

        window.dispatchEvent(new CustomEvent('simpbj-permission-changed', { detail: { permission } }));

        if (permission === 'granted') {
            await displayNativeBrowserNotification(
                'SIM-PBJ Politeknik Negeri Jakarta',
                'Notifikasi browser aktif! Anda akan menerima pemberitahuan pengadaan secara langsung.',
                '/notifikasi',
                `welcome-${Date.now()}`
            );
        }

        return permission;
    } catch (error) {
        console.warn('Gagal meminta izin notifikasi browser:', error);
        return null;
    }
}

/**
 * Mengelola polling notifikasi latar belakang, antrean toast in-app, dan notifikasi native OS.
 *
 * Tujuan: memisahkan kursor ID notifikasi per akun di `localStorage` dan memberi throttle
 * minimal 5 detik per permintaan agar tab browser ganda maupun fokus ulang tidak membanjiri server.
 * Parameter: Tidak menerima argumen (identitas akun dibaca dari `this.$el.dataset.userId`).
 * Nilai kembali: Objek state dan method pengelola notifikasi real-time untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="desktopNotificationManager()" data-user-id="{{ Auth::id() }}">`
 */
export function desktopNotificationManager() {
    return {
        userId: 'guest',
        supported: isBrowserNotificationSupported(),
        permission: isBrowserNotificationSupported() ? Notification.permission : 'denied',
        showBanner: false,
        inAppToasts: [],
        seenToastIds: new Set(),

        /**
         * Memeriksa izin notifikasi browser, mendaftarkan Service Worker, dan menyalakan poller.
         *
         * Tujuan: hanya menampilkan kontrol permintaan izin bila konteks asal aman mendukung Web Notification
         * serta menyinkronkan status izin terbaru bila diubah lewat tombol halaman Notifikasi.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis saat komponen dimuat oleh Alpine.
         */
        init() {
            this.userId = this.$el?.dataset?.userId || 'guest';
            this.supported = isBrowserNotificationSupported();

            if (this.supported) {
                this.permission = Notification.permission;
                const dismissKey = `simpbj_os_notif_dismissed_${this.userId}`;
                if (this.permission === 'default' && !localStorage.getItem(dismissKey)) {
                    this.showBanner = true;
                }
            }

            window.addEventListener('simpbj-permission-changed', (event) => {
                if (event.detail?.permission) {
                    this.permission = event.detail.permission;
                    this.showBanner = false;
                }
            });

            if (this.supported && 'serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((err) => {
                    console.warn('Gagal mendaftarkan Service Worker SIM-PBJ:', err);
                });
            }

            this.startPolling();
        },

        /**
         * Meminta izin notifikasi native browser kepada pengguna secara langsung dan mengirim notifikasi uji bila disetujui.
         *
         * Tujuan: memanggil helper izin sinkron dari handler klik dan menutup banner permintaan izin.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{Promise<void>}`
         * Contoh pemanggilan: `@click="requestPermission()"`
         */
        async requestPermission() {
            await requestDirectNotificationPermission((perm) => {
                this.permission = perm;
                this.showBanner = false;
            });
        },

        /**
         * Menyimpan status penutupan banner izin notifikasi per user ke `localStorage`.
         *
         * Tujuan: menghormati pilihan pengguna yang menolak banner agar tidak terus muncul di
         * setiap perpindahan halaman.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="dismissBanner()"`
         */
        dismissBanner() {
            localStorage.setItem(`simpbj_os_notif_dismissed_${this.userId}`, 'true');
            this.showBanner = false;
        },

        /**
         * Menambahkan item notifikasi baru ke tumpukan toast di sudut layar secara unik (deduplikasi).
         *
         * Tujuan: membatasi maksimal 4 toast aktif sekaligus dan menghapusnya otomatis setelah 9
         * detik agar tidak menutupi area aksi utama aplikasi.
         * Parameter: `{Object} item` - Objek notifikasi dari API `/api/notifikasi-terbaru`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.pushToast(item);`
         */
        pushToast(item) {
            // cek seenToastIds untuk menjamin notifikasi dengan ID yang sama tidak muncul dua kali.
            if (!item || !item.id || this.seenToastIds.has(item.id)) {
                return;
            }
            this.seenToastIds.add(item.id);

            const toast = {
                id: item.id,
                judul: item.judul || 'Pemberitahuan Baru',
                pesan: item.pesan || '',
                referensi: item.referensi || '',
                badge: item.badge || 'Pengingat',
                action_url: item.action_url || '/notifikasi',
                action_label: item.action_label || 'Buka',
            };

            this.inAppToasts.unshift(toast);
            if (this.inAppToasts.length > 4) {
                this.inAppToasts = this.inAppToasts.slice(0, 4);
            }

            setTimeout(() => {
                this.dismissToast(toast.id);
            }, 9000);
        },

        /**
         * Menghapus satu toast dari daftar tampilan berdasarkan ID notifikasi.
         *
         * Tujuan: membiarkan pengguna menutup toast lebih cepat atau membersihkannya lewat timer.
         * Parameter: `{number|string} toastId` - ID unik notifikasi toast yang ditutup.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="dismissToast(toast.id)"`
         */
        dismissToast(toastId) {
            this.inAppToasts = this.inAppToasts.filter((t) => t.id !== toastId);
        },

        /**
         * Memulai polling periodik 20 detik sekali beserta sinkronisasi saat tab kembali aktif.
         *
         * Tujuan: memunculkan hingga 3 notifikasi belum dibaca terbaru pada kunjungan perdana agar
         * alert sebelum login tidak terlewati, lalu memantau item berikutnya memakai `since_id`.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.startPolling();`
         */
        startPolling() {
            if (window.__simpbjNotifPollerStartedForUser === this.userId) {
                return;
            }
            window.__simpbjNotifPollerStartedForUser = this.userId;

            if (window.__simpbjNotifPollerTimer) {
                clearInterval(window.__simpbjNotifPollerTimer);
            }

            const cursorKey = `simpbj_last_notified_id_${this.userId}`;
            let lastCheckAt = 0;

            const dispatchItemAlerts = (items) => {
                items.forEach((item) => {
                    this.pushToast(item);

                    if (isBrowserNotificationSupported() && Notification.permission === 'granted') {
                        this.showNativeAlert(
                            item.judul || 'SIM-PBJ: Notifikasi Pengadaan',
                            item.pesan + (item.referensi ? ` (${item.referensi})` : ''),
                            item.action_url || '/notifikasi',
                            item.id
                        );
                    }
                });
            };

            const checkNewAlerts = () => {
                const now = Date.now();
                // pasang jeda minimum 5 detik supaya trigger berdekatan dari visibilitychange tidak memukul API dua kali.
                if (now - lastCheckAt < 5000) {
                    return;
                }
                lastCheckAt = now;

                const savedCursorRaw = localStorage.getItem(cursorKey);
                const hasCursor = savedCursorRaw !== null && !Number.isNaN(parseInt(savedCursorRaw, 10));
                let lastSeenId = hasCursor ? parseInt(savedCursorRaw, 10) : 0;

                const query = hasCursor ? `?since_id=${lastSeenId}` : '';

                fetch(`/api/notifikasi-terbaru${query}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                    .then(async (res) => {
                        // Jika sesi berakhir atau akun dinonaktifkan (401), hentikan polling dan alihkan ke halaman masuk.
                        if (res.status === 401) {
                            const expiredPayload = await res.json().catch(() => null);
                            redirectToLoginOnSessionEnd(expiredPayload?.redirect);
                            return null;
                        }
                        return res.json();
                    })
                    .then((payload) => {
                        if (!payload || payload.status !== 'success') {
                            return;
                        }

                        if (!hasCursor) {
                            // memicu toast & notifikasi OS untuk maksimal 3 item belum dibaca terbaru agar notifikasi yang masuk sebelum tab terbuka tetap tersampaikan.
                            const unreadRecent = Array.isArray(payload.recent)
                                ? payload.recent.filter((item) => !item.is_read).slice(0, 3).reverse()
                                : [];

                            if (unreadRecent.length > 0) {
                                dispatchItemAlerts(unreadRecent);
                            }

                            const initialCursor = Number(payload.latest_id || 0);
                            localStorage.setItem(cursorKey, String(initialCursor));
                        } else if (Array.isArray(payload.data) && payload.data.length > 0) {
                            dispatchItemAlerts(payload.data);

                            payload.data.forEach((item) => {
                                if (item.id > lastSeenId) {
                                    lastSeenId = item.id;
                                }
                            });
                            localStorage.setItem(cursorKey, String(lastSeenId));
                        }

                        window.dispatchEvent(new CustomEvent('simpbj-notifications-synced', { detail: payload }));
                    })
                    .catch(() => {});
            };

            setTimeout(checkNewAlerts, 2000);
            window.__simpbjNotifPollerTimer = setInterval(checkNewAlerts, 20000);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && Date.now() - lastCheckAt > 5000) {
                    checkNewAlerts();
                }
            });
        },

        /**
         * Menampilkan notifikasi desktop OS melalui Service Worker atau fallback API Notification langsung.
         *
         * Tujuan: meneruskan pengiriman ke helper terpusat lengkap dengan tag unik per ID notifikasi.
         * Parameter: `{string} title`, `{string} body`, `{string} url`, `{string|number} [tagId]`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.showNativeAlert('Judul', 'Isi pesan', '/notifikasi', 42);`
         */
        showNativeAlert(title, body, url, tagId = '') {
            void displayNativeBrowserNotification(title, body, url, tagId);
        },
    };
}

/**
 * Menyediakan kontrol UI untuk menampilkan status izin dan memicu permintaan izin notifikasi browser langsung.
 *
 * Tujuan: memanggil `Notification.requestPermission()` secara sinkron di dalam aksi klik pengguna
 * serta mendeteksi konteks asal tidak aman agar pesan status akurat di seluruh browser.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `supported`, `secureContext`, `permission`, dan method `activate()` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="browserNotificationControl()">`
 */
export function browserNotificationControl() {
    const secureContext = window.isSecureContext !== false;

    return {
        secureContext,
        supported: typeof Notification !== 'undefined' && secureContext,
        permission: isBrowserNotificationSupported() ? Notification.permission : 'unsupported',

        /**
         * Mendengarkan pembaruan status izin notifikasi agar label tombol UI ikut berubah seketika.
         *
         * Tujuan: menjaga konsistensi status izin di seluruh komponen terpasang saat pengguna
         * mengizinkan atau menolak prompt browser.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis saat inisialisasi Alpine.
         */
        init() {
            window.addEventListener('simpbj-permission-changed', (e) => {
                if (e.detail?.permission) {
                    this.permission = e.detail.permission;
                }
            });
        },

        /**
         * Meminta izin notifikasi secara langsung di konteks klik pengguna agar tidak diblokir browser.
         *
         * Tujuan: menghindari pemutusan rantai user-gesture akibat pengiriman CustomEvent antarkomponen.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{Promise<void>}`
         * Contoh pemanggilan: `@click="activate()"`
         */
        async activate() {
            if (!this.supported) {
                return;
            }

            await requestDirectNotificationPermission((perm) => {
                this.permission = perm;
            });
        },
    };
}
