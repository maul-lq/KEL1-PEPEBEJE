<?php

use App\Http\Controllers\AlokasiMAKController;
use App\Http\Controllers\AnggaranMAKController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumentasiPenerimaanBarangController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KontrakSPKController;
use App\Http\Controllers\MemoBayarController;
use App\Http\Controllers\NotifikasiUserController;
use App\Http\Controllers\PaketPengadaanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PengadaanLogController;
use App\Http\Controllers\PermohonanPengadaanController;
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

// Rute Tamu (Guest)
Route::middleware('guest')->group(function () {
    Route::get('/tes-login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/tes-login', [AuthController::class, 'login']);

    Route::get('/tes-registrasi', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/tes-registrasi', [AuthController::class, 'register']);
});

// Rute Terautentikasi (Auth)
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('home');
    });

    Route::get('/tes-home', [HomeController::class, 'index'])->name('home');
    Route::get('/tes-api', [HomeController::class, 'testApi'])->name('test.api');
    Route::get('/tes-permohonan', [HomeController::class, 'tesPermohonan'])->name('permohonan.web');
    Route::post('/tes-permohonan', [HomeController::class, 'storePermohonan'])->name('permohonan.web.store');
    Route::post('/tes-permohonan/revisi', [HomeController::class, 'revisiPermohonan'])->name('permohonan.web.revisi');
    Route::get('/tes-monitoring', [HomeController::class, 'tesMonitoring'])->name('monitoring.web');
    Route::get('/tes-paket', [HomeController::class, 'tesPaket'])->name('paket.web');
    Route::post('/tes-paket', [HomeController::class, 'storePaket'])->name('paket.web.store');
    Route::post('/tes-paket/reassign-ppk', [HomeController::class, 'reassignPpkWeb'])->name('paket.web.reassign');
    Route::get('/tes-verifikasi-b2', [HomeController::class, 'tesVerifikasiB2'])->name('verifikasi-b2.web');
    Route::post('/tes-verifikasi-b2/teruskan-staff', [HomeController::class, 'teruskanStaffB2'])->name('verifikasi-b2.web.teruskan');
    Route::post('/tes-verifikasi-b2/keputusan-wadir', [HomeController::class, 'keputusanWadir2'])->name('verifikasi-b2.web.keputusan');
    Route::get('/tes-unduh-pdf/{jenis}/{id}', [HomeController::class, 'unduhDokumenPdf'])->name('unduh.pdf.web')->where(['id' => '.*']);
    Route::post('/tes-logout', [AuthController::class, 'logout'])->name('logout');
});

// Endpoint API Backend (18 Entitas & Workflow Endpoints dengan RBAC)
Route::prefix('api')->group(function () {
    // Master data dasar (dapat diakses seluruh pengguna terotentikasi)
    Route::apiResource('units', UnitController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('vendors', VendorController::class);

    // Permohonan Pengadaan & Riwayat User (FR 4.1) & Split & Revisi & Tracking (FR 3 & NFR 7) & Unduh PDF (NFR 12)
    Route::get('permohonan-pengadaan/riwayat-user', [PermohonanPengadaanController::class, 'riwayatUser'])
        ->name('permohonan-pengadaan.riwayat-user');
    Route::get('permohonan-pengadaan/{permohonan_pengadaan}/unduh-pdf', [PermohonanPengadaanController::class, 'unduhPdf'])
        ->name('permohonan-pengadaan.unduh-pdf')
        ->where(['permohonan_pengadaan' => '.*']);
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/split', [PermohonanPengadaanController::class, 'split'])
        ->name('permohonan-pengadaan.split')
        ->middleware('role:perencanaan,ppbj')
        ->where(['permohonan_pengadaan' => '.*']);
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/revisi', [PermohonanPengadaanController::class, 'revisi'])
        ->name('permohonan-pengadaan.revisi')
        ->where(['permohonan_pengadaan' => '.*']);
    Route::get('permohonan-pengadaan/{permohonan_pengadaan}/tracking', [PermohonanPengadaanController::class, 'tracking'])
        ->name('permohonan-pengadaan.tracking')
        ->where(['permohonan_pengadaan' => '.*']);
    Route::get('monitoring-status', [PermohonanPengadaanController::class, 'monitoringStatus'])
        ->name('monitoring.status');
    Route::apiResource('permohonan-pengadaan', PermohonanPengadaanController::class)->where(['permohonan_pengadaan' => '.*']);

    // Anggaran MAK & Lock (Lock & Manajemen hanya untuk Perencanaan)
    Route::post('anggaran-mak/{anggaran_mak}/lock', [AnggaranMAKController::class, 'lock'])
        ->middleware('role:perencanaan');
    Route::apiResource('anggaran-mak', AnggaranMAKController::class);

    // Paket Pengadaan & Alih Tugas PPK (Reassign hanya untuk PPBJ & Perencanaan - FR 7.3 & NFR 4)
    Route::post('paket-pengadaan/{paket_pengadaan}/reassign-ppk', [PaketPengadaanController::class, 'reassignPpk'])
        ->name('paket-pengadaan.reassign-ppk')
        ->middleware('role:ppbj,perencanaan')
        ->where(['paket_pengadaan' => '.*']);
    Route::apiResource('paket-pengadaan', PaketPengadaanController::class)->where(['paket_pengadaan' => '.*']);

    // Verifikasi Paralel & Alokasi MAK (Verifikasi Paralel untuk Staff B2, Wadir 2, Perencanaan)
    Route::post('verifikasi-paralel/teruskan-ke-wadir', [VerifikasiParalelController::class, 'teruskanKeWadir'])
        ->name('verifikasi-paralel.teruskan-ke-wadir');
    Route::apiResource('verifikasi-paralel', VerifikasiParalelController::class)->where(['verifikasi_paralel' => '.*']);
    Route::apiResource('alokasi-mak', AlokasiMAKController::class)->where(['alokasi_mak' => '.*']);

    // Reviu Dokumen (Hanya untuk PP, PPK, dan PPBJ)
    Route::apiResource('reviu-dokumen', ReviuDokumenController::class);
    Route::apiResource('reviu-pengadaan', ReviuPengadaanController::class);
    Route::apiResource('riwayat-penugasan-ppk', RiwayatPenugasanPPKController::class);

    // Kontrak SPK (Diterbitkan oleh PPBJ / PPK) & Unduh PDF (NFR 12)
    Route::get('kontrak-spk/{kontrak_spk}/unduh-pdf', [KontrakSPKController::class, 'unduhPdf'])
        ->name('kontrak-spk.unduh-pdf')
        ->where(['kontrak_spk' => '.*']);
    Route::apiResource('kontrak-spk', KontrakSPKController::class)->where(['kontrak_spk' => '.*']);

    // Penerimaan Barang & Foto Dokumentasi (Perlengkapan) & Unduh PDF BAST (NFR 12)
    Route::get('penerimaan-barang/{penerimaan_barang}/unduh-pdf', [PenerimaanBarangController::class, 'unduhPdf'])
        ->name('penerimaan-barang.unduh-pdf')
        ->where(['penerimaan_barang' => '.*']);
    Route::apiResource('penerimaan-barang', PenerimaanBarangController::class)->where(['penerimaan_barang' => '.*']);
    Route::apiResource('dokumentasi-penerimaan-barang', DokumentasiPenerimaanBarangController::class);

    // Pembayaran & Memo Bayar Bertingkat (Memo bayar untuk PPK & Wadir 2)
    Route::post('pembayaran/{pembayaran}/perintah-bayar', [PembayaranController::class, 'terbitkanPerintahBayar'])
        ->middleware('role:ppbj');
    Route::apiResource('pembayaran', PembayaranController::class)->where(['pembayaran' => '.*']);
    Route::apiResource('memo-bayar', MemoBayarController::class)->where(['memo_bayar' => '.*']);

    // Transaksi Pencairan (Khusus Bagian Keuangan) & Unduh PDF Bukti Transfer (NFR 12)
    Route::get('transaksi-pencairan/{transaksi_pencairan}/unduh-pdf', [TransaksiPencairanController::class, 'unduhPdf'])
        ->name('transaksi-pencairan.unduh-pdf')
        ->where(['transaksi_pencairan' => '.*']);
    Route::apiResource('transaksi-pencairan', TransaksiPencairanController::class)->where(['transaksi_pencairan' => '.*']);

    // Reminders & Notifikasi User
    Route::apiResource('reminders', ReminderController::class);
    Route::get('notifikasi-user', [NotifikasiUserController::class, 'index']);
    Route::post('notifikasi-user/{notifikasi}/read', [NotifikasiUserController::class, 'markAsRead']);
    Route::post('notifikasi-user/{notifikasi}/dismiss', [NotifikasiUserController::class, 'dismiss']);

    // Audit Log & Pengadaan Log
    Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
    Route::apiResource('pengadaan-logs', PengadaanLogController::class)->only(['index', 'show']);
});
