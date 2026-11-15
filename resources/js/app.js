/**
 * Entrypoint Skrip Frontend Utama SIM-PBJ Politeknik Negeri Jakarta (app.js)
 *
 * Mengimpor seluruh modul fitur dari resources/js/modules/ berdasarkan domain,
 * mendaftarkannya ke Alpine.js dan objek window, serta mengikat event global halaman.
 */

import { desktopNotificationManager } from './modules/desktop_notification.js';
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
import { initLoginAutofill, loginForm, registerForm } from './modules/auth.js';
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
    permohonanDeleteModal,
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

const alpineComponents = {
    desktopNotificationManager,
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
    permohonanDeleteModal,
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

Object.entries(alpineComponents).forEach(([name, factory]) => {
    window[name] = factory;
});

document.addEventListener('alpine:init', () => {
    if (window.Alpine) {
        Object.entries(alpineComponents).forEach(([name, factory]) => {
            window.Alpine.data(name, factory);
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    initGlobalDomHandlers();
    initLoginAutofill();
    initPembayaranPencairan();
});
