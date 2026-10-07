<?php

namespace App\Http\Controllers;

use App\Models\ReviuPengadaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviuPengadaanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReviuPengadaan::with(['paket', 'user']);

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('status')) {
            $query->where('status_reviu', $request->status);
        }

        $reviu = $query->latest('tanggal_reviu')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $reviu,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'statuses' => [
                ReviuPengadaan::STATUS_DISETUJUI,
                ReviuPengadaan::STATUS_PERLU_PERBAIKAN,
                ReviuPengadaan::STATUS_DITOLAK,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'id_user' => ['required', 'exists:users,id_user'],
            'status_reviu' => ['required', 'string', Rule::in([
                ReviuPengadaan::STATUS_DISETUJUI,
                ReviuPengadaan::STATUS_PERLU_PERBAIKAN,
                ReviuPengadaan::STATUS_DITOLAK,
            ])],
            'catatan_perbaikan' => ['nullable', 'string'],
            'tanggal_reviu' => ['nullable', 'date'],
        ]);

        $validated['tanggal_reviu'] = $validated['tanggal_reviu'] ?? now();

        $reviu = ReviuPengadaan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Reviu pengadaan berhasil disimpan',
            'data' => $reviu,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $reviu = ReviuPengadaan::with(['paket', 'user'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $reviu,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        $reviu = ReviuPengadaan::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $reviu,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $reviu = ReviuPengadaan::findOrFail($id);

        $validated = $request->validate([
            'status_reviu' => ['sometimes', 'required', 'string', Rule::in([
                ReviuPengadaan::STATUS_DISETUJUI,
                ReviuPengadaan::STATUS_PERLU_PERBAIKAN,
                ReviuPengadaan::STATUS_DITOLAK,
            ])],
            'catatan_perbaikan' => ['nullable', 'string'],
            'tanggal_reviu' => ['nullable', 'date'],
        ]);

        $reviu->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Reviu pengadaan berhasil diperbarui',
            'data' => $reviu,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $reviu = ReviuPengadaan::findOrFail($id);
        $reviu->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reviu pengadaan berhasil dihapus',
        ]);
    }
}
