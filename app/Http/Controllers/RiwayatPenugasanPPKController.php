<?php

namespace App\Http\Controllers;

use App\Models\RiwayatPenugasanPPK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiwayatPenugasanPPKController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RiwayatPenugasanPPK::with(['paket', 'ppkLama', 'ppkBaru', 'diubahOleh']);

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
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to record assignment of PPK',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'ppk_lama_user_id' => ['nullable', 'exists:users,id_user'],
            'ppk_baru_user_id' => ['required', 'exists:users,id_user'],
            'diubah_oleh_user_id' => ['required', 'exists:users,id_user'],
            'alasan_perubahan' => ['nullable', 'string'],
            'tanggal_penugasan' => ['nullable', 'date'],
        ]);

        $validated['tanggal_penugasan'] = $validated['tanggal_penugasan'] ?? now();

        $riwayat = RiwayatPenugasanPPK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat penugasan PPK berhasil dicatat',
            'data' => $riwayat,
        ], 201);
    }

    /**
     * Display the specified resource.
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
     * Show the form for editing the specified resource.
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
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
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
