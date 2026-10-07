<?php

namespace App\Http\Controllers;

use App\Models\TransaksiPencairan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransaksiPencairanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TransaksiPencairan::with(['pembayaran', 'user']);

        if ($request->filled('nomor_pembayaran')) {
            $query->where('nomor_pembayaran', $request->nomor_pembayaran);
        }

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->id_user);
        }

        $transaksi = $query->latest('tanggal_transfer')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $transaksi,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to record bank transfer transaction',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_transaksi_bank' => ['required', 'string', 'max:100', 'unique:transaksi_pencairan,nomor_transaksi_bank'],
            'nomor_pembayaran' => ['required', 'string', 'max:100'],
            'nomor_paket' => ['required', 'string', 'max:100'],
            'tanggal_transfer' => ['nullable', 'date'],
            'nominal_transfer' => ['required', 'numeric', 'min:0'],
            'file_bukti_transfer' => ['required', 'string', 'max:255'],
            'catatan_keuangan' => ['nullable', 'string'],
            'id_user' => ['required', 'exists:users,id_user'],
        ]);

        $validated['tanggal_transfer'] = $validated['tanggal_transfer'] ?? now();

        $transaksi = TransaksiPencairan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan dana berhasil dicatat',
            'data' => $transaksi,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($nomorTransaksi): JsonResponse
    {
        $transaksi = TransaksiPencairan::with(['pembayaran', 'user'])->findOrFail($nomorTransaksi);

        return response()->json([
            'status' => 'success',
            'data' => $transaksi,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($nomorTransaksi): JsonResponse
    {
        $transaksi = TransaksiPencairan::findOrFail($nomorTransaksi);

        return response()->json([
            'status' => 'success',
            'data' => $transaksi,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $nomorTransaksi): JsonResponse
    {
        $transaksi = TransaksiPencairan::findOrFail($nomorTransaksi);

        $validated = $request->validate([
            'tanggal_transfer' => ['nullable', 'date'],
            'nominal_transfer' => ['sometimes', 'required', 'numeric', 'min:0'],
            'file_bukti_transfer' => ['sometimes', 'required', 'string', 'max:255'],
            'catatan_keuangan' => ['nullable', 'string'],
        ]);

        $transaksi->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan dana berhasil diperbarui',
            'data' => $transaksi,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($nomorTransaksi): JsonResponse
    {
        $transaksi = TransaksiPencairan::findOrFail($nomorTransaksi);
        $transaksi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan dana berhasil dihapus',
        ]);
    }
}
