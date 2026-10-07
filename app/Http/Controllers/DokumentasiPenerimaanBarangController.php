<?php

namespace App\Http\Controllers;

use App\Models\DokumentasiPenerimaanBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokumentasiPenerimaanBarangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DokumentasiPenerimaanBarang::with('penerimaanBarang');

        if ($request->filled('nomor_bast')) {
            $query->where('nomor_bast', $request->nomor_bast);
        }

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        $fotos = $query->latest('uploaded_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $fotos,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to upload documentation photo',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_bast' => ['required', 'string', 'max:100'],
            'nomor_paket' => ['required', 'string', 'max:100'],
            'foto_dokumentasi' => ['required', 'string', 'max:255'],
            'keterangan_foto' => ['nullable', 'string', 'max:255'],
            'uploaded_at' => ['nullable', 'date'],
        ]);

        $validated['uploaded_at'] = $validated['uploaded_at'] ?? now();

        $foto = DokumentasiPenerimaanBarang::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi barang berhasil disimpan',
            'data' => $foto,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $foto = DokumentasiPenerimaanBarang::with('penerimaanBarang')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $foto,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        $foto = DokumentasiPenerimaanBarang::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $foto,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $foto = DokumentasiPenerimaanBarang::findOrFail($id);

        $validated = $request->validate([
            'foto_dokumentasi' => ['sometimes', 'required', 'string', 'max:255'],
            'keterangan_foto' => ['nullable', 'string', 'max:255'],
        ]);

        $foto->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi berhasil diperbarui',
            'data' => $foto,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $foto = DokumentasiPenerimaanBarang::findOrFail($id);
        $foto->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi berhasil dihapus',
        ]);
    }
}
