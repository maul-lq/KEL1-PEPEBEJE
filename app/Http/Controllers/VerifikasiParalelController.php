<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\User;
use App\Models\VerifikasiParalel;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Verifikasi Berantai & Persetujuan Awal Usulan Pengadaan (VerifikasiParalelController)
 *
 * Mengelola tahapan persetujuan awal berantai sesuai Notulensi Pertemuan PPBJ:
 * 1. Staf Bidang 2: Memeriksa kelengkapan berkas surat dari User dan mengirim Memo Cek MAK ke Perencanaan.
 * 2. Bagian Perencanaan: Membuat/mengecek ketersediaan MAK DIPA (Tersedia? / Hold), memilih 1 dari 4 akun PPK
 *    (id_user_ppk_awal), dan mengirim Memo Ketersediaan MAK ke Bidang 2 (Wadir 2) & PPK terpilih.
 * 3. Wadir 2 (ACC Kegiatan) & PPK Terpilih (ACC Usulan): Memberikan persetujuan ganda sebelum berkas
 *    otomatis diteruskan menjadi 'disetujui' ke PPBJ.
 */
class VerifikasiParalelController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar entri verifikasi paralel dengan filter nomor surat, peran, dan status keputusan.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VerifikasiParalel::with(['user', 'permohonan.unit']);

        // Filter berdasarkan nomor surat usulan
        if ($request->filled('nomor_surat')) {
            $query->where('nomor_surat', $request->nomor_surat);
        }

        // Filter berdasarkan peran verifikator ('perencanaan', 'staff_bidang_2', 'wadir_2')
        if ($request->filled('role_verifikator')) {
            $query->where('role_verifikator', $request->role_verifikator);
        }

        // Filter berdasarkan status keputusan ('menunggu', 'disetujui', 'ditolak', 'revisi', 'pending_anggaran')
        if ($request->filled('status_keputusan')) {
            $query->where('status_keputusan', $request->status_keputusan);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(15),
        ]);
    }

    /**
     * Menyimpan baris verifikasi paralel baru secara manual (jika diperlukan).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Validasi data payload verifikasi
        $validated = $request->validate([
            'id_user' => 'nullable|exists:users,id_user',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'role_verifikator' => 'required|string|in:perencanaan,staff_bidang_2,wadir_2,ppk',
            'status_keputusan' => 'required|string|in:menunggu,disetujui,ditolak,revisi,pending_anggaran',
            'catatan_alasan' => 'nullable|string',
        ]);

        if (empty($validated['id_user'])) {
            $validated['id_user'] = $user?->id_user ?? User::first()->id_user;
        }

        // 2. Alasan penolakan WAJIB diisi jika verifikator menolak atau meminta revisi
        if (in_array($validated['status_keputusan'], ['ditolak', 'revisi']) && empty($validated['catatan_alasan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi permohonan.',
            ], 422);
        }

        $validated['tanggal_keputusan'] = now();

        // 3. Simpan entri verifikasi paralel ke database
        $verif = VerifikasiParalel::create($validated);

        // 4. Evaluasi kelulusan dual approval paralel
        $this->cekKelulusanParalel($verif->nomor_surat, $validated['id_user']);

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan verifikasi paralel berhasil disimpan',
            'data' => $verif->load(['user', 'permohonan']),
        ], 201);
    }

    /**
     * Menampilkan detail satu entri keputusan verifikasi paralel.
     */
    public function show(VerifikasiParalel $verifikasi_paralel): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $verifikasi_paralel->load(['user', 'permohonan']),
        ]);
    }

    /**
     * Memperbarui status keputusan verifikasi paralel (Disetujui / Ditolak / Revisi / Pending Anggaran).
     * Menerapkan validasi wajib catatan alasan jika keputusan bernilai 'ditolak' atau 'revisi'.
     */
    public function update(Request $request, VerifikasiParalel $verifikasi_paralel): JsonResponse
    {
        $user = $request->user();

        // Tegakkan isolasi akun PPK jika entri verifikasi awal milik role PPK
        if ($verifikasi_paralel->role_verifikator === 'ppk' && $user && $user->role === 'ppk') {
            $permohonanTerkait = PermohonanPengadaan::find($verifikasi_paralel->nomor_surat);
            if ($permohonanTerkait && (int) $permohonanTerkait->id_user_ppk_awal !== (int) $user->id_user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Anda bukan akun PPK yang ditunjuk pada Memo Ketersediaan MAK usulan ini (Isolasi Akun PPK).',
                ], 403);
            }
        }

        // 1. Validasi status dan keharusan catatan alasan saat menolak/meminta revisi
        $validated = $request->validate([
            'status_keputusan' => 'required|string|in:menunggu,disetujui,ditolak,revisi,pending_anggaran',
            'catatan_alasan' => 'required_if:status_keputusan,ditolak,revisi|nullable|string',
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi permohonan.',
        ]);

        if ($user) {
            $verifikasi_paralel->id_user = $user->id_user;
        }

        $validated['tanggal_keputusan'] = now();
        // 2. Simpan pembaruan keputusan verifikator
        $verifikasi_paralel->update($validated);

        // 3. Evaluasi apakah kondisi persetujuan lengkap telah terpenuhi untuk meneruskan status permohonan ke PPBJ
        $this->cekKelulusanParalel($verifikasi_paralel->nomor_surat, $verifikasi_paralel->id_user);

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan verifikasi paralel berhasil diperbarui',
            'data' => $verifikasi_paralel->load(['user', 'permohonan']),
        ]);
    }

    /**
     * Tahap 1 Alur Berantai: Staf Bidang 2 memeriksa kelengkapan berkas dan mengirimkan Memo Cek MAK ke Perencanaan.
     */
    public function memoCekMak(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $request->filled('catatan_memo_cek_mak') && $request->filled('catatan_pemeriksaan')) {
            $request->merge(['catatan_memo_cek_mak' => $request->input('catatan_pemeriksaan')]);
        }

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'nomor_memo_cek_mak' => 'nullable|string|max:100',
            'catatan_memo_cek_mak' => 'nullable|string',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $statusLama = $permohonan->status_permohonan;
        $operatorId = $user?->id_user ?? User::first()->id_user;

        $nomorMemo = $validated['nomor_memo_cek_mak']
            ?? $permohonan->nomor_memo_cek_mak
            ?? ('MEMO-CEK-MAK/'.date('Ymd').'/'.rand(100, 999));
        $catatan = $validated['catatan_memo_cek_mak'] ?? 'Berkas lengkap, mohon pemeriksaan ketersediaan MAK DIPA.';

        $permohonan->nomor_memo_cek_mak = $nomorMemo;
        $permohonan->catatan_memo_cek_mak = $catatan;
        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN;
        $permohonan->save();

        // Catat penyelesaian telaah Staf Bidang 2
        $verifB2 = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'staff_bidang_2'],
            ['id_user' => $operatorId, 'status_keputusan' => 'menunggu']
        );
        $verifB2->id_user = $operatorId;
        $verifB2->status_keputusan = 'disetujui';
        $verifB2->catatan_alasan = $catatan;
        $verifB2->tanggal_keputusan = now();
        $verifB2->save();

        // Aktifkan antrean pemeriksaan MAK di Bagian Perencanaan
        $perencanaanUser = User::where('role', 'perencanaan')->where('status_aktif', true)->first();
        VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'perencanaan'],
            ['id_user' => $perencanaanUser?->id_user ?? $operatorId, 'status_keputusan' => 'menunggu']
        );

        AuditLog::catat(
            $operatorId,
            'MEMO_CEK_MAK_KE_PERENCANAAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            "Bidang 2 menerbitkan Memo Cek MAK ({$nomorMemo}) ke Perencanaan: {$catatan}"
        );

        $this->notificationService->notifyMemoCekMak($permohonan, $nomorMemo, $catatan);

        return response()->json([
            'status' => 'success',
            'message' => 'Memo Cek MAK berhasil dikirimkan dari Bidang 2 ke Bagian Perencanaan',
            'data' => $permohonan->load(['verifikasiParalel']),
        ]);
    }

    /**
     * Alias kompatibilitas endpoint lama penerusan berkas Staf Bidang 2.
     */
    public function teruskanKeWadir(Request $request): JsonResponse
    {
        return $this->memoCekMak($request);
    }

    /**
     * Tahap 2 Alur Berantai: Bagian Perencanaan memeriksa ketersediaan MAK (Tersedia? / Hold / Tidak Tersedia),
     * memilih 1 dari 4 akun PPK (id_user_ppk_awal), dan mengirimkan Memo Ketersediaan MAK ke Bidang 2 (Wadir 2) & PPK.
     */
    public function memoKetersediaanMak(Request $request): JsonResponse
    {
        $user = $request->user();
        $operatorId = $user?->id_user ?? User::first()->id_user;

        if (! $request->filled('keputusan_mak') && $request->filled('keputusan_anggaran')) {
            $request->merge(['keputusan_mak' => $request->input('keputusan_anggaran')]);
        }

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'keputusan_mak' => 'required|string|in:tersedia,tidak_tersedia,hold',
            'kode_mak' => 'required_if:keputusan_mak,tersedia|nullable|exists:anggaran_mak,kode_mak',
            'nominal_alokasi' => 'nullable|numeric|min:0',
            'id_user_ppk_awal' => 'required_if:keputusan_mak,tersedia|nullable|exists:users,id_user',
            'nomor_memo_ketersediaan_mak' => 'nullable|string|max:100',
            'catatan_memo_ketersediaan_mak' => 'required_if:keputusan_mak,tidak_tersedia,hold|nullable|string',
        ], [
            'id_user_ppk_awal.required_if' => 'Perencanaan wajib memilih salah satu dari 4 akun PPK untuk menerima Memo Ketersediaan MAK.',
            'kode_mak.required_if' => 'Kode MAK wajib dipilih apabila anggaran dinyatakan tersedia.',
            'catatan_memo_ketersediaan_mak.required_if' => 'Catatan alasan wajib diisi apabila MAK ditahan (Hold) atau tidak tersedia.',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $statusLama = $permohonan->status_permohonan;

        $verifPerencanaan = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'perencanaan'],
            ['id_user' => $operatorId, 'status_keputusan' => 'menunggu']
        );
        $verifPerencanaan->id_user = $operatorId;
        $verifPerencanaan->tanggal_keputusan = now();

        if (in_array($validated['keputusan_mak'], ['hold', 'tidak_tersedia'], true)) {
            $isHold = $validated['keputusan_mak'] === 'hold';
            $verifPerencanaan->status_keputusan = $isHold ? 'pending_anggaran' : 'ditolak';
            $verifPerencanaan->catatan_alasan = $validated['catatan_memo_ketersediaan_mak'];
            $verifPerencanaan->save();

            $permohonan->status_permohonan = $isHold
                ? PermohonanPengadaan::STATUS_PENDING_ANGGARAN
                : PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->status_anggaran = $isHold ? 'hold' : 'ditolak';
            $permohonan->catatan_memo_ketersediaan_mak = $validated['catatan_memo_ketersediaan_mak'];
            if (! $isHold) {
                $permohonan->alasan_revisi = $validated['catatan_memo_ketersediaan_mak'];
            }
            $permohonan->save();

            AuditLog::catat(
                $operatorId,
                $isHold ? 'HOLD_ANGGARAN_MAK' : 'TOLAK_MAK_TIDAK_TERSEDIA',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                $permohonan->status_permohonan,
                'Keputusan ketersediaan MAK oleh Perencanaan: '.$validated['catatan_memo_ketersediaan_mak']
            );

            return response()->json([
                'status' => 'success',
                'message' => $isHold
                    ? 'Permohonan ditandai Pending Anggaran (Hold) oleh Perencanaan'
                    : 'Permohonan dikembalikan karena MAK tidak tersedia',
                'data' => $permohonan->load(['verifikasiParalel']),
            ]);
        }

        // Validasi bahwa user yang dipilih sebagai PPK awal benar-benar memiliki role 'ppk'
        $ppkAwal = User::find($validated['id_user_ppk_awal']);
        if (! $ppkAwal || $ppkAwal->role !== 'ppk') {
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => [
                    'id_user_ppk_awal' => ['Akun PPK terpilih harus memiliki role PPK aktif (1 dari 4 akun PPK).'],
                ],
            ], 422);
        }

        $mak = AnggaranMAK::findOrFail($validated['kode_mak']);
        $nominalAlokasi = (float) ($validated['nominal_alokasi'] ?? $permohonan->estimasi_total_pagu ?? 0);

        if ($nominalAlokasi > 0) {
            $existingAlokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
                ->where('kode_mak', $mak->kode_mak)
                ->first();
            $nominalLamaAlokasi = $existingAlokasi ? (float) $existingAlokasi->nominal_alokasi : 0;
            $netDeduction = $nominalAlokasi - $nominalLamaAlokasi;

            AlokasiMAK::updateOrCreate(
                ['nomor_surat' => $permohonan->nomor_surat, 'kode_mak' => $mak->kode_mak],
                ['nominal_alokasi' => $nominalAlokasi]
            );

            if ($netDeduction != 0 && (float) $mak->sisa_pagu_mak >= $netDeduction) {
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $netDeduction);
                $mak->save();
            }
        }

        $nomorMemo = $validated['nomor_memo_ketersediaan_mak']
            ?? $permohonan->nomor_memo_ketersediaan_mak
            ?? ('MEMO-MAK/'.date('Ymd').'/'.rand(100, 999));
        $catatan = $validated['catatan_memo_ketersediaan_mak'] ?? "MAK {$mak->kode_mak} tersedia dan siap disetujui Wadir 2 serta PPK.";

        $verifPerencanaan->status_keputusan = 'disetujui';
        $verifPerencanaan->catatan_alasan = $catatan;
        $verifPerencanaan->save();

        $permohonan->kode_mak = $mak->kode_mak;
        $permohonan->id_user_ppk_awal = $ppkAwal->id_user;
        $permohonan->nomor_memo_ketersediaan_mak = $nomorMemo;
        $permohonan->catatan_memo_ketersediaan_mak = $catatan;
        $permohonan->status_anggaran = 'tersedia';
        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->save();

        // Aktifkan antrean persetujuan ganda pada Wadir 2 dan PPK terpilih
        $wadirUser = User::where('role', 'wadir_2')->where('status_aktif', true)->first();
        VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'wadir_2'],
            ['id_user' => $wadirUser?->id_user ?? $operatorId, 'status_keputusan' => 'menunggu']
        );

        $verifPpk = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'ppk'],
            ['id_user' => $ppkAwal->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifPpk->id_user = $ppkAwal->id_user;
        $verifPpk->save();

        // Evaluasi jika sebelumnya Wadir 2 & PPK sudah menyetujui
        $this->cekKelulusanParalel($permohonan->nomor_surat, $operatorId);
        $permohonan->refresh();

        AuditLog::catat(
            $operatorId,
            'MEMO_KETERSEDIAAN_MAK_KE_B2_PPK',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            $permohonan->status_permohonan,
            "Perencanaan memastikan MAK {$mak->kode_mak} tersedia, menunjuk PPK {$ppkAwal->nama}, & mengirim Memo {$nomorMemo} ke Bidang 2 dan PPK"
        );

        $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'setuju', $catatan);

        return response()->json([
            'status' => 'success',
            'message' => 'Memo Ketersediaan MAK berhasil dikirimkan ke Bidang 2 (Wadir 2) dan PPK terpilih',
            'data' => $permohonan->load(['anggaranMak', 'ppkAwal', 'verifikasiParalel']),
        ]);
    }

    /**
     * Tahap 3 Alur Berantai: Persetujuan ganda oleh Wadir 2 (ACC Kegiatan) dan PPK Terpilih (ACC Usulan).
     * Berkas hanya beralih ke 'disetujui' (siap diproses PPBJ) apabila Wadir 2 DAN PPK terpilih sama-sama menyetujui.
     */
    public function keputusanAwal(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'status_keputusan' => 'required|string|in:disetujui,ditolak,revisi',
            'catatan_alasan' => 'required_if:status_keputusan,ditolak,revisi|nullable|string',
            'role_verifikator' => 'nullable|string|in:wadir_2,ppk',
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi usulan.',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $roleVerifikator = ($user && in_array($user->role, ['wadir_2', 'ppk'], true))
            ? $user->role
            : ($validated['role_verifikator'] ?? 'wadir_2');

        // Isolasi 4 akun PPK: Hanya akun PPK yang ditunjuk pada id_user_ppk_awal yang berhak memutus
        if ($roleVerifikator === 'ppk' && $user && $user->role === 'ppk') {
            if ((int) $permohonan->id_user_ppk_awal !== (int) $user->id_user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Anda bukan akun PPK yang ditunjuk pada Memo Ketersediaan MAK usulan ini (Isolasi Akun PPK).',
                ], 403);
            }
        }

        $operatorId = $user?->id_user ?? ($roleVerifikator === 'ppk' ? $permohonan->id_user_ppk_awal : User::first()->id_user);

        $verif = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => $roleVerifikator],
            ['id_user' => $operatorId, 'status_keputusan' => 'menunggu']
        );
        $verif->id_user = $operatorId;
        $verif->status_keputusan = $validated['status_keputusan'];
        $verif->catatan_alasan = $validated['catatan_alasan'] ?? null;
        $verif->tanggal_keputusan = now();
        $verif->save();

        if ($validated['status_keputusan'] === 'ditolak') {
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();
        }

        $this->cekKelulusanParalel($permohonan->nomor_surat, $operatorId);
        $permohonan->refresh();

        if ($roleVerifikator === 'wadir_2') {
            $this->notificationService->notifyKeputusanWadir2($permohonan, $validated['status_keputusan'], $validated['catatan_alasan'] ?? null);
        } else {
            $this->notificationService->notifyKeputusanPpkAwal($permohonan, $validated['status_keputusan'], $validated['catatan_alasan'] ?? null);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan persetujuan awal '.strtoupper($roleVerifikator).' berhasil disimpan',
            'data' => $permohonan->load(['ppkAwal', 'verifikasiParalel']),
        ]);
    }

    /**
     * Logika internal untuk mengevaluasi kelulusan persetujuan awal secara otomatis.
     *
     * Aturan cascade status sesuai Notulensi PPBJ:
     * - Jika salah satu verifikator menolak ('ditolak') => Status permohonan menjadi 'ditolak'.
     * - Jika ada keputusan 'pending_anggaran' => Status permohonan menjadi 'pending_anggaran' (Hold).
     * - Jika Perencanaan, Wadir 2, DAN PPK terpilih (bila id_user_ppk_awal / antrean PPK ada) semuanya 'disetujui'
     *   => Status permohonan menjadi 'disetujui' (otomatis diteruskan ke PPBJ).
     */
    private function cekKelulusanParalel(string $nomor_surat, ?int $operatorId = null): void
    {
        $verifikasi = VerifikasiParalel::where('nomor_surat', $nomor_surat)->get();
        $permohonan = PermohonanPengadaan::find($nomor_surat);

        if (! $permohonan || $verifikasi->isEmpty()) {
            return;
        }

        $statusLama = $permohonan->status_permohonan;

        // KASUS 1: Salah satu verifikator menolak usulan
        if ($verifikasi->contains('status_keputusan', 'ditolak')) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->save();

            AuditLog::catat(
                $operatorId ?? $permohonan->id_user,
                'KEPUTUSAN_VERIFIKASI_DITOLAK',
                'permohonan_pengadaan',
                $nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_DITOLAK,
                'Permohonan ditolak oleh verifikator dalam persetujuan awal'
            );

            return;
        }

        // KASUS 2: Pagu anggaran belum tersedia di DIPA kampus (Hold / Pending Anggaran - FR 6.2)
        if ($verifikasi->contains('status_keputusan', 'pending_anggaran')) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
            $permohonan->status_anggaran = 'hold';
            $permohonan->save();

            AuditLog::catat(
                $operatorId ?? $permohonan->id_user,
                'KEPUTUSAN_VERIFIKASI_HOLD_ANGGARAN',
                'permohonan_pengadaan',
                $nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                'Permohonan ditahan (Hold/Pending) karena anggaran belum tersedia di Perencanaan'
            );

            return;
        }

        // KASUS 3: Evaluasi persetujuan paralel (AND-join): Perencanaan, Wadir 2, dan PPK terpilih wajib disetujui
        $perencanaanOk = $verifikasi->where('role_verifikator', 'perencanaan')->where('status_keputusan', 'disetujui')->isNotEmpty();
        $wadirOk = $verifikasi->where('role_verifikator', 'wadir_2')->where('status_keputusan', 'disetujui')->isNotEmpty();
        $ppkOk = $verifikasi->where('role_verifikator', 'ppk')->where('status_keputusan', 'disetujui')->isNotEmpty();

        if ($perencanaanOk && $wadirOk && $ppkOk) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->status_anggaran = 'disetujui';
            $permohonan->save();

            AuditLog::catat(
                $operatorId ?? $permohonan->id_user,
                'KEPUTUSAN_VERIFIKASI_DISETUJUI_PARALEL',
                'permohonan_pengadaan',
                $nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_DISETUJUI,
                'Permohonan disetujui penuh oleh Perencanaan, Wadir 2, dan PPK serta siap diregistrasi PPBJ'
            );
        }
    }

    /**
     * Menghapus baris verifikasi paralel dari database.
     */
    public function destroy(VerifikasiParalel $verifikasi_paralel): JsonResponse
    {
        $verifikasi_paralel->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi paralel berhasil dihapus',
        ]);
    }
}
