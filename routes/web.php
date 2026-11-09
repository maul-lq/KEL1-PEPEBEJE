<?php

use App\Http\Controllers\Admin\ApprovalRegistrasiController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
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

    // Modul 1: Permohonan Pengadaan
    Route::get('/permohonan', [HomeController::class, 'permohonanIndex'])->name('permohonan.index');
    Route::get('/permohonan/create', [HomeController::class, 'permohonanCreate'])->name('permohonan.create');
    Route::post('/permohonan', [HomeController::class, 'storePermohonan'])->name('permohonan.store');
    Route::post('/permohonan/revisi', [HomeController::class, 'revisiPermohonan'])->name('permohonan.revisi');
    Route::get('/permohonan/{id}', [HomeController::class, 'permohonanShow'])->name('permohonan.show')->where(['id' => '.*']);

    Route::get('/permohonan/{id}', [HomeController::class, 'permohonanShow'])
        ->name('permohonan.show')
        ->where(['id' => '.*']);

    Route::delete('/permohonan/{id}', [HomeController::class, 'destroyPermohonan'])
        ->name('permohonan.destroy')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul 2 & 3: Verifikasi Berantai Awal (Staf B2 -> Perencanaan -> Wadir 2 & PPK)
    // -------------------------------------------------------------------------
    Route::middleware('role:staff_bidang_2')->group(function () {
        Route::get('/verifikasi/bidang-2', [HomeController::class, 'verifikasiBidang2'])->name('verifikasi.bidang-2');
        Route::post('/verifikasi/bidang-2/teruskan', [HomeController::class, 'teruskanStaffB2'])->name('verifikasi.bidang-2.teruskan');
    });

    Route::middleware('role:wadir_2')->group(function () {
        Route::get('/verifikasi/wadir-2', [HomeController::class, 'verifikasiWadir2'])->name('verifikasi.wadir-2');
        Route::post('/verifikasi/wadir-2/keputusan', [HomeController::class, 'keputusanWadir2'])->name('verifikasi.wadir-2.keputusan');
    });

    Route::middleware('role:ppk')->group(function () {
        Route::get('/verifikasi/ppk', [HomeController::class, 'verifikasiPpk'])->name('verifikasi.ppk');
        Route::post('/verifikasi/ppk/keputusan', [HomeController::class, 'keputusanPpkAwal'])->name('verifikasi.ppk.keputusan');

        // BPMN N30-N36: Menerima & Menyetujui Dokumen Pengadaan (setelah PPBJ susun & PP verifikasi)
        Route::get('/reviu-pengadaan/ppk', [HomeController::class, 'persetujuanDokumenPpkIndex'])->name('ppk.persetujuan-dokumen.index');
        Route::post('/reviu-pengadaan/ppk/keputusan', [HomeController::class, 'keputusanDokumenPpk'])->name('ppk.persetujuan-dokumen.keputusan');
    });

    Route::middleware('role:perencanaan')->group(function () {
        Route::get('/verifikasi/perencanaan', [HomeController::class, 'verifikasiPerencanaan'])->name('verifikasi.perencanaan');
        Route::post('/verifikasi/perencanaan/mak', [HomeController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.mak');
        Route::post('/verifikasi/perencanaan/alokasi', [HomeController::class, 'alokasiMakPerencanaan'])->name('verifikasi.perencanaan.alokasi');
        Route::post('/verifikasi/perencanaan/mak/store', [HomeController::class, 'storeMakPerencanaan'])->name('verifikasi.perencanaan.mak.store');
        Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/lock', [HomeController::class, 'lockMakWeb'])
            ->name('verifikasi.perencanaan.mak.lock')
            ->where(['anggaran_mak' => '.*']);
        Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/unlock', [HomeController::class, 'unlockMakWeb'])
            ->name('verifikasi.perencanaan.mak.unlock')
            ->where(['anggaran_mak' => '.*']);
        Route::post('/verifikasi/perencanaan/split', [HomeController::class, 'splitMakPerencanaan'])->name('verifikasi.perencanaan.split');
    });

    // -------------------------------------------------------------------------
    // Modul 4: Paket Pengadaan & Belanja Langsung <= 50 Juta (Koordinator PPBJ)
    // -------------------------------------------------------------------------
    Route::get('/paket-pengadaan', [HomeController::class, 'paketIndex'])->name('paket.index');
    Route::get('/paket-pengadaan/split-jenis', [HomeController::class, 'splitJenisCreate'])->name('paket.split-jenis.create')->middleware('role:ppbj');
    Route::get('/paket-pengadaan/reassign-ppk', [HomeController::class, 'reassignPpkCreate'])->name('paket.reassign-ppk.create')->middleware('role:ppbj,perencanaan,wadir_2');
    Route::middleware('role:ppbj')->group(function () {
        Route::get('/paket-pengadaan/create', [HomeController::class, 'paketCreate'])->name('paket.create');
        Route::post('/paket-pengadaan', [HomeController::class, 'storePaket'])->name('paket.store');
        Route::post('/paket-pengadaan/belanja-langsung', [HomeController::class, 'storeBelanjaLangsungWeb'])->name('paket.belanja-langsung');
        Route::post('/paket-pengadaan/reassign-ppk', [HomeController::class, 'reassignPpkWeb'])->name('paket.reassign-ppk');
        Route::post('/paket-pengadaan/split-jenis', [HomeController::class, 'splitJenisPpbj'])->name('paket.split-jenis');
    });
    // Titik Kembali BPMN N18: Perbaikan / Penyusunan Ulang Dokumen Pengadaan yang Ditolak PP (N27) atau PPK (N35)
    Route::get('/paket-pengadaan/{id}/edit', [HomeController::class, 'paketEdit'])
        ->name('paket.edit')
        ->middleware('role:ppbj,admin')
        ->where(['id' => '.*']);
    Route::put('/paket-pengadaan/{id}', [HomeController::class, 'paketUpdate'])
        ->name('paket.update')
        ->middleware('role:ppbj,admin')
        ->where(['id' => '.*']);
    Route::get('/paket-pengadaan/{id}', [HomeController::class, 'paketShow'])
        ->name('paket.show')
        ->where(['id' => '.*']);

    // -------------------------------------------------------------------------
    // Modul 5: Review PP, Approval PPK, Eksekusi Belanja, & Upload 1 Dokumen Perikatan PPBJ
    // -------------------------------------------------------------------------
    // Dashboard Monitoring Aktivitas PP (BPMN N21, N27, N31) untuk PPBJ, Pengawas, & Admin
    Route::get('/monitor-pp', [HomeController::class, 'monitorPpIndex'])
        ->name('monitor-pp.index')
        ->middleware('role:ppbj,admin,pp,ppk,lihat_pp,pp.monitoring_aktivitas');

    Route::get('/kontrak-spk', [HomeController::class, 'kontrakIndex'])->name('kontrak.index')->middleware('role:pp,ppk,ppbj,admin');
    Route::post('/kontrak-spk', [HomeController::class, 'storeKontrak'])->name('kontrak.store')->middleware('role:pp,ppbj,admin');
    Route::post('/kontrak-spk/reviu', [HomeController::class, 'storeReviuWeb'])->name('kontrak.reviu')->middleware('role:pp,ppk');

    // Modul 6: Penerimaan Barang & BAST
    Route::middleware('role:perlengkapan')->group(function () {
        Route::get('/penerimaan-barang', [HomeController::class, 'penerimaanIndex'])->name('penerimaan.index');
        Route::post('/penerimaan-barang', [HomeController::class, 'storePenerimaan'])->name('penerimaan.store');
    });

    // Modul 7: Tagihan SPP & Pencairan Dana
    Route::get('/pembayaran', [HomeController::class, 'pembayaranIndex'])->name('pembayaran.index')->middleware('role:ppbj,ppk,wadir_2,keuangan');
    Route::post('/pembayaran/pengajuan', [HomeController::class, 'storePengajuanBayarWeb'])->name('pembayaran.pengajuan')->middleware('role:ppbj');
    // Titik Kembali BPMN N52: Perbaikan Berkas Dokumen Pembayaran yang Ditolak Wadir II (N60) atau PPK (N61)
    Route::put('/pembayaran/{nomor_pembayaran}', [HomeController::class, 'pembayaranUpdate'])
        ->name('pembayaran.update')
        ->middleware('role:ppbj,admin')
        ->where(['nomor_pembayaran' => '.*']);
    Route::post('/pembayaran/memo/approve', [HomeController::class, 'approveMemoBayarWeb'])->name('pembayaran.memo.approve')->middleware('role:ppk,wadir_2');
    Route::post('/pembayaran/terbitkan-spp', [HomeController::class, 'terbitkanSppWeb'])->name('pembayaran.terbitkan-spp')->middleware('role:ppbj');
    Route::post('/pembayaran', [HomeController::class, 'storePembayaran'])->name('pembayaran.store')->middleware('role:keuangan');

    // Modul Riwayat & Laporan (Menggantikan Modul Monitoring & Rekapitulasi Terpisah)
    Route::get('/laporan', [HomeController::class, 'laporanIndex'])->name('laporan.index');
    Route::get('/laporan/unduh-laporan-pdf', [HomeController::class, 'unduhLaporanRingkasanPdf'])->name('laporan.unduh.ringkasan');
    Route::get('/laporan/unduh-detail-pdf', [HomeController::class, 'unduhLaporanDetailPdf'])->name('laporan.unduh.detail');

    // Rute kompatibilitas legacy untuk pengalihan navigasi dan pengujian terdahulu
    Route::get('/laporan/rekapitulasi-anggaran', [HomeController::class, 'laporanIndex'])->name('laporan.rekapitulasi');
    Route::get('/laporan/rekapitulasi-anggaran/pdf', [HomeController::class, 'unduhLaporanRingkasanPdf'])->name('laporan.rekapitulasi.pdf');
    Route::get('/monitoring', fn () => redirect()->route('laporan.index'))->name('monitoring.index');

    // Modul Notifikasi & Reminder
    Route::get('/notifikasi', [HomeController::class, 'notifikasiIndex'])->name('notifikasi.index');
    Route::post('/notifikasi/tes-kirim', [HomeController::class, 'kirimNotifikasiUjiCoba'])->name('notifikasi.tes-kirim');
    Route::post('/notifikasi/mark-all-read', [HomeController::class, 'markAllReadWeb'])->name('notifikasi.mark-all-read');

    // Alias route nama camelCase agar mendukung panggil route('notifikasi.markAllRead')
    Route::post('/notifikasi/mark-all-read-alias', [HomeController::class, 'markAllReadWeb'])->name('notifikasi.markAllRead');

    Route::post('/notifikasi/{id}/read', [HomeController::class, 'markAsReadWeb'])->name('notifikasi.read');
    Route::post('/notifikasi/{id}/dismiss', [HomeController::class, 'dismissNotificationWeb'])->name('notifikasi.dismiss');
    Route::get('/api/notifikasi-terbaru', [NotifikasiUserController::class, 'notifikasiTerbaru'])->name('notifikasi.terbaru');

    // Modul Admin
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

/*
|--------------------------------------------------------------------------
| ENDPOINT RESTful API BACKEND
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    Route::apiResource('units', UnitController::class);
    Route::apiResource('users', UserController::class);
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
        ->middleware('role:perencanaan,ppbj')
        ->where(['permohonan_pengadaan' => '.*']);

    Route::post('permohonan/{permohonan_pengadaan}/split-ppbj', [PermohonanPengadaanController::class, 'split'])
        ->name('permohonan.split-ppbj')
        ->middleware('role:ppbj')
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

    Route::get('monitoring-status', [PermohonanPengadaanController::class, 'monitoringStatus'])->name('monitoring.status');
    Route::get('permohonan-pengadaan/monitoring', [PermohonanPengadaanController::class, 'monitoringStatus'])->name('permohonan-pengadaan.monitoring');

    // Penahanan status pagu (Hold / Pending Anggaran) jika anggaran DIPA belum siap (FR 6.2)
    Route::post('anggaran-mak/hold', [AnggaranMAKController::class, 'holdAnggaran'])
        ->name('anggaran-mak.hold')
        ->middleware('role:perencanaan');

    // Penguncian dan pembukaan kunci pagu MAK secara eksklusif oleh Bagian Perencanaan (NFR Integritas MAK)
    Route::post('anggaran-mak/{anggaran_mak}/lock', [AnggaranMAKController::class, 'lock'])
        ->name('anggaran-mak.lock')
        ->middleware('role:perencanaan')
        ->where(['anggaran_mak' => '.*']);
    Route::post('anggaran-mak/{anggaran_mak}/unlock', [AnggaranMAKController::class, 'unlock'])
        ->name('anggaran-mak.unlock')
        ->middleware('role:perencanaan')
        ->where(['anggaran_mak' => '.*']);

    // Operasi CRUD master mata anggaran kas (MAK) DIPA kampus
    Route::apiResource('anggaran-mak', AnggaranMAKController::class)->where(['anggaran_mak' => '.*']);

    // Pengalihan penugasan (reassign) paket pengadaan ke PPK lain disertai alasan dan jejak audit (FR 7.3 & NFR 4)
    Route::post('paket-pengadaan/{paket_pengadaan}/reassign-ppk', [PaketPengadaanController::class, 'reassignPpk'])
        ->name('paket-pengadaan.reassign-ppk')
        ->middleware('role:ppbj,perencanaan')
        ->where(['paket_pengadaan' => '.*']);

    // Eksekusi Belanja Langsung <= Rp 50 Juta oleh PPBJ (output Kwitansi, tanpa Kontrak/SPK)
    Route::post('paket-pengadaan/{paket_pengadaan}/belanja-langsung', [PaketPengadaanController::class, 'belanjaLangsung'])
        ->name('paket-pengadaan.belanja-langsung')
        ->middleware('role:ppbj')
        ->where(['paket_pengadaan' => '.*']);

    // Review kelengkapan & input Harga Perkiraan, Harga Akhir, serta Vendor oleh PP (50jt - 200jt; PP tidak bisa approval final)
    Route::post('paket-pengadaan/{paket_pengadaan}/review-pp', [PaketPengadaanController::class, 'reviewPp'])
        ->name('paket-pengadaan.review-pp')
        ->middleware('role:pp')
        ->where(['paket_pengadaan' => '.*']);

    // Eksekusi kewenangan belanja oleh PP (50jt - 200jt pasca-approval PPK) atau PPK (> 200jt)
    Route::post('paket-pengadaan/{paket_pengadaan}/eksekusi-belanja', [PaketPengadaanController::class, 'eksekusiBelanja'])
        ->name('paket-pengadaan.eksekusi-belanja')
        ->middleware('role:pp,ppk')
        ->where(['paket_pengadaan' => '.*']);

    // Registrasi dan manajemen paket pengadaan barang/jasa dengan auto-routing HPS (NFR 4)
    Route::apiResource('paket-pengadaan', PaketPengadaanController::class)->where(['paket_pengadaan' => '.*']);

    // Alur Persetujuan Awal Berantai (Notulensi PPBJ):
    // 1. Staf Bidang 2 mengirim Memo Cek MAK ke Perencanaan
    Route::post('verifikasi-paralel/memo-cek-mak', [VerifikasiParalelController::class, 'memoCekMak'])
        ->name('verifikasi-paralel.memo-cek-mak')
        ->middleware('role:staff_bidang_2,wadir_2');

    // 2. Perencanaan memeriksa/membuat MAK, memilih 1 dari 4 PPK, & mengirim Memo Ketersediaan MAK ke Wadir 2 + PPK
    Route::post('verifikasi-paralel/memo-ketersediaan-mak', [VerifikasiParalelController::class, 'memoKetersediaanMak'])
        ->name('verifikasi-paralel.memo-ketersediaan-mak')
        ->middleware('role:perencanaan');

    // 3. Keputusan persetujuan awal ganda oleh Wadir 2 (ACC kegiatan) dan PPK terpilih (ACC usulan)
    Route::post('verifikasi-paralel/keputusan-awal', [VerifikasiParalelController::class, 'keputusanAwal'])
        ->name('verifikasi-paralel.keputusan-awal')
        ->middleware('role:wadir_2,ppk');

    // Alias kompatibilitas telaah berkas oleh Staf Bidang 2
    Route::post('verifikasi-paralel/teruskan-ke-wadir', [VerifikasiParalelController::class, 'teruskanKeWadir'])
        ->name('verifikasi-paralel.teruskan-ke-wadir');

    // Manajemen keputusan verifikasi paralel oleh pimpinan (Wadir 2) dan Bagian Perencanaan
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

    // Penerbitan instruksi perintah bayar oleh Koordinator PPBJ setelah pemeriksaan berkas rampung
    Route::post('pembayaran/{pembayaran}/perintah-bayar', [PembayaranController::class, 'terbitkanPerintahBayar'])
        ->name('pembayaran.perintah-bayar')
        ->middleware('role:ppbj')
        ->where(['pembayaran' => '.*']);

    // Pengajuan tagihan pembayaran SPP (Surat Permintaan Pembayaran) bertahap atau lunas
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
