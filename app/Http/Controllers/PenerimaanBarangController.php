<?php

namespace App\Http\Controllers;

use App\Models\DokumentasiPenerimaanBarang;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenerimaanBarangController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PenerimaanBarang::with(['paket', 'user', 'fotoDokumentasi']);

        if ($request->filled('status_fisik')) {
            $query->where('status_fisik', $request->status_fisik);
        }

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_penerimaan')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_bast' => 'nullable|string|max:100|unique:penerimaan_barang,nomor_bast',
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'id_user' => 'required|exists:users,id_user',
            'tanggal_penerimaan' => 'required|date',
            'status_fisik' => 'required|string|in:terima,pending,tidak_sesuai',
            'ttd_digital_bast' => 'nullable|string',
            'file_bast_signed' => 'nullable|string|max:255',
            'file_penerimaan_pihak3' => 'nullable|string|max:255',
            'catatan_pemeriksaan' => 'nullable|string',
            'foto_dokumentasi' => 'nullable|array',
            'foto_dokumentasi.*' => 'string|max:255',
        ]);

        $validated['waktu_selesai_inspeksi'] = now();

        $penerimaan = PenerimaanBarang::create($validated);

        if (! empty($validated['foto_dokumentasi'])) {
            foreach ($validated['foto_dokumentasi'] as $idx => $foto) {
                DokumentasiPenerimaanBarang::create([
                    'id_penerimaan' => $penerimaan->id_penerimaan,
                    'foto_dokumentasi' => $foto,
                    'keterangan_foto' => 'Foto fisik barang #' . ($idx + 1),
                ]);
            }
        }

        // Update status paket pengadaan
        $paket = PaketPengadaan::find($penerimaan->nomor_paket);
        if ($paket) {
            $paket->status_paket = ($penerimaan->status_fisik === 'terima') ? 'penerimaan_selesai' : 'penerimaan_pending';
            $paket->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data penerimaan barang dan BAST berhasil dicatat',
            'data' => $penerimaan->load(['paket', 'user', 'fotoDokumentasi']),
        ], 201);
    }

    public function show(PenerimaanBarang $penerimaan_barang): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $penerimaan_barang->load(['paket.vendor', 'user', 'fotoDokumentasi']),
        ]);
    }

    public function update(Request $request, PenerimaanBarang $penerimaan_barang): JsonResponse
    {
        $validated = $request->validate([
            'status_fisik' => 'sometimes|required|string|in:terima,pending,tidak_sesuai',
            'ttd_digital_bast' => 'nullable|string',
            'file_bast_signed' => 'nullable|string|max:255',
            'file_penerimaan_pihak3' => 'nullable|string|max:255',
            'catatan_pemeriksaan' => 'nullable|string',
        ]);

        $penerimaan_barang->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data penerimaan barang berhasil diperbarui',
            'data' => $penerimaan_barang->load(['paket', 'fotoDokumentasi']),
        ]);
    }

    public function destroy(PenerimaanBarang $penerimaan_barang): JsonResponse
    {
        $penerimaan_barang->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data penerimaan barang berhasil dihapus',
        ]);
    }
}
