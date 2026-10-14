<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Alokasi Pagu MAK ke Permohonan Pengadaan (AlokasiMAKController)
 *
 * Mengelola pemetaan mata anggaran kas (MAK) terhadap surat usulan permohonan yang disetujui:
 * - Menetapkan porsi nominal pagu MAK yang dialokasikan ke permohonan (Tabel relasi many-to-many composite key: nomor_surat + kode_mak).
 * - Mencegah alokasi duplikat pada pasangan nomor surat dan kode MAK yang sama.
 */
class AlokasiMAKController extends Controller
{
    /**
     * Menampilkan daftar pemetaan alokasi MAK dengan filter nomor surat dan kode MAK.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AlokasiMAK::with(['permohonan', 'anggaranMak']);

        // Filter berdasarkan nomor surat permohonan pengadaan
        if ($request->filled('nomor_surat')) {
            $query->where('nomor_surat', $request->nomor_surat);
        }

        // Filter berdasarkan kode MAK anggaran
        if ($request->filled('kode_mak')) {
            $query->where('kode_mak', $request->kode_mak);
        }

        $alokasi = $query->latest('tanggal_alokasi')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $alokasi,
        ]);
    }

    /**
     * Helper status kesiapan alokasi pagu anggaran.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to allocate MAK to permohonan',
        ]);
    }

    /**
     * Menyimpan pemetaan alokasi pagu MAK baru ke usulan permohonan.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi keberadaan nomor surat, kode MAK, dan nominal alokasi non-negatif
        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'nominal_alokasi' => ['required', 'numeric', 'min:0'],
            'tanggal_alokasi' => ['nullable', 'date'],
        ]);

        // 2. Cegah duplikasi alokasi: kombinasi nomor_surat dan kode_mak harus unik
        $exists = AlokasiMAK::where('nomor_surat', $validated['nomor_surat'])
            ->where('kode_mak', $validated['kode_mak'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alokasi MAK ini sudah ada untuk permohonan tersebut',
            ], 422);
        }

        $validated['tanggal_alokasi'] = $validated['tanggal_alokasi'] ?? now();

        // 3. Simpan baris alokasi MAK ke database
        $alokasi = AlokasiMAK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Alokasi MAK berhasil disimpan',
            'data' => $alokasi,
        ], 201);
    }

    /**
     * Menampilkan detail satu pemetaan alokasi MAK berdasarkan composite key.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $alokasi->load(['permohonan', 'anggaranMak']),
        ]);
    }

    /**
     * Menampilkan form edit untuk pemetaan alokasi MAK.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $alokasi,
        ]);
    }

    /**
     * Memperbarui nominal pagu MAK yang dialokasikan ke permohonan.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        $validated = $request->validate([
            'nominal_alokasi' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tanggal_alokasi' => ['nullable', 'date'],
        ]);

        AlokasiMAK::where('nomor_surat', $alokasi->nomor_surat)
            ->where('kode_mak', $alokasi->kode_mak)
            ->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Alokasi MAK berhasil diperbarui',
            'data' => $alokasi->fresh(),
        ]);
    }

    /**
     * Menghapus alokasi MAK dari permohonan pengadaan.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        AlokasiMAK::where('nomor_surat', $alokasi->nomor_surat)
            ->where('kode_mak', $alokasi->kode_mak)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Alokasi MAK berhasil dihapus',
        ]);
    }

    /**
     * Helper internal untuk memecahkan komposit primary key (nomor_surat:kode_mak).
     */
    protected function findComposite(Request $request, $id): AlokasiMAK
    {
        if ($request->filled('nomor_surat') && $request->filled('kode_mak')) {
            return AlokasiMAK::where('nomor_surat', $request->nomor_surat)
                ->where('kode_mak', $request->kode_mak)
                ->firstOrFail();
        }

        $parts = explode(':', $id, 2);
        if (count($parts) === 2) {
            return AlokasiMAK::where('nomor_surat', $parts[0])
                ->where('kode_mak', $parts[1])
                ->firstOrFail();
        }

        return AlokasiMAK::where('nomor_surat', $id)->firstOrFail();
    }
}
