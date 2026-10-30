/**
 * Entrypoint Skrip Frontend Utama SIM-PBJ Politeknik Negeri Jakarta (app.js)
 *
 * Mengorkestrasi interaktivitas modern antarmuka pengguna front end (non-tes_*).
 *
 * Catatan Arsitektur:
 * Berkas ini bertindak sebagai entrypoint/bundler utama. Jangan menumpuk seluruh kode
 * JavaScript halaman/topik di sini agar berkas tidak membengkak dan sulit dirawat.
 * Pecah logika fitur ke dalam modul-modul mandiri pada direktori resources/js/modules/
 * (misal: tracking.js, dokumentasi.js, dsb.) lalu impor modul terkait ke dalam berkas ini.
 */

import { desktopNotificationManager } from './modules/desktop_notification.js';
import { trackingManager } from './modules/tracking.js';
import { splitMakManager } from './modules/split_mak.js';

// Bind globally for Alpine.js and inline evaluations
window.desktopNotificationManager = desktopNotificationManager;
window.trackingManager = trackingManager;
window.splitMakManager = splitMakManager;

document.addEventListener('alpine:init', () => {
    if (window.Alpine) {
        window.Alpine.data('desktopNotificationManager', desktopNotificationManager);
        window.Alpine.data('trackingManager', trackingManager);
        window.Alpine.data('splitMakManager', splitMakManager);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    // Ruang inisialisasi modul front end modern (misal: Alpine.js, komponen interaktif, dsb.)
});
