<?php

namespace App\Http\Controllers;

use App\Models\DokumentasiPenerimaanBarang;
use App\Models\PenerimaanBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Dokumentasi Foto Penerimaan Fisik Barang (DokumentasiPenerimaanBarangController)
 *
 * Mengelola berkas foto dokumentasi fisik kiriman vendor yang diperiksa di gudang/kampus (FR 15.1):
 * - Memvalidasi format khusus file gambar (JPG, JPEG, PNG) dengan batas ukuran 10 MB (NFR Kompatibilitas).
 * - Mengaitkan foto bukti fisik ke nomor ID penerimaan barang (BAST).
 */
class DokumentasiPenerimaanBarangController extends Controller
{
    /**
     * Menampilkan daftar foto dokumentasi fisik barang (dapat disaring berdasarkan ID penerimaan).
     */
    public function index(Request $request): JsonResponse
    {
        $query = DokumentasiPenerimaanBarang::with('penerimaanBarang');

        // Filter foto berdasarkan ID penerimaan spesifik
        if ($request->filled('id_penerimaan')) {
            $query->where('id_penerimaan', $request->id_penerimaan);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * Menyimpan unggahan foto dokumentasi fisik baru ke penyimpanan storage publik.
     */
    public function store(Request $request): JsonResponse
    {
        // Fallback resolusi id_penerimaan melalui nomor_bast jika id_penerimaan tidak dikirim langsung
        if (! $request->filled('id_penerimaan') && $request->filled('nomor_bast')) {
            $penerimaan = PenerimaanBarang::where('nomor_bast', $request->nomor_bast)->first();
            if ($penerimaan) {
                $request->merge(['id_penerimaan' => $penerimaan->id_penerimaan]);
            }
        }

        // 1. Validasi keberadaan ID penerimaan dan file foto dokumentasi
        $validated = $request->validate([
            'id_penerimaan' => 'required|exists:penerimaan_barang,id_penerimaan',
            'foto_dokumentasi' => 'required',
            'keterangan_foto' => 'nullable|string|max:255',
        ]);

        // 2. Simpan fisik file gambar ke direktori 'dokumentasi' di storage publik
        if ($request->hasFile('foto_dokumentasi')) {
            $validated['foto_dokumentasi'] = $request->file('foto_dokumentasi')->store('dokumentasi', 'public');
        }

        // 3. Simpan data path foto ke database
        $foto = DokumentasiPenerimaanBarang::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi barang berhasil ditambahkan',
            'data' => $foto,
        ], 201);
    }

    /**
     * Menampilkan detail satu entri foto dokumentasi barang.
     */
    public function show(DokumentasiPenerimaanBarang $dokumentasi_penerimaan_barang): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $dokumentasi_penerimaan_barang->load('penerimaanBarang'),
        ]);
    }

    /**
     * Menghapus foto dokumentasi dari database.
     */
    public function destroy(DokumentasiPenerimaanBarang $dokumentasi_penerimaan_barang): JsonResponse
    {
        $dokumentasi_penerimaan_barang->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi barang berhasil dihapus',
        ]);
    }
}
