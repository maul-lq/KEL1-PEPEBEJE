<?php

use App\Http\Controllers\Admin\ApprovalRegistrasiController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KontrakSPKController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\NotifikasiUserController;
use App\Http\Controllers\PaketPengadaanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PermohonanPengadaanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VerifikasiParalelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RUTE TAMU / GUEST (Belum Login)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    // Halaman Login
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Halaman Registrasi
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Lupa Password & Reset Password
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

    // Otentikasi Masuk Google OAuth (Laravel Socialite)
    Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

/*
|--------------------------------------------------------------------------
| RUTE TERAUTENTIKASI (Sudah Login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Redirect halaman utama ke dashboard
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // Dashboard & Logout
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->name('logout');

    // -------------------------------------------------------------------------
    // Modul 1: Permohonan Pengadaan
    // -------------------------------------------------------------------------
    Route::get('/permohonan', [PermohonanPengadaanController::class, 'permohonanIndex'])->name('permohonan.index');
    Route::get('/permohonan/create', [PermohonanPengadaanController::class, 'permohonanCreate'])->name('permohonan.create');
    Route::post('/permohonan', [PermohonanPengadaanController::class, 'storePermohonan'])->name('permohonan.store');
    Route::post('/permohonan/revisi', [PermohonanPengadaanController::class, 'revisiPermohonan'])->name('permohonan.revisi');
    Route::get('/permohonan/{id}', [PermohonanPengadaanController::class, 'permohonanShow'])
        ->name('permohonan.show')
        ->where(['id' => '.*']);
    Route::delete('/permohonan/{id}', [PermohonanPengadaanController::class, 'destroyPermohonan'])
        ->name('permohonan.destroy')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul 2 & 3: Verifikasi Berantai Awal (Staf B2 -> Perencanaan -> Wadir 2 & PPK)
    // -------------------------------------------------------------------------
    Route::middleware('role:staff_bidang_2')->group(function () {
        Route::get('/verifikasi/bidang-2', [VerifikasiParalelController::class, 'verifikasiBidang2'])->name('verifikasi.bidang-2');
        Route::post('/verifikasi/bidang-2/teruskan', [VerifikasiParalelController::class, 'teruskanStaffB2'])->name('verifikasi.bidang-2.teruskan');
    });

    Route::middleware('role:wadir_2')->group(function () {
        Route::get('/verifikasi/wadir-2', [VerifikasiParalelController::class, 'verifikasiWadir2'])->name('verifikasi.wadir-2');
        Route::post('/verifikasi/wadir-2/keputusan', [VerifikasiParalelController::class, 'keputusanWadir2'])->name('verifikasi.wadir-2.keputusan');
    });

    Route::middleware('role:ppk')->group(function () {
        Route::get('/verifikasi/ppk', [VerifikasiParalelController::class, 'verifikasiPpk'])->name('verifikasi.ppk');
        Route::post('/verifikasi/ppk/keputusan', [VerifikasiParalelController::class, 'keputusanPpkAwal'])->name('verifikasi.ppk.keputusan');

        // BPMN N30-N36: Menerima & Menyetujui Dokumen Pengadaan (setelah PPBJ susun & PP verifikasi)
        Route::get('/reviu-pengadaan/ppk', [KontrakSPKController::class, 'persetujuanDokumenPpkIndex'])->name('ppk.persetujuan-dokumen.index');
        Route::post('/reviu-pengadaan/ppk/keputusan', [KontrakSPKController::class, 'keputusanDokumenPpk'])->name('ppk.persetujuan-dokumen.keputusan');
    });

    Route::middleware('role:perencanaan')->group(function () {
        Route::get('/verifikasi/perencanaan', [VerifikasiParalelController::class, 'verifikasiPerencanaan'])->name('verifikasi.perencanaan');
        Route::post('/verifikasi/perencanaan/mak', [VerifikasiParalelController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.mak');
        Route::post('/verifikasi/perencanaan/alokasi', [VerifikasiParalelController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.alokasi');
        Route::post('/verifikasi/perencanaan/mak/store', [VerifikasiParalelController::class, 'storeMakPerencanaan'])->name('verifikasi.perencanaan.mak.store');
        Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/lock', [VerifikasiParalelController::class, 'lockMakWeb'])
            ->name('verifikasi.perencanaan.mak.lock')
            ->where(['anggaran_mak' => '.*']);
        Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/unlock', [VerifikasiParalelController::class, 'unlockMakWeb'])
            ->name('verifikasi.perencanaan.mak.unlock')
            ->where(['anggaran_mak' => '.*']);
        Route::post('/verifikasi/perencanaan/split', [VerifikasiParalelController::class, 'splitMakPerencanaan'])->name('verifikasi.perencanaan.split');
    });

    // -------------------------------------------------------------------------
    // Modul 4: Paket Pengadaan & Belanja Langsung <= 50 Juta (Koordinator PPBJ)
    // -------------------------------------------------------------------------
    Route::get('/paket-pengadaan', [PaketPengadaanController::class, 'paketIndex'])->name('paket.index');
    Route::get('/paket-pengadaan/split-jenis', [PaketPengadaanController::class, 'splitJenisCreate'])->name('paket.split-jenis.create')->middleware('role:ppbj');
    Route::get('/paket-pengadaan/reassign-ppk', [PaketPengadaanController::class, 'reassignPpkCreate'])->name('paket.reassign-ppk.create')->middleware('role:ppbj,perencanaan,wadir_2');
    Route::middleware('role:ppbj')->group(function () {
        Route::get('/paket-pengadaan/create', [PaketPengadaanController::class, 'paketCreate'])->name('paket.create');
        Route::post('/paket-pengadaan', [PaketPengadaanController::class, 'storePaket'])->name('paket.store');
        Route::post('/paket-pengadaan/belanja-langsung', [PaketPengadaanController::class, 'storeBelanjaLangsungWeb'])->name('paket.belanja-langsung');
        Route::post('/paket-pengadaan/reassign-ppk', [PaketPengadaanController::class, 'reassignPpkWeb'])->name('paket.reassign-ppk');
        Route::post('/paket-pengadaan/split-jenis', [PaketPengadaanController::class, 'splitJenisPpbj'])->name('paket.split-jenis');
    });
    // Titik Kembali BPMN N18: Perbaikan / Penyusunan Ulang Dokumen Pengadaan yang Ditolak PP (N27) atau PPK (N35)
    Route::get('/paket-pengadaan/{id}/edit', [PaketPengadaanController::class, 'paketEdit'])
        ->name('paket.edit')
        ->middleware('role:ppbj,admin')
        ->where(['id' => '.*']);
    Route::put('/paket-pengadaan/{id}', [PaketPengadaanController::class, 'paketUpdate'])
        ->name('paket.update')
        ->middleware('role:ppbj,admin')
        ->where(['id' => '.*']);
    Route::get('/paket-pengadaan/{id}', [PaketPengadaanController::class, 'paketShow'])
        ->name('paket.show')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul 5: Review PP, Approval PPK, Eksekusi Belanja, & Upload Dokumen Perikatan
    // -------------------------------------------------------------------------
    Route::get('/monitor-pp', [KontrakSPKController::class, 'monitorPpIndex'])
        ->name('monitor-pp.index')
        ->middleware('role:ppbj,admin,pp,ppk,lihat_pp,pp.monitoring_aktivitas');

    Route::get('/kontrak-spk', [KontrakSPKController::class, 'kontrakIndex'])->name('kontrak.index')->middleware('role:pp,ppk,ppbj,admin');
    Route::post('/kontrak-spk', [KontrakSPKController::class, 'storeKontrak'])->name('kontrak.store')->middleware('role:pp,ppbj,admin');
    Route::post('/kontrak-spk/reviu', [KontrakSPKController::class, 'storeReviuWeb'])->name('kontrak.reviu')->middleware('role:pp,ppk');

    // -------------------------------------------------------------------------
    // Modul 6: Penerimaan Barang & BAST
    // -------------------------------------------------------------------------
    Route::middleware('role:perlengkapan')->group(function () {
        Route::get('/penerimaan-barang', [PenerimaanBarangController::class, 'penerimaanIndex'])->name('penerimaan.index');
        Route::post('/penerimaan-barang', [PenerimaanBarangController::class, 'storePenerimaan'])->name('penerimaan.store');
    });

    // -------------------------------------------------------------------------
    // Modul 7: Tagihan SPP & Pencairan Dana
    // -------------------------------------------------------------------------
    Route::get('/pembayaran', [PembayaranController::class, 'pembayaranIndex'])->name('pembayaran.index')->middleware('role:ppbj,ppk,wadir_2,keuangan');
    Route::post('/pembayaran/pengajuan', [PembayaranController::class, 'storePengajuanBayarWeb'])->name('pembayaran.pengajuan')->middleware('role:ppbj');
    // Titik Kembali BPMN N52: Perbaikan Berkas Dokumen Pembayaran yang Ditolak Wadir II (N60) atau PPK (N61)
    Route::put('/pembayaran/{nomor_pembayaran}', [PembayaranController::class, 'pembayaranUpdate'])
        ->name('pembayaran.update')
        ->middleware('role:ppbj,admin')
        ->where(['nomor_pembayaran' => '.*']);
    Route::post('/pembayaran/memo/approve', [PembayaranController::class, 'approveMemoBayarWeb'])->name('pembayaran.memo.approve')->middleware('role:ppk,wadir_2');
    Route::post('/pembayaran/terbitkan-spp', [PembayaranController::class, 'terbitkanSppWeb'])->name('pembayaran.terbitkan-spp')->middleware('role:ppbj');
    Route::post('/pembayaran', [PembayaranController::class, 'storePembayaran'])->name('pembayaran.store')->middleware('role:keuangan');

    // -------------------------------------------------------------------------
    // Modul 8: Riwayat & Laporan Terpadu
    // -------------------------------------------------------------------------
    Route::get('/laporan', [LaporanController::class, 'laporanIndex'])->name('laporan.index');
    Route::get('/laporan/unduh-laporan-pdf', [LaporanController::class, 'unduhLaporanRingkasanPdf'])->name('laporan.unduh.ringkasan');
    Route::get('/laporan/unduh-detail-pdf', [LaporanController::class, 'unduhLaporanDetailPdf'])->name('laporan.unduh.detail');

    // Rute kompatibilitas legacy untuk pengalihan navigasi dan pengujian terdahulu
    Route::get('/laporan/rekapitulasi-anggaran', [LaporanController::class, 'laporanIndex'])->name('laporan.rekapitulasi');
    Route::get('/laporan/rekapitulasi-anggaran/pdf', [LaporanController::class, 'unduhLaporanRingkasanPdf'])->name('laporan.rekapitulasi.pdf');
    Route::get('/monitoring', fn () => redirect()->route('laporan.index'))->name('monitoring.index');

    // -------------------------------------------------------------------------
    // Modul 9: Notifikasi & Reminder
    // -------------------------------------------------------------------------
    Route::get('/notifikasi', [NotifikasiUserController::class, 'notifikasiIndex'])->name('notifikasi.index');
    Route::post('/notifikasi/tes-kirim', [NotifikasiUserController::class, 'kirimNotifikasiUjiCoba'])->name('notifikasi.tes-kirim');
    Route::post('/notifikasi/mark-all-read', [NotifikasiUserController::class, 'markAllReadWeb'])->name('notifikasi.mark-all-read');
    Route::post('/notifikasi/mark-all-read-alias', [NotifikasiUserController::class, 'markAllReadWeb'])->name('notifikasi.markAllRead');
    Route::post('/notifikasi/{id}/read', [NotifikasiUserController::class, 'markAsReadWeb'])->name('notifikasi.read');
    Route::post('/notifikasi/{id}/dismiss', [NotifikasiUserController::class, 'dismissNotificationWeb'])->name('notifikasi.dismiss');

    // Endpoint polling notifikasi terbaru untuk notifikasi desktop / peramban
    Route::get('/api/notifikasi-terbaru', [NotifikasiUserController::class, 'notifikasiTerbaru'])->name('notifikasi.terbaru');

    // -------------------------------------------------------------------------
    // Modul Admin
    // -------------------------------------------------------------------------
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/persetujuan-registrasi', [ApprovalRegistrasiController::class, 'index'])->name('persetujuan-registrasi.index');
        Route::post('/persetujuan-registrasi/{user}/acc', [ApprovalRegistrasiController::class, 'acc'])->name('persetujuan-registrasi.acc');
        Route::post('/persetujuan-registrasi/{user}/tolak', [ApprovalRegistrasiController::class, 'tolak'])->name('persetujuan-registrasi.tolak');

        // Manajemen Data Pengguna (CRUD User Admin)
        Route::resource('users', AdminUserController::class);

        // Manajemen Master Role & Matriks Hak Akses (Permissions)
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // -------------------------------------------------------------------------
    // Helper Unduh Berkas Resmi Format PDF (< 3 Detik - NFR 12)
    // -------------------------------------------------------------------------
    Route::get('/dokumen-pdf/{jenis}/{id}', [HomeController::class, 'unduhDokumenPdf'])
        ->name('dokumen.pdf')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul Profil Pengguna & Penautan Akun Google (Google Account Linking)
    // -------------------------------------------------------------------------
    Route::get('/profil', [HomeController::class, 'profileIndex'])->name('profile.index');
    Route::put('/profil', [HomeController::class, 'profileUpdate'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profil/hapus-akun', [ProfileController::class, 'destroyAccount'])->name('profile.destroy');

    // Google OAuth Link
    Route::get('/profile/google/link', [GoogleAuthController::class, 'redirectToGoogleForLinking'])->name('profile.google.link');
    Route::get('/profile/google/callback', [GoogleAuthController::class, 'handleGoogleLinkingCallback'])->name('profile.google.callback');
    Route::post('/profile/google/unlink', [GoogleAuthController::class, 'unlinkGoogle'])->name('profile.google.unlink');
});

// Health Checks
Route::get('/health', [HomeController::class, 'healthCheck'])->name('health');
Route::get('/health-check', [HomeController::class, 'healthCheck']);
