<?php

namespace App\Http\Controllers;

use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pembayaran::with(['paket.vendor', 'pengaju', 'memoBayar', 'transaksiPencairan']);

        if ($request->filled('status')) {
            $query->where('status_pembayaran', $request->status);
        }

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_pengajuan')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_pembayaran' => 'required|string|max:100|unique:pembayaran,nomor_pembayaran',
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'id_user_pengaju' => 'required|exists:users,id_user',
            'tahap_termin' => 'required|integer|min:1',
            'nominal_pengajuan' => 'required|numeric|min:0',
            'tanggal_pengajuan' => 'required|date',
            'file_tagihan_vendor' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_dokumen_penunjang' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'nomor_memo' => 'nullable|string|max:100',
            'file_memo' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        if ($request->hasFile('file_tagihan_vendor')) {
            $validated['file_tagihan_vendor'] = $request->file('file_tagihan_vendor')->store('tagihan', 'public');
        }

        if ($request->hasFile('file_dokumen_penunjang')) {
            $validated['file_dokumen_penunjang'] = $request->file('file_dokumen_penunjang')->store('penunjang', 'public');
        }

        if ($request->hasFile('file_memo')) {
            $validated['file_memo'] = $request->file('file_memo')->store('memo', 'public');
        }

        $validated['status_pembayaran'] = 'reviu_memo';
        $pembayaran = Pembayaran::create($validated);

        // Buat memo bayar bertingkat otomatis: Level 1 (PPK) & Level 2 (Wadir 2) (FR 16.1)
        $paket = PaketPengadaan::find($pembayaran->nomor_paket);
        $ppkUserId = $paket->id_user_ppk ?? $pembayaran->id_user_pengaju;

        MemoBayar::create([
            'id_user' => $ppkUserId,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        // Cari user wadir 2
        $wadirUser = User::where('role', 'wadir_2')->first();
        if ($wadirUser) {
            MemoBayar::create([
                'id_user' => $wadirUser->id_user,
                'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
                'role_approver' => 'wadir_2',
                'urutan_level' => 2,
                'status_memo' => 'menunggu',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pembayaran berhasil diajukan dan memo pembayaran diteruskan ke PPK dan Wadir 2',
            'data' => $pembayaran->load(['paket', 'memoBayar']),
        ], 201);
    }

    public function show(Pembayaran $pembayaran): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $pembayaran->load(['paket.vendor', 'pengaju', 'reviuUser', 'memoBayar.user', 'transaksiPencairan']),
        ]);
    }

    public function terbitkanPerintahBayar(Request $request, Pembayaran $pembayaran): JsonResponse
    {
        $validated = $request->validate([
            'id_user_reviu' => 'required|exists:users,id_user',
            'catatan_reviu_akhir' => 'nullable|string',
        ]);

        // Pastikan memo sudah disetujui kedua approver
        $memoPending = $pembayaran->memoBayar()->where('status_memo', '!=', 'disetujui')->count();
        if ($memoPending > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Perintah pembayaran belum dapat diterbitkan karena memo bayar belum disetujui lengkap oleh PPK dan Wadir 2.',
            ], 422);
        }

        $pembayaran->id_user_reviu = $validated['id_user_reviu'];
        $pembayaran->tanggal_perintah_bayar = now();
        $pembayaran->status_reviu_akhir = 'disetujui';
        $pembayaran->catatan_reviu_akhir = $validated['catatan_reviu_akhir'] ?? 'Dokumen telah diverifikasi lengkap';
        $pembayaran->status_pembayaran = 'perintah_bayar';
        $pembayaran->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Perintah pembayaran berhasil diterbitkan ke Bagian Keuangan',
            'data' => $pembayaran,
        ]);
    }

    public function update(Request $request, Pembayaran $pembayaran): JsonResponse
    {
        $validated = $request->validate([
            'status_pembayaran' => 'sometimes|required|string',
            'nominal_pengajuan' => 'sometimes|required|numeric|min:0',
            'jatuh_tempo' => 'sometimes|required|date',
            'file_tagihan_vendor' => 'nullable|string|max:255',
            'file_dokumen_penunjang' => 'nullable|string|max:255',
        ]);

        $pembayaran->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data pembayaran berhasil diperbarui',
            'data' => $pembayaran,
        ]);
    }

    public function destroy(Pembayaran $pembayaran): JsonResponse
    {
        $pembayaran->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data pembayaran berhasil dihapus',
        ]);
    }
}
