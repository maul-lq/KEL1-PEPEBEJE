/**
 * Modul Manajemen Notifikasi In-App & Browser SIM-PBJ Politeknik Negeri Jakarta
 *
 * Mengelola polling berkala untuk alert toast di dalam aplikasi, sinkronisasi lencana header,
 * perizinan notifikasi browser, serta pendaftaran Service Worker.
 */

export function desktopNotificationManager() {
    return {
        userId: 'guest',
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'denied',
        showBanner: false,
        inAppToasts: [],
        seenToastIds: new Set(),

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

        dismissBanner() {
            localStorage.setItem(`simpbj_os_notif_dismissed_${this.userId}`, 'true');
            this.showBanner = false;
        },

        pushToast(item) {
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

        dismissToast(toastId) {
            this.inAppToasts = this.inAppToasts.filter((t) => t.id !== toastId);
        },

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

export function browserNotificationControl() {
    return {
        supported: typeof Notification !== 'undefined',
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'unsupported',

        init() {
            window.addEventListener('simpbj-permission-changed', (e) => {
                if (e.detail?.permission) {
                    this.permission = e.detail.permission;
                }
            });
        },

        activate() {
            if (!this.supported) {
                return;
            }
            window.dispatchEvent(new CustomEvent('simpbj-request-browser-permission'));
        },
    };
}
