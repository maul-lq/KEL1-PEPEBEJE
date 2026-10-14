<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\User;
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
        $user = $request->user();

        $validated = $request->validate([
            'id_user' => 'nullable|exists:users,id_user',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'role_verifikator' => 'required|string|in:perencanaan,staff_bidang_2,wadir_2',
            'status_keputusan' => 'required|string|in:menunggu,disetujui,ditolak,revisi,pending_anggaran',
            'catatan_alasan' => 'nullable|string',
        ]);

        if (empty($validated['id_user'])) {
            $validated['id_user'] = $user?->id_user ?? User::first()->id_user;
        }

        if (in_array($validated['status_keputusan'], ['ditolak', 'revisi']) && empty($validated['catatan_alasan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi permohonan.',
            ], 422);
        }

        $validated['tanggal_keputusan'] = now();

        $verif = VerifikasiParalel::create($validated);
        $this->cekKelulusanParalel($verif->nomor_surat, $validated['id_user']);

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
        $user = $request->user();

        $validated = $request->validate([
            'status_keputusan' => 'required|string|in:menunggu,disetujui,ditolak,revisi,pending_anggaran',
            'catatan_alasan' => 'required_if:status_keputusan,ditolak,revisi|nullable|string',
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan wajib diisi saat menolak atau meminta revisi permohonan.',
        ]);

        if ($user) {
            $verifikasi_paralel->id_user = $user->id_user;
        }

        $validated['tanggal_keputusan'] = now();
        $verifikasi_paralel->update($validated);

        $this->cekKelulusanParalel($verifikasi_paralel->nomor_surat, $verifikasi_paralel->id_user);

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan verifikasi paralel berhasil diperbarui',
            'data' => $verifikasi_paralel->load(['user', 'permohonan']),
        ]);
    }

    /**
     * FR 5.1 & Notulensi: Staff Bidang 2 memeriksa berkas dan menyampaikan/meneruskan permohonan masuk ke Wadir 2.
     */
    public function teruskanKeWadir(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'catatan_pemeriksaan' => 'nullable|string',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        AuditLog::catat(
            $user?->id_user ?? User::first()->id_user,
            'STAFF_B2_TERUSKAN_KE_WADIR',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $permohonan->status_permohonan,
            $permohonan->status_permohonan,
            'Staff Bidang 2 memeriksa kelengkapan berkas dan menyampaikan usulan permohonan ke Wadir 2 untuk persetujuan (FR 5.1): '.($validated['catatan_pemeriksaan'] ?? 'Berkas lengkap')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas permohonan berhasil disampaikan/diteruskan ke Wadir 2 untuk persetujuan eksekutif (FR 5.1)',
            'data' => [
                'nomor_surat' => $permohonan->nomor_surat,
                'status_permohonan' => $permohonan->status_permohonan,
                'catatan_pemeriksaan' => $validated['catatan_pemeriksaan'] ?? 'Berkas lengkap telah diteruskan ke Wadir 2',
            ],
        ]);
    }

    private function cekKelulusanParalel(string $nomor_surat, ?int $operatorId = null): void
    {
        $verifikasi = VerifikasiParalel::where('nomor_surat', $nomor_surat)->get();
        $permohonan = PermohonanPengadaan::find($nomor_surat);

        if (! $permohonan || $verifikasi->isEmpty()) {
            return;
        }

        $statusLama = $permohonan->status_permohonan;

        // Jika salah satu menolak
        if ($verifikasi->contains('status_keputusan', 'ditolak')) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->save();

            AuditLog::catat(
                $operatorId ?? $permohonan->id_user,
                'KEPUTUSAN_VERIFIKASI_DITOLAK',
                'permohonan_pengadaan',
                $nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_DITOLAK,
                'Permohonan ditolak oleh verifikator dalam verifikasi paralel'
            );

            return;
        }

        // Jika ada status pending anggaran (Hold) - FR 6.2
        if ($verifikasi->contains('status_keputusan', 'pending_anggaran')) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
            $permohonan->status_anggaran = 'hold';
            $permohonan->save();

            AuditLog::catat(
                $operatorId ?? $permohonan->id_user,
                'KEPUTUSAN_VERIFIKASI_HOLD_ANGGARAN',
                'permohonan_pengadaan',
                $nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                'Permohonan ditahan (Hold/Pending) karena anggaran belum tersedia di Perencanaan'
            );

            return;
        }

        // Jika keduanya menyetujui (Perencanaan & Wadir 2)
        $perencanaanOk = $verifikasi->where('role_verifikator', 'perencanaan')->where('status_keputusan', 'disetujui')->isNotEmpty();
        $wadirOk = $verifikasi->where('role_verifikator', 'wadir_2')->where('status_keputusan', 'disetujui')->isNotEmpty();

        if ($perencanaanOk && $wadirOk) {
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->status_anggaran = 'disetujui';
            $permohonan->save();

            AuditLog::catat(
                $operatorId ?? $permohonan->id_user,
                'KEPUTUSAN_VERIFIKASI_DISETUJUI_PARALEL',
                'permohonan_pengadaan',
                $nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_DISETUJUI,
                'Permohonan disetujui penuh oleh Perencanaan & Wadir 2 dan siap diregistrasi PPBJ (FR 3.3)'
            );
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
