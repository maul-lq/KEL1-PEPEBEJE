<?php

namespace App\Http\Controllers;

use App\Models\DokumentasiPenerimaanBarang;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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

        // NFR Integritas Data MAK & TTD Digital: Amankan TTD digital dengan token kriptografis
        if (! empty($validated['ttd_digital_bast'])) {
            $validated['ttd_digital_bast'] = PenerimaanBarang::generateSignatureToken(
                $validated['nomor_bast'] ?? 'BAST-'.time(),
                (int) $validated['id_user']
            );
        }

        $penerimaan = PenerimaanBarang::create($validated);

        if (! empty($validated['foto_dokumentasi'])) {
            foreach ($validated['foto_dokumentasi'] as $idx => $foto) {
                DokumentasiPenerimaanBarang::create([
                    'id_penerimaan' => $penerimaan->id_penerimaan,
                    'foto_dokumentasi' => $foto,
                    'keterangan_foto' => 'Foto fisik barang #'.($idx + 1),
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

    /**
     * NFR 12: Unduh berkas resmi PDF Berita Acara Serah Terima (BAST) (< 3 Detik).
     */
    public function unduhPdf(PenerimaanBarang $penerimaan_barang): Response
    {
        $filename = 'BAST_'.str_replace(['/', '\\'], '_', $penerimaan_barang->nomor_bast ?? ('ID_'.$penerimaan_barang->id_penerimaan)).'.pdf';

        if (! empty($penerimaan_barang->file_bast_signed) && Storage::disk('public')->exists($penerimaan_barang->file_bast_signed)) {
            return Storage::disk('public')->download(
                $penerimaan_barang->file_bast_signed,
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 95>>stream\nBT /F1 12 Tf 50 800 Td (BERITA ACARA SERAH TERIMA BARANG PBJ PNJ: ".addslashes($penerimaan_barang->nomor_bast ?? 'BAST-DRAFT').") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n347\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
