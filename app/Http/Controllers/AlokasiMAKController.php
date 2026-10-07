<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlokasiMAKController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AlokasiMAK::with(['permohonan', 'anggaranMak']);

        if ($request->filled('nomor_surat')) {
            $query->where('nomor_surat', $request->nomor_surat);
        }

        if ($request->filled('kode_mak')) {
            $query->where('kode_mak', $request->kode_mak);
        }

        $alokasi = $query->latest('tanggal_alokasi')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $alokasi,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to allocate MAK to permohonan',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'nominal_alokasi' => ['required', 'numeric', 'min:0'],
            'tanggal_alokasi' => ['nullable', 'date'],
        ]);

        $exists = AlokasiMAK::where('nomor_surat', $validated['nomor_surat'])
            ->where('kode_mak', $validated['kode_mak'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alokasi MAK ini sudah ada untuk permohonan tersebut',
            ], 422);
        }

        $validated['tanggal_alokasi'] = $validated['tanggal_alokasi'] ?? now();

        $alokasi = AlokasiMAK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Alokasi MAK berhasil disimpan',
            'data' => $alokasi,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $alokasi->load(['permohonan', 'anggaranMak']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $alokasi,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        $validated = $request->validate([
            'nominal_alokasi' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tanggal_alokasi' => ['nullable', 'date'],
        ]);

        AlokasiMAK::where('nomor_surat', $alokasi->nomor_surat)
            ->where('kode_mak', $alokasi->kode_mak)
            ->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Alokasi MAK berhasil diperbarui',
            'data' => $alokasi->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $alokasi = $this->findComposite($request, $id);

        AlokasiMAK::where('nomor_surat', $alokasi->nomor_surat)
            ->where('kode_mak', $alokasi->kode_mak)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Alokasi MAK berhasil dihapus',
        ]);
    }

    /**
     * Helper to resolve composite primary key record.
     */
    protected function findComposite(Request $request, $id): AlokasiMAK
    {
        if ($request->filled('nomor_surat') && $request->filled('kode_mak')) {
            return AlokasiMAK::where('nomor_surat', $request->nomor_surat)
                ->where('kode_mak', $request->kode_mak)
                ->firstOrFail();
        }

        $parts = explode(':', $id, 2);
        if (count($parts) === 2) {
            return AlokasiMAK::where('nomor_surat', $parts[0])
                ->where('kode_mak', $parts[1])
                ->firstOrFail();
        }

        return AlokasiMAK::where('nomor_surat', $id)->firstOrFail();
    }
}
