<?php

namespace App\Http\Controllers;

use App\Models\RiwayatPenugasanPPK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Riwayat Alih Penugasan Pejabat Pembuat Komitmen (RiwayatPenugasanPPKController)
 *
 * Mengelola jejak audit mutasi atau pergantian akun PPK pada paket pengadaan (FR 7.3 & NFR 4):
 * - Mencatat PPK lama, PPK baru, staf pengubah, tanggal pergantian, dan alasan mutasi tugas.
 * - Menjamin transparansi bila terjadi redistribusi beban kerja antar 4 akun PPK di kampus.
 */
class RiwayatPenugasanPPKController extends Controller
{
    /**
     * Menampilkan daftar riwayat mutasi/alih penugasan PPK dengan filter nomor paket.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RiwayatPenugasanPPK::with(['paket', 'ppkLama', 'ppkBaru', 'diubahOleh']);

        // Filter berdasarkan nomor paket pengadaan tertentu
        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        $riwayat = $query->latest('tanggal_penugasan')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $riwayat,
        ]);
    }

    /**
     * Helper status kesiapan pencatatan riwayat penugasan PPK.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to record assignment of PPK',
        ]);
    }

    /**
     * Menyimpan data riwayat pergantian penugasan PPK baru ke database.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input: nomor paket, ID akun PPK baru, dan ID aktor pengubah
        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'ppk_lama_user_id' => ['nullable', 'exists:users,id_user'],
            'ppk_baru_user_id' => ['required', 'exists:users,id_user'],
            'diubah_oleh_user_id' => ['required', 'exists:users,id_user'],
            'alasan_perubahan' => ['nullable', 'string'],
            'tanggal_penugasan' => ['nullable', 'date'],
        ]);

        $validated['tanggal_penugasan'] = $validated['tanggal_penugasan'] ?? now();

        // 2. Simpan entri riwayat ke tabel riwayat_penugasan_ppk
        $riwayat = RiwayatPenugasanPPK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat penugasan PPK berhasil dicatat',
            'data' => $riwayat,
        ], 201);
    }

    /**
     * Menampilkan detail satu entri riwayat pergantian penugasan PPK.
     */
    public function show($id): JsonResponse
    {
        $riwayat = RiwayatPenugasanPPK::with(['paket', 'ppkLama', 'ppkBaru', 'diubahOleh'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $riwayat,
        ]);
    }

    /**
     * Menampilkan data riwayat untuk formulir edit.
     */
    public function edit($id): JsonResponse
    {
        $riwayat = RiwayatPenugasanPPK::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $riwayat,
        ]);
    }

    /**
     * Memperbarui alasan perubahan atau tanggal penugasan pada riwayat.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $riwayat = RiwayatPenugasanPPK::findOrFail($id);

        $validated = $request->validate([
            'alasan_perubahan' => ['nullable', 'string'],
            'tanggal_penugasan' => ['nullable', 'date'],
        ]);

        $riwayat->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat penugasan PPK berhasil diperbarui',
            'data' => $riwayat,
        ]);
    }

    /**
     * Menghapus baris riwayat penugasan dari database.
     */
    public function destroy($id): JsonResponse
    {
        $riwayat = RiwayatPenugasanPPK::findOrFail($id);
        $riwayat->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat penugasan PPK berhasil dihapus',
        ]);
    }
}
