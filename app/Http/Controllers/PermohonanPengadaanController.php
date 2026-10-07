<?php

namespace App\Http\Controllers;

use App\Models\PermohonanPengadaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermohonanPengadaanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PermohonanPengadaan::with(['user', 'induk']);

        if ($request->filled('status')) {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('asal_unit')) {
            $query->where('asal_unit', $request->asal_unit);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('judul_pengadaan', 'like', "%{$search}%");
            });
        }

        $permohonan = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $permohonan,
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
                PermohonanPengadaan::STATUS_DIAJUKAN,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'judul_pengadaan' => ['required', 'string', 'max:255'],
            'asal_unit' => ['required', 'string', 'max:100'],
            'tujuan_ringkas' => ['required', 'string'],
            'file_pdf_srikandi' => ['required', 'string', 'max:255'],
            'status_permohonan' => ['required', 'string', Rule::in([
                PermohonanPengadaan::STATUS_DIAJUKAN,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ])],
            'no_draft_srikandi' => ['nullable', 'string', 'max:100'],
            'id_user' => ['required', 'exists:users,id_user'],
            'nomor_surat_induk' => ['nullable', 'exists:permohonan_pengadaan,nomor_surat'],
        ]);

        $permohonan = PermohonanPengadaan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil diajukan',
            'data' => $permohonan,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($nomorSurat): JsonResponse
    {
        $permohonan = PermohonanPengadaan::with([
            'user',
            'induk',
            'anakSplit',
            'paketPengadaan',
            'verifikasiParalel',
            'alokasiMak.anggaranMak',
        ])->findOrFail($nomorSurat);

        return response()->json([
            'status' => 'success',
            'data' => $permohonan,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($nomorSurat): JsonResponse
    {
        $permohonan = PermohonanPengadaan::findOrFail($nomorSurat);

        return response()->json([
            'status' => 'success',
            'data' => $permohonan,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $nomorSurat): JsonResponse
    {
        $permohonan = PermohonanPengadaan::findOrFail($nomorSurat);

        $validated = $request->validate([
            'judul_pengadaan' => ['sometimes', 'required', 'string', 'max:255'],
            'asal_unit' => ['sometimes', 'required', 'string', 'max:100'],
            'tujuan_ringkas' => ['sometimes', 'required', 'string'],
            'file_pdf_srikandi' => ['sometimes', 'required', 'string', 'max:255'],
            'status_permohonan' => ['sometimes', 'required', 'string', Rule::in([
                PermohonanPengadaan::STATUS_DIAJUKAN,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ])],
            'no_draft_srikandi' => ['nullable', 'string', 'max:100'],
            'nomor_surat_induk' => ['nullable', 'exists:permohonan_pengadaan,nomor_surat'],
        ]);

        $permohonan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil diperbarui',
            'data' => $permohonan,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($nomorSurat): JsonResponse
    {
        $permohonan = PermohonanPengadaan::findOrFail($nomorSurat);
        $permohonan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil dihapus',
        ]);
    }
}
