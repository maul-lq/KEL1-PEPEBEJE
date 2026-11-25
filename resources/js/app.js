/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Entrypoint JavaScript utama aplikasi SIM-PBJ yang susun untuk mengimpor seluruh
 *             modul komponen Alpine.js, mengekspos pabriknya ke objek global `window`, mendaftarkannya
 *             pada event `alpine:init`, dan menjalankan listener DOM global saat halaman siap.
 */

import Alpine from 'alpinejs';
import { browserNotificationControl, desktopNotificationManager } from './modules/desktop_notification.js';
import { trackingManager } from './modules/tracking.js';
import { splitMakManager } from './modules/split_mak.js';
import { laporanApp } from './modules/laporan.js';
import {
    docPreviewModal,
    dropdownMenu,
    initGlobalDomHandlers,
    lightboxViewer,
    modalDialog,
} from './modules/ui_helpers.js';
import { appShell, headerNotificationBar } from './modules/layout.js';
import { loginForm, registerForm, resetPasswordForm } from './modules/auth.js';
import {
    adminDashboardApproval,
    adminRoleRow,
    adminRolesPage,
    adminUsers,
    ppbjDashboard,
} from './modules/admin.js';
import {
    profileDeleteForm,
    profilePage,
    profilePasswordForm,
    profilePhotoUpload,
    profileReminderToggle,
} from './modules/profile.js';
import {
    permohonanCreateModalUpload,
    permohonanCreatePage,
    permohonanQuickModal,
    rupiahField,
} from './modules/permohonan_form.js';
import {
    ppkPersetujuanDokumen,
    verifikasiKeputusanPpk,
    verifikasiKeputusanWadir2,
    verifikasiMakForm,
    verifikasiMakList,
} from './modules/verifikasi.js';
import { paketCreate, paketEdit, paketIndexPage } from './modules/paket.js';
import { kontrakManager } from './modules/kontrak.js';
import {
    initPembayaranPencairan,
    pembayaranPengajuan,
    pembayaranRejectRow,
} from './modules/pembayaran.js';

/**
 * Peta registrasi komponen Alpine.js lintas halaman SIM-PBJ.
 *
 * Tujuan: memusatkan seluruh factory komponen agar dapat diekspos serempak baik lewat
 * properti global `window` maupun `Alpine.data()` tanpa duplikasi registrasi di setiap Blade view.
 * Parameter: Berisi pasangan nama komponen dan fungsi pabriknya.
 * Nilai kembali: Objek peta factory siap iterasi untuk registrasi global dan Alpine.
 * Contoh pemanggilan: `Alpine.data('trackingManager', alpineComponents.trackingManager);`
 */
const alpineComponents = {
    desktopNotificationManager,
    browserNotificationControl,
    trackingManager,
    splitMakManager,
    laporanApp,
    dropdownMenu,
    modalDialog,
    docPreviewModal,
    lightboxViewer,
    appShell,
    headerNotificationBar,
    loginForm,
    registerForm,
    resetPasswordForm,
    adminUsers,
    adminRolesPage,
    adminRoleRow,
    adminDashboardApproval,
    ppbjDashboard,
    profilePage,
    profilePhotoUpload,
    profileReminderToggle,
    profilePasswordForm,
    profileDeleteForm,
    permohonanCreatePage,
    permohonanCreateModalUpload,
    rupiahField,
    permohonanQuickModal,
    verifikasiMakForm,
    verifikasiMakList,
    verifikasiKeputusanPpk,
    verifikasiKeputusanWadir2,
    ppkPersetujuanDokumen,
    paketCreate,
    paketEdit,
    paketIndexPage,
    kontrakManager,
    pembayaranPengajuan,
    pembayaranRejectRow,
};

// mengekspos instance Alpine ke global window agar inspeksi devtools dan skrip pendukung tetap mengenali Alpine lokal.
window.Alpine = Alpine;

// tempelkan tiap factory ke window supaya atribut x-data="namaKomponen()" selalu teresolusi sebelum maupun sesudah alpine:init.
Object.entries(alpineComponents).forEach(([name, factory]) => {
    window[name] = factory;
});

/**
 * Pendaftaran komponen ke registri resmi Alpine saat kerangka Alpine menginisialisasi dirinya.
 *
 * Tujuan: memastikan komponen tersedia melalui `Alpine.data` sebelum `Alpine.start()`
 * memindai DOM sehingga tidak perlu memuat skrip Alpine.js dari CDN eksternal.
 * Parameter: Callback event `alpine:init`.
 * Nilai kembali: Tidak mengembalikan nilai.
 * Contoh pemanggilan: Dipicu otomatis oleh `Alpine.start()`.
 */
document.addEventListener('alpine:init', () => {
    Object.entries(alpineComponents).forEach(([name, factory]) => {
        Alpine.data(name, factory);
    });
});

// memulai runtime Alpine.js lokal setelah listener alpine:init terpasang.
Alpine.start();

/**
 * Inisialisasi pengikat event DOM global setelah struktur dokumen HTML selesai dimuat.
 *
 * Tujuan: menjalankan delegasi tombol global dan pemilih SPP cair
 * tepat setelah DOM tersedia tanpa memerlukan inline `<script>` di file Blade.
 * Parameter: Tidak menerima parameter masukan.
 * Nilai kembali: Tidak mengembalikan nilai.
 * Contoh pemanggilan: `bootstrapDomHandlers();`
 */
function bootstrapDomHandlers() {
    initGlobalDomHandlers();
    initPembayaranPencairan();
}

// mengecek readyState agar inisialisasi tetap berjalan jika bundel module dievaluasi setelah DOMContentLoaded.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapDomHandlers);
} else {
    bootstrapDomHandlers();
}
