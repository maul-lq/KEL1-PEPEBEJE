<?php

namespace App\Http\Controllers;

use App\Models\DokumentasiPenerimaanBarang;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokumentasiPenerimaanBarangController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DokumentasiPenerimaanBarang::with('penerimaanBarang');

        if ($request->filled('id_penerimaan')) {
            $query->where('id_penerimaan', $request->id_penerimaan);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_penerimaan' => 'required|exists:penerimaan_barang,id_penerimaan',
            'foto_dokumentasi' => ['required', DokumenUploadRule::imageOnly()],
            'keterangan_foto' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('foto_dokumentasi')) {
            $validated['foto_dokumentasi'] = $request->file('foto_dokumentasi')->store('dokumentasi', 'public');
        }

        $foto = DokumentasiPenerimaanBarang::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi barang berhasil ditambahkan',
            'data' => $foto,
        ], 201);
    }

    public function show(DokumentasiPenerimaanBarang $dokumentasi_penerimaan_barang): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $dokumentasi_penerimaan_barang->load('penerimaanBarang'),
        ]);
    }

    public function destroy(DokumentasiPenerimaanBarang $dokumentasi_penerimaan_barang): JsonResponse
    {
        $dokumentasi_penerimaan_barang->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Foto dokumentasi barang berhasil dihapus',
        ]);
    }
}
