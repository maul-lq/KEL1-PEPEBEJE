<?php

namespace App\Http\Controllers;

use App\Models\MemoBayar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoBayarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MemoBayar::with(['user', 'pembayaran.paket']);

        if ($request->filled('nomor_pembayaran')) {
            $query->where('nomor_pembayaran', $request->nomor_pembayaran);
        }

        if ($request->filled('role_approver')) {
            $query->where('role_approver', $request->role_approver);
        }

        if ($request->filled('status_memo')) {
            $query->where('status_memo', $request->status_memo);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => 'required|exists:users,id_user',
            'nomor_pembayaran' => 'required|exists:pembayaran,nomor_pembayaran',
            'role_approver' => 'required|string|in:ppk,wadir_2',
            'urutan_level' => 'required|integer|in:1,2',
            'status_memo' => 'required|string|in:menunggu,disetujui,ditolak',
            'catatan_memo' => 'nullable|string',
        ]);

        $validated['tanggal_acc'] = ($validated['status_memo'] === 'disetujui') ? now() : null;

        $memo = MemoBayar::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Memo bayar berhasil dicatat',
            'data' => $memo->load(['user', 'pembayaran']),
        ], 201);
    }

    public function show(MemoBayar $memo_bayar): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $memo_bayar->load(['user', 'pembayaran']),
        ]);
    }

    public function update(Request $request, MemoBayar $memo_bayar): JsonResponse
    {
        $validated = $request->validate([
            'status_memo' => 'required|string|in:menunggu,disetujui,ditolak',
            'catatan_memo' => 'nullable|string',
        ]);

        $validated['tanggal_acc'] = ($validated['status_memo'] === 'disetujui') ? now() : null;
        $memo_bayar->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Status memo bayar berhasil diperbarui',
            'data' => $memo_bayar->load(['user', 'pembayaran']),
        ]);
    }

    public function destroy(MemoBayar $memo_bayar): JsonResponse
    {
        $memo_bayar->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Memo bayar berhasil dihapus',
        ]);
    }
}
