/**
 * Modul Desktop Notification OS Manager SIM-PBJ Politeknik Negeri Jakarta
 *
 * Mengelola perizinan dan pengiriman notifikasi desktop OS secara native
 * serta integrasi dengan Service Worker dan polling berkala.
 */

export function desktopNotificationManager() {
    return {
        permission: (typeof Notification !== 'undefined') ? Notification.permission : 'denied',
        showBanner: false,

        init() {
            if (typeof Notification !== 'undefined') {
                this.permission = Notification.permission;
                if (this.permission === 'default' && !localStorage.getItem('simpbj_os_notif_dismissed')) {
                    this.showBanner = true;
                }
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js').catch(function(err) {
                    console.warn('Gagal mendaftarkan Service Worker SIM-PBJ:', err);
                });
            }

            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                this.startPolling();
            }
        },

        requestPermission() {
            if (typeof Notification === 'undefined') {
                alert('Peramban Anda tidak mendukung Web Notification API.');
                return;
            }

            const self = this;
            Notification.requestPermission().then(function(perm) {
                self.permission = perm;
                self.showBanner = false;

                if (perm === 'granted') {
                    self.showNativeAlert(
                        'SIM-PBJ Politeknik Negeri Jakarta',
                        'Notifikasi Desktop aktif! Anda akan menerima alarm pengadaan langsung di OS.',
                        '/notifikasi'
                    );
                    self.startPolling();
                }
            });
        },

        dismissBanner() {
            localStorage.setItem('simpbj_os_notif_dismissed', 'true');
            this.showBanner = false;
        },

        startPolling() {
            let lastSeenId = parseInt(localStorage.getItem('simpbj_last_notified_id') || '0', 10);
            const self = this;

            const checkNewAlerts = function() {
                fetch('/api/notifikasi-terbaru?since_id=' + lastSeenId, { credentials: 'same-origin' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success' && data.data && data.data.length > 0) {
                            data.data.forEach(item => {
                                self.showNativeAlert(
                                    item.judul || 'SIM-PBJ: Notifikasi Pengadaan',
                                    item.pesan + (item.referensi ? ' (' + item.referensi + ')' : ''),
                                    item.action_url || '/notifikasi'
                                );
                                if (item.id > lastSeenId) {
                                    lastSeenId = item.id;
                                }
                            });
                            localStorage.setItem('simpbj_last_notified_id', lastSeenId.toString());
                        }
                    })
                    .catch(err => {});
            };

            setTimeout(checkNewAlerts, 4000);
            setInterval(checkNewAlerts, 30000);
        },

        showNativeAlert(title, body, url) {
            if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
                return;
            }

            const options = {
                body: body,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                data: { url: url || '/notifikasi' },
                tag: 'simpbj-notif-' + Date.now(),
                renotify: true
            };

            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.showNotification(title, options);
                });
            } else {
                try {
                    const notif = new Notification(title, options);
                    notif.onclick = function() {
                        window.focus();
                        window.location.href = url || '/notifikasi';
                    };
                } catch (e) {
                    console.warn('Fallback Notification gagal:', e);
                }
            }
        }
    };
}
