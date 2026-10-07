<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnggaranMAKController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AnggaranMAK::with('user');

        if ($request->filled('tahun')) {
            $query->where('tahun_anggaran', $request->tahun);
        }

        if ($request->filled('is_locked')) {
            $query->where('is_locked', $request->boolean('is_locked'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_mak', 'like', "%{$search}%")
                    ->orWhere('uraian_mak', 'like', "%{$search}%");
            });
        }

        $anggaran = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $anggaran,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to create Anggaran MAK',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_mak' => ['required', 'string', 'max:100', 'unique:anggaran_mak,kode_mak'],
            'uraian_mak' => ['required', 'string', 'max:255'],
            'pagu_anggaran' => ['required', 'numeric', 'min:0'],
            'sumber_dana' => ['required', 'string', 'max:100'],
            'tahun_anggaran' => ['required', 'integer', 'min:2000', 'max:2100'],
            'is_locked' => ['nullable', 'boolean'],
            'tanggal_kunci' => ['nullable', 'date'],
            'id_user' => ['required', 'exists:users,id_user'],
        ]);

        $validated['is_locked'] = $request->boolean('is_locked', false);

        $anggaran = AnggaranMAK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dibuat',
            'data' => $anggaran,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($kodeMak): JsonResponse
    {
        $anggaran = AnggaranMAK::with(['user', 'paketPengadaan', 'alokasiMak'])->findOrFail($kodeMak);

        return response()->json([
            'status' => 'success',
            'data' => $anggaran,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($kodeMak): JsonResponse
    {
        $anggaran = AnggaranMAK::findOrFail($kodeMak);

        return response()->json([
            'status' => 'success',
            'data' => $anggaran,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $kodeMak): JsonResponse
    {
        $anggaran = AnggaranMAK::findOrFail($kodeMak);

        $validated = $request->validate([
            'uraian_mak' => ['sometimes', 'required', 'string', 'max:255'],
            'pagu_anggaran' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sumber_dana' => ['sometimes', 'required', 'string', 'max:100'],
            'tahun_anggaran' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'is_locked' => ['nullable', 'boolean'],
            'tanggal_kunci' => ['nullable', 'date'],
        ]);

        if ($request->has('is_locked')) {
            $validated['is_locked'] = $request->boolean('is_locked');
        }

        $anggaran->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil diperbarui',
            'data' => $anggaran,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($kodeMak): JsonResponse
    {
        $anggaran = AnggaranMAK::findOrFail($kodeMak);
        $anggaran->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran MAK berhasil dihapus',
        ]);
    }
}
