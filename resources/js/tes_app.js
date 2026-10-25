/**
 * Entrypoint Skrip Pengujian Backend SIM-PBJ Politeknik Negeri Jakarta (tes_app.js)
 *
 * Mengorkestrasi modul-modul interaktivitas semantik pengujian backend secara terstruktur:
 * 1. Autofill Kredensial Login Demo (./tes/autofill_login.js)
 * 2. Indikator Auto-Routing Ambang Batas HPS (./tes/routing_hint.js)
 *
 * Catatan Arsitektur:
 * Berkas ini hanya bertindak sebagai entrypoint/orchestrator global. Seluruh kode interaktivitas
 * fitur atau halaman dipecah ke dalam modul-modul modular di direktori resources/js/tes/
 * agar kode terstruktur rapi, mudah dirawat, dan tidak membengkak.
 */

import { initAutofillLogin } from './tes/autofill_login.js';
import { initNotificationManager } from './tes/notification_manager.js';
import { initRoutingHint } from './tes/routing_hint.js';

document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi modul pembantu pengujian backend semantik
    initAutofillLogin();
    initRoutingHint();
    initNotificationManager();
});
