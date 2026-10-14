<?php

namespace App\Http\Controllers;

use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Penerbitan Dokumen Kontrak & SPK (KontrakSPKController)
 *
 * Mengelola penerbitan perjanjian resmi dengan vendor rekanan:
 * - Penerbitan Surat Perintah Kerja (SPK) / Surat Kontrak / Surat Pesanan.
 * - Memicu penguncian otomatis (auto-lock) anggaran MAK agar pagu tidak dapat diubah (NFR Integritas MAK).
 * - Pemutakhiran otomatis status paket menjadi 'spk_terbit'.
 * - Unduh salinan PDF resmi berkas kontrak (< 3 detik) (NFR 12).
 */
class KontrakSPKController extends Controller
{
    /**
     * Menampilkan daftar kontrak/SPK dengan filter status penerbitan dan tipe dokumen.
     */
    public function index(Request $request): JsonResponse
    {
        $query = KontrakSPK::with(['paket.vendor', 'user']);

        // Filter berdasarkan status dokumen kontrak ('draft', 'terbit', 'aktif', 'selesai')
        if ($request->filled('status_spk')) {
            $query->where('status_spk', $request->status_spk);
        }

        // Filter berdasarkan tipe berkas ('spk', 'surat_kontrak', 'surat_pesanan')
        if ($request->filled('tipe_dokumen')) {
            $query->where('tipe_dokumen', $request->tipe_dokumen);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_spk')->paginate(15),
        ]);
    }

    /**
     * Menerbitkan berkas kontrak / SPK baru dan memicu penguncian otomatis anggaran MAK (NFR Integritas MAK).
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input penerbitan kontrak/SPK
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
            'file_spk' => ['required', DokumenUploadRule::pdfOnly()], // File wajib format PDF maks 10MB
        ]);

        // Simpan dokumen fisik SPK ke direktori 'spk' di storage publik
        if ($request->hasFile('file_spk')) {
            $validated['file_spk'] = $request->file('file_spk')->store('spk', 'public');
        }

        // 2. Simpan record kontrak_spk (model booted otomatis mengunci MAK terkait jika status 'terbit' / 'aktif')
        $spk = KontrakSPK::create($validated);

        // 3. Perbarui status paket pengadaan menjadi 'spk_terbit'
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

    /**
     * Menampilkan detail lengkap dokumen kontrak/SPK beserta paket dan vendor terkait.
     */
    public function show(KontrakSPK $kontrak_spk): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $kontrak_spk->load(['paket.vendor', 'paket.permohonan', 'user']),
        ]);
    }

    /**
     * Memperbarui informasi tanggal, sifat, atau berkas kontrak/SPK.
     */
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
     * Mengunduh berkas fisik resmi format PDF Surat Perintah Kerja (SPK) dengan respons < 3 detik (NFR 12).
     */
    public function unduhPdf(KontrakSPK $kontrak_spk): Response
    {
        $filename = 'SPK_'.str_replace(['/', '\\'], '_', $kontrak_spk->nomor_spk).'.pdf';

        // 1. Jika file fisik tersimpan di disk, langsung layani proses download browser
        if (! empty($kontrak_spk->file_spk) && Storage::disk('public')->exists($kontrak_spk->file_spk)) {
            return Storage::disk('public')->download(
                $kontrak_spk->file_spk,
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        // 2. Jika berkas demo/belum ada, stream PDF valid standar ISO %PDF-1.4 secara instan
        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 85>>stream\nBT /F1 12 Tf 50 800 Td (SURAT PERINTAH KERJA PBJ KAMPUS PNJ: ".addslashes($kontrak_spk->nomor_spk).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n347\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Menghapus berkas kontrak/SPK dari database.
     */
    public function destroy(KontrakSPK $kontrak_spk): JsonResponse
    {
        $kontrak_spk->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak/SPK berhasil dihapus',
        ]);
    }
}
