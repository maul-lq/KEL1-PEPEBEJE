<?php

namespace App\Http\Controllers;

use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KontrakSPKController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KontrakSPK::with(['paket.vendor', 'user']);

        if ($request->filled('status_spk')) {
            $query->where('status_spk', $request->status_spk);
        }

        if ($request->filled('tipe_dokumen')) {
            $query->where('tipe_dokumen', $request->tipe_dokumen);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_spk')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_spk' => 'required|string|max:100|unique:kontrak_spk,nomor_spk',
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'id_user' => 'nullable|exists:users,id_user',
            'tipe_dokumen' => 'required|string|in:spk,surat_kontrak,surat_pesanan',
            'sifat_kontrak' => 'required|string|in:sekali_selesai,lumpsum,harga_satuan,termin_bulanan,kontrak_tahunan',
            'status_spk' => 'required|string|in:draft,terbit,aktif,selesai',
            'tanggal_spk' => 'required|date',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date',
            'jadwal_pengiriman' => 'required|date',
            'file_spk' => ['required', DokumenUploadRule::pdfOnly()],
        ]);

        if ($request->hasFile('file_spk')) {
            $validated['file_spk'] = $request->file('file_spk')->store('spk', 'public');
        }

        $spk = KontrakSPK::create($validated);

        // Update paket pengadaan status
        $paket = PaketPengadaan::find($spk->nomor_paket);
        if ($paket) {
            $paket->status_paket = 'spk_terbit';
            $paket->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak/SPK berhasil diterbitkan dan data MAK terkait telah dikunci permanen',
            'data' => $spk->load(['paket', 'user']),
        ], 201);
    }

    public function show(KontrakSPK $kontrak_spk): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $kontrak_spk->load(['paket.vendor', 'paket.permohonan', 'user']),
        ]);
    }

    public function update(Request $request, KontrakSPK $kontrak_spk): JsonResponse
    {
        $validated = $request->validate([
            'tipe_dokumen' => 'sometimes|required|string',
            'sifat_kontrak' => 'sometimes|required|string',
            'status_spk' => 'sometimes|required|string|in:draft,terbit,aktif,selesai',
            'tanggal_spk' => 'sometimes|required|date',
            'jadwal_pengiriman' => 'sometimes|required|date',
            'nilai_kontrak' => 'sometimes|required|numeric|min:0',
            'file_spk' => 'sometimes|required|string|max:255',
        ]);

        $kontrak_spk->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak/SPK berhasil diperbarui',
            'data' => $kontrak_spk->load('paket'),
        ]);
    }

    public function destroy(KontrakSPK $kontrak_spk): JsonResponse
    {
        $kontrak_spk->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak/SPK berhasil dihapus',
        ]);
    }
}
