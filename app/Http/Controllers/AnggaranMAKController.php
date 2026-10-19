<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Anggaran Mata Anggaran Kas (AnggaranMAKController)
 *
 * Mengelola master data DIPA anggaran kampus di bawah Bagian Perencanaan:
 * - Pencatatan pagu anggaran DIPA per kode MAK dan tahun anggaran (FR 6.1).
 * - Penegakan immutabilitas pagu anggaran MAK yang telah dikunci (is_locked == true) (NFR Integritas MAK).
 * - Fitur penguncian manual maupun otomatis (saat SPK terbit) untuk mencegah manipulasi anggaran.
 */
class AnggaranMAKController extends Controller
{
    /**
     * Menampilkan daftar mata anggaran kas (MAK) dengan filter tahun anggaran, sumber dana, dan status kunci.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AnggaranMAK::with('user');

        // Filter berdasarkan tahun anggaran (misal: 2026)
        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', $request->tahun_anggaran);
        }

        // Filter berdasarkan sumber dana (misal: 'BOPTN', 'PNBP', 'Rupiah Murni')
        if ($request->filled('sumber_dana')) {
            $query->where('sumber_dana', $request->sumber_dana);
        }

        // Filter status terkunci (true/false)
        if ($request->filled('is_locked')) {
            $query->where('is_locked', filter_var($request->is_locked, FILTER_VALIDATE_BOOLEAN));
        }

        // Pencarian berdasarkan kode MAK atau uraian MAK
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_mak', 'like', "%{$search}%")
                    ->orWhere('uraian_mak', 'like', "%{$search}%");
            });
        }

        $anggaran = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $anggaran,
        ]);
    }

    /**
     * Mendaftarkan kode MAK dan pagu anggaran baru ke dalam DIPA kampus.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi keunikan kode MAK, pagu positif, dan tahun 4 digit
        $validated = $request->validate([
            'kode_mak' => 'required|string|max:50|unique:anggaran_mak,kode_mak',
            'uraian_mak' => 'required|string|max:255',
            'pagu_anggaran' => 'required|numeric|min:0',
            'sumber_dana' => 'required|string|max:100',
            'tahun_anggaran' => 'required|integer|digits:4',
            'catatan_mak' => 'nullable|string',
            'id_user' => 'nullable|exists:users,id_user',
        ]);

        // Sisa pagu awal bernilai sama dengan pagu anggaran yang baru ditetapkan
        $validated['sisa_pagu_mak'] = $validated['pagu_anggaran'];
        $validated['tanggal_penetapan'] = now();
        $validated['is_locked'] = false;

        // 2. Simpan MAK baru ke tabel anggaran_mak
        $anggaran = AnggaranMAK::create($validated);

        // Rekam jejak penetapan MAK ke audit log (FR 6.1 & NFR 9)
        AuditLog::catat(
            $anggaran->id_user ?? $request->user()?->id_user ?? 1,
            'PENETAPAN_MAK',
            'anggaran_mak',
            $anggaran->kode_mak,
            null,
            (string) $anggaran->pagu_anggaran,
            'Penetapan mata anggaran kas baru (FR 6.1): '.$anggaran->uraian_mak
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dibuat',
            'data' => $anggaran->load('user'),
        ], 201);
    }

    /**
     * Menampilkan detail lengkap satu mata anggaran kas (MAK) beserta riwayat alokasi permohonan.
     */
    public function show(AnggaranMAK $anggaran_mak): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $anggaran_mak->load(['user', 'permohonanPengadaan', 'alokasiMak']),
        ]);
    }

    /**
     * Memperbarui rincian atau pagu MAK.
     * Menerapkan NFR Integritas Data MAK: Menolak perubahan jika MAK sudah berstatus terkunci (is_locked == true).
     */
    public function update(Request $request, AnggaranMAK $anggaran_mak): JsonResponse
    {
        // 1. Periksa apakah MAK dalam kondisi terkunci karena SPK sudah terbit
        if ($anggaran_mak->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anggaran MAK telah dikunci permanen karena SPK telah terbit dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'uraian_mak' => 'sometimes|required|string|max:255',
            'pagu_anggaran' => 'sometimes|required|numeric|min:0',
            'sisa_pagu_mak' => 'sometimes|required|numeric|min:0',
            'sumber_dana' => 'sometimes|required|string|max:100',
            'tahun_anggaran' => 'sometimes|required|integer|digits:4',
            'catatan_mak' => 'nullable|string',
            'id_user' => 'nullable|exists:users,id_user',
        ]);

        $nilaiLama = (string) $anggaran_mak->pagu_anggaran;

        try {
            // 2. Eksekusi pembaruan (model booted akan memverifikasi immutabilitas)
            $anggaran_mak->update($validated);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        // Rekam jejak perubahan MAK ke audit log sebelum SPK terbit (FR 6.4 & NFR 9)
        AuditLog::catat(
            $request->user()?->id_user ?? $anggaran_mak->id_user ?? 1,
            'PERUBAHAN_MAK',
            'anggaran_mak',
            $anggaran_mak->kode_mak,
            $nilaiLama,
            (string) $anggaran_mak->pagu_anggaran,
            'Pembaruan rincian/pagu MAK sebelum SPK terbit (FR 6.4 & NFR 9): '.($validated['catatan_mak'] ?? 'Penyesuaian MAK')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil diperbarui',
            'data' => $anggaran_mak->load('user'),
        ]);
    }

    /**
     * Mengunci pagu anggaran MAK secara manual oleh Bagian Perencanaan.
     */
    public function lock(AnggaranMAK $anggaran_mak): JsonResponse
    {
        $anggaran_mak->lock();

        // Rekam aksi penguncian ke audit log (NFR 6 & NFR 9)
        AuditLog::catat(
            request()->user()?->id_user ?? $anggaran_mak->id_user ?? 1,
            'KUNCI_MAK_PERMANEN',
            'anggaran_mak',
            $anggaran_mak->kode_mak,
            'unlocked',
            'locked',
            'Penguncian permanen anggaran MAK (NFR 6 & FR 11.2)'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dikunci permanen',
            'data' => $anggaran_mak,
        ]);
    }

    /**
     * Menetapkan status Hold / Pending Anggaran pada permohonan pengadaan jika pagu belum tersedia (FR 6.2).
     */
    public function holdAnggaran(Request $request): JsonResponse
    {
        if (! $request->filled('catatan_alasan') && $request->filled('catatan')) {
            $request->merge(['catatan_alasan' => $request->input('catatan')]);
        }

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'catatan_alasan' => 'required|string',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $statusLama = $permohonan->status_permohonan;

        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
        $permohonan->status_anggaran = 'hold';
        $permohonan->save();

        AuditLog::catat(
            $request->user()?->id_user ?? 1,
            'HOLD_ANGGARAN_MAK',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
            'Permohonan ditahan (Hold/Pending) oleh Perencanaan karena anggaran belum siap (FR 6.2): '.$validated['catatan_alasan']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status usulan permohonan berhasil ditetapkan menjadi Pending Anggaran (Hold) (FR 6.2)',
            'data' => $permohonan,
        ]);
    }

    /**
     * Menghapus mata anggaran kas (dilarang jika sudah berstatus terkunci).
     */
    public function destroy(AnggaranMAK $anggaran_mak): JsonResponse
    {
        // Tolak penghapusan jika anggaran telah dikunci
        if ($anggaran_mak->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anggaran MAK yang telah dikunci tidak dapat dihapus.',
            ], 422);
        }

        $anggaran_mak->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dihapus',
        ]);
    }
}
