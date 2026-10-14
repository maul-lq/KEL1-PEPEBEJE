<?php

namespace App\Http\Controllers;

use App\Models\PaketPengadaan;
use App\Models\RiwayatPenugasanPPK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaketPengadaanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PaketPengadaan::with(['permohonan.unit', 'ppbjUser', 'ppkUser', 'ppUser', 'vendor']);

        if ($request->filled('status')) {
            $query->where('status_paket', $request->status);
        }

        if ($request->filled('jalur_routing')) {
            $query->where('jalur_routing', $request->jalur_routing);
        }

        if ($request->filled('jenis_pengadaan')) {
            $query->where('jenis_pengadaan', $request->jenis_pengadaan);
        }

        if ($request->filled('id_user_ppk')) {
            $query->where('id_user_ppk', $request->id_user_ppk);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_paket', 'like', "%{$search}%")
                    ->orWhere('nama_paket', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_registrasi_paket')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => 'required|string|max:100|unique:paket_pengadaan,nomor_paket',
            'nama_paket' => 'required|string|max:255',
            'jenis_pengadaan' => 'required|string|in:barang,jasa_lainnya,konstruksi,jasa_konsultansi',
            'platform_pengadaan' => 'required|string|max:50',
            'mekanisme_pengadaan' => 'required|string|max:50',
            'nilai_hps' => 'required|numeric|min:0',
            'alokasi_pagu_paket' => 'required|numeric|min:0',
            'file_dokumen_pengadaan' => 'nullable|string|max:255',
            'id_paket_lkpp' => 'nullable|string|max:100',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'nomor_memo_ppbj' => 'nullable|string|max:100',
            'id_user_ppbj' => 'required|exists:users,id_user',
            'id_user_ppk' => 'nullable|exists:users,id_user',
            'id_user_pp' => 'nullable|exists:users,id_user',
            'npwp_vendor' => 'nullable|exists:vendors,npwp',
        ]);

        // Hitung jalur routing otomatis (FR 8.1 - 8.3)
        $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        $validated['tanggal_registrasi_paket'] = now();
        $validated['status_paket'] = 'draft';

        if (! empty($validated['id_user_ppk'])) {
            $validated['tanggal_penugasan_ppk'] = now();
            $validated['status_penugasan_ppk'] = 'ditugaskan';
        }

        if (! empty($validated['id_user_pp'])) {
            $validated['tanggal_penugasan_pp'] = now();
        }

        $paket = PaketPengadaan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil diregistrasi dengan jalur routing: ' . $paket->jalur_routing,
            'data' => $paket->load(['permohonan', 'ppbjUser', 'ppkUser', 'ppUser']),
        ], 201);
    }

    public function show(PaketPengadaan $paket_pengadaan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $paket_pengadaan->load([
                'permohonan.unit',
                'ppbjUser',
                'ppkUser',
                'ppUser',
                'vendor.filesLegalitas',
                'reviuDokumen.user',
                'riwayatPpk',
                'kontrakSpk',
                'penerimaanBarang.fotoDokumentasi',
                'pembayaran.memoBayar',
                'reminders',
            ]),
        ]);
    }

    public function update(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'nama_paket' => 'sometimes|required|string|max:255',
            'jenis_pengadaan' => 'sometimes|required|string',
            'platform_pengadaan' => 'sometimes|required|string',
            'mekanisme_pengadaan' => 'sometimes|required|string',
            'nilai_hps' => 'sometimes|required|numeric|min:0',
            'alokasi_pagu_paket' => 'sometimes|required|numeric|min:0',
            'file_dokumen_pengadaan' => 'nullable|string|max:255',
            'status_paket' => 'sometimes|required|string',
            'id_paket_lkpp' => 'nullable|string|max:100',
            'nilai_hasil_negosiasi' => 'nullable|numeric|min:0',
            'catatan_verifikasi_pp' => 'nullable|string',
            'npwp_vendor' => 'nullable|exists:vendors,npwp',
        ]);

        if (isset($validated['nilai_hps'])) {
            $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        }

        $paket_pengadaan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil diperbarui',
            'data' => $paket_pengadaan,
        ]);
    }

    public function reassignPpk(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'ppk_baru_user_id' => 'required|exists:users,id_user',
            'diubah_oleh_user_id' => 'required|exists:users,id_user',
            'alasan_perubahan' => 'required|string',
        ]);

        $ppkLama = $paket_pengadaan->id_user_ppk;

        $paket_pengadaan->id_user_ppk = $validated['ppk_baru_user_id'];
        $paket_pengadaan->tanggal_penugasan_ppk = now();
        $paket_pengadaan->alasan_pengalihan_ppk = $validated['alasan_perubahan'];
        $paket_pengadaan->save();

        RiwayatPenugasanPPK::create([
            'nomor_paket' => $paket_pengadaan->nomor_paket,
            'ppk_lama_user_id' => $ppkLama,
            'ppk_baru_user_id' => $validated['ppk_baru_user_id'],
            'diubah_oleh_user_id' => $validated['diubah_oleh_user_id'],
            'alasan_perubahan' => $validated['alasan_perubahan'],
            'tanggal_penugasan' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan PPK berhasil dialihkan dan dicatat pada riwayat',
            'data' => $paket_pengadaan->load(['ppkUser', 'riwayatPpk']),
        ]);
    }

    public function destroy(PaketPengadaan $paket_pengadaan): JsonResponse
    {
        $paket_pengadaan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil dihapus',
        ]);
    }
}
