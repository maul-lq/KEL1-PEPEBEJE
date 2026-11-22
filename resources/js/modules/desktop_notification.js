/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini bangun untuk mengelola notifikasi real-time di dalam aplikasi dan
 *             browser (toast antarmuka, polling berkala API notifikasi, kursor ID persisten di
 *             localStorage, serta pendaftaran Service Worker PWA `/sw.js`).
 */

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
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'denied',
        showBanner: false,
        inAppToasts: [],
        seenToastIds: new Set(),

        /**
         * Memeriksa izin notifikasi browser, mendaftarkan Service Worker, dan menyalakan poller.
         *
         * Tujuan: hanya menampilkan banner permintaan izin sekali per pengguna jika pengguna
         * belum memilih dan belum menekan tombol tutup permanen.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis saat komponen dimuat oleh Alpine.
         */
        init() {
            this.userId = this.$el?.dataset?.userId || 'guest';

            if (typeof Notification !== 'undefined') {
                this.permission = Notification.permission;
                const dismissKey = `simpbj_os_notif_dismissed_${this.userId}`;
                if (this.permission === 'default' && !localStorage.getItem(dismissKey)) {
                    this.showBanner = true;
                }
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js').catch((err) => {
                    console.warn('Gagal mendaftarkan Service Worker SIM-PBJ:', err);
                });
            }

            this.startPolling();
        },

        /**
         * Meminta izin notifikasi native browser kepada pengguna dan mengirim notifikasi uji bila disetujui.
         *
         * Tujuan: menyebarkan CustomEvent `simpbj-permission-changed` agar komponen tombol status
         * izin di halaman profil atau header langsung tersinkron tanpa reload.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="requestPermission()"`
         */
        requestPermission() {
            if (typeof Notification === 'undefined') {
                return;
            }

            Notification.requestPermission().then((perm) => {
                this.permission = perm;
                this.showBanner = false;
                window.dispatchEvent(new CustomEvent('simpbj-permission-changed', { detail: { permission: perm } }));

                if (perm === 'granted') {
                    this.showNativeAlert(
                        'SIM-PBJ Politeknik Negeri Jakarta',
                        'Notifikasi browser aktif! Anda akan menerima pemberitahuan pengadaan secara langsung.',
                        '/notifikasi'
                    );
                }
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
         * Tujuan: menyimpan penanda poller global per `userId` untuk mencegah interval ganda saat
         * komponen terinisialisasi ulang, serta mematikan timer otomatis bila server merespons HTTP 401.
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
                    .then((res) => {
                        // Jika sesi berakhir (401), hentikan interval agar tidak terus mengirim request gagal.
                        if (res.status === 401) {
                            if (window.__simpbjNotifPollerTimer) {
                                clearInterval(window.__simpbjNotifPollerTimer);
                            }
                            return null;
                        }
                        return res.json();
                    })
                    .then((payload) => {
                        if (!payload || payload.status !== 'success') {
                            return;
                        }

                        // Pada kunjungan perdana tanpa kursor, simpan latest_id tanpa membanjiri layar dengan notifikasi lama.
                        if (!hasCursor) {
                            const initialCursor = Number(payload.latest_id || 0);
                            localStorage.setItem(cursorKey, String(initialCursor));
                        } else if (Array.isArray(payload.data) && payload.data.length > 0) {
                            payload.data.forEach((item) => {
                                this.pushToast(item);

                                if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                                    this.showNativeAlert(
                                        item.judul || 'SIM-PBJ: Notifikasi Pengadaan',
                                        item.pesan + (item.referensi ? ` (${item.referensi})` : ''),
                                        item.action_url || '/notifikasi'
                                    );
                                }

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
                if (!document.hidden && Date.now() - lastCheckAt > 15000) {
                    checkNewAlerts();
                }
            });
        },

        /**
         * Menampilkan notifikasi desktop OS melalui Service Worker atau fallback API Notification.
         *
         * Tujuan: memprioritaskan `registration.showNotification` agar notifikasi mendukung
         * klik navigasi di background lewat `sw.js`, dengan fallback objek `Notification` standar.
         * Parameter: `{string} title`, `{string} body`, `{string} url`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.showNativeAlert('Judul', 'Isi pesan', '/notifikasi');`
         */
        showNativeAlert(title, body, url) {
            if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
                return;
            }

            const options = {
                body,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                data: { url: url || '/notifikasi' },
                tag: `simpbj-notif-${Date.now()}`,
                renotify: true,
            };

            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready.then((reg) => {
                    reg.showNotification(title, options);
                });
            } else {
                try {
                    const notif = new Notification(title, options);
                    notif.onclick = function () {
                        window.focus();
                        window.location.href = url || '/notifikasi';
                    };
                } catch (e) {
                    console.warn('Fallback Notification gagal:', e);
                }
            }
        },
    };
}

/**
 * Menyediakan kontrol UI sederhana untuk menampilkan dan memicu izin notifikasi browser.
 *
 * Tujuan: menjembatani tombol pengaktifan notifikasi di halaman notifikasi/profil dengan
 * komponen manajer utama lewat event global `simpbj-request-browser-permission`.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `supported`, `permission`, dan method `activate()` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="browserNotificationControl()">`
 */
export function browserNotificationControl() {
    return {
        supported: typeof Notification !== 'undefined',
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'unsupported',

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
         * Mengirim sinyal global guna meminta prompt perizinan notifikasi dari manajer utama.
         *
         * Tujuan: mengabaikan aksi secara aman jika peramban tidak mendukung Notification API.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="activate()"`
         */
        activate() {
            if (!this.supported) {
                return;
            }
            window.dispatchEvent(new CustomEvent('simpbj-request-browser-permission'));
        },
    };
}
