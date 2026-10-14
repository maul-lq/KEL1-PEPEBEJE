<?php

namespace App\Http\Controllers;

use App\Models\MemoBayar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Disposisi Memo Bayar Bertingkat (MemoBayarController)
 *
 * Mengelola persetujuan bertingkat memo pembayaran tagihan vendor:
 * - Level 1: Persetujuan Pejabat Pembuat Komitmen (PPK) penanggung jawab paket.
 * - Level 2: Persetujuan Pimpinan Eksekutif (Wadir 2).
 * - Menjadi prasyarat mutlak sebelum PPBJ dapat menerbitkan Perintah Pembayaran (FR 16.1 & FR 17.1).
 */
class MemoBayarController extends Controller
{
    /**
     * Menampilkan daftar disposisi memo bayar dengan filter nomor pembayaran, peran verifikator, dan status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemoBayar::with(['user', 'pembayaran.paket']);

        // Filter berdasarkan nomor SPP pembayaran
        if ($request->filled('nomor_pembayaran')) {
            $query->where('nomor_pembayaran', $request->nomor_pembayaran);
        }

        // Filter berdasarkan peran verifikator ('ppk' atau 'wadir_2')
        if ($request->filled('role_approver')) {
            $query->where('role_approver', $request->role_approver);
        }

        // Filter berdasarkan status memo ('menunggu', 'disetujui', 'ditolak')
        if ($request->filled('status_memo')) {
            $query->where('status_memo', $request->status_memo);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * Menyimpan baris memo bayar bertingkat baru (Level 1 atau Level 2).
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi data payload memo bayar
        $validated = $request->validate([
            'id_user' => 'required|exists:users,id_user',
            'nomor_pembayaran' => 'required|exists:pembayaran,nomor_pembayaran',
            'role_approver' => 'required|string|in:ppk,wadir_2',
            'urutan_level' => 'required|integer|in:1,2',
            'status_memo' => 'required|string|in:menunggu,disetujui,ditolak',
            'catatan_memo' => 'nullable|string',
        ]);

        // Catat tanggal persetujuan jika langsung disetujui
        $validated['tanggal_acc'] = ($validated['status_memo'] === 'disetujui') ? now() : null;

        // 2. Simpan memo bayar ke database
        $memo = MemoBayar::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Memo bayar berhasil dicatat',
            'data' => $memo->load(['user', 'pembayaran']),
        ], 201);
    }

    /**
     * Menampilkan detail satu entri memo bayar beserta data pembayaran dan paketnya.
     */
    public function show(MemoBayar $memo_bayar): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $memo_bayar->load(['user', 'pembayaran']),
        ]);
    }

    /**
     * Memperbarui status keputusan memo bayar (Setujui / Tolak oleh PPK atau Wadir 2).
     */
    public function update(Request $request, MemoBayar $memo_bayar): JsonResponse
    {
        // 1. Validasi status memo baru
        $validated = $request->validate([
            'status_memo' => 'required|string|in:menunggu,disetujui,ditolak',
            'catatan_memo' => 'nullable|string',
        ]);

        // Set tanggal acc otomatis saat status diubah menjadi disetujui
        $validated['tanggal_acc'] = ($validated['status_memo'] === 'disetujui') ? now() : null;
        $memo_bayar->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Status memo bayar berhasil diperbarui',
            'data' => $memo_bayar->load(['user', 'pembayaran']),
        ]);
    }

    /**
     * Menghapus baris memo bayar dari database.
     */
    public function destroy(MemoBayar $memo_bayar): JsonResponse
    {
        $memo_bayar->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Memo bayar berhasil dihapus',
        ]);
    }
}
