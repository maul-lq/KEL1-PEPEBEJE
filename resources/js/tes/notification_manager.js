/**
 * Modul Manajer Notifikasi Desktop OS & Izin Web Notification API (notification_manager.js)
 *
 * Mengelola izin notifikasi native komputer (OS Windows Action Center / macOS Notification Center),
 * uji coba kemunculan notifikasi pop-up semantik Zero-CSS, serta polling berkala alarm pengadaan
 * dari endpoint /api/notifikasi-terbaru.
 */

export function initNotificationManager() {
    const statusBadge = document.getElementById('os-notif-status-badge');
    const requestBtn = document.querySelector('[data-action="request-os-notification"]');
    const testBtn = document.querySelector('[data-action="test-os-notification"]');
    const messageBox = document.getElementById('os-notif-message-box');

    // Jika komponen tidak berada pada halaman saat ini, abaikan
    if (!statusBadge && !requestBtn && !testBtn) {
        return;
    }

    const updateStatusDisplay = () => {
        if (!('Notification' in window)) {
            if (statusBadge) {
                statusBadge.textContent = '[TIDAK DIDUKUNG PERAMBAN INI]';
            }
            if (requestBtn) {
                requestBtn.disabled = true;
            }
            if (testBtn) {
                testBtn.disabled = true;
            }
            return;
        }

        const perm = Notification.permission;
        if (statusBadge) {
            if (perm === 'granted') {
                statusBadge.textContent = '[DIIZINKAN & AKTIF]';
            } else if (perm === 'denied') {
                statusBadge.textContent = '[DIBLOKIR / DITOLAK PADA BROWSER]';
            } else {
                statusBadge.textContent = '[BELUM DIIZINKAN (KLIK TOMBOL)]';
            }
        }
    };

    updateStatusDisplay();

    // Fungsi pemuncul notifikasi native desktop
    const showDesktopAlert = (title, body, targetUrl = '/notifikasi') => {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        try {
            const notif = new Notification(title, {
                body: body,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                tag: 'simpbj-alert-' + Date.now(),
            });

            notif.onclick = function () {
                window.focus();
                window.location.href = targetUrl;
            };
        } catch (e) {
            console.warn('Gagal memicu desktop notification:', e);
        }
    };

    // Handler tombol minta izin notifikasi OS
    if (requestBtn) {
        requestBtn.addEventListener('click', function () {
            if (!('Notification' in window)) {
                alert('Peramban Anda tidak mendukung Web Notification API.');
                return;
            }

            Notification.requestPermission().then(function (perm) {
                updateStatusDisplay();
                if (perm === 'granted') {
                    if (messageBox) {
                        messageBox.innerHTML = '<small><strong>Sukses: Izin notifikasi desktop berhasil diaktifkan! Komputer Anda kini siap menerima alarm pengadaan.</strong></small>';
                    }
                    showDesktopAlert(
                        'SIM-PBJ Politeknik Negeri Jakarta',
                        'Notifikasi Desktop Berhasil Diaktifkan! Anda akan menerima pemberitahuan resmi secara langsung di layar komputer.',
                        '/notifikasi'
                    );
                    startPolling();
                } else if (perm === 'denied') {
                    alert('Izin notifikasi diblokir pada setelan peramban. Harap buka ikon gembok/setelan situs di address bar untuk mengizinkan notifikasi.');
                }
            });
        });
    }

    // Handler tombol uji coba kemunculan notifikasi
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            if (!('Notification' in window)) {
                alert('Peramban Anda tidak mendukung Web Notification API.');
                return;
            }

            if (Notification.permission === 'granted') {
                showDesktopAlert(
                    'SIM-PBJ: Uji Coba Notifikasi Desktop',
                    'Uji coba berhasil! Notifikasi pop-up dan suara sistem operasi Anda berfungsi dengan baik.',
                    '/notifikasi'
                );
            } else {
                Notification.requestPermission().then(function (perm) {
                    updateStatusDisplay();
                    if (perm === 'granted') {
                        showDesktopAlert(
                            'SIM-PBJ: Uji Coba Notifikasi Desktop',
                            'Izin diberikan! Notifikasi pop-up desktop Anda kini aktif.',
                            '/notifikasi'
                        );
                        startPolling();
                    } else {
                        alert('Silakan berikan izin notifikasi di peramban untuk memunculkan notifikasi desktop.');
                    }
                });
            }
        });
    }

    // Polling background alarm notifikasi pengadaan
    let pollingInterval = null;
    const startPolling = () => {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        if (pollingInterval) {
            return;
        }

        let lastSeenId = parseInt(sessionStorage.getItem('simpbj_tes_last_id') || '0', 10);

        const checkAlerts = () => {
            fetch('/api/notifikasi-terbaru?since_id=' + lastSeenId, { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        data.data.forEach(item => {
                            showDesktopAlert(
                                item.judul || 'SIM-PBJ: Pemberitahuan Pengadaan',
                                item.pesan + (item.referensi ? ' (' + item.referensi + ')' : ''),
                                item.action_url || '/notifikasi'
                            );
                            if (item.id > lastSeenId) {
                                lastSeenId = item.id;
                            }
                        });
                        sessionStorage.setItem('simpbj_tes_last_id', lastSeenId.toString());
                    }
                })
                .catch(() => {});
        };

        // Periksa setelah 3 detik lalu setiap 20 detik
        setTimeout(checkAlerts, 3000);
        pollingInterval = setInterval(checkAlerts, 20000);
    };

    if ('Notification' in window && Notification.permission === 'granted') {
        startPolling();
    }
}
