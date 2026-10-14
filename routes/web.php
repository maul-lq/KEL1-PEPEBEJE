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
    Route::post('/tes-logout', [AuthController::class, 'logout'])->name('logout');
});

// Endpoint API Backend (18 Entitas & Workflow Endpoints)
Route::prefix('api')->group(function () {
    Route::apiResource('units', UnitController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('vendors', VendorController::class);

    // Permohonan Pengadaan & Split
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/split', [PermohonanPengadaanController::class, 'split']);
    Route::apiResource('permohonan-pengadaan', PermohonanPengadaanController::class)->where(['permohonan_pengadaan' => '.*']);

    // Anggaran MAK & Lock
    Route::post('anggaran-mak/{anggaran_mak}/lock', [AnggaranMAKController::class, 'lock']);
    Route::apiResource('anggaran-mak', AnggaranMAKController::class);

    // Paket Pengadaan & Alih Tugas PPK
    Route::post('paket-pengadaan/{paket_pengadaan}/reassign-ppk', [PaketPengadaanController::class, 'reassignPpk']);
    Route::apiResource('paket-pengadaan', PaketPengadaanController::class);

    // Verifikasi Paralel & Alokasi MAK
    Route::apiResource('verifikasi-paralel', VerifikasiParalelController::class)->where(['verifikasi_paralel' => '.*']);
    Route::apiResource('alokasi-mak', AlokasiMAKController::class)->where(['alokasi_mak' => '.*']);

    // Reviu Dokumen (PP / PPK) & Riwayat Penugasan
    Route::apiResource('reviu-dokumen', ReviuDokumenController::class);
    Route::apiResource('reviu-pengadaan', ReviuPengadaanController::class);
    Route::apiResource('riwayat-penugasan-ppk', RiwayatPenugasanPPKController::class);

    // Kontrak SPK
    Route::apiResource('kontrak-spk', KontrakSPKController::class)->where(['kontrak_spk' => '.*']);

    // Penerimaan Barang & Foto Dokumentasi
    Route::apiResource('penerimaan-barang', PenerimaanBarangController::class)->where(['penerimaan_barang' => '.*']);
    Route::apiResource('dokumentasi-penerimaan-barang', DokumentasiPenerimaanBarangController::class);

    // Pembayaran & Memo Bayar Bertingkat
    Route::post('pembayaran/{pembayaran}/perintah-bayar', [PembayaranController::class, 'terbitkanPerintahBayar']);
    Route::apiResource('pembayaran', PembayaranController::class)->where(['pembayaran' => '.*']);
    Route::apiResource('memo-bayar', MemoBayarController::class)->where(['memo_bayar' => '.*']);

    // Transaksi Pencairan
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
