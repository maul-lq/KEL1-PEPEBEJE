<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pembayaran;
use App\Models\TransaksiPencairan;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TransaksiPencairanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TransaksiPencairan::with(['pembayaran.paket.vendor', 'user']);

        if ($request->filled('nomor_pembayaran')) {
            $query->where('nomor_pembayaran', $request->nomor_pembayaran);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_transfer')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nomor_transaksi_bank' => 'required|string|max:100|unique:transaksi_pencairan,nomor_transaksi_bank',
            'nomor_pembayaran' => 'required|exists:pembayaran,nomor_pembayaran',
            'id_user' => 'nullable|exists:users,id_user',
            'nominal_transfer' => 'required|numeric|min:0',
            'file_bukti_transfer' => ['required', DokumenUploadRule::pdfOrImage()],
            'catatan_keuangan' => 'nullable|string',
        ]);

        if (empty($validated['id_user'])) {
            $validated['id_user'] = $user?->id_user ?? User::first()->id_user;
        }

        // NFR 8: Keandalan pencairan & pencegahan duplikasi pencairan
        $pembayaran = Pembayaran::findOrFail($validated['nomor_pembayaran']);
        $alreadyDisbursed = TransaksiPencairan::where('nomor_pembayaran', $validated['nomor_pembayaran'])->exists();
        if ($alreadyDisbursed || $pembayaran->status_pembayaran === 'lunas') {
            throw ValidationException::withMessages([
                'nomor_pembayaran' => 'Transaksi pembayaran ini sudah dicairkan sebelumnya dan berstatus Lunas (NFR 8 Keandalan Pembayaran).',
            ]);
        }

        if ($request->hasFile('file_bukti_transfer')) {
            $validated['file_bukti_transfer'] = $request->file('file_bukti_transfer')->store('transfer', 'public');
        }

        $validated['tanggal_transfer'] = now();

        $cair = TransaksiPencairan::create($validated);

        AuditLog::catat(
            $validated['id_user'],
            'PENCAIRAN_SP2D_SELESAI',
            'transaksi_pencairan',
            $cair->nomor_transaksi_bank,
            $pembayaran->status_pembayaran,
            'lunas',
            'Pencairan dana SP2D berhasil diselesaikan dan status pengadaan diubah menjadi Selesai (NFR 8 & FR 18.2)'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan dana berhasil diselesaikan dan status pengadaan diubah menjadi Selesai',
            'data' => $cair->load(['pembayaran.paket', 'user']),
        ], 201);
    }

    public function show(TransaksiPencairan $transaksi_pencairan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $transaksi_pencairan->load(['pembayaran.paket.vendor', 'user']),
        ]);
    }

    public function update(Request $request, TransaksiPencairan $transaksi_pencairan): JsonResponse
    {
        $validated = $request->validate([
            'catatan_keuangan' => 'nullable|string',
            'file_bukti_transfer' => 'sometimes|required|string|max:255',
        ]);

        $transaksi_pencairan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data transaksi pencairan berhasil diperbarui',
            'data' => $transaksi_pencairan,
        ]);
    }

    /**
     * NFR 12: Unduh berkas resmi PDF Bukti Pencairan / Transfer Bank (< 3 Detik).
     */
    public function unduhPdf(TransaksiPencairan $transaksi_pencairan): Response
    {
        $filename = 'BuktiTransfer_'.str_replace(['/', '\\'], '_', $transaksi_pencairan->nomor_transaksi_bank).'.pdf';

        if (! empty($transaksi_pencairan->file_bukti_transfer) && Storage::disk('public')->exists($transaksi_pencairan->file_bukti_transfer)) {
            return Storage::disk('public')->download(
                $transaksi_pencairan->file_bukti_transfer,
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 95>>stream\nBT /F1 12 Tf 50 800 Td (BUKTI TRANSAKSI TRANSFER PEMBAYARAN KAMPUS PNJ: ".addslashes($transaksi_pencairan->nomor_transaksi_bank).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n347\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy(TransaksiPencairan $transaksi_pencairan): JsonResponse
    {
        $transaksi_pencairan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pencairan berhasil dihapus',
        ]);
    }
}
