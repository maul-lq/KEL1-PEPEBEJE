<?php

namespace App\Http\Controllers;

use App\Models\PermohonanPengadaan;
use App\Models\VerifikasiParalel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermohonanPengadaanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PermohonanPengadaan::with(['user', 'unit', 'anggaranMak', 'indukSplit', 'verifikasiParalel']);

        if ($request->filled('status')) {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('status_anggaran')) {
            $query->where('status_anggaran', $request->status_anggaran);
        }

        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('judul_pengadaan', 'like', "%{$search}%");
            });
        }

        $permohonan = $query->latest('tanggal_pengajuan')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $permohonan,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => 'required|string|max:255',
            'kode_unit' => 'required|string|exists:units,kode_unit',
            'id_user' => 'required|exists:users,id_user',
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'kode_mak' => 'nullable|exists:anggaran_mak,kode_mak',
        ]);

        $validated['status_permohonan'] = PermohonanPengadaan::STATUS_REVIU_PARALEL;
        $validated['status_anggaran'] = 'pending';
        $validated['tanggal_pengajuan'] = now();

        $permohonan = PermohonanPengadaan::create($validated);

        // Otomatis buat 2 baris verifikasi paralel: Perencanaan & Staff/Wadir 2 (FR 3.3)
        VerifikasiParalel::create([
            'id_user' => $permohonan->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $permohonan->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil diajukan dan diteruskan ke Verifikasi Paralel',
            'data' => $permohonan->load(['unit', 'user', 'verifikasiParalel']),
        ], 201);
    }

    public function show(PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $permohonan_pengadaan->load([
                'user',
                'unit',
                'anggaranMak',
                'indukSplit',
                'anakSplit',
                'suratAsalRevisi',
                'suratHasilRevisi',
                'verifikasiParalel.user',
                'alokasiMak.anggaranMak',
                'paketPengadaan',
            ]),
        ]);
    }

    public function update(Request $request, PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'judul_pengadaan' => 'sometimes|required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'sometimes|required|string',
            'file_permohonan' => 'sometimes|required|string|max:255',
            'status_permohonan' => 'sometimes|required|string',
            'status_anggaran' => 'sometimes|required|string',
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'kode_mak' => 'nullable|exists:anggaran_mak,kode_mak',
        ]);

        $permohonan_pengadaan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil diperbarui',
            'data' => $permohonan_pengadaan->load(['unit', 'user', 'anggaranMak']),
        ]);
    }

    public function split(Request $request, PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat_baru' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'alasan_pemecahan' => 'required|string',
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'kode_mak' => 'nullable|exists:anggaran_mak,kode_mak',
        ]);

        $anak = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $permohonan_pengadaan->tujuan_pengadaan_ringkas,
            'file_permohonan' => $permohonan_pengadaan->file_permohonan,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'] ?? $permohonan_pengadaan->estimasi_total_pagu,
            'kode_unit' => $permohonan_pengadaan->kode_unit,
            'id_user' => $permohonan_pengadaan->id_user,
            'nomor_surat_induk_split' => $permohonan_pengadaan->nomor_surat,
            'tanggal_pemecahan' => now(),
            'alasan_pemecahan' => $validated['alasan_pemecahan'],
            'kode_mak' => $validated['kode_mak'] ?? $permohonan_pengadaan->kode_mak,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil dipecah (split)',
            'data' => $anak,
        ], 201);
    }

    public function destroy(PermohonanPengadaan $permohonan_pengadaan): JsonResponse
    {
        $permohonan_pengadaan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan pengadaan berhasil dihapus',
        ]);
    }
}
