<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnggaranMAKController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AnggaranMAK::with('user');

        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', $request->tahun_anggaran);
        }

        if ($request->filled('sumber_dana')) {
            $query->where('sumber_dana', $request->sumber_dana);
        }

        if ($request->filled('is_locked')) {
            $query->where('is_locked', filter_var($request->is_locked, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_mak', 'like', "%{$search}%")
                    ->orWhere('uraian_mak', 'like', "%{$search}%");
            });
        }

        $anggaran = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $anggaran,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_mak' => 'required|string|max:50|unique:anggaran_mak,kode_mak',
            'uraian_mak' => 'required|string|max:255',
            'pagu_anggaran' => 'required|numeric|min:0',
            'sumber_dana' => 'required|string|max:100',
            'tahun_anggaran' => 'required|integer|digits:4',
            'catatan_mak' => 'nullable|string',
            'id_user' => 'nullable|exists:users,id_user',
        ]);

        $validated['sisa_pagu_mak'] = $validated['pagu_anggaran'];
        $validated['tanggal_penetapan'] = now();

        $anggaran = AnggaranMAK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dibuat',
            'data' => $anggaran->load('user'),
        ], 201);
    }

    public function show(AnggaranMAK $anggaran_mak): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $anggaran_mak->load(['user', 'permohonanPengadaan', 'alokasiMak']),
        ]);
    }

    public function update(Request $request, AnggaranMAK $anggaran_mak): JsonResponse
    {
        if ($anggaran_mak->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anggaran MAK telah dikunci permanen karena SPK telah terbit dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'uraian_mak' => 'sometimes|required|string|max:255',
            'pagu_anggaran' => 'sometimes|required|numeric|min:0',
            'sisa_pagu_mak' => 'sometimes|required|numeric|min:0',
            'sumber_dana' => 'sometimes|required|string|max:100',
            'tahun_anggaran' => 'sometimes|required|integer|digits:4',
            'catatan_mak' => 'nullable|string',
            'id_user' => 'nullable|exists:users,id_user',
        ]);

        try {
            $anggaran_mak->update($validated);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil diperbarui',
            'data' => $anggaran_mak->load('user'),
        ]);
    }

    public function lock(AnggaranMAK $anggaran_mak): JsonResponse
    {
        $anggaran_mak->lock();

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dikunci permanen',
            'data' => $anggaran_mak,
        ]);
    }

    public function destroy(AnggaranMAK $anggaran_mak): JsonResponse
    {
        if ($anggaran_mak->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anggaran MAK yang telah dikunci tidak dapat dihapus.',
            ], 422);
        }

        $anggaran_mak->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dihapus',
        ]);
    }
}
