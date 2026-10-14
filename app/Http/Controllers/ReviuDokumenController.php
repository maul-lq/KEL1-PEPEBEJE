<?php

namespace App\Http\Controllers;

use App\Models\PaketPengadaan;
use App\Models\ReviuDokumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Reviu Dokumen Teknis & Penelaahan HPS (ReviuDokumenController)
 *
 * Mengelola evaluasi dokumen persiapan pengadaan oleh Pejabat Pengadaan (PP) atau PPK:
 * - Penelaahan spesifikasi teknis, rancangan kontrak, dan rincian HPS (FR 9.1).
 * - Menegakkan isolasi berkas 4 akun PPK: PPK dilarang mereviu paket milik PPK lain (NFR RBAC).
 * - Mewajibkan catatan perbaikan jika status reviu bernilai 'perlu_perbaikan' atau 'ditolak'.
 * - Memperbarui status tahapan paket menjadi 'reviu_dokumen_disetujui'.
 */
class ReviuDokumenController extends Controller
{
    /**
     * Menampilkan daftar penelaahan dokumen dengan penyaringan otomatis isolasi PPK.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReviuDokumen::with(['paket', 'user']);

        // 1. NFR RBAC: Isolasi berkas 4 akun PPK (PPK hanya melihat reviu pada paket yang ditugaskan kepadanya)
        if ($request->user() && $request->user()->role === 'ppk') {
            $query->whereHas('paket', function ($q) use ($request) {
                $q->where('id_user_ppk', $request->user()->id_user);
            });
        }

        // Filter berdasarkan nomor paket pengadaan
        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        // Filter berdasarkan peran penelaah ('pp' atau 'ppk')
        if ($request->filled('role_reviewer')) {
            $query->where('role_reviewer', $request->role_reviewer);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_reviu')->paginate(15),
        ]);
    }

    /**
     * Menyimpan hasil penelaahan dokumen pengadaan dan mengevaluasi status paket (FR 9.1).
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input reviu dokumen
        $validated = $request->validate([
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'id_user' => 'required|exists:users,id_user',
            'putaran_ke' => 'required|integer|min:1',
            'role_reviewer' => 'required|string|in:pp,ppk',
            'status_reviu' => 'required|string|in:disetujui,perlu_perbaikan,ditolak',
            'catatan_perbaikan' => 'nullable|string',
        ]);

        // 2. NFR RBAC: Tegakkan isolasi berkas 4 akun PPK saat mereviu paket
        $user = $request->user();
        if ($user && $user->role === 'ppk') {
            $paket = PaketPengadaan::find($validated['nomor_paket']);
            if ($paket && (int) $paket->id_user_ppk !== (int) $user->id_user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Anda tidak dapat mereviu paket yang ditugaskan kepada PPK lain (Isolasi Berkas PPK).',
                ], 403);
            }
        }

        // 3. Catatan perbaikan wajib disertakan jika hasil reviu menuntut perbaikan atau menolak dokumen
        if (in_array($validated['status_reviu'], ['perlu_perbaikan', 'ditolak']) && empty($validated['catatan_perbaikan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan perbaikan wajib diisi saat meminta perbaikan atau menolak dokumen pengadaan.',
            ], 422);
        }

        $validated['tanggal_reviu'] = now();

        // 4. Simpan hasil reviu ke database
        $reviu = ReviuDokumen::create($validated);

        // 5. Perbarui status tahapan pada tabel paket_pengadaan
        $paket = $reviu->paket;
        if ($paket) {
            if ($validated['status_reviu'] === 'disetujui') {
                $paket->status_paket = 'reviu_dokumen_disetujui';
            } elseif ($validated['status_reviu'] === 'perlu_perbaikan') {
                $paket->status_paket = 'perlu_perbaikan';
            }
            $paket->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil reviu dokumen berhasil disimpan',
            'data' => $reviu->load(['paket', 'user']),
        ], 201);
    }

    /**
     * Menampilkan detail satu dokumen hasil reviu teknis pengadaan.
     */
    public function show(ReviuDokumen $reviu_dokumen): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $reviu_dokumen->load(['paket', 'user']),
        ]);
    }

    /**
     * Memperbarui status atau catatan tindak lanjut reviu dokumen.
     */
    public function update(Request $request, ReviuDokumen $reviu_dokumen): JsonResponse
    {
        $validated = $request->validate([
            'status_reviu' => 'required|string|in:disetujui,perlu_perbaikan,ditolak',
            'catatan_perbaikan' => 'nullable|string',
        ]);

        $reviu_dokumen->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Reviu dokumen berhasil diperbarui',
            'data' => $reviu_dokumen->load(['paket', 'user']),
        ]);
    }

    /**
     * Menghapus baris reviu dokumen dari database.
     */
    public function destroy(ReviuDokumen $reviu_dokumen): JsonResponse
    {
        $reviu_dokumen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reviu dokumen berhasil dihapus',
        ]);
    }
}
