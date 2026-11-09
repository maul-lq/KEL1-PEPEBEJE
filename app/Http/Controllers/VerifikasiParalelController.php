<?php

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
 * Controller Verifikasi Berantai & Penetapan MAK (Modul 2 & 3)
 *
 * Mengelola tahapan verifikasi berantai awal sesuai alur BPMN Kampus:
 * 1. Staf Bidang 2: Memeriksa kelengkapan berkas usulan dan menerbitkan Memo Cek MAK ke Perencanaan.
 * 2. Bagian Perencanaan: Mengelola MAK DIPA, alokasi/pemecahan MAK, penunjukan akun PPK (1 dari 4),
 *    serta menerbitkan Memo Ketersediaan MAK ke Wadir 2 & PPK.
 * 3. Wadir 2 & PPK: Memberikan persetujuan berpasangan (Dual Approval). Berkas diteruskan ke PPBJ
 *    hanya setelah kedua belah pihak menyetujui.
 */
class VerifikasiParalelController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar permohonan masuk ke Staf Bidang 2.
     */
    public function verifikasiBidang2(Request $request): View
    {
        $query = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel'])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
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
     * Memproses pemeriksaan berkas Staf Bidang 2 dan menerbitkan Memo Cek MAK ke Bagian Perencanaan.
     */
    public function teruskanStaffB2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_memo_cek_mak' => ['nullable', 'string', 'max:100'],
            'catatan_staff' => ['nullable', 'string'],
            'catatan_memo_cek_mak' => ['nullable', 'string'],
            'file_memo_cek_mak' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $nomorMemo = $validated['nomor_memo_cek_mak'] ?? ('MEMO-B2/CEK-MAK/'.date('Ymd').'/'.rand(100, 999));
        $catatan = $validated['catatan_memo_cek_mak'] ?? $validated['catatan_staff'] ?? 'Berkas KAK dan spesifikasi teknis lengkap, mohon cek ketersediaan MAK DIPA';

        $fileMemoPath = null;
        if ($request->hasFile('file_memo_cek_mak')) {
            $fileMemoPath = $request->file('file_memo_cek_mak')->store('memo_cek_mak', 'public');
        }

        // Perbarui entri verifikasi Staf Bidang 2
        $verifB2 = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifB2->id_user = $user->id_user;
        $verifB2->status_keputusan = 'disetujui';
        $verifB2->catatan_alasan = $catatan;
        $verifB2->nomor_memo = $nomorMemo;
        if ($fileMemoPath) {
            $verifB2->file_memo = $fileMemoPath;
        }
        $verifB2->tanggal_keputusan = now();
        $verifB2->save();

        // Simpan Memo Cek MAK pada permohonan dan arahkan ke Perencanaan
        $statusLama = $permohonan->status_permohonan;
        $permohonan->nomor_memo_cek_mak = $nomorMemo;
        $permohonan->catatan_memo_cek_mak = $catatan;
        if ($fileMemoPath) {
            $permohonan->file_memo_cek_mak = $fileMemoPath;
        }
        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN;
        $permohonan->status = PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN;
        $permohonan->save();

        // Aktifkan antrean verifikasi bagi Bagian Perencanaan
        VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );

        AuditLog::catat(
            $user->id_user,
            'MEMO_CEK_MAK_B2_KE_PERENCANAAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            "Staff Bidang 2 mengirim Memo Cek MAK ({$nomorMemo}) ke Perencanaan: {$catatan}"
        );

        // Memicu notifikasi ke Perencanaan untuk memeriksa/membuat MAK
        $this->notificationService->notifyMemoCekMak($permohonan, $nomorMemo, $catatan);

        return redirect()->route('verifikasi.bidang-2')->with(
            'success',
            "Memo Cek MAK ({$nomorMemo}) untuk surat {$permohonan->nomor_surat} berhasil dikirimkan ke Bagian Perencanaan."
        );
    }

    /**
     * Menampilkan antrean keputusan persetujuan kegiatan Wakil Direktur II (ACC Kegiatan / Tolak).
     */
    public function verifikasiWadir2(): View
    {
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal', 'verifikasiParalel'])
            ->whereIn('status_permohonan', [
                'diajukan',
                PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
            ])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.wadir_2', compact('permohonanAntrean'));
    }

    /**
     * Memproses keputusan Wadir 2 (ACC Kegiatan / Tolak / Revisi).
     */
    public function keputusanWadir2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'keputusan' => ['required', 'in:disetujui,ditolak,revisi'],
            'catatan_alasan' => ['required_if:keputusan,ditolak,revisi', 'nullable', 'string'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'file_memo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan/revisi wajib diisi saat Wadir 2 menolak atau meminta revisi usulan (FR 5.3).',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        if (! in_array($permohonan->status_permohonan, [
            PermohonanPengadaan::STATUS_DIAJUKAN,
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
        ], true)) {
            return redirect()->route('verifikasi.wadir-2')
                ->with('error', 'Permohonan yang telah disetujui atau diproses lanjut tidak dapat diubah keputusannya.');
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
                'Persetujuan Wadir 2 berhasil dicatat. Wadir 2 dan PPK telah menyetujui lengkap, usulan diteruskan ke PPBJ.'
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
            'Persetujuan kegiatan (ACC) Wadir 2 telah dicatat. Menunggu persetujuan PPK terpilih / Perencanaan sebelum lanjut ke PPBJ.'
        );
    }

    /**
     * Menampilkan antrean persetujuan awal usulan pengadaan bagi akun PPK terpilih (Isolasi 4 Akun PPK).
     */
    public function verifikasiPpk(): View
    {
        $user = Auth::user();

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
     */
    public function keputusanPpkAwal(Request $request): RedirectResponse
    {
        $user = Auth::user();

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

        // Penegakan isolasi ketat 4 akun PPK pada persetujuan awal
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
                "Persetujuan PPK dicatat. Wadir 2 dan PPK telah menyetujui lengkap, berkas {$permohonan->nomor_surat} diteruskan ke PPBJ."
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
            "PPK ({$user->nama}) menyetujui usulan awal; menunggu ACC kegiatan Wadir 2."
        );

        $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'disetujui', $validated['catatan_alasan'] ?? null);

        return redirect()->back()->with(
            'success',
            "Persetujuan awal PPK untuk surat {$permohonan->nomor_surat} berhasil dicatat. Menunggu ACC kegiatan oleh Wadir II."
        );
    }

    /**
     * Menampilkan antrean permohonan dan daftar MAK pada antarmuka Bagian Perencanaan.
     */
    public function verifikasiPerencanaan(): View
    {
        // 1. Antrean usulan yang menunggu penetapan alokasi MAK DIPA
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

        // 2. Daftar usulan yang telah memiliki MAK DIPA
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
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('verifikasi.perencanaan', compact('permohonanAntrean', 'permohonanSudahMak', 'makList', 'ppkUsers'));
    }

    /**
     * Mendaftarkan rekening MAK DIPA baru melalui antarmuka web oleh Bagian Perencanaan (FR 6.1).
     */
    public function storeMakPerencanaan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_mak' => ['required', 'string', 'max:50', 'unique:anggaran_mak,kode_mak'],
            'uraian_mak' => ['required', 'string', 'max:255'],
            'pagu_anggaran' => ['required', 'numeric', 'min:1'],
            'sumber_dana' => ['required', 'string', 'max:100'],
            'tahun_anggaran' => ['required', 'integer', 'digits:4'],
            'catatan_mak' => ['nullable', 'string'],
        ]);

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
            ->with('success', "Rekening MAK {$mak->kode_mak} ({$mak->uraian_mak}) berhasil ditambahkan ke DIPA kampus.");
    }

    /**
     * Mengunci rekening MAK secara eksklusif oleh Bagian Perencanaan via antarmuka web.
     */
    public function lockMakWeb(AnggaranMAK $anggaran_mak): RedirectResponse
    {
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
            ->with('success', "Rekening MAK {$anggaran_mak->kode_mak} berhasil dikunci oleh Bagian Perencanaan.");
    }

    /**
     * Membuka kunci rekening MAK secara eksklusif oleh Bagian Perencanaan via antarmuka web.
     */
    public function unlockMakWeb(AnggaranMAK $anggaran_mak): RedirectResponse
    {
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
            ->with('success', "Kunci rekening MAK {$anggaran_mak->kode_mak} berhasil dibuka oleh Bagian Perencanaan.");
    }

    /**
     * Memproses pemeriksaan ketersediaan MAK, pemilihan 1 dari 4 akun PPK, dan pengiriman Memo Ketersediaan MAK.
     */
    public function alokasiMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

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
            $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'Pagu anggaran belum tersedia di DIPA kampus (Hold).';
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
                'Perencanaan menahan (Hold/Pending) usulan karena anggaran belum tersedia (FR 6.2): '.($validated['catatan_anggaran'] ?? 'Anggaran belum siap')
            );

            $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'tahan', $validated['catatan_anggaran'] ?? null);

            return redirect()->route('verifikasi.perencanaan')->with(
                'error',
                "Usulan {$permohonan->nomor_surat} ditandai pending anggaran (Hold) oleh Bagian Perencanaan (FR 6.2)."
            );
        }

        // Validasi pemilihan 1 dari 4 akun PPK untuk menerima Memo Ketersediaan MAK
        $idPpkAwal = $validated['id_user_ppk_awal']
            ?? $permohonan->id_user_ppk_awal
            ?? User::where('role', 'ppk')->where('status_aktif', true)->first()?->id_user;

        if ($idPpkAwal) {
            $ppkTarget = User::find($idPpkAwal);
            if (! $ppkTarget || $ppkTarget->role !== 'ppk') {
                return back()->withErrors(['id_user_ppk_awal' => 'Akun PPK awal yang dipilih harus memiliki peran PPK.'])->withInput();
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
                ->with('error', 'Nominal alokasi melebihi sisa pagu MAK yang tersedia.');
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
        $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'MAK tersedia di DIPA dan Memo Ketersediaan MAK diterbitkan ke Bidang 2 & PPK.';
        $verifPerencanaan->tanggal_keputusan = now();
        $verifPerencanaan->save();

        // Simpan metadata Memo Ketersediaan MAK dan PPK terpilih pada permohonan
        $statusLama = $permohonan->status_permohonan;
        $permohonan->kode_mak = $mak->kode_mak;
        $permohonan->status_anggaran = 'tersedia';
        $permohonan->id_user_ppk_awal = $idPpkAwal;
        $permohonan->nomor_memo_ketersediaan_mak = $nomorMemoMak;
        $permohonan->catatan_memo_ketersediaan_mak = $validated['catatan_anggaran'] ?? 'MAK tersedia di DIPA kampus.';

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
                "MAK {$mak->kode_mak} tersedia (Memo: {$nomorMemoMak}), Wadir 2 dan PPK telah ACC, diteruskan ke PPBJ."
            );

            $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'setuju', $validated['catatan_anggaran'] ?? null);

            return redirect()->route('verifikasi.perencanaan')->with(
                'success',
                "MAK {$mak->kode_mak} berhasil dialokasikan. Wadir 2 dan PPK telah menyetujui, berkas diteruskan ke PPBJ."
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
            "MAK {$mak->kode_mak} tersedia dan Memo Ketersediaan MAK ({$nomorMemoMak}) telah dikirimkan ke {$infoTujuan} untuk disetujui."
        );
    }

    /**
     * Memproses pemecahan (split) permohonan pengadaan berdasarkan pos anggaran MAK (FR 6.3).
     */
    public function splitMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();
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

            if ($totalSplit > (float) ($induk->estimasi_total_pagu ?? $induk->anggaran_diajukan)) {
                return back()->with('error', 'Total alokasi pagu pecahan melebihi pagu usulan induk.')->withInput();
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
                        "Pemecahan usulan MAK {$item['kode_mak']} (FR 6.3): {$validated['alasan_pemecahan']}"
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
            $judul = ($indukRef?->judul_pengadaan ?? $indukRef?->perihal ?? 'Pecahan Pengadaan').' (Pecahan MAK '.$request->input('kode_mak').')';
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
            return back()->with('error', 'Alokasi pagu pecahan melebihi pagu usulan induk.')->withInput();
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
                'Pemecahan usulan permohonan oleh Perencanaan berdasarkan MAK '.$validated['kode_mak'].' (FR 6.3 & NFR 9): '.$validated['alasan_pemecahan']
            );

            return $anak;
        });

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "Permohonan berhasil dipecah menjadi surat baru {$anak->nomor_surat} dengan alokasi MAK {$validated['kode_mak']} (FR 6.3)."
        );
    }
}
