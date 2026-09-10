<?php

use App\Http\Controllers\ApprovalWadir2Controller;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PenerimaanPerlengkapanController;
use App\Http\Controllers\PengadaanController;
use App\Http\Controllers\PerencanaanController;
use App\Http\Controllers\PpbjController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReviewPpkController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Authentication & Prototype Role-Switcher
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/quick-login/{role}', [AuthController::class, 'quickLogin'])->name('quick-login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/monitoring', [DashboardController::class, 'monitoring'])->name('monitoring');

    // Pengadaan Core
    Route::get('/pengadaan', [PengadaanController::class, 'index'])->name('pengadaan.index');
    Route::get('/pengadaan/create', [PengadaanController::class, 'create'])->name('pengadaan.create');
    Route::post('/pengadaan', [PengadaanController::class, 'store'])->name('pengadaan.store');
    Route::get('/pengadaan/{pengadaan}', [PengadaanController::class, 'show'])->name('pengadaan.show');
    Route::get('/pengadaan/{pengadaan}/cetak', [PengadaanController::class, 'cetak'])->name('pengadaan.cetak');

    // 1. Wadir 2 (Persetujuan & Kebijakan)
    Route::prefix('wadir2')->name('wadir2.')->group(function () {
        Route::get('/review/{pengadaan}', [ApprovalWadir2Controller::class, 'showReview'])->name('review');
        Route::post('/approve/{pengadaan}', [ApprovalWadir2Controller::class, 'approve'])->name('approve');
        Route::post('/reject/{pengadaan}', [ApprovalWadir2Controller::class, 'reject'])->name('reject');
        Route::post('/acc-pembayaran/{pengadaan}', [PembayaranController::class, 'accWadir2'])->name('acc-pembayaran');
    });

    // 2. Perencanaan (MAK & Anggaran)
    Route::prefix('perencanaan')->name('perencanaan.')->group(function () {
        Route::get('/mak/{pengadaan}', [PerencanaanController::class, 'showInputMak'])->name('input-mak');
        Route::post('/mak/{pengadaan}', [PerencanaanController::class, 'storeMak'])->name('store-mak');
    });

    // 3. PPBJ (Pengelola Pengadaan B/J)
    Route::prefix('ppbj')->name('ppbj.')->group(function () {
        Route::get('/register/{pengadaan}', [PpbjController::class, 'showRegister'])->name('register');
        Route::post('/register/{pengadaan}', [PpbjController::class, 'storeRegister'])->name('store-register');
        Route::get('/spk/{pengadaan}', [PpbjController::class, 'showCreateSpk'])->name('create-spk');
        Route::post('/spk/{pengadaan}', [PpbjController::class, 'storeSpk'])->name('store-spk');
        Route::get('/ajukan-pembayaran/{pengadaan}', [PpbjController::class, 'showAjukanPembayaran'])->name('ajukan-pembayaran');
        Route::post('/ajukan-pembayaran/{pengadaan}', [PpbjController::class, 'storeAjukanPembayaran'])->name('store-ajukan-pembayaran');
        Route::post('/terbitkan-spp/{pengadaan}', [PpbjController::class, 'terbitkanSpp'])->name('terbitkan-spp');
    });

    // 4. PPK / Pejabat Pengadaan
    Route::prefix('ppk')->name('ppk.')->group(function () {
        Route::get('/review/{pengadaan}', [ReviewPpkController::class, 'showReview'])->name('review');
        Route::post('/approve/{pengadaan}', [ReviewPpkController::class, 'approve'])->name('approve');
        Route::post('/revisi/{pengadaan}', [ReviewPpkController::class, 'mintaPerbaikan'])->name('revisi');
        Route::post('/acc-pembayaran/{pengadaan}', [PembayaranController::class, 'accPpk'])->name('acc-pembayaran');
    });

    // 5. Perlengkapan (BMN & Penerimaan)
    Route::prefix('perlengkapan')->name('perlengkapan.')->group(function () {
        Route::get('/penerimaan/{pengadaan}', [PenerimaanPerlengkapanController::class, 'showPenerimaan'])->name('penerimaan');
        Route::post('/penerimaan/{pengadaan}', [PenerimaanPerlengkapanController::class, 'storePenerimaan'])->name('store-penerimaan');
    });

    // 6. Keuangan (Pencairan & Pembayaran)
    Route::prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/pencairan/{pengadaan}', [KeuanganController::class, 'showPencairan'])->name('pencairan');
        Route::post('/pencairan/{pengadaan}', [KeuanganController::class, 'storePencairan'])->name('store-pencairan');
    });

    // Vendors Management (FR 10.0)
    Route::resource('vendor', VendorController::class)->only(['index', 'create', 'store', 'show']);

    // Reminders & Alarm Notifications (FR 12.0)
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('/reminders/{reminder}/dismiss', [ReminderController::class, 'dismiss'])->name('reminders.dismiss');
});

// Laravel Boost Browser Log endpoint
Route::get('/_boost/browser-logs', function () {
    return response()->json([
        'status' => 'active',
        'message' => 'Laravel Boost browser logger endpoint is active.',
    ]);
});
