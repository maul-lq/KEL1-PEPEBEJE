<?php

namespace App\Http\Controllers;

use App\Models\PermohonanPengadaan;
use App\Models\VerifikasiParalel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifikasiParalelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VerifikasiParalel::with(['user', 'permohonan.unit']);

        if ($request->filled('nomor_surat')) {
            $query->where('nomor_surat', $request->nomor_surat);
        }

        if ($request->filled('role_verifikator')) {
            $query->where('role_verifikator', $request->role_verifikator);
        }

        if ($request->filled('status_keputusan')) {
            $query->where('status_keputusan', $request->status_keputusan);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => 'required|exists:users,id_user',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'role_verifikator' => 'required|string|in:perencanaan,staff_bidang_2,wadir_2',
            'status_keputusan' => 'required|string|in:menunggu,disetujui,ditolak,revisi,pending_anggaran',
            'catatan_alasan' => 'nullable|string',
        ]);

        if (in_array($validated['status_keputusan'], ['ditolak', 'revisi']) && empty($validated['catatan_alasan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi permohonan.',
            ], 422);
        }

        $validated['tanggal_keputusan'] = now();

        $verif = VerifikasiParalel::create($validated);
        $this->cekKelulusanParalel($verif->nomor_surat);

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan verifikasi paralel berhasil disimpan',
            'data' => $verif->load(['user', 'permohonan']),
        ], 201);
    }

    public function show(VerifikasiParalel $verifikasi_paralel): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $verifikasi_paralel->load(['user', 'permohonan']),
        ]);
    }

    public function update(Request $request, VerifikasiParalel $verifikasi_paralel): JsonResponse
    {
        $validated = $request->validate([
            'status_keputusan' => 'required|string|in:menunggu,disetujui,ditolak,revisi,pending_anggaran',
            'catatan_alasan' => 'nullable|string',
        ]);

        if (in_array($validated['status_keputusan'], ['ditolak', 'revisi']) && empty($validated['catatan_alasan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi permohonan.',
            ], 422);
        }

        $validated['tanggal_keputusan'] = now();
        $verifikasi_paralel->update($validated);

        $this->cekKelulusanParalel($verifikasi_paralel->nomor_surat);

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan verifikasi paralel berhasil diperbarui',
            'data' => $verifikasi_paralel->load(['user', 'permohonan']),
        ]);
    }

    private function cekKelulusanParalel(string $nomor_surat): void
    {
        $verifikasi = VerifikasiParalel::where('nomor_surat', $nomor_surat)->get();
        $permohonan = PermohonanPengadaan::find($nomor_surat);

        if (! $permohonan || $verifikasi->isEmpty()) {
            return;
        }

        // Jika salah satu menolak
        if ($verifikasi->contains('status_keputusan', 'ditolak')) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->save();

            return;
        }

        // Jika ada status pending anggaran
        if ($verifikasi->contains('status_keputusan', 'pending_anggaran')) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
            $permohonan->status_anggaran = 'hold';
            $permohonan->save();

            return;
        }

        // Jika keduanya menyetujui (Perencanaan & Wadir 2)
        $perencanaanOk = $verifikasi->where('role_verifikator', 'perencanaan')->where('status_keputusan', 'disetujui')->isNotEmpty();
        $wadirOk = $verifikasi->where('role_verifikator', 'wadir_2')->where('status_keputusan', 'disetujui')->isNotEmpty();

        if ($perencanaanOk && $wadirOk) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->status_anggaran = 'disetujui';
            $permohonan->save();
        }
    }

    public function destroy(VerifikasiParalel $verifikasi_paralel): JsonResponse
    {
        $verifikasi_paralel->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi paralel berhasil dihapus',
        ]);
    }
}
