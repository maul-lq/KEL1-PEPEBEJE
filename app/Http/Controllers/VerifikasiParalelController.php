<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Controller ini rancang untuk menangani alur verifikasi berantai dan persetujuan
 *             paralel usulan pengadaan pada Staf Bidang II, Bagian Perencanaan (MAK DIPA & split
 *             MAK), Wakil Direktur II, serta persetujuan awal oleh akun PPK terpilih.
 */

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\User;
use App\Models\VerifikasiParalel;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mengelola tahapan verifikasi berantai awal dan penetapan pos anggaran MAK pada usulan pengadaan.
 *
 * Tujuan: menyatukan titik kendali Staf Bidang II, Bagian Perencanaan (alokasi/kunci/pecah
 * MAK serta penunjukan PPK), Wakil Direktur II, dan PPK awal hingga berkas layak dikirim ke PPBJ.
 *
 * Contoh pemanggilan:
 * `$response = app(VerifikasiParalelController::class)->verifikasiBidang2($request);`
 */
class VerifikasiParalelController extends Controller
{
    /**
     * Menyuntikkan layanan notifikasi pengadaan untuk setiap transisi tahap verifikasi.
     *
     * Tujuan: menaruh dependensi di konstruktor agar penerbitan memo Bidang II, Perencanaan,
     * maupun keputusan Wadir II dan PPK dapat langsung memicu notifikasi pengguna terkait.
     *
     * @param  PengadaanNotificationService  $notificationService  Layanan pengirim notifikasi PBJ.
     * @return void
     *
     * Contoh pemanggilan:
     * `new VerifikasiParalelController(app(PengadaanNotificationService::class));`
     */
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar permohonan masuk beserta filter pencarian pada halaman Staf Bidang 2.
     *
     * Tujuan: menyaring berkas yang tidak digantikan oleh revisi serta menerapkan pencarian
     * case-insensitive (`ilike` PostgreSQL) pada nomor surat, judul, dan perihal pengadaan.
     *
     * @param  Request  $request  Permintaan HTTP berisi kata kunci atau filter status permohonan.
     * @return View Halaman `verifikasi.bidang_2` dengan koleksi `$permohonanMasuk`.
     *
     * Contoh pemanggilan:
     * `Route::get('/verifikasi/bidang-2', [VerifikasiParalelController::class, 'verifikasiBidang2']);`
     */
    public function verifikasiBidang2(Request $request): View
    {
        if (! Auth::user()?->hasPermission('verifikasi.bidang_2_view')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin memantau antrean berkas usulan masuk.');
        }

        $query = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel', 'paketPengadaan.penerimaanBarang.fotoDokumentasi'])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi'); // kecualikan surat lama yang telah digantikan revisi agar daftar pemantauan bebas duplikat.

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                // pakai operator ilike agar pencarian tetap case-insensitive di PostgreSQL.
                $q->where('nomor_surat', 'ilike', "%{$keyword}%")
                    ->orWhere('judul_pengadaan', 'ilike', "%{$keyword}%")
                    ->orWhere('perihal', 'ilike', "%{$keyword}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'Semua') {
            $query->where('status_permohonan', $request->status);
        }

        $permohonanMasuk = $query->latest('tanggal_pengajuan')->get();

        return view('verifikasi.bidang_2', compact('permohonanMasuk'));
    }

    /**
     * Menampilkan antrean keputusan persetujuan kegiatan Wakil Direktur II (ACC Kegiatan / Tolak).
     *
     * Tujuan: mengumpulkan usulan yang telah ditetapkan pos anggaran dan PPK-nya oleh Bagian Perencanaan
     * beserta riwayat verifikasi paralelnya untuk diputuskan Wadir II secara paralel dengan PPK terpilih.
     *
     * @return View Halaman `verifikasi.wadir_2` dengan variabel `$permohonanAntrean`.
     *
     * Contoh pemanggilan:
     * `Route::get('/verifikasi/wadir-2', [VerifikasiParalelController::class, 'verifikasiWadir2']);`
     */
    public function verifikasiWadir2(): View
    {
        if (! Auth::user()?->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin melihat antrean persetujuan Wakil Direktur II.');
        }

        // hanya memuat usulan yang sudah selesai diperiksa Bagian Perencanaan agar verifikasi paralel Wadir 2 dan PPK berjalan berurutan pasca-MAK.
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal', 'verifikasiParalel'])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
            ])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.wadir_2', compact('permohonanAntrean'));
    }

    /**
     * Memproses keputusan Wadir 2 (ACC Kegiatan / Tolak / Revisi).
     *
     * Tujuan: memastikan alasan wajib diisi saat Wadir II menolak atau meminta revisi, lalu
     * mengevaluasi kecukupan dual-approval bersama PPK sebelum menaikkan status ke PPBJ.
     *
     * @param  Request  $request  Payload keputusan Wadir II, catatan alasan, dan memo opsional.
     * @return RedirectResponse Redirect kembali ke antrean verifikasi Wadir II.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/wadir-2/keputusan', [VerifikasiParalelController::class, 'keputusanWadir2']);`
     */
    public function keputusanWadir2(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('verifikasi.wadir_2_decide')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin memutuskan persetujuan usulan Wadir II.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'keputusan' => ['required', 'in:disetujui,ditolak,revisi'],
            'catatan_alasan' => ['required_if:keputusan,ditolak,revisi', 'nullable', 'string'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'file_memo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan/revisi wajib diisi saat Wadir 2 menolak atau meminta revisi usulan.',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        if (! in_array($permohonan->status_permohonan, [
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
        ], true)) {
            return redirect()->route('verifikasi.wadir-2')
                ->with('error', 'Usulan hanya dapat diputuskan Wadir II setelah pos anggaran dan PPK ditetapkan oleh Bagian Perencanaan.');
        }

        // Simpan keputusan pada tabel verifikasi_paralel
        $verifWadir = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifWadir->id_user = $user->id_user;
        $verifWadir->status_keputusan = $validated['keputusan'];
        $verifWadir->catatan_alasan = $validated['catatan_alasan'] ?? null;
        if ($request->filled('nomor_memo')) {
            $verifWadir->nomor_memo = $validated['nomor_memo'];
        } elseif ($validated['keputusan'] === 'disetujui' && empty($verifWadir->nomor_memo)) {
            $verifWadir->nomor_memo = 'MEMO-WADIR2/'.date('Ymd').'/'.rand(100, 999);
        }
        if ($request->hasFile('file_memo')) {
            $verifWadir->file_memo = $request->file('file_memo')->store('memo_wadir2', 'public');
        }
        $verifWadir->tanggal_keputusan = now();
        $verifWadir->save();

        if ($validated['keputusan'] === 'revisi') {
            $permohonan->status = 'perlu_revisi';
            $permohonan->status_permohonan = 'perlu_revisi';
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'WADIR2_MINTA_REVISI',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                'reviu_paralel',
                'perlu_revisi',
                "Wadir 2 meminta revisi usulan permohonan dengan catatan: {$validated['catatan_alasan']}"
            );

            // Memicu notifikasi revisi ke pengusul
            $this->notificationService->notifyKeputusanWadir2($permohonan, 'revisi', $validated['catatan_alasan']);

            return redirect()->back()->with(
                'success',
                "Permintaan revisi usulan {$permohonan->nomor_surat} telah disimpan dan dikembalikan ke pengusul."
            );
        }

        if ($validated['keputusan'] === 'ditolak') {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'ditolak';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'WADIR2_TOLAK_USULAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'ditolak',
                "Wadir 2 menolak usulan permohonan dengan alasan: {$validated['catatan_alasan']}"
            );

            $this->notificationService->notifyKeputusanWadir2($permohonan, 'ditolak', $validated['catatan_alasan']);

            return redirect()->back()->with(
                'success',
                "Keputusan penolakan usulan {$permohonan->nomor_surat} telah disimpan dengan alasan resmi."
            );
        }

        // Evaluasi kelulusan paralel (AND-join): Perencanaan (MAK) + Wadir 2 (ACC Kegiatan) + PPK Terpilih (ACC Usulan)
        $perencanaanAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
            ->where('status_keputusan', 'disetujui')
            ->exists() || ! empty($permohonan->kode_mak);

        $ppkAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($perencanaanAcc && $ppkAcc) {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'disetujui',
                'Usulan disetujui penuh oleh Perencanaan, Wadir 2, dan PPK terpilih; otomatis diteruskan ke PPBJ.'
            );

            $this->notificationService->notifyKeputusanWadir2($permohonan, 'disetujui');

            return redirect()->back()->with(
                'success',
                'Persetujuan Wadir 2 berhasil dicatat. Wadir 2 dan PPK telah menyetujui, usulan diteruskan ke Unit Pengadaan.'
            );
        }

        $permohonan->status_permohonan = $perencanaanAcc
            ? PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK
            : PermohonanPengadaan::STATUS_REVIU_PARALEL;
        $permohonan->status = $permohonan->status_permohonan;
        $permohonan->save();

        $this->notificationService->notifyKeputusanWadir2($permohonan, 'disetujui');

        return redirect()->back()->with(
            'success',
            'Persetujuan Wadir 2 berhasil dicatat. Menunggu persetujuan PPK terpilih dan Perencanaan.'
        );
    }

    /**
     * Menampilkan antrean persetujuan awal usulan pengadaan bagi akun PPK terpilih (Isolasi 4 Akun PPK).
     *
     * Tujuan: memfilter daftar usulan hanya berdasarkan `id_user_ppk_awal` akun yang login
     * sehingga tiap PPK fokus meninjau usulan yang memang ditugaskan oleh Bagian Perencanaan.
     *
     * @return View Halaman `verifikasi.ppk` berisi daftar `$permohonanAntrean`.
     *
     * Contoh pemanggilan:
     * `Route::get('/verifikasi/ppk', [VerifikasiParalelController::class, 'verifikasiPpk']);`
     */
    public function verifikasiPpk(): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('verifikasi.ppk_approve')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin melihat antrean persetujuan awal usulan PPK.');
        }

        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal', 'verifikasiParalel'])
            ->where('id_user_ppk_awal', $user->id_user)
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
            ])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.ppk', compact('permohonanAntrean'));
    }

    /**
     * Memproses persetujuan awal usulan oleh PPK yang dipilih Perencanaan.
     *
     * Tujuan: menegakkan isolasi akun PPK lewat pengecekan 403 bila akun lain mencoba
     * memutuskan berkas tersebut, serta mengecek dual-approval paralel dengan Wadir II.
     *
     * @param  Request  $request  Payload keputusan PPK (`disetujui`/`ditolak`), alasan, dan memo.
     * @return RedirectResponse Redirect kembali ke halaman verifikasi PPK.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/ppk/keputusan', [VerifikasiParalelController::class, 'keputusanPpkAwal']);`
     */
    public function keputusanPpkAwal(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('verifikasi.ppk_approve')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin menyetujui atau menolak usulan awal sebagai PPK.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'keputusan' => ['required', 'in:disetujui,ditolak'],
            'catatan_alasan' => ['required_if:keputusan,ditolak', 'nullable', 'string'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'file_memo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan wajib diisi saat PPK menolak usulan permohonan.',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        // tegakkan isolasi 4 akun PPK: PPK yang tidak ditugaskan sebagai id_user_ppk_awal ditolak dengan 403.
        if ((int) $permohonan->id_user_ppk_awal !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak memproses persetujuan awal untuk usulan yang ditugaskan ke akun PPK lain.');
        }

        $verifPpk = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifPpk->id_user = $user->id_user;
        $verifPpk->status_keputusan = $validated['keputusan'];
        $verifPpk->catatan_alasan = $validated['catatan_alasan'] ?? 'Usulan disetujui oleh PPK atas Memo Ketersediaan MAK Perencanaan';
        if ($request->filled('nomor_memo')) {
            $verifPpk->nomor_memo = $validated['nomor_memo'];
        } elseif ($validated['keputusan'] === 'disetujui' && empty($verifPpk->nomor_memo)) {
            $verifPpk->nomor_memo = 'MEMO-PPK/'.date('Ymd').'/'.rand(100, 999);
        }
        if ($request->hasFile('file_memo')) {
            $verifPpk->file_memo = $request->file('file_memo')->store('memo_ppk', 'public');
        }
        $verifPpk->tanggal_keputusan = now();
        $verifPpk->save();

        if ($validated['keputusan'] === 'ditolak') {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'perlu_revisi';
            $permohonan->status_permohonan = 'perlu_revisi';
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PPK_AWAL_TOLAK_USULAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'perlu_revisi',
                "PPK ({$user->nama}) mengembalikan usulan permohonan untuk perbaikan: {$validated['catatan_alasan']}"
            );

            $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'ditolak', $validated['catatan_alasan']);

            return redirect()->back()->with(
                'success',
                "Keputusan penolakan/permintaan revisi usulan {$permohonan->nomor_surat} oleh PPK telah disimpan dan dikembalikan ke pengusul."
            );
        }

        $perencanaanAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
            ->where('status_keputusan', 'disetujui')
            ->exists() || ! empty($permohonan->kode_mak);

        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($perencanaanAcc && $wadirAcc) {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'disetujui',
                "PPK ({$user->nama}) dan Wadir 2 telah menyetujui lengkap; berkas diteruskan ke PPBJ."
            );

            $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'disetujui', $validated['catatan_alasan'] ?? null);

            return redirect()->back()->with(
                'success',
                "Persetujuan PPK dicatat. Wadir 2 dan PPK telah menyetujui, berkas {$permohonan->nomor_surat} diteruskan ke Unit Pengadaan."
            );
        }

        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->status = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->save();

        AuditLog::catat(
            $user->id_user,
            'PPK_AWAL_SETUJU_USULAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            "PPK ({$user->nama}) menyetujui usulan awal; menunggu persetujuan Wadir 2."
        );

        $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'disetujui', $validated['catatan_alasan'] ?? null);

        return redirect()->back()->with(
            'success',
            "Persetujuan PPK untuk surat {$permohonan->nomor_surat} berhasil dicatat. Menunggu persetujuan Wadir II."
        );
    }

    /**
     * Menampilkan antrean permohonan dan daftar MAK pada antarmuka Bagian Perencanaan.
     *
     * Tujuan: memisahkan antrean berkas yang belum ber-MAK dari daftar berkas yang sudah
     * memiliki alokasi MAK agar staf Perencanaan mudah melacak usulan baru maupun hasil pecahan.
     *
     * @return View Halaman `verifikasi.perencanaan` beserta antrean, daftar MAK, dan akun PPK aktif.
     *
     * Contoh pemanggilan:
     * `Route::get('/verifikasi/perencanaan', [VerifikasiParalelController::class, 'verifikasiPerencanaan']);`
     */
    public function verifikasiPerencanaan(): View
    {
        // pisahkan antrean usulan yang belum memperoleh alokasi MAK atau masih dalam tahap pemeriksaan anggaran.
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal', 'verifikasiParalel.user', 'anggaranMak'])
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_SELESAI,
                'digantikan_oleh_revisi',
            ])
            ->where(function ($q) {
                $q->whereNull('kode_mak')
                    ->orWhere('kode_mak', '')
                    ->orWhereIn('status_permohonan', [
                        PermohonanPengadaan::STATUS_DIAJUKAN,
                        PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                        PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                    ]);
            })
            ->latest('tanggal_pengajuan')
            ->get();

        // Daftar kedua khususkan bagi usulan yang sudah memiliki kode MAK untuk keperluan audit/monitoring.
        $permohonanSudahMak = PermohonanPengadaan::with(['unit', 'user', 'anggaranMak', 'ppkAwal', 'verifikasiParalel.user'])
            ->whereNotNull('kode_mak')
            ->where('kode_mak', '!=', '')
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DITOLAK,
                'digantikan_oleh_revisi',
            ])
            ->latest('updated_at')
            ->get();

        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::eligibleForPpk()->get();

        return view('verifikasi.perencanaan', compact('permohonanAntrean', 'permohonanSudahMak', 'makList', 'ppkUsers'));
    }

    /**
     * Mendaftarkan rekening MAK DIPA baru melalui antarmuka web oleh Bagian Perencanaan.
     *
     * Tujuan: memvalidasi keunikan `kode_mak`, menyamakan saldo awal `sisa_pagu_mak` dengan
     * pagu anggaran, serta mencatat jejak pembuatan rekening pada AuditLog.
     *
     * @param  Request  $request  Input formulir penambahan pos anggaran MAK baru.
     * @return RedirectResponse Redirect ke halaman perencanaan dengan notifikasi berhasil.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/perencanaan/mak', [VerifikasiParalelController::class, 'storeMakPerencanaan']);`
     */
    public function storeMakPerencanaan(Request $request): RedirectResponse
    {
        if (! Auth::user()?->hasPermission('anggaran.mak_manage')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin menambah pos anggaran baru.');
        }
        $validated = $request->validate([
            'kode_mak' => ['required', 'string', 'max:50', 'unique:anggaran_mak,kode_mak'],
            'uraian_mak' => ['required', 'string', 'max:255'],
            'pagu_anggaran' => ['required', 'numeric', 'min:1'],
            'sumber_dana' => ['required', 'string', 'max:100'],
            'tahun_anggaran' => ['required', 'integer', 'digits:4'],
            'catatan_mak' => ['nullable', 'string'],
        ]);

        // samakan sisa pagu awal dengan pagu anggaran agar saldo siap dialokasikan secara bertahap.
        $validated['sisa_pagu_mak'] = $validated['pagu_anggaran'];
        $validated['tanggal_penetapan'] = now();
        $validated['is_locked'] = false;
        $validated['id_user'] = Auth::id();

        $mak = AnggaranMAK::create($validated);

        AuditLog::catat(
            Auth::id() ?? 1,
            'PENETAPAN_MAK_WEB',
            'anggaran_mak',
            $mak->kode_mak,
            null,
            (string) $mak->pagu_anggaran,
            'Penetapan rekening MAK baru via web oleh Perencanaan: '.$mak->uraian_mak
        );

        return redirect()->route('verifikasi.perencanaan')
            ->with('success', "Pos Anggaran {$mak->kode_mak} ({$mak->uraian_mak}) berhasil ditambahkan ke anggaran kampus.");
    }

    /**
     * Mengunci rekening MAK secara eksklusif oleh Bagian Perencanaan via antarmuka web.
     *
     * Tujuan: menandai rekening MAK sebagai terkunci agar tidak ada alokasi baru selama
     * proses revisi DIPA atau penutupan anggaran berlangsung.
     *
     * @param  AnggaranMAK  $anggaran_mak  Model rekening MAK target penguncian.
     * @return RedirectResponse Redirect kembali ke halaman perencanaan dengan pesan sukses.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/lock', [VerifikasiParalelController::class, 'lockMakWeb']);`
     */
    public function lockMakWeb(AnggaranMAK $anggaran_mak): RedirectResponse
    {
        if (! Auth::user()?->hasPermission('anggaran.mak_lock')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin mengunci pos anggaran.');
        }

        $anggaran_mak->lock();

        AuditLog::catat(
            Auth::id() ?? 1,
            'KUNCI_MAK_PERENCANAAN',
            'anggaran_mak',
            $anggaran_mak->kode_mak,
            'unlocked',
            'locked',
            "Bagian Perencanaan mengunci rekening MAK {$anggaran_mak->kode_mak}."
        );

        return redirect()->route('verifikasi.perencanaan')
            ->with('success', "Pos Anggaran {$anggaran_mak->kode_mak} berhasil dikunci oleh Bagian Perencanaan.");
    }

    /**
     * Membuka kunci rekening MAK secara eksklusif oleh Bagian Perencanaan via antarmuka web.
     *
     * Tujuan: mengembalikan status rekening MAK agar dapat kembali menerima alokasi usulan
     * pengadaan beserta pencatatan log audit.
     *
     * @param  AnggaranMAK  $anggaran_mak  Model rekening MAK yang hendak dibuka kuncinya.
     * @return RedirectResponse Redirect ke halaman verifikasi Perencanaan.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/perencanaan/mak/{anggaran_mak}/unlock', [VerifikasiParalelController::class, 'unlockMakWeb']);`
     */
    public function unlockMakWeb(AnggaranMAK $anggaran_mak): RedirectResponse
    {
        if (! Auth::user()?->hasPermission('anggaran.mak_lock')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin membuka kunci pos anggaran.');
        }

        $anggaran_mak->unlock();

        AuditLog::catat(
            Auth::id() ?? 1,
            'BUKA_KUNCI_MAK_PERENCANAAN',
            'anggaran_mak',
            $anggaran_mak->kode_mak,
            'locked',
            'unlocked',
            "Bagian Perencanaan membuka kunci rekening MAK {$anggaran_mak->kode_mak}."
        );

        return redirect()->route('verifikasi.perencanaan')
            ->with('success', "Kunci Pos Anggaran {$anggaran_mak->kode_mak} berhasil dibuka oleh Bagian Perencanaan.");
    }

    /**
     * Memproses pemeriksaan ketersediaan MAK, pemilihan 1 dari 4 akun PPK, dan pengiriman Memo Ketersediaan MAK.
     *
     * Tujuan: memastikan pengalokasian anggaran tidak melebihi sisa pagu rekening yang aktif
     * atau membypass rekening terkunci, atau menangguhkan usulan bila anggaran belum tersedia.
     *
     * @param  Request  $request  Payload keputusan anggaran, kode MAK, nominal alokasi, dan akun PPK awal.
     * @return RedirectResponse Redirect kembali ke halaman perencanaan dengan status keputusan.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/perencanaan/alokasi', [VerifikasiParalelController::class, 'alokasiMakPerencanaan']);`
     */
    public function alokasiMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('anggaran.mak_alokasi')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin mengalokasikan anggaran dan menetapkan PPK.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'nominal_alokasi' => ['required', 'numeric', 'min:1'],
            'keputusan_anggaran' => ['required', 'in:setuju,tahan'],
            'id_user_ppk_awal' => ['nullable', 'exists:users,id_user'],
            'nomor_memo_ketersediaan_mak' => ['nullable', 'string', 'max:100'],
            'catatan_anggaran' => ['nullable', 'string'],
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $mak = AnggaranMAK::findOrFail($validated['kode_mak']);

        if ($validated['keputusan_anggaran'] === 'tahan') {
            $verifPerencanaan = VerifikasiParalel::firstOrCreate(
                ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
                ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
            );
            $verifPerencanaan->id_user = $user->id_user;
            $verifPerencanaan->status_keputusan = 'pending_anggaran';
            $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'Anggaran belum tersedia di kampus (Ditahan).';
            $verifPerencanaan->tanggal_keputusan = now();
            $verifPerencanaan->save();

            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'pending_anggaran';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
            $permohonan->status_anggaran = 'hold';
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'HOLD_ANGGARAN_PERMOHONAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                'Perencanaan menahan (Hold/Pending) usulan karena anggaran belum tersedia: '.($validated['catatan_anggaran'] ?? 'Anggaran belum siap')
            );

            $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'tahan', $validated['catatan_anggaran'] ?? null);

            return redirect()->route('verifikasi.perencanaan')->with(
                'error',
                "Usulan {$permohonan->nomor_surat} ditahan sementara karena anggaran belum siap."
            );
        }

        // Validasi pemilihan akun PPK yang berwenang untuk menerima penetapan anggaran awal
        $idPpkAwal = $validated['id_user_ppk_awal']
            ?? $permohonan->id_user_ppk_awal
            ?? User::eligibleForPpk()->first()?->id_user;

        if ($idPpkAwal) {
            $ppkTarget = User::find($idPpkAwal);
            if (! $ppkTarget || ! $ppkTarget->isEligiblePpk()) {
                return back()->withErrors(['id_user_ppk_awal' => 'Akun PPK awal yang dipilih harus memiliki hak akses PPK.'])->withInput();
            }
        }

        $nomorMemoMak = $validated['nomor_memo_ketersediaan_mak'] ?? ('MEMO-PRC/MAK/'.date('Ymd').'/'.rand(100, 999));
        $nominalAlokasi = (float) $validated['nominal_alokasi'];

        // Cek jika usulan sebelumnya telah dialokasikan ke pos MAK yang berbeda
        if (! empty($permohonan->kode_mak) && $permohonan->kode_mak !== $mak->kode_mak) {
            $oldMak = AnggaranMAK::find($permohonan->kode_mak);
            $oldAlokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
                ->where('kode_mak', $permohonan->kode_mak)
                ->first();
            if ($oldMak && $oldAlokasi) {
                $oldMak->sisa_pagu_mak = (float) $oldMak->sisa_pagu_mak + (float) $oldAlokasi->nominal_alokasi;
                $oldMak->save();
                $oldAlokasi->delete();
            }
        }

        // Cek alokasi yang sudah ada untuk usulan ini pada kode_mak terkait
        $existingAlokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
            ->where('kode_mak', $mak->kode_mak)
            ->first();
        $nominalLamaAlokasi = $existingAlokasi ? (float) $existingAlokasi->nominal_alokasi : 0;
        $netDeduction = $nominalAlokasi - $nominalLamaAlokasi;

        // Validasi ketersediaan saldo sisa pagu MAK sebelum alokasi
        if ($netDeduction > (float) $mak->sisa_pagu_mak) {
            return redirect()->back()
                ->with('error', 'Nominal alokasi melebihi sisa pos anggaran yang tersedia.');
        }

        // Catat alokasi MAK
        AlokasiMAK::updateOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'kode_mak' => $mak->kode_mak],
            ['nominal_alokasi' => $nominalAlokasi]
        );

        // Sesuaikan sisa pagu MAK jika terdapat perubahan bersih (net deduction)
        if ($netDeduction != 0) {
            $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $netDeduction);
            $mak->save();
        }

        // Perbarui kode_mak dan status anggaran usulan
        $permohonan->kode_mak = $mak->kode_mak;
        $permohonan->status_anggaran = 'allocated';

        // Catat keputusan ketersediaan MAK oleh Perencanaan
        $verifPerencanaan = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifPerencanaan->id_user = $user->id_user;
        $verifPerencanaan->status_keputusan = 'disetujui';
        $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'Pos Anggaran tersedia dan Memo Ketersediaan Anggaran diterbitkan ke Bidang 2 & PPK.';
        $verifPerencanaan->tanggal_keputusan = now();
        $verifPerencanaan->save();

        // Simpan metadata Memo Ketersediaan MAK dan PPK terpilih pada permohonan
        $statusLama = $permohonan->status_permohonan;
        $permohonan->kode_mak = $mak->kode_mak;
        $permohonan->status_anggaran = 'tersedia';
        $permohonan->id_user_ppk_awal = $idPpkAwal;
        $permohonan->nomor_memo_ketersediaan_mak = $nomorMemoMak;
        $permohonan->catatan_memo_ketersediaan_mak = $validated['catatan_anggaran'] ?? 'Pos Anggaran tersedia di anggaran kampus.';

        // Penanganan Verifikasi Wadir 2:
        // Pihak yang sudah menyetujui tetap berstatus 'disetujui'
        $verifWadir = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->first();

        if (! $verifWadir) {
            VerifikasiParalel::create([
                'nomor_surat' => $permohonan->nomor_surat,
                'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
                'id_user' => $user->id_user,
                'status_keputusan' => 'menunggu',
            ]);
        } elseif ($verifWadir->status_keputusan !== 'disetujui') {
            $verifWadir->status_keputusan = 'menunggu';
            $verifWadir->save();
        }

        // Penanganan Verifikasi PPK:
        if ($idPpkAwal) {
            $verifPpk = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
                ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
                ->first();

            // Cek apakah akun PPK diganti oleh Perencanaan
            $isGantiPpk = $verifPpk && ((int) $verifPpk->id_user !== (int) $idPpkAwal);

            if (! $verifPpk) {
                VerifikasiParalel::create([
                    'nomor_surat' => $permohonan->nomor_surat,
                    'role_verifikator' => VerifikasiParalel::ROLE_PPK,
                    'id_user' => $idPpkAwal,
                    'status_keputusan' => 'menunggu',
                ]);
            } elseif ($isGantiPpk) {
                // Akun PPK baru harus berstatus 'menunggu'
                $verifPpk->id_user = $idPpkAwal;
                $verifPpk->status_keputusan = 'menunggu';
                $verifPpk->nomor_memo = null;
                $verifPpk->file_memo = null;
                $verifPpk->catatan_alasan = null;
                $verifPpk->tanggal_keputusan = null;
                $verifPpk->save();
            } elseif ($verifPpk->status_keputusan !== 'disetujui') {
                $verifPpk->id_user = $idPpkAwal;
                $verifPpk->status_keputusan = 'menunggu';
                $verifPpk->save();
            }
        }

        // Evaluasi apakah Wadir 2 DAN PPK terpilih keduanya sudah menyetujui
        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        $ppkAcc = ! $idPpkAwal || VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($wadirAcc && $ppkAcc) {
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'ALOKASI_MAK_DISETUJUI_PENUH',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'disetujui',
                "Pos Anggaran {$mak->kode_mak} tersedia (Memo: {$nomorMemoMak}), Wadir 2 dan PPK telah menyetujui, diteruskan ke Unit Pengadaan."
            );

            $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'setuju', $validated['catatan_anggaran'] ?? null);

            return redirect()->route('verifikasi.perencanaan')->with(
                'success',
                "Pos Anggaran {$mak->kode_mak} berhasil dialokasikan. Wadir 2 dan PPK telah menyetujui, berkas diteruskan ke Unit Pengadaan."
            );
        }

        $permohonan->status = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->save();

        AuditLog::catat(
            $user->id_user,
            'MEMO_KETERSEDIAAN_MAK_KE_WADIR2_PPK',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            "Perencanaan menerbitkan Memo Ketersediaan MAK ({$nomorMemoMak}) pada {$mak->kode_mak} ke verifikator terkait."
        );

        // Memicu notifikasi Memo Ketersediaan MAK ke verifikator yang belum menyetujui
        $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'setuju', $validated['catatan_anggaran'] ?? null);

        $pihakMenunggu = [];
        if (! $wadirAcc) {
            $pihakMenunggu[] = 'Wadir II';
        }
        if (! $ppkAcc) {
            $pihakMenunggu[] = 'PPK terpilih';
        }
        $infoTujuan = implode(' serta ', $pihakMenunggu);

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "Pos Anggaran {$mak->kode_mak} tersedia dan Memo Ketersediaan Anggaran ({$nomorMemoMak}) telah dikirim ke {$infoTujuan}."
        );
    }

    /**
     * Memproses pemecahan (split) permohonan pengadaan berdasarkan pos anggaran MAK.
     *
     * Tujuan: memfasilitasi pemecahan satu surat induk menjadi minimal dua surat pecahan per MAK
     * maupun mode pecahan satuan sembari menjaga total alokasi tidak melebihi pagu usulan induk.
     *
     * @param  Request  $request  Data surat induk, alasan pemecahan, dan rincian item pecahan MAK.
     * @return RedirectResponse Redirect ke halaman perencanaan atau kembali dengan pesan validasi.
     *
     * Contoh pemanggilan:
     * `Route::post('/verifikasi/perencanaan/split-mak', [VerifikasiParalelController::class, 'splitMakPerencanaan']);`
     */
    public function splitMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('permohonan.split_mak')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin memecah usulan berdasarkan pos anggaran.');
        }

        $nomorInduk = $request->input('nomor_surat_induk');

        if ($request->has('items') && is_array($request->input('items'))) {
            $validated = $request->validate([
                'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
                'alasan_pemecahan' => ['required', 'string'],
                'items' => ['required', 'array', 'min:2'],
                'items.*.nomor_surat_pecahan' => ['required', 'string', 'max:100', 'distinct', 'unique:permohonan_pengadaan,nomor_surat'],
                'items.*.judul_pengadaan' => ['required', 'string', 'max:255'],
                'items.*.kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
                'items.*.estimasi_pagu_pecahan' => ['required', 'numeric', 'min:1'],
            ]);

            $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);
            $totalSplit = array_sum(array_column($validated['items'], 'estimasi_pagu_pecahan'));

            // cegah pemecahan yang memekarkan total nilai pagu di atas usulan induk aslinya.
            if ($totalSplit > (float) ($induk->estimasi_total_pagu ?? $induk->anggaran_diajukan)) {
                return back()->with('error', 'Total alokasi pecahan melebihi anggaran usulan induk.')->withInput();
            }

            DB::transaction(function () use ($validated, $induk, $user, $totalSplit) {
                $induk->estimasi_total_pagu = max(0, (float) ($induk->estimasi_total_pagu ?? 0) - $totalSplit);
                $induk->save();

                foreach ($validated['items'] as $item) {
                    $anak = PermohonanPengadaan::create([
                        'nomor_surat' => $item['nomor_surat_pecahan'],
                        'judul_pengadaan' => $item['judul_pengadaan'],
                        'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
                        'file_permohonan' => $induk->file_permohonan,
                        'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        'status_anggaran' => 'pending',
                        'revisi_ke' => 0,
                        'estimasi_total_pagu' => $item['estimasi_pagu_pecahan'],
                        'kode_unit' => $induk->kode_unit,
                        'id_user' => $induk->id_user,
                        'nomor_surat_induk_split' => $induk->nomor_surat,
                        'tanggal_pemecahan' => now(),
                        'alasan_pemecahan' => $validated['alasan_pemecahan'],
                        'kode_mak' => $item['kode_mak'],
                    ]);

                    VerifikasiParalel::create([
                        'id_user' => $anak->id_user,
                        'nomor_surat' => $anak->nomor_surat,
                        'role_verifikator' => 'perencanaan',
                        'status_keputusan' => 'menunggu',
                    ]);

                    VerifikasiParalel::create([
                        'id_user' => $anak->id_user,
                        'nomor_surat' => $anak->nomor_surat,
                        'role_verifikator' => 'wadir_2',
                        'status_keputusan' => 'menunggu',
                    ]);

                    AuditLog::catat(
                        $user->id_user,
                        'SPLIT_PERMOHONAN_MAK',
                        'permohonan_pengadaan',
                        $anak->nomor_surat,
                        $induk->nomor_surat,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        "Pemecahan usulan MAK {$item['kode_mak']}: {$validated['alasan_pemecahan']}"
                    );
                }
            });

            return redirect()->route('verifikasi.perencanaan')
                ->with('success', 'Usulan permohonan berhasil dipecah menjadi '.count($validated['items']).' berkas pecahan baru.');
        }

        if (! $request->filled('nomor_surat_baru') && $nomorInduk) {
            $countChild = PermohonanPengadaan::where('nomor_surat_induk_split', $nomorInduk)->count();
            $request->merge(['nomor_surat_baru' => $nomorInduk.'.'.($countChild + 1)]);
        }
        if (! $request->filled('estimasi_total_pagu') && $request->filled('nominal_split')) {
            $request->merge(['estimasi_total_pagu' => $request->input('nominal_split')]);
        }
        if (! $request->filled('judul_pengadaan') && $nomorInduk) {
            $indukRef = PermohonanPengadaan::find($nomorInduk);
            $judul = ($indukRef?->judul_pengadaan ?? $indukRef?->perihal ?? 'Pecahan Pengadaan').' (Pecahan Pos Anggaran '.$request->input('kode_mak').')';
            $request->merge(['judul_pengadaan' => $judul]);
        }

        $validated = $request->validate([
            'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'judul_pengadaan' => ['required', 'string', 'max:255'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'alasan_pemecahan' => ['required', 'string'],
            'estimasi_total_pagu' => ['required', 'numeric', 'min:1'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);
        $nominalSplit = (float) $validated['estimasi_total_pagu'];
        $paguInduk = (float) ($induk->estimasi_total_pagu ?? $induk->anggaran_diajukan ?? 0);

        if (round($nominalSplit, 2) > round($paguInduk, 2)) {
            return back()->with('error', 'Alokasi pecahan melebihi anggaran usulan induk.')->withInput();
        }

        $anak = DB::transaction(function () use ($validated, $induk, $nominalSplit, $paguInduk, $user) {
            $induk->estimasi_total_pagu = max(0, $paguInduk - $nominalSplit);
            $induk->save();

            $anak = PermohonanPengadaan::create([
                'nomor_surat' => $validated['nomor_surat_baru'],
                'judul_pengadaan' => $validated['judul_pengadaan'],
                'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
                'file_permohonan' => $induk->file_permohonan,
                'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
                'status_anggaran' => 'pending',
                'revisi_ke' => 0,
                'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
                'kode_unit' => $induk->kode_unit,
                'id_user' => $induk->id_user,
                'nomor_surat_induk_split' => $induk->nomor_surat,
                'tanggal_pemecahan' => now(),
                'alasan_pemecahan' => $validated['alasan_pemecahan'],
                'kode_mak' => $validated['kode_mak'],
            ]);

            VerifikasiParalel::create([
                'id_user' => $anak->id_user,
                'nomor_surat' => $anak->nomor_surat,
                'role_verifikator' => 'perencanaan',
                'status_keputusan' => 'menunggu',
            ]);

            VerifikasiParalel::create([
                'id_user' => $anak->id_user,
                'nomor_surat' => $anak->nomor_surat,
                'role_verifikator' => 'wadir_2',
                'status_keputusan' => 'menunggu',
            ]);

            AuditLog::catat(
                $user->id_user,
                'SPLIT_PERMOHONAN_MAK',
                'permohonan_pengadaan',
                $anak->nomor_surat,
                $induk->nomor_surat,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                'Pemecahan usulan permohonan oleh Perencanaan berdasarkan MAK '.$validated['kode_mak'].': '.$validated['alasan_pemecahan']
            );

            return $anak;
        });

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "Permohonan berhasil dipecah menjadi surat {$anak->nomor_surat} dengan pos anggaran {$validated['kode_mak']}."
        );
    }
}
