<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermohonanPengadaanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PermohonanPengadaan::with(['user', 'unit', 'anggaranMak', 'indukSplit', 'verifikasiParalel']);

        $user = $request->user();
        if ($user) {
            $query->forUser($user);
        }

        if ($request->filled('status')) {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('status_anggaran')) {
            $query->where('status_anggaran', $request->status_anggaran);
        }

        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('judul_pengadaan', 'like', "%{$search}%");
            });
        }

        $permohonan = $query->latest('tanggal_pengajuan')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $permohonan,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nomor_surat' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => ['required', DokumenUploadRule::pdfOnly()],
            'kode_unit' => 'nullable|string|exists:units,kode_unit',
            'id_user' => 'nullable|exists:users,id_user',
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'kode_mak' => 'nullable|exists:anggaran_mak,kode_mak',
        ]);

        if (empty($validated['id_user'])) {
            $validated['id_user'] = $user?->id_user ?? User::first()->id_user;
        }

        if (empty($validated['kode_unit'])) {
            $validated['kode_unit'] = $user?->kode_unit ?? Unit::first()->kode_unit;
        }

        if ($request->hasFile('file_permohonan')) {
            $validated['file_permohonan'] = $request->file('file_permohonan')->store('permohonan', 'public');
        }

        $validated['status_permohonan'] = PermohonanPengadaan::STATUS_REVIU_PARALEL;
        $validated['status_anggaran'] = 'pending';
        $validated['tanggal_pengajuan'] = now();

        $permohonan = PermohonanPengadaan::create($validated);

        // FR 3.3: Otomatis buat 2 baris verifikasi paralel: Perencanaan & Staff/Wadir 2
        VerifikasiParalel::create([
            'id_user' => $permohonan->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $permohonan->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        AuditLog::catat(
            $permohonan->id_user,
            'PENGAJUAN_PERMOHONAN_BARU',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            null,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'Pengajuan usulan permohonan pengadaan surat Srikandi diteruskan ke verifikasi paralel (FR 3.1 - FR 3.3)'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil diajukan dan diteruskan ke Verifikasi Paralel',
            'data' => $permohonan->load(['unit', 'user', 'verifikasiParalel']),
        ], 201);
    }

    public function show(PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $permohonan_pengadaan->load([
                'user',
                'unit',
                'anggaranMak',
                'indukSplit',
                'anakSplit',
                'suratAsalRevisi',
                'suratHasilRevisi',
                'verifikasiParalel.user',
                'alokasiMak.anggaranMak',
                'paketPengadaan',
            ]),
        ]);
    }

    public function update(Request $request, PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'judul_pengadaan' => 'sometimes|required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'sometimes|required|string',
            'file_permohonan' => 'sometimes|required|string|max:255',
            'status_permohonan' => 'sometimes|required|string',
            'status_anggaran' => 'sometimes|required|string',
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'kode_mak' => 'nullable|exists:anggaran_mak,kode_mak',
        ]);

        $permohonan_pengadaan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil diperbarui',
            'data' => $permohonan_pengadaan->load(['unit', 'user', 'anggaranMak']),
        ]);
    }

    /**
     * FR 3.4: Menerbitkan nomor surat baru jika terjadi revisi/pengajuan ulang permohonan yang ditolak.
     */
    public function revisi(Request $request, PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat_baru' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => ['required', DokumenUploadRule::pdfOnly()],
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'alasan_revisi' => 'required|string',
        ]);

        if ($request->hasFile('file_permohonan')) {
            $validated['file_permohonan'] = $request->file('file_permohonan')->store('permohonan', 'public');
        }

        $user = $request->user();

        // Buat record permohonan baru dengan relasi unary nomor_surat_revisi
        $revisi = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'],
            'file_permohonan' => $validated['file_permohonan'],
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => $permohonan_pengadaan->revisi_ke + 1,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'] ?? $permohonan_pengadaan->estimasi_total_pagu,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $permohonan_pengadaan->kode_unit,
            'id_user' => $user?->id_user ?? $permohonan_pengadaan->id_user,
            'nomor_surat_revisi' => $permohonan_pengadaan->nomor_surat,
            'tanggal_revisi' => now(),
            'alasan_revisi' => $validated['alasan_revisi'],
            'kode_mak' => $permohonan_pengadaan->kode_mak,
        ]);

        // Terbitkan verifikasi paralel baru untuk permohonan revisi
        VerifikasiParalel::create([
            'id_user' => $revisi->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $revisi->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        AuditLog::catat(
            $revisi->id_user,
            'REVISI_PERMOHONAN_DITOLAK',
            'permohonan_pengadaan',
            $revisi->nomor_surat,
            $permohonan_pengadaan->nomor_surat,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'Revisi pengajuan ulang dengan nomor surat baru atas penolakan surat '.$permohonan_pengadaan->nomor_surat.' (FR 3.4)'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan revisi berhasil diajukan dengan nomor surat baru: '.$revisi->nomor_surat,
            'data' => $revisi->load(['unit', 'user', 'suratAsalRevisi', 'verifikasiParalel']),
        ], 201);
    }

    public function split(Request $request, PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat_baru' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'alasan_pemecahan' => 'required|string',
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'kode_mak' => 'nullable|exists:anggaran_mak,kode_mak',
        ]);

        $anak = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $permohonan_pengadaan->tujuan_pengadaan_ringkas,
            'file_permohonan' => $permohonan_pengadaan->file_permohonan,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'] ?? $permohonan_pengadaan->estimasi_total_pagu,
            'kode_unit' => $permohonan_pengadaan->kode_unit,
            'id_user' => $permohonan_pengadaan->id_user,
            'nomor_surat_induk_split' => $permohonan_pengadaan->nomor_surat,
            'tanggal_pemecahan' => now(),
            'alasan_pemecahan' => $validated['alasan_pemecahan'],
            'kode_mak' => $validated['kode_mak'] ?? $permohonan_pengadaan->kode_mak,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil dipecah (split)',
            'data' => $anak,
        ], 201);
    }

    /**
     * NFR 7: Menampilkan posisi berkas secara real-time bagi seluruh role (<5 detik).
     */
    public function tracking(PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $startTime = microtime(true);

        $permohonan_pengadaan->load([
            'unit',
            'user',
            'verifikasiParalel.user',
            'alokasiMak.anggaranMak',
            'paketPengadaan.ppkUser',
            'paketPengadaan.ppUser',
            'paketPengadaan.reviuDokumen.user',
            'paketPengadaan.kontrakSpk',
            'paketPengadaan.penerimaanBarang.fotoDokumentasi',
            'paketPengadaan.pembayaran.transaksiPencairan.user',
        ]);

        $stages = [];

        // Tahap 1: Pengajuan Usulan Srikandi (User)
        $stages[] = [
            'stage_number' => 1,
            'stage_name' => 'Pengajuan Usulan Srikandi',
            'role_in_charge' => 'Unit Pemohon ('.($permohonan_pengadaan->unit ? $permohonan_pengadaan->unit->nama_unit : $permohonan_pengadaan->kode_unit).')',
            'actor' => $permohonan_pengadaan->user ? $permohonan_pengadaan->user->nama : 'Pemohon',
            'status' => 'selesai',
            'timestamp' => $permohonan_pengadaan->tanggal_pengajuan ? $permohonan_pengadaan->tanggal_pengajuan->toIso8601String() : null,
            'details' => [
                'nomor_surat' => $permohonan_pengadaan->nomor_surat,
                'judul_pengadaan' => $permohonan_pengadaan->judul_pengadaan,
                'estimasi_pagu' => (float) $permohonan_pengadaan->estimasi_total_pagu,
                'is_revisi' => $permohonan_pengadaan->isRevisi(),
                'nomor_surat_revisi' => $permohonan_pengadaan->nomor_surat_revisi,
                'is_split' => $permohonan_pengadaan->isSplit(),
            ],
        ];

        // Tahap 2: Verifikasi Paralel (Staff B2, Wadir 2, Perencanaan)
        $verif = $permohonan_pengadaan->verifikasiParalel;
        $verifPerencanaan = $verif->where('role_verifikator', 'perencanaan')->first();
        $verifWadir = $verif->where('role_verifikator', 'wadir_2')->first();
        $statusStage2 = 'menunggu';
        if ($permohonan_pengadaan->status_permohonan === 'disetujui') {
            $statusStage2 = 'selesai';
        } elseif ($permohonan_pengadaan->status_permohonan === 'ditolak') {
            $statusStage2 = 'ditolak';
        } elseif ($permohonan_pengadaan->status_permohonan === 'pending_anggaran') {
            $statusStage2 = 'pending_anggaran';
        } elseif ($verif->isNotEmpty()) {
            $statusStage2 = 'sedang_berjalan';
        }

        $stages[] = [
            'stage_number' => 2,
            'stage_name' => 'Verifikasi Paralel Pimpinan & Perencanaan',
            'role_in_charge' => 'Staff Bidang 2, Wadir 2 & Bagian Perencanaan',
            'actor' => 'Perencanaan & Wadir 2',
            'status' => $statusStage2,
            'timestamp' => $verif->max('tanggal_keputusan')?->toIso8601String() ?? null,
            'details' => [
                'perencanaan' => $verifPerencanaan ? [
                    'status' => $verifPerencanaan->status_keputusan,
                    'catatan' => $verifPerencanaan->catatan_alasan,
                    'tanggal' => $verifPerencanaan->tanggal_keputusan?->toIso8601String(),
                ] : null,
                'wadir_2' => $verifWadir ? [
                    'status' => $verifWadir->status_keputusan,
                    'catatan' => $verifWadir->catatan_alasan,
                    'tanggal' => $verifWadir->tanggal_keputusan?->toIso8601String(),
                ] : null,
            ],
        ];

        // Tahap 3: Alokasi MAK (Perencanaan)
        $alokasi = $permohonan_pengadaan->alokasiMak;
        $mak = $permohonan_pengadaan->anggaranMak;
        $statusStage3 = $alokasi->isNotEmpty() || $mak ? 'selesai' : ($statusStage2 === 'selesai' ? 'sedang_berjalan' : 'menunggu');

        $stages[] = [
            'stage_number' => 3,
            'stage_name' => 'Penetapan & Alokasi Pagu MAK',
            'role_in_charge' => 'Bagian Perencanaan',
            'actor' => $mak?->user?->nama ?? 'Tim Perencanaan',
            'status' => $statusStage3,
            'timestamp' => $mak?->updated_at?->toIso8601String() ?? null,
            'details' => [
                'kode_mak' => $mak?->kode_mak ?? $permohonan_pengadaan->kode_mak,
                'uraian_mak' => $mak?->uraian_mak,
                'is_locked' => (bool) ($mak?->is_locked),
                'total_alokasi_mak' => (float) $alokasi->sum('nominal_alokasi'),
            ],
        ];

        // Tahap 4: Registrasi Paket & Auto-Routing (PPBJ)
        $paket = $permohonan_pengadaan->paketPengadaan->first();
        $statusStage4 = $paket ? 'selesai' : ($statusStage3 === 'selesai' ? 'sedang_berjalan' : 'menunggu');

        $stages[] = [
            'stage_number' => 4,
            'stage_name' => 'Registrasi Paket & Auto-Routing PP/PPK',
            'role_in_charge' => 'Koordinator / Staf PPBJ',
            'actor' => $paket?->ppbjUser?->nama ?? 'Staf PPBJ',
            'status' => $statusStage4,
            'timestamp' => $paket?->tanggal_registrasi_paket?->toIso8601String() ?? null,
            'details' => $paket ? [
                'nomor_paket' => $paket->nomor_paket,
                'nama_paket' => $paket->nama_paket,
                'jalur_routing' => $paket->jalur_routing,
                'nilai_hps' => (float) $paket->nilai_hps,
                'ppk_ditugaskan' => $paket->ppkUser ? $paket->ppkUser->nama : null,
                'pp_ditugaskan' => $paket->ppUser ? $paket->ppUser->nama : null,
            ] : null,
        ];

        // Tahap 5: Reviu Dokumen & HPS (PP / PPK)
        $reviu = $paket?->reviuDokumen?->first();
        $statusStage5 = $reviu ? 'selesai' : ($statusStage4 === 'selesai' ? 'sedang_berjalan' : 'menunggu');

        $stages[] = [
            'stage_number' => 5,
            'stage_name' => 'Reviu Dokumen Pengadaan & HPS',
            'role_in_charge' => $paket && $paket->jalur_routing === 'ppk_diatas_200jt' ? 'Pejabat Pembuat Komitmen (PPK)' : 'Pejabat Pengadaan (PP)',
            'actor' => $reviu?->user?->nama ?? ($paket?->ppkUser?->nama ?? $paket?->ppUser?->nama),
            'status' => $statusStage5,
            'timestamp' => $reviu?->tanggal_reviu?->toIso8601String() ?? null,
            'details' => $reviu ? [
                'nomor_reviu' => $reviu->nomor_reviu,
                'status_reviu' => $reviu->status_reviu,
                'catatan' => $reviu->catatan_reviu,
            ] : null,
        ];

        // Tahap 6: Penerbitan Kontrak & SPK (PP / PPK)
        $spk = $paket?->kontrakSpk;
        $statusStage6 = $spk ? 'selesai' : ($statusStage5 === 'selesai' ? 'sedang_berjalan' : 'menunggu');

        $stages[] = [
            'stage_number' => 6,
            'stage_name' => 'Penerbitan Kontrak / Surat Perintah Kerja (SPK)',
            'role_in_charge' => 'PPK / PP',
            'actor' => $paket?->ppkUser?->nama ?? $paket?->ppUser?->nama,
            'status' => $statusStage6,
            'timestamp' => $spk?->tanggal_spk?->toIso8601String() ?? null,
            'details' => $spk ? [
                'nomor_spk' => $spk->nomor_spk,
                'nilai_kontrak' => (float) $spk->nilai_kontrak,
                'tanggal_mulai' => $spk->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai' => $spk->tanggal_selesai?->format('Y-m-d'),
                'status_spk' => $spk->status_spk,
            ] : null,
        ];

        // Tahap 7: Penerimaan Barang & BAST Digital (Perlengkapan)
        $bast = $paket?->penerimaanBarang?->first();
        $statusStage7 = $bast ? 'selesai' : ($statusStage6 === 'selesai' ? 'sedang_berjalan' : 'menunggu');

        $stages[] = [
            'stage_number' => 7,
            'stage_name' => 'Penerimaan Fisik & BAST Digital',
            'role_in_charge' => 'Unit Perlengkapan & BMN',
            'actor' => $bast?->user?->nama ?? 'Staf Perlengkapan',
            'status' => $statusStage7,
            'timestamp' => $bast?->tanggal_penerimaan?->toIso8601String() ?? null,
            'details' => $bast ? [
                'nomor_bast' => $bast->nomor_bast,
                'status_penerimaan' => $bast->status_penerimaan,
                'jumlah_foto_dokumentasi' => $bast->fotoDokumentasi?->count() ?? 0,
                'ttd_digital_verified' => ! empty($bast->ttd_digital_bast),
            ] : null,
        ];

        // Tahap 8: Pembayaran & Pencairan Dana SP2D (Keuangan)
        $pembayaran = $paket?->pembayaran?->first();
        $cair = $pembayaran?->transaksiPencairan;
        $statusStage8 = $cair ? 'selesai' : ($pembayaran ? 'sedang_berjalan' : 'menunggu');

        $stages[] = [
            'stage_number' => 8,
            'stage_name' => 'Pembayaran SPP & Pencairan Dana SP2D',
            'role_in_charge' => 'Bagian Keuangan',
            'actor' => $cair?->user?->nama ?? 'Bendahara / Staf Keuangan',
            'status' => $statusStage8,
            'timestamp' => $cair?->tanggal_transfer?->toIso8601String() ?? ($pembayaran?->tanggal_pengajuan?->toIso8601String() ?? null),
            'details' => [
                'nomor_spp' => $pembayaran?->nomor_spp,
                'nomor_sp2d' => $pembayaran?->nomor_sp2d,
                'status_pembayaran' => $pembayaran?->status_pembayaran ?? 'belum_diajukan',
                'nomor_transaksi_bank' => $cair?->nomor_transaksi_bank,
                'tanggal_transfer' => $cair?->tanggal_transfer?->toIso8601String(),
                'nominal_transfer' => $cair ? (float) $cair->nominal_transfer : null,
            ],
        ];

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => 'success',
            'response_time_ms' => $responseTime,
            'data' => [
                'nomor_surat' => $permohonan_pengadaan->nomor_surat,
                'judul_pengadaan' => $permohonan_pengadaan->judul_pengadaan,
                'overall_status' => $permohonan_pengadaan->status_permohonan,
                'current_stage' => collect($stages)->where('status', 'selesai')->count(),
                'total_stages' => count($stages),
                'timeline' => $stages,
            ],
        ]);
    }

    /**
     * NFR 7: Monitoring agregat posisi berkas real-time dengan filter.
     */
    public function monitoringStatus(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $query = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel',
            'paketPengadaan.ppkUser',
            'paketPengadaan.ppUser',
            'paketPengadaan.kontrakSpk',
            'paketPengadaan.pembayaran.transaksiPencairan',
        ]);

        $user = $request->user();
        if ($user) {
            $query->forUser($user);
        }

        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        if ($request->filled('status')) {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('judul_pengadaan', 'like', "%{$search}%");
            });
        }

        $items = $query->latest('tanggal_pengajuan')->paginate(20);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => 'success',
            'response_time_ms' => $responseTime,
            'data' => $items,
        ]);
    }

    public function destroy(PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $permohonan_pengadaan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil dihapus',
        ]);
    }
}
