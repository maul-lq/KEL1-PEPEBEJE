<?php

namespace App\Http\Controllers;

use App\Models\TransaksiPencairan;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransaksiPencairanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TransaksiPencairan::with(['pembayaran.paket.vendor', 'user']);

        if ($request->filled('nomor_pembayaran')) {
            $query->where('nomor_pembayaran', $request->nomor_pembayaran);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_transfer')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_transaksi_bank' => 'required|string|max:100|unique:transaksi_pencairan,nomor_transaksi_bank',
            'nomor_pembayaran' => 'required|exists:pembayaran,nomor_pembayaran',
            'id_user' => 'required|exists:users,id_user',
            'nominal_transfer' => 'required|numeric|min:0',
            'file_bukti_transfer' => ['required', DokumenUploadRule::pdfOrImage()],
            'catatan_keuangan' => 'nullable|string',
        ]);

        if ($request->hasFile('file_bukti_transfer')) {
            $validated['file_bukti_transfer'] = $request->file('file_bukti_transfer')->store('transfer', 'public');
        }

        $validated['tanggal_transfer'] = now();

        $cair = TransaksiPencairan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan dana berhasil diselesaikan dan status pengadaan diubah menjadi Selesai',
            'data' => $cair->load(['pembayaran.paket', 'user']),
        ], 201);
    }

    public function show(TransaksiPencairan $transaksi_pencairan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $transaksi_pencairan->load(['pembayaran.paket.vendor', 'user']),
        ]);
    }

    public function update(Request $request, TransaksiPencairan $transaksi_pencairan): JsonResponse
    {
        $validated = $request->validate([
            'catatan_keuangan' => 'nullable|string',
            'file_bukti_transfer' => 'sometimes|required|string|max:255',
        ]);

        $transaksi_pencairan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data transaksi pencairan berhasil diperbarui',
            'data' => $transaksi_pencairan,
        ]);
    }

    public function destroy(TransaksiPencairan $transaksi_pencairan): JsonResponse
    {
        $transaksi_pencairan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan berhasil dihapus',
        ]);
    }
}
