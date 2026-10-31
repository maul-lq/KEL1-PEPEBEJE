<?php

use App\Http\Controllers\Admin\ApprovalRegistrasiController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\AlokasiMAKController;
use App\Http\Controllers\AnggaranMAKController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumentasiPenerimaanBarangController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KontrakSPKController;
use App\Http\Controllers\MemoBayarController;
use App\Http\Controllers\NotifikasiUserController;
use App\Http\Controllers\PaketPengadaanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PengadaanLogController;
use App\Http\Controllers\PermohonanPengadaanController;
use App\Http\Controllers\ProfileController; // Import ProfileController Baru
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReviuDokumenController;
use App\Http\Controllers\ReviuPengadaanController;
use App\Http\Controllers\RiwayatPenugasanPPKController;
use App\Http\Controllers\TransaksiPencairanController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
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

    // Backward-compatibility aliases
    Route::get('/tes-login', [AuthController::class, 'showLoginForm']);
    Route::post('/tes-login', [AuthController::class, 'login']);
    Route::get('/tes-registrasi', [AuthController::class, 'showRegistrationForm']);
    Route::post('/tes-registrasi', [AuthController::class, 'register']);

    // Otentikasi Google OAuth
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

    // Modul 1: Permohonan Pengadaan
    Route::get('/permohonan', [HomeController::class, 'permohonanIndex'])->name('permohonan.index');
    Route::get('/permohonan/create', [HomeController::class, 'permohonanCreate'])->name('permohonan.create');
    Route::post('/permohonan', [HomeController::class, 'storePermohonan'])->name('permohonan.store');
    Route::post('/permohonan/revisi', [HomeController::class, 'revisiPermohonan'])->name('permohonan.revisi');
    Route::get('/permohonan/{id}', [HomeController::class, 'permohonanShow'])->name('permohonan.show')->where(['id' => '.*']);

    // Modul 2 & 3: Verifikasi Paralel
    Route::middleware('role:staff_bidang_2')->group(function () {
        Route::get('/verifikasi/bidang-2', [HomeController::class, 'verifikasiBidang2'])->name('verifikasi.bidang-2');
        Route::post('/verifikasi/bidang-2/teruskan', [HomeController::class, 'teruskanStaffB2'])->name('verifikasi.bidang-2.teruskan');
    });

    Route::middleware('role:wadir_2')->group(function () {
        Route::get('/verifikasi/wadir-2', [HomeController::class, 'verifikasiWadir2'])->name('verifikasi.wadir-2');
        Route::post('/verifikasi/wadir-2/keputusan', [HomeController::class, 'keputusanWadir2'])->name('verifikasi.wadir-2.keputusan');
    });

    Route::middleware('role:perencanaan')->group(function () {
        Route::get('/verifikasi/perencanaan', [HomeController::class, 'verifikasiPerencanaan'])->name('verifikasi.perencanaan');
        Route::post('/verifikasi/perencanaan/mak', [HomeController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.mak');
        Route::post('/verifikasi/perencanaan/mak/store', [HomeController::class, 'storeMakPerencanaan'])->name('verifikasi.perencanaan.mak.store');
        Route::post('/verifikasi/perencanaan/split', [HomeController::class, 'splitMakPerencanaan'])->name('verifikasi.perencanaan.split');
    });

    // Modul 4: Paket Pengadaan
    Route::get('/paket-pengadaan', [HomeController::class, 'paketIndex'])->name('paket.index');
    Route::middleware('role:ppbj')->group(function () {
        Route::get('/paket-pengadaan/create', [HomeController::class, 'paketCreate'])->name('paket.create');
        Route::post('/paket-pengadaan', [HomeController::class, 'storePaket'])->name('paket.store');
        Route::post('/paket-pengadaan/reassign-ppk', [HomeController::class, 'reassignPpkWeb'])->name('paket.reassign-ppk');
        Route::post('/paket-pengadaan/split-jenis', [HomeController::class, 'splitJenisPpbj'])->name('paket.split-jenis');
    });

    // Modul 5: Kontrak SPK
    Route::get('/kontrak-spk', [HomeController::class, 'kontrakIndex'])->name('kontrak.index');
    Route::post('/kontrak-spk', [HomeController::class, 'storeKontrak'])->name('kontrak.store');
    Route::post('/kontrak-spk/reviu', [HomeController::class, 'storeReviuWeb'])->name('kontrak.reviu');

    // Modul 6: Penerimaan Barang & BAST
    Route::middleware('role:perlengkapan')->group(function () {
        Route::get('/penerimaan-barang', [HomeController::class, 'penerimaanIndex'])->name('penerimaan.index');
        Route::post('/penerimaan-barang', [HomeController::class, 'storePenerimaan'])->name('penerimaan.store');
    });

    // Modul 7: Tagihan SPP & Pencairan Dana
    Route::get('/pembayaran', [HomeController::class, 'pembayaranIndex'])->name('pembayaran.index')->middleware('role:ppbj,ppk,wadir_2,keuangan');
    Route::post('/pembayaran/pengajuan', [HomeController::class, 'storePengajuanBayarWeb'])->name('pembayaran.pengajuan')->middleware('role:ppbj');
    Route::post('/pembayaran/memo/approve', [HomeController::class, 'approveMemoBayarWeb'])->name('pembayaran.memo.approve')->middleware('role:ppk,wadir_2');
    Route::post('/pembayaran/terbitkan-spp', [HomeController::class, 'terbitkanSppWeb'])->name('pembayaran.terbitkan-spp')->middleware('role:ppbj');
    Route::post('/pembayaran', [HomeController::class, 'storePembayaran'])->name('pembayaran.store')->middleware('role:keuangan');

    // Modul 8: Monitoring Status
    Route::get('/monitoring', [HomeController::class, 'monitoringIndex'])->name('monitoring.index');

    // Modul 9: Laporan & PDF
    Route::get('/laporan/rekapitulasi-anggaran', [HomeController::class, 'laporanRekapitulasiIndex'])->name('laporan.rekapitulasi');
    Route::get('/laporan/rekapitulasi-anggaran/pdf', [HomeController::class, 'unduhRekapitulasiPdf'])->name('laporan.rekapitulasi.pdf');

    // Modul Notifikasi & Reminder
    Route::get('/notifikasi', [HomeController::class, 'notifikasiIndex'])->name('notifikasi.index');
    Route::post('/notifikasi/tes-kirim', [HomeController::class, 'kirimNotifikasiUjiCoba'])->name('notifikasi.tes-kirim');
    Route::post('/notifikasi/mark-all-read', [HomeController::class, 'markAllReadWeb'])->name('notifikasi.mark-all-read');
    Route::post('/notifikasi/mark-all-read-alias', [HomeController::class, 'markAllReadWeb'])->name('notifikasi.markAllRead');
    Route::post('/notifikasi/{id}/read', [HomeController::class, 'markAsReadWeb'])->name('notifikasi.read');
    Route::post('/notifikasi/{id}/dismiss', [HomeController::class, 'dismissNotificationWeb'])->name('notifikasi.dismiss');
    Route::get('/api/notifikasi-terbaru', [NotifikasiUserController::class, 'notifikasiTerbaru'])->name('notifikasi.terbaru');

    // Modul Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/persetujuan-registrasi', [ApprovalRegistrasiController::class, 'index'])->name('persetujuan-registrasi.index');
        Route::post('/persetujuan-registrasi/{user}/acc', [ApprovalRegistrasiController::class, 'acc'])->name('persetujuan-registrasi.acc');
        Route::post('/persetujuan-registrasi/{user}/tolak', [ApprovalRegistrasiController::class, 'tolak'])->name('persetujuan-registrasi.tolak');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Modul Profil Pengguna & Google Link (Diperbarui mengarah ke ProfileController)
    Route::get('/profil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profil/hapus-akun', [ProfileController::class, 'destroyAccount'])->name('profile.destroy');

    // Google OAuth Link
    Route::get('/profile/google/link', [GoogleAuthController::class, 'redirectToGoogleForLinking'])->name('profile.google.link');
    Route::get('/profile/google/callback', [GoogleAuthController::class, 'handleGoogleLinkingCallback'])->name('profile.google.callback');
    Route::post('/profile/google/unlink', [GoogleAuthController::class, 'unlinkGoogle'])->name('profile.google.unlink');

    // Unduh Dokumen PDF
    Route::get('/dokumen-pdf/{jenis}/{id}', [HomeController::class, 'unduhDokumenPdf'])->name('dokumen.pdf')->where(['id' => '.*']);
});

/*
|--------------------------------------------------------------------------
| ENDPOINT RESTful API BACKEND
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    Route::apiResource('units', UnitController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('vendors', VendorController::class);

    Route::get('permohonan-pengadaan/riwayat-user', [PermohonanPengadaanController::class, 'riwayatUser'])->name('permohonan-pengadaan.riwayat-user');
    Route::get('permohonan-pengadaan/{permohonan_pengadaan}/unduh-pdf', [PermohonanPengadaanController::class, 'unduhPdf'])->name('permohonan-pengadaan.unduh-pdf')->where(['permohonan_pengadaan' => '.*']);
    
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/split', [PermohonanPengadaanController::class, 'split'])->name('permohonan-pengadaan.split')->middleware('role:perencanaan,ppbj,admin')->where(['permohonan_pengadaan' => '.*']);
    Route::post('permohonan/{permohonan_pengadaan}/split-ppbj', [PermohonanPengadaanController::class, 'split'])->name('permohonan.split-ppbj')->middleware('role:ppbj,admin')->where(['permohonan_pengadaan' => '.*']);
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/revisi', [PermohonanPengadaanController::class, 'revisi'])->name('permohonan-pengadaan.revisi')->where(['permohonan_pengadaan' => '.*']);
    Route::get('permohonan-pengadaan/{permohonan_pengadaan}/tracking', [PermohonanPengadaanController::class, 'tracking'])->name('permohonan-pengadaan.tracking')->where(['permohonan_pengadaan' => '.*']);

    Route::get('monitoring-status', [PermohonanPengadaanController::class, 'monitoringStatus'])->name('monitoring.status');
    Route::get('permohonan-pengadaan/monitoring', [PermohonanPengadaanController::class, 'monitoringStatus'])->name('permohonan-pengadaan.monitoring');

    Route::apiResource('permohonan-pengadaan', PermohonanPengadaanController::class)->where(['permohonan_pengadaan' => '.*']);

    Route::post('anggaran-mak/hold', [AnggaranMAKController::class, 'holdAnggaran'])->name('anggaran-mak.hold')->middleware('role:perencanaan');
    Route::post('anggaran-mak/{anggaran_mak}/lock', [AnggaranMAKController::class, 'lock'])->middleware('role:perencanaan');
    Route::apiResource('anggaran-mak', AnggaranMAKController::class);

    Route::post('paket-pengadaan/{paket_pengadaan}/reassign-ppk', [PaketPengadaanController::class, 'reassignPpk'])->name('paket-pengadaan.reassign-ppk')->middleware('role:ppbj,perencanaan')->where(['paket_pengadaan' => '.*']);
    Route::apiResource('paket-pengadaan', PaketPengadaanController::class)->where(['paket_pengadaan' => '.*']);

    Route::post('verifikasi-paralel/teruskan-ke-wadir', [VerifikasiParalelController::class, 'teruskanKeWadir'])->name('verifikasi-paralel.teruskan-ke-wadir');
    Route::apiResource('verifikasi-paralel', VerifikasiParalelController::class)->where(['verifikasi_paralel' => '.*']);

    Route::apiResource('alokasi-mak', AlokasiMAKController::class)->where(['alokasi_mak' => '.*']);
    Route::apiResource('reviu-dokumen', ReviuDokumenController::class);
    Route::apiResource('reviu-pengadaan', ReviuPengadaanController::class);
    Route::apiResource('riwayat-penugasan-ppk', RiwayatPenugasanPPKController::class);

    Route::get('kontrak-spk/{kontrak_spk}/unduh-pdf', [KontrakSPKController::class, 'unduhPdf'])->name('kontrak-spk.unduh-pdf')->where(['kontrak_spk' => '.*']);
    Route::apiResource('kontrak-spk', KontrakSPKController::class)->where(['kontrak_spk' => '.*']);

    Route::get('penerimaan-barang/{penerimaan_barang}/unduh-pdf', [PenerimaanBarangController::class, 'unduhPdf'])->name('penerimaan-barang.unduh-pdf')->where(['penerimaan_barang' => '.*']);
    Route::apiResource('penerimaan-barang', PenerimaanBarangController::class)->where(['penerimaan_barang' => '.*']);
    Route::apiResource('dokumentasi-penerimaan-barang', DokumentasiPenerimaanBarangController::class);

    Route::post('pembayaran/{pembayaran}/perintah-bayar', [PembayaranController::class, 'terbitkanPerintahBayar'])->middleware('role:ppbj');
    Route::apiResource('pembayaran', PembayaranController::class)->where(['pembayaran' => '.*']);
    Route::apiResource('memo-bayar', MemoBayarController::class)->where(['memo_bayar' => '.*']);

    Route::get('transaksi-pencairan/{transaksi_pencairan}/unduh-pdf', [TransaksiPencairanController::class, 'unduhPdf'])->name('transaksi-pencairan.unduh-pdf')->where(['transaksi_pencairan' => '.*']);
    Route::apiResource('transaksi-pencairan', TransaksiPencairanController::class)->where(['transaksi_pencairan' => '.*']);

    Route::post('reminders/sync', [ReminderController::class, 'sinkronkanOtomatis'])->name('reminders.sync');
    Route::apiResource('reminders', ReminderController::class);

    Route::get('laporan/rekapitulasi-anggaran', [HomeController::class, 'laporanRekapitulasiIndex']);
    Route::get('health-check', [HomeController::class, 'healthCheck'])->name('api.health-check');

    Route::get('notifikasi-user', [NotifikasiUserController::class, 'index']);
    Route::post('notifikasi-user/{notifikasi}/read', [NotifikasiUserController::class, 'markAsRead']);
    Route::post('notifikasi-user/{notifikasi}/dismiss', [NotifikasiUserController::class, 'dismiss']);

    Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show', 'store']);
    Route::apiResource('pengadaan-logs', PengadaanLogController::class)->only(['index', 'show', 'store']);

    Route::get('admin/persetujuan-registrasi', [ApprovalRegistrasiController::class, 'index'])->middleware('role:admin');
    Route::post('admin/persetujuan-registrasi/{user}/acc', [ApprovalRegistrasiController::class, 'acc'])->middleware('role:admin');
    Route::post('admin/persetujuan-registrasi/{user}/tolak', [ApprovalRegistrasiController::class, 'tolak'])->middleware('role:admin');

    Route::apiResource('roles', RoleController::class)->middleware('role:admin');
});

// Health Checks
Route::get('/health', [HomeController::class, 'healthCheck'])->name('health');
Route::get('/health-check', [HomeController::class, 'healthCheck']);