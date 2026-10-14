<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Master Data Unit Kerja & Jurusan (UnitController)
 *
 * Mengelola master data struktural di lingkungan Politeknik Negeri Jakarta:
 * - 12 Jurusan dan unit-unit pendukung (misal: TIK, Mesin, Sipil, Akuntansi, Perpustakaan, BMN).
 * - Menjadi referensi kode_unit pada data pengguna (User) dan usulan pengadaan (PermohonanPengadaan).
 */
class UnitController extends Controller
{
    /**
     * Menampilkan seluruh daftar unit kerja kampus beserta jumlah pengguna dan usulan pengadaannya.
     */
    public function index(): JsonResponse
    {
        // Ambil unit kerja dan hitung jumlah relasi users dan permohonan secara agregat
        $units = Unit::withCount(['users', 'permohonanPengadaan'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $units,
        ]);
    }

    /**
     * Menyimpan data unit kerja atau jurusan baru ke sistem.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi keunikan kode_unit dan kelengkapan nama unit
        $validated = $request->validate([
            'kode_unit' => 'required|string|max:20|unique:units,kode_unit',
            'nama_unit' => 'required|string|max:150',
            'jenis_unit' => 'required|string|max:50',
        ]);

        // 2. Simpan record unit ke tabel units
        $unit = Unit::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil ditambahkan',
            'data' => $unit,
        ], 201);
    }

    /**
     * Menampilkan detail satu unit kerja beserta daftar pegawai dan permohonan usulannya.
     */
    public function show(string $kode_unit): JsonResponse
    {
        $unit = Unit::with(['users', 'permohonanPengadaan'])->findOrFail($kode_unit);

        return response()->json([
            'status' => 'success',
            'data' => $unit,
        ]);
    }

    /**
     * Memperbarui nama atau klasifikasi jenis unit kerja.
     */
    public function update(Request $request, string $kode_unit): JsonResponse
    {
        $unit = Unit::findOrFail($kode_unit);

        $validated = $request->validate([
            'nama_unit' => 'sometimes|required|string|max:150',
            'jenis_unit' => 'sometimes|required|string|max:50',
        ]);

        $unit->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil diperbarui',
            'data' => $unit,
        ]);
    }

    /**
     * Menghapus unit kerja dari sistem.
     */
    public function destroy(string $kode_unit): JsonResponse
    {
        $unit = Unit::findOrFail($kode_unit);
        $unit->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil dihapus',
        ]);
    }
}
