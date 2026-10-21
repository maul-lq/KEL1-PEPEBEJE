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

/*
|--------------------------------------------------------------------------
| RUTE TAMU / GUEST (Belum Login)
|--------------------------------------------------------------------------
| Seluruh rute di bawah ini hanya dapat diakses oleh pengunjung yang belum login.
| Jika user sudah memiliki sesi login aktif, mereka akan dialihkan otomatis.
*/
Route::middleware('guest')->group(function () {
    // Halaman resmi formulir login (mendukung Username, NIP 18-digit, atau Email PNJ)
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Halaman resmi formulir pendaftaran akun pemohon baru
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Lupa Password & Reset Password
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

    // Backward-compatibility aliases untuk rute pengujian sebelumnya
    Route::get('/tes-login', [AuthController::class, 'showLoginForm']);
    Route::post('/tes-login', [AuthController::class, 'login']);
    Route::get('/tes-registrasi', [AuthController::class, 'showRegistrationForm']);
    Route::post('/tes-registrasi', [AuthController::class, 'register']);
});

/*
|--------------------------------------------------------------------------
| RUTE TERAUTENTIKASI (Sudah Login) - PROGRAM RIIL BERBASIS PERAN TANPA CSS
|--------------------------------------------------------------------------
| Mengatur navigasi dan aksi seluruh 9 peran resmi sistem SIM-PBJ Politeknik Negeri Jakarta.
*/
Route::middleware('auth')->group(function () {
    // Redirect halaman beranda ke dashboard resmi
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // Dashboard utama operasional dinamis sesuai peran pengguna
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->name('logout');

    // -------------------------------------------------------------------------
    // Modul 1: Permohonan Pengadaan (User / Jurusan)
    // -------------------------------------------------------------------------
    Route::get('/permohonan', [HomeController::class, 'permohonanIndex'])->name('permohonan.index');
    Route::get('/permohonan/create', [HomeController::class, 'permohonanCreate'])->name('permohonan.create');
    Route::post('/permohonan', [HomeController::class, 'storePermohonan'])->name('permohonan.store');
    Route::post('/permohonan/revisi', [HomeController::class, 'revisiPermohonan'])->name('permohonan.revisi');

    // TAMBAHKAN BARIS INI:
    Route::get('/permohonan/{id}', [HomeController::class, 'permohonanShow'])->name('permohonan.show');
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // Modul 2 & 3: Verifikasi Paralel (Staf B2, Wadir 2, & Perencanaan)
    // -------------------------------------------------------------------------
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
        Route::post('/verifikasi/perencanaan/split', [HomeController::class, 'splitMakPerencanaan'])->name('verifikasi.perencanaan.split');
    });

    // -------------------------------------------------------------------------
    // Modul 4: Paket Pengadaan (Koordinator PPBJ)
    // -------------------------------------------------------------------------
    Route::get('/paket-pengadaan', [HomeController::class, 'paketIndex'])->name('paket.index');
    Route::middleware('role:ppbj')->group(function () {
        Route::get('/paket-pengadaan/create', [HomeController::class, 'paketCreate'])->name('paket.create');
        Route::post('/paket-pengadaan', [HomeController::class, 'storePaket'])->name('paket.store');
        Route::post('/paket-pengadaan/reassign-ppk', [HomeController::class, 'reassignPpkWeb'])->name('paket.reassign-ppk');
        Route::post('/paket-pengadaan/split-jenis', [HomeController::class, 'splitJenisPpbj'])->name('paket.split-jenis');
    });

    // -------------------------------------------------------------------------
    // Modul 5: Kontrak SPK (Pejabat Pengadaan & 4 Akun PPK Terisolasi)
    // -------------------------------------------------------------------------
    Route::get('/kontrak-spk', [HomeController::class, 'kontrakIndex'])->name('kontrak.index');
    Route::post('/kontrak-spk', [HomeController::class, 'storeKontrak'])->name('kontrak.store');
    Route::post('/kontrak-spk/reviu', [HomeController::class, 'storeReviuWeb'])->name('kontrak.reviu');

    // -------------------------------------------------------------------------
    // Modul 6: Penerimaan Barang & BAST (Unit Perlengkapan / BMN)
    // -------------------------------------------------------------------------
    Route::middleware('role:perlengkapan')->group(function () {
        Route::get('/penerimaan-barang', [HomeController::class, 'penerimaanIndex'])->name('penerimaan.index');
        Route::post('/penerimaan-barang', [HomeController::class, 'storePenerimaan'])->name('penerimaan.store');
    });

    // -------------------------------------------------------------------------
    // Modul 7: Tagihan SPP & Pencairan Dana (Bagian Keuangan & PPBJ & PPK & Wadir 2)
    // -------------------------------------------------------------------------
    Route::get('/pembayaran', [HomeController::class, 'pembayaranIndex'])->name('pembayaran.index')->middleware('role:ppbj,ppk,wadir_2,keuangan');
    Route::post('/pembayaran/pengajuan', [HomeController::class, 'storePengajuanBayarWeb'])->name('pembayaran.pengajuan')->middleware('role:ppbj');
    Route::post('/pembayaran/memo/approve', [HomeController::class, 'approveMemoBayarWeb'])->name('pembayaran.memo.approve')->middleware('role:ppk,wadir_2');
    Route::post('/pembayaran/terbitkan-spp', [HomeController::class, 'terbitkanSppWeb'])->name('pembayaran.terbitkan-spp')->middleware('role:ppbj');
    Route::post('/pembayaran', [HomeController::class, 'storePembayaran'])->name('pembayaran.store')->middleware('role:keuangan');

    // -------------------------------------------------------------------------
    // Modul 8: Monitoring 8 Siklus Pengadaan & Audit Trail Transparansi
    // -------------------------------------------------------------------------
    Route::get('/monitoring', [HomeController::class, 'monitoringIndex'])->name('monitoring.index');

    // -------------------------------------------------------------------------
    // Modul 9: Laporan Rekapitulasi Anggaran & Unduh PDF (FR 20)
    // -------------------------------------------------------------------------
    Route::get('/laporan/rekapitulasi-anggaran', [HomeController::class, 'laporanRekapitulasiIndex'])->name('laporan.rekapitulasi');
    Route::get('/laporan/rekapitulasi-anggaran/pdf', [HomeController::class, 'unduhRekapitulasiPdf'])->name('laporan.rekapitulasi.pdf');
    Route::post('/notifikasi/{id}/dismiss', [HomeController::class, 'dismissNotificationWeb'])->name('notifikasi.dismiss');

    // -------------------------------------------------------------------------
    // Helper Unduh Berkas Resmi Format PDF (< 3 Detik - NFR 12)
    // -------------------------------------------------------------------------
    Route::get('/dokumen-pdf/{jenis}/{id}', [HomeController::class, 'unduhDokumenPdf'])
        ->name('dokumen.pdf')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Backward-Compatibility Aliases (Menjaga Kompatibilitas Tautan Pengujian Lama)
    // -------------------------------------------------------------------------
    Route::get('/tes-home', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/tes-permohonan', [HomeController::class, 'permohonanIndex'])->name('permohonan.web');
    Route::post('/tes-permohonan', [HomeController::class, 'storePermohonan'])->name('permohonan.web.store');
    Route::post('/tes-permohonan/revisi', [HomeController::class, 'revisiPermohonan'])->name('permohonan.web.revisi');
    Route::get('/tes-monitoring', [HomeController::class, 'monitoringIndex'])->name('monitoring.web');
    Route::get('/tes-paket', [HomeController::class, 'paketIndex'])->name('paket.web');
    Route::post('/tes-paket', [HomeController::class, 'storePaket'])->name('paket.web.store')->middleware('role:ppbj');
    Route::post('/tes-paket/reassign-ppk', [HomeController::class, 'reassignPpkWeb'])->name('paket.web.reassign')->middleware('role:ppbj');
    Route::get('/tes-verifikasi-b2', [HomeController::class, 'verifikasiBidang2'])->name('verifikasi-b2.web')->middleware('role:staff_bidang_2');
    Route::post('/tes-verifikasi-b2/teruskan-staff', [HomeController::class, 'teruskanStaffB2'])->name('verifikasi-b2.web.teruskan')->middleware('role:staff_bidang_2');
    Route::post('/tes-verifikasi-b2/keputusan-wadir', [HomeController::class, 'keputusanWadir2'])->name('verifikasi-b2.web.keputusan')->middleware('role:wadir_2');
    Route::get('/tes-unduh-pdf/{jenis}/{id}', [HomeController::class, 'unduhDokumenPdf'])->name('unduh.pdf.web')->where(['id' => '.*']);
    Route::post('/tes-logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| ENDPOINT RESTful API BACKEND (18 Entitas & Workflow Pengadaan)
|--------------------------------------------------------------------------
| Semua endpoint ini menggunakan awalan '/api' dan mengembalikan respons JSON.
| Dilengkapi otorisasi berbasis peran (Role-Based Access Control) dan perlindungan data.
*/
Route::prefix('api')->group(function () {
    // Master data unit kerja dan jurusan di lingkungan kampus Politeknik Negeri Jakarta
    Route::apiResource('units', UnitController::class);

    // Manajemen data akun pengguna dan penetapan 9 peran sistem
    Route::apiResource('users', UserController::class);

    // Registrasi dan pengelolaan direktori rekanan penyedia/vendor barang & jasa
    Route::apiResource('vendors', VendorController::class);

    // Permohonan Pengadaan: Mengambil riwayat pengajuan khusus user login beserta jejak penolakannya (FR 4.1)
    Route::get('permohonan-pengadaan/riwayat-user', [PermohonanPengadaanController::class, 'riwayatUser'])
        ->name('permohonan-pengadaan.riwayat-user');

    // Unduh berkas fisik resmi permohonan format PDF dengan standar respons < 3 detik (NFR 12)
    Route::get('permohonan-pengadaan/{permohonan_pengadaan}/unduh-pdf', [PermohonanPengadaanController::class, 'unduhPdf'])
        ->name('permohonan-pengadaan.unduh-pdf')
        ->where(['permohonan_pengadaan' => '.*']);

    // Memecah (split) usulan permohonan pengadaan berdasarkan Kode MAK (FR 6.3 - Perencanaan) atau Jenis Pengadaan (FR 7.2 - PPBJ)
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/split', [PermohonanPengadaanController::class, 'split'])
        ->name('permohonan-pengadaan.split')
        ->middleware('role:perencanaan,ppbj,admin')
        ->where(['permohonan_pengadaan' => '.*']);

    Route::post('permohonan/{permohonan_pengadaan}/split-ppbj', [PermohonanPengadaanController::class, 'split'])
        ->name('permohonan.split-ppbj')
        ->middleware('role:ppbj,admin')
        ->where(['permohonan_pengadaan' => '.*']);

    // Mengajukan revisi permohonan yang ditolak dengan nomor surat baru dan relasi unary (FR 3.4)
    Route::post('permohonan-pengadaan/{permohonan_pengadaan}/revisi', [PermohonanPengadaanController::class, 'revisi'])
        ->name('permohonan-pengadaan.revisi')
        ->where(['permohonan_pengadaan' => '.*']);

    // Melacak 8 tahapan siklus permohonan pengadaan secara detail dan real-time (NFR 7)
    Route::get('permohonan-pengadaan/{permohonan_pengadaan}/tracking', [PermohonanPengadaanController::class, 'tracking'])
        ->name('permohonan-pengadaan.tracking')
        ->where(['permohonan_pengadaan' => '.*']);

    // Endpoint pemantauan agregat status pengadaan di kampus dengan filter dan paginasi (NFR 7 & FR 19)
    Route::get('monitoring-status', [PermohonanPengadaanController::class, 'monitoringStatus'])
        ->name('monitoring.status');
    Route::get('permohonan-pengadaan/monitoring', [PermohonanPengadaanController::class, 'monitoringStatus'])
        ->name('permohonan-pengadaan.monitoring');

    // Operasi CRUD standar untuk usulan permohonan pengadaan
    Route::apiResource('permohonan-pengadaan', PermohonanPengadaanController::class)->where(['permohonan_pengadaan' => '.*']);

    // Menetapkan status Hold / Pending Anggaran pada permohonan pengadaan (FR 6.2)
    Route::post('anggaran-mak/hold', [AnggaranMAKController::class, 'holdAnggaran'])
        ->name('anggaran-mak.hold')
        ->middleware('role:perencanaan');

    // Penguncian pagu MAK secara manual atau otomatis untuk mencegah mutasi anggaran (NFR Integritas MAK)
    Route::post('anggaran-mak/{anggaran_mak}/lock', [AnggaranMAKController::class, 'lock'])
        ->middleware('role:perencanaan');

    // Operasi CRUD master mata anggaran kas (MAK) DIPA kampus
    Route::apiResource('anggaran-mak', AnggaranMAKController::class);

    // Pengalihan penugasan (reassign) paket pengadaan ke PPK lain disertai alasan dan jejak audit (FR 7.3 & NFR 4)
    Route::post('paket-pengadaan/{paket_pengadaan}/reassign-ppk', [PaketPengadaanController::class, 'reassignPpk'])
        ->name('paket-pengadaan.reassign-ppk')
        ->middleware('role:ppbj,perencanaan')
        ->where(['paket_pengadaan' => '.*']);

    // Registrasi dan manajemen paket pengadaan barang/jasa dengan auto-routing HPS (NFR 4)
    Route::apiResource('paket-pengadaan', PaketPengadaanController::class)->where(['paket_pengadaan' => '.*']);

    // Telaah berkas oleh Staf Bidang 2 untuk diteruskan secara resmi ke Wadir 2 (FR 5)
    Route::post('verifikasi-paralel/teruskan-ke-wadir', [VerifikasiParalelController::class, 'teruskanKeWadir'])
        ->name('verifikasi-paralel.teruskan-ke-wadir');

    // Manajemen keputusan verifikasi paralel oleh pimpinan (Wadir 2) dan Bagian Perencanaan
    Route::apiResource('verifikasi-paralel', VerifikasiParalelController::class)->where(['verifikasi_paralel' => '.*']);

    // Pemetaan alokasi pagu MAK ke permohonan pengadaan yang telah disetujui
    Route::apiResource('alokasi-mak', AlokasiMAKController::class)->where(['alokasi_mak' => '.*']);

    // Hasil penelaahan dokumen teknis & evaluasi HPS oleh Pejabat Pengadaan / PPK
    Route::apiResource('reviu-dokumen', ReviuDokumenController::class);
    Route::apiResource('reviu-pengadaan', ReviuPengadaanController::class);

    // Log pencatatan riwayat penugasan atau alih tugas paket kepada 4 akun PPK
    Route::apiResource('riwayat-penugasan-ppk', RiwayatPenugasanPPKController::class);

    // Unduh salinan resmi Surat Perintah Kerja (SPK) format PDF (< 3 detik)
    Route::get('kontrak-spk/{kontrak_spk}/unduh-pdf', [KontrakSPKController::class, 'unduhPdf'])
        ->name('kontrak-spk.unduh-pdf')
        ->where(['kontrak_spk' => '.*']);

    // Penerbitan dan pengelolaan Kontrak / SPK dengan rekanan penyedia
    Route::apiResource('kontrak-spk', KontrakSPKController::class)->where(['kontrak_spk' => '.*']);

    // Unduh Berita Acara Serah Terima (BAST) fisik barang format PDF lengkap dengan Token HMAC (NFR 12)
    Route::get('penerimaan-barang/{penerimaan_barang}/unduh-pdf', [PenerimaanBarangController::class, 'unduhPdf'])
        ->name('penerimaan-barang.unduh-pdf')
        ->where(['penerimaan_barang' => '.*']);

    // Pemeriksaan fisik barang kiriman vendor dan penerbitan BAST oleh bagian Perlengkapan
    Route::apiResource('penerimaan-barang', PenerimaanBarangController::class)->where(['penerimaan_barang' => '.*']);

    // Unggah bukti foto dokumentasi fisik barang yang diterima di gudang kampus
    Route::apiResource('dokumentasi-penerimaan-barang', DokumentasiPenerimaanBarangController::class);

    // Penerbitan instruksi perintah bayar oleh Koordinator PPBJ setelah pemeriksaan berkas rampung
    Route::post('pembayaran/{pembayaran}/perintah-bayar', [PembayaranController::class, 'terbitkanPerintahBayar'])
        ->middleware('role:ppbj');

    // Pengajuan tagihan pembayaran SPP (Surat Permintaan Pembayaran) bertahap atau lunas
    Route::apiResource('pembayaran', PembayaranController::class)->where(['pembayaran' => '.*']);

    // Disposisi catatan memo bayar bertingkat oleh PPK dan Wadir 2
    Route::apiResource('memo-bayar', MemoBayarController::class)->where(['memo_bayar' => '.*']);

    // Unduh bukti slip transfer pemindahbukuan bank resmi format PDF (NFR 12)
    Route::get('transaksi-pencairan/{transaksi_pencairan}/unduh-pdf', [TransaksiPencairanController::class, 'unduhPdf'])
        ->name('transaksi-pencairan.unduh-pdf')
        ->where(['transaksi_pencairan' => '.*']);

    // Eksekusi pencairan dana SP2D oleh Bendahara/Keuangan dengan proteksi anti-duplikasi (NFR 8)
    Route::apiResource('transaksi-pencairan', TransaksiPencairanController::class)->where(['transaksi_pencairan' => '.*']);

    // Pengelolaan jadwal pengingat masa kontrak, BAST, dan jatuh tempo termin (NFR 5)
    Route::post('reminders/sync', [ReminderController::class, 'sinkronkanOtomatis'])->name('reminders.sync');
    Route::apiResource('reminders', ReminderController::class);

    // Endpoint Laporan Rekapitulasi Anggaran JSON & Health-Check Uptime
    Route::get('laporan/rekapitulasi-anggaran', [HomeController::class, 'laporanRekapitulasiIndex']);
    Route::get('health-check', [HomeController::class, 'healthCheck'])->name('api.health-check');

    // Feed daftar notifikasi personal bagi pengguna yang sedang login
    Route::get('notifikasi-user', [NotifikasiUserController::class, 'index']);

    // Menandai satu notifikasi telah dibaca oleh pengguna
    Route::post('notifikasi-user/{notifikasi}/read', [NotifikasiUserController::class, 'markAsRead']);

    // Mengarsipkan atau menghapus notifikasi dari feed aktif pengguna
    Route::post('notifikasi-user/{notifikasi}/dismiss', [NotifikasiUserController::class, 'dismiss']);

    // Jejak audit otomatis setiap mutasi data untuk pengawasan auditor dan pimpinan
    Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show', 'store']);
    Route::apiResource('pengadaan-logs', PengadaanLogController::class)->only(['index', 'show', 'store']);
});

// Endpoint Pemeriksaan Ketersediaan & Kesehatan Sistem (NFR 10)
Route::get('/health', [HomeController::class, 'healthCheck'])->name('health');
Route::get('/health-check', [HomeController::class, 'healthCheck']);
