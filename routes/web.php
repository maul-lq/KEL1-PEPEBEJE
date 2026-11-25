<?php

/**
 * Informasi File
 *
 * Penulis   : DendyRafi, riyan-sa, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menyusun berkas perutean web ini sebagai peta seluruh endpoint HTTP aplikasi SIM-PBJ, mulai dari autentikasi tamu, alur pengadaan 8 siklus, RBAC admin, hingga polling notifikasi.
 */

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

/**
 * Grup Rute Tamu (Guest Routes)
 *
 * mengelompokkan seluruh rute login, registrasi, reset kata sandi, dan Google OAuth di bawah middleware 'guest'
 * agar pengguna yang sudah memiliki sesi aktif otomatis diarahkan menjauh dari halaman autentikasi.
 *
 * Parameter    : Middleware 'guest' dan closure definisi rute publik.
 * Nilai kembali: \Illuminate\Routing\RouteRegistrar — pendaftar grup rute tamu ke Router Laravel.
 * Contoh pemanggilan:
 * `route('login')` atau `route('auth.google')`
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

/**
 * Grup Rute Terautentikasi (Authenticated Routes)
 *
 * mewajibkan middleware 'auth' pada seluruh rute operasional pengadaan barang/jasa
 * dan melapisinya per modul dengan middleware 'role:...' yang memeriksa izin RBAC dinamis maupun persetujuan akun.
 *
 * Parameter    : Middleware 'auth' dan closure definisi rute seluruh modul SIM-PBJ.
 * Nilai kembali: \Illuminate\Routing\RouteRegistrar — pendaftar grup rute terautentikasi.
 * Contoh pemanggilan:
 * `route('dashboard')` atau `route('permohonan.show', ['id' => '001/PNJ/2026'])`
 */
Route::middleware(['auth', 'prevent.back'])->group(function () {
    // mengarahkan root URL langsung ke dashboard agar pengguna yang sudah login tidak mendarat di halaman kosong.
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // mengizinkan GET dan POST untuk logout demi kompatibilitas tombol tautan maupun form CSRF di berbagai halaman.
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->name('logout');

    // -------------------------------------------------------------------------
    // Modul 1: Permohonan Pengadaan
    // -------------------------------------------------------------------------
    Route::get('/permohonan', [PermohonanPengadaanController::class, 'permohonanIndex'])->name('permohonan.index');
    Route::get('/permohonan/create', [PermohonanPengadaanController::class, 'permohonanCreate'])
        ->name('permohonan.create')
        ->middleware('role:permohonan.create');
    Route::post('/permohonan', [PermohonanPengadaanController::class, 'storePermohonan'])
        ->name('permohonan.store')
        ->middleware('role:permohonan.create');
    Route::post('/permohonan/revisi', [PermohonanPengadaanController::class, 'revisiPermohonan'])
        ->name('permohonan.revisi')
        ->middleware('role:permohonan.revisi');
    // memakai pola '.*' pada parameter {id} karena nomor surat dinas PNJ mengandung karakter garis miring ('/').
    Route::get('/permohonan/{id}', [PermohonanPengadaanController::class, 'permohonanShow'])
        ->name('permohonan.show')
        ->where(['id' => '.*']);
    Route::delete('/permohonan/{id}', [PermohonanPengadaanController::class, 'destroyPermohonan'])
        ->name('permohonan.destroy')
        ->middleware('role:permohonan.delete,permohonan.create')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul 2 & 3: Pemantauan Berkas & Verifikasi Paralel (Perencanaan -> Wadir 2 & PPK)
    // -------------------------------------------------------------------------
    Route::get('/verifikasi/bidang-2', [VerifikasiParalelController::class, 'verifikasiBidang2'])
        ->name('verifikasi.bidang-2')
        ->middleware('role:verifikasi.bidang_2_view');

    Route::get('/verifikasi/wadir-2', [VerifikasiParalelController::class, 'verifikasiWadir2'])
        ->name('verifikasi.wadir-2')
        ->middleware('role:verifikasi.wadir_2_view,verifikasi.wadir_2_decide');
    Route::post('/verifikasi/wadir-2/keputusan', [VerifikasiParalelController::class, 'keputusanWadir2'])
        ->name('verifikasi.wadir-2.keputusan')
        ->middleware('role:verifikasi.wadir_2_decide');

    Route::middleware('role:verifikasi.ppk_approve')->group(function () {
        Route::get('/verifikasi/ppk', [VerifikasiParalelController::class, 'verifikasiPpk'])->name('verifikasi.ppk');
        Route::post('/verifikasi/ppk/keputusan', [VerifikasiParalelController::class, 'keputusanPpkAwal'])->name('verifikasi.ppk.keputusan');
    });

    // memisahkan endpoint persetujuan dokumen paket pengadaan untuk PPK agar terisolasi dari verifikasi usulan awal.
    Route::middleware('role:reviu.ppk')->group(function () {
        Route::get('/reviu-pengadaan/ppk', [KontrakSPKController::class, 'persetujuanDokumenPpkIndex'])->name('ppk.persetujuan-dokumen.index');
        Route::post('/reviu-pengadaan/ppk/keputusan', [KontrakSPKController::class, 'keputusanDokumenPpk'])->name('ppk.persetujuan-dokumen.keputusan');
    });

    Route::get('/verifikasi/perencanaan', [VerifikasiParalelController::class, 'verifikasiPerencanaan'])
        ->name('verifikasi.perencanaan')
        ->middleware('role:anggaran.mak_view,anggaran.mak_manage,anggaran.mak_alokasi,anggaran.mak_lock,permohonan.split_mak');
    Route::middleware('role:anggaran.mak_alokasi')->group(function () {
        Route::post('/verifikasi/perencanaan/mak', [VerifikasiParalelController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.mak');
        // mempertahankan alias '/alokasi' agar endpoint pemanggilan lama maupun formulir baru tetap mengarah ke handler alokasi yang sama.
        Route::post('/verifikasi/perencanaan/alokasi', [VerifikasiParalelController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.alokasi');
    });
    Route::post('/verifikasi/perencanaan/mak/store', [VerifikasiParalelController::class, 'storeMakPerencanaan'])
        ->name('verifikasi.perencanaan.mak.store')
        ->middleware('role:anggaran.mak_manage');
    // menerapkan wildcard '.*' pada {anggaran_mak} karena kode MAK dapat mengandung titik atau garis miring; hanya pemegang izin kunci yang boleh lock/unlock.
    Route::middleware('role:anggaran.mak_lock')->group(function () {
        Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/lock', [VerifikasiParalelController::class, 'lockMakWeb'])
            ->name('verifikasi.perencanaan.mak.lock')
            ->where(['anggaran_mak' => '.*']);
        Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/unlock', [VerifikasiParalelController::class, 'unlockMakWeb'])
            ->name('verifikasi.perencanaan.mak.unlock')
            ->where(['anggaran_mak' => '.*']);
    });
    Route::post('/verifikasi/perencanaan/split', [VerifikasiParalelController::class, 'splitMakPerencanaan'])
        ->name('verifikasi.perencanaan.split')
        ->middleware('role:permohonan.split_mak');

    // -------------------------------------------------------------------------
    // Modul 4: Paket Pengadaan & Belanja Langsung <= 50 Juta (Koordinator PPBJ)
    // -------------------------------------------------------------------------
    Route::get('/paket-pengadaan', [PaketPengadaanController::class, 'paketIndex'])->name('paket.index');
    Route::middleware('role:permohonan.split_jenis')->group(function () {
        Route::get('/paket-pengadaan/split-jenis', [PaketPengadaanController::class, 'splitJenisCreate'])->name('paket.split-jenis.create');
        Route::post('/paket-pengadaan/split-jenis', [PaketPengadaanController::class, 'splitJenisPpbj'])->name('paket.split-jenis');
    });
    Route::middleware('role:paket.reassign_ppk')->group(function () {
        Route::get('/paket-pengadaan/reassign-ppk', [PaketPengadaanController::class, 'reassignPpkCreate'])->name('paket.reassign-ppk.create');
        Route::post('/paket-pengadaan/reassign-ppk', [PaketPengadaanController::class, 'reassignPpkWeb'])->name('paket.reassign-ppk');
    });
    Route::middleware('role:paket.create')->group(function () {
        Route::get('/paket-pengadaan/create', [PaketPengadaanController::class, 'paketCreate'])->name('paket.create');
        Route::post('/paket-pengadaan', [PaketPengadaanController::class, 'storePaket'])->name('paket.store');
        Route::post('/paket-pengadaan/belanja-langsung', [PaketPengadaanController::class, 'storeBelanjaLangsungWeb'])->name('paket.belanja-langsung');
    });
    // menempatkan rute statis (/create, /split-jenis, /reassign-ppk) sebelum rute wildcard {id} agar tidak tertelan oleh regex '.*'.
    Route::get('/paket-pengadaan/{id}/edit', [PaketPengadaanController::class, 'paketEdit'])
        ->name('paket.edit')
        ->middleware('role:paket.create')
        ->where(['id' => '.*']);
    Route::put('/paket-pengadaan/{id}', [PaketPengadaanController::class, 'paketUpdate'])
        ->name('paket.update')
        ->middleware('role:paket.create')
        ->where(['id' => '.*']);
    Route::get('/paket-pengadaan/{id}', [PaketPengadaanController::class, 'paketShow'])
        ->name('paket.show')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul 5: Review PP, Approval PPK, Eksekusi Belanja, & Upload Dokumen Perikatan
    // -------------------------------------------------------------------------
    Route::get('/monitor-pp', [KontrakSPKController::class, 'monitorPpIndex'])
        ->name('monitor-pp.index')
        ->middleware('role:pp.monitoring_aktivitas');

    Route::get('/kontrak-spk', [KontrakSPKController::class, 'kontrakIndex'])
        ->name('kontrak.index')
        ->middleware('role:spk.create,reviu.pp,reviu.ppk,pp.monitoring_aktivitas');
    Route::post('/kontrak-spk', [KontrakSPKController::class, 'storeKontrak'])
        ->name('kontrak.store')
        ->middleware('role:spk.create');
    Route::post('/kontrak-spk/reviu', [KontrakSPKController::class, 'storeReviuWeb'])
        ->name('kontrak.reviu')
        ->middleware('role:reviu.pp,reviu.ppk');

    // -------------------------------------------------------------------------
    // Modul 6: Penerimaan Barang & BAST
    // -------------------------------------------------------------------------
    Route::get('/penerimaan-barang', [PenerimaanBarangController::class, 'penerimaanIndex'])
        ->name('penerimaan.index')
        ->middleware('role:penerimaan.view,penerimaan.bast_create,penerimaan.bast_sign');
    Route::post('/penerimaan-barang', [PenerimaanBarangController::class, 'storePenerimaan'])
        ->name('penerimaan.store')
        ->middleware('role:penerimaan.bast_create,penerimaan.bast_sign');

    // -------------------------------------------------------------------------
    // Modul 7: Tagihan SPP & Pencairan Dana
    // -------------------------------------------------------------------------
    Route::get('/pembayaran', [PembayaranController::class, 'pembayaranIndex'])
        ->name('pembayaran.index')
        ->middleware('role:pembayaran.pengajuan,pembayaran.memo_ppk,pembayaran.memo_wadir2,pembayaran.spp_terbitkan,pembayaran.pencairan_sp2d');
    Route::post('/pembayaran/pengajuan', [PembayaranController::class, 'storePengajuanBayarWeb'])
        ->name('pembayaran.pengajuan')
        ->middleware('role:pembayaran.pengajuan');
    // membuka endpoint PUT perbaikan berkas tagihan agar PPBJ dapat memperbaiki dokumen yang ditolak Wadir II atau PPK tanpa membuat tagihan ganda.
    Route::put('/pembayaran/{nomor_pembayaran}', [PembayaranController::class, 'pembayaranUpdate'])
        ->name('pembayaran.update')
        ->middleware('role:pembayaran.pengajuan')
        ->where(['nomor_pembayaran' => '.*']);
    Route::post('/pembayaran/memo/approve', [PembayaranController::class, 'approveMemoBayarWeb'])
        ->name('pembayaran.memo.approve')
        ->middleware('role:pembayaran.memo_ppk,pembayaran.memo_wadir2');
    Route::post('/pembayaran/terbitkan-spp', [PembayaranController::class, 'terbitkanSppWeb'])
        ->name('pembayaran.terbitkan-spp')
        ->middleware('role:pembayaran.spp_terbitkan');
    Route::post('/pembayaran', [PembayaranController::class, 'storePembayaran'])
        ->name('pembayaran.store')
        ->middleware('role:pembayaran.pencairan_sp2d');

    // -------------------------------------------------------------------------
    // Modul 8: Riwayat & Laporan Terpadu
    // -------------------------------------------------------------------------
    Route::get('/laporan', [LaporanController::class, 'laporanIndex'])->name('laporan.index');
    Route::get('/laporan/unduh-pdf', [LaporanController::class, 'unduhLaporanPdf'])
        ->name('laporan.unduh.pdf')
        ->middleware('role:dokumen.unduh_pdf');
    Route::get('/laporan/unduh-csv', [LaporanController::class, 'unduhLaporanCsv'])
        ->name('laporan.unduh.csv')
        ->middleware('role:dokumen.unduh_pdf');
    Route::get('/laporan/unduh-laporan-pdf', [LaporanController::class, 'unduhLaporanRingkasanPdf'])
        ->name('laporan.unduh.ringkasan')
        ->middleware('role:dokumen.unduh_pdf');
    Route::get('/laporan/unduh-detail-pdf', [LaporanController::class, 'unduhLaporanDetailPdf'])
        ->name('laporan.unduh.detail')
        ->middleware('role:dokumen.unduh_pdf');

    // mempertahankan rute rekapitulasi dan /monitoring sebagai pengalih permanen agar tautan bookmark atau tes lama tidak putus dengan 404.
    Route::get('/laporan/rekapitulasi-anggaran', [LaporanController::class, 'laporanIndex'])->name('laporan.rekapitulasi');
    Route::get('/laporan/rekapitulasi-anggaran/pdf', [LaporanController::class, 'unduhLaporanRingkasanPdf'])
        ->name('laporan.rekapitulasi.pdf')
        ->middleware('role:dokumen.unduh_pdf');
    Route::get('/monitoring', fn () => redirect()->route('laporan.index'))->name('monitoring.index');

    // -------------------------------------------------------------------------
    // Modul 9: Notifikasi & Reminder
    // -------------------------------------------------------------------------
    Route::get('/notifikasi', [NotifikasiUserController::class, 'notifikasiIndex'])->name('notifikasi.index');
    Route::post('/notifikasi/mark-all-read', [NotifikasiUserController::class, 'markAllReadWeb'])->name('notifikasi.mark-all-read');
    Route::post('/notifikasi/mark-all-read-alias', [NotifikasiUserController::class, 'markAllReadWeb'])->name('notifikasi.markAllRead');
    Route::post('/notifikasi/{id}/read', [NotifikasiUserController::class, 'markAsReadWeb'])->name('notifikasi.read');
    Route::post('/notifikasi/{id}/dismiss', [NotifikasiUserController::class, 'dismissNotificationWeb'])->name('notifikasi.dismiss');

    // menyediakan endpoint JSON terpisah untuk polling notifikasi latar agar tidak membebani rendering Blade penuh setiap beberapa detik.
    Route::get('/api/notifikasi-terbaru', [NotifikasiUserController::class, 'notifikasiTerbaru'])->name('notifikasi.terbaru');

    // -------------------------------------------------------------------------
    // Modul Admin (Berbasis Permission RBAC)
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('role:admin.approval_registrasi')->group(function () {
            Route::get('/persetujuan-registrasi', [ApprovalRegistrasiController::class, 'index'])->name('persetujuan-registrasi.index');
            Route::post('/persetujuan-registrasi/{user}/acc', [ApprovalRegistrasiController::class, 'acc'])->name('persetujuan-registrasi.acc');
            Route::post('/persetujuan-registrasi/{user}/tolak', [ApprovalRegistrasiController::class, 'tolak'])->name('persetujuan-registrasi.tolak');
        });

        // Manajemen Data Pengguna (CRUD User Admin)
        Route::resource('users', AdminUserController::class)->middleware('role:admin.user_manage');

        // Manajemen Master Role & Matriks Hak Akses (Permissions)
        Route::middleware('role:admin.role_manage')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });

    // -------------------------------------------------------------------------
    // Helper Unduh Berkas Resmi Format PDF
    // -------------------------------------------------------------------------
    // mengizinkan wildcard '.*' pada {id} agar nomor surat/paket/pembayaran yang bertanda '/' bisa diunduh lewat satu endpoint PDF terpusat.
    Route::get('/dokumen-pdf/{jenis}/{id}', [HomeController::class, 'unduhDokumenPdf'])
        ->name('dokumen.pdf')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul Profil Pengguna & Penautan Akun Google (Google Account Linking)
    // -------------------------------------------------------------------------
    Route::get('/profil', [HomeController::class, 'profileIndex'])->name('profile.index');
    Route::put('/profil', [HomeController::class, 'profileUpdate'])->name('profile.update');
    Route::post('/profil/reminder-email', [HomeController::class, 'updateReminderEmail'])->name('profile.reminder-email');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profil/hapus-akun', [ProfileController::class, 'destroyAccount'])->name('profile.destroy');

    // Penautan Akun Google OAuth untuk pengguna yang sudah masuk
    Route::get('/profile/google/link', [GoogleAuthController::class, 'redirectToGoogleForLinking'])->name('profile.google.link');
    Route::get('/profile/google/callback', [GoogleAuthController::class, 'handleGoogleLinkingCallback'])->name('profile.google.callback');
    Route::post('/profile/google/unlink', [GoogleAuthController::class, 'unlinkGoogle'])->name('profile.google.unlink');
});

// membiarkan endpoint health-check berada di luar middleware 'auth' agar load balancer atau skrip monitoring dapat memantau status database kapan saja.
Route::get('/health', [HomeController::class, 'healthCheck'])->name('health');
Route::get('/health-check', [HomeController::class, 'healthCheck']);
