<?php

use App\Http\Controllers\AlokasiMAKController;
use App\Http\Controllers\AnggaranMAKController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumentasiPenerimaanBarangController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KontrakSPKController;
use App\Http\Controllers\MemoBayarController;
use App\Http\Controllers\PaketPengadaanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PengadaanLogController;
use App\Http\Controllers\PermohonanPengadaanController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReviuPengadaanController;
use App\Http\Controllers\RiwayatPenugasanPPKController;
use App\Http\Controllers\TransaksiPencairanController;
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

// Endpoint API Backend (17 Entitas)
Route::prefix('api')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('vendors', VendorController::class);
    Route::apiResource('permohonan-pengadaan', PermohonanPengadaanController::class)->where(['permohonan_pengadaan' => '.*']);
    Route::apiResource('anggaran-mak', AnggaranMAKController::class);
    Route::apiResource('paket-pengadaan', PaketPengadaanController::class);
    Route::apiResource('verifikasi-paralel', VerifikasiParalelController::class)->where(['verifikasi_paralel' => '.*']);
    Route::apiResource('alokasi-mak', AlokasiMAKController::class)->where(['alokasi_mak' => '.*']);
    Route::apiResource('reviu-pengadaan', ReviuPengadaanController::class);
    Route::apiResource('riwayat-penugasan-ppk', RiwayatPenugasanPPKController::class);
    Route::apiResource('kontrak-spk', KontrakSPKController::class)->where(['kontrak_spk' => '.*']);
    Route::apiResource('penerimaan-barang', PenerimaanBarangController::class)->where(['penerimaan_barang' => '.*']);
    Route::apiResource('dokumentasi-penerimaan-barang', DokumentasiPenerimaanBarangController::class);
    Route::apiResource('pembayaran', PembayaranController::class)->where(['pembayaran' => '.*']);
    Route::apiResource('memo-bayar', MemoBayarController::class)->where(['memo_bayar' => '.*']);
    Route::apiResource('transaksi-pencairan', TransaksiPencairanController::class)->where(['transaksi_pencairan' => '.*']);
    Route::apiResource('reminders', ReminderController::class);
    Route::apiResource('pengadaan-logs', PengadaanLogController::class);
});
