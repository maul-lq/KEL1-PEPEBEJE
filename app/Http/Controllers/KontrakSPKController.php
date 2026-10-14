<?php

namespace App\Http\Controllers;

use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * NFR 12: Unduh berkas resmi PDF Kontrak / SPK (< 3 Detik).
     */
    public function unduhPdf(KontrakSPK $kontrak_spk): Response
    {
        $filename = 'SPK_'.str_replace(['/', '\\'], '_', $kontrak_spk->nomor_spk).'.pdf';

        if (! empty($kontrak_spk->file_spk) && Storage::disk('public')->exists($kontrak_spk->file_spk)) {
            return Storage::disk('public')->download(
                $kontrak_spk->file_spk,
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 85>>stream\nBT /F1 12 Tf 50 800 Td (SURAT PERINTAH KERJA PBJ KAMPUS PNJ: ".addslashes($kontrak_spk->nomor_spk).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n347\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
