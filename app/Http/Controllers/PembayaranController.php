<?php

namespace App\Http\Controllers;

use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Pengajuan Tagihan Pembayaran SPP (PembayaranController)
 *
 * Mengelola siklus tagihan dan instruksi pembayaran SPP kampus:
 * - Pengajuan tagihan SPP termin / pelunasan oleh PPK atau Bendahara (FR 16.1).
 * - Pembangkitan memo bayar bertingkat otomatis: Level 1 (PPK) & Level 2 (Wadir 2).
 * - Penerbitan perintah bayar resmi oleh PPBJ setelah memo disetujui penuh (FR 17.1).
 * - Koordinasi dokumen penunjang tagihan vendor (invoice, faktur pajak, slip).
 */
class PembayaranController extends Controller
{
    /**
     * Menampilkan daftar riwayat pengajuan pembayaran SPP dengan filter status dan nomor paket.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pembayaran::with(['paket.vendor', 'pengaju', 'memoBayar', 'transaksiPencairan']);

        // Filter berdasarkan status pembayaran ('reviu_memo', 'perintah_bayar', 'lunas', dll.)
        if ($request->filled('status')) {
            $query->where('status_pembayaran', $request->status);
        }

        // Filter berdasarkan nomor paket pengadaan spesifik
        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_pengajuan')->paginate(15),
        ]);
    }

    /**
     * Menyimpan pengajuan pembayaran SPP baru dan otomatis membangkitkan memo bertingkat (FR 16.1).
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input pengajuan pembayaran SPP
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

        // Simpan dokumen tagihan vendor ke penyimpanan disk
        if ($request->hasFile('file_tagihan_vendor')) {
            $validated['file_tagihan_vendor'] = $request->file('file_tagihan_vendor')->store('tagihan', 'public');
        }

        // Simpan dokumen penunjang lainnya
        if ($request->hasFile('file_dokumen_penunjang')) {
            $validated['file_dokumen_penunjang'] = $request->file('file_dokumen_penunjang')->store('penunjang', 'public');
        }

        // Simpan dokumen memo internal jika ada
        if ($request->hasFile('file_memo')) {
            $validated['file_memo'] = $request->file('file_memo')->store('memo', 'public');
        }

        // Set status awal pembayaran menuju tahap reviu memo
        $validated['status_pembayaran'] = 'reviu_memo';
        $pembayaran = Pembayaran::create($validated);

        // 2. FR 16.1: Buat memo bayar bertingkat secara otomatis
        $paket = PaketPengadaan::find($pembayaran->nomor_paket);
        $ppkUserId = $paket->id_user_ppk ?? $pembayaran->id_user_pengaju;

        // Level 1: Persetujuan memo oleh Pejabat Pembuat Komitmen (PPK) penanggung jawab paket
        MemoBayar::create([
            'id_user' => $ppkUserId,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        // Level 2: Persetujuan memo oleh Pimpinan Eksekutif (Wadir 2)
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

    /**
     * Menampilkan rincian satu pembayaran SPP beserta status memo bayar bertingkatnya.
     */
    public function show(Pembayaran $pembayaran): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $pembayaran->load(['paket.vendor', 'pengaju', 'reviuUser', 'memoBayar.user', 'transaksiPencairan']),
        ]);
    }

    /**
     * Menerbitkan perintah pembayaran resmi ke Bagian Keuangan setelah memo disetujui (FR 17.1).
     */
    public function terbitkanPerintahBayar(Request $request, Pembayaran $pembayaran): JsonResponse
    {
        // 1. Validasi ID verifikator PPBJ
        $validated = $request->validate([
            'id_user_reviu' => 'required|exists:users,id_user',
            'catatan_reviu_akhir' => 'nullable|string',
        ]);

        // 2. Pastikan seluruh memo bayar bertingkat (PPK & Wadir 2) sudah berstatus 'disetujui'
        $memoPending = $pembayaran->memoBayar()->where('status_memo', '!=', 'disetujui')->count();
        if ($memoPending > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Perintah pembayaran belum dapat diterbitkan karena memo bayar belum disetujui lengkap oleh PPK dan Wadir 2.',
            ], 422);
        }

        // 3. Perbarui status pembayaran menjadi 'perintah_bayar' siap dicairkan Keuangan
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

    /**
     * Memperbarui informasi atau jatuh tempo pembayaran SPP.
     */
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

    /**
     * Menghapus baris data pengajuan pembayaran dari database.
     */
    public function destroy(Pembayaran $pembayaran): JsonResponse
    {
        $pembayaran->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data pembayaran berhasil dihapus',
        ]);
    }
}
