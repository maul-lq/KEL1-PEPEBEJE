<?php

namespace App\Http\Controllers;

use App\Models\PaketPengadaan;
use App\Models\ReviuDokumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviuDokumenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ReviuDokumen::with(['paket', 'user']);

        // NFR RBAC: Isolasi berkas 4 akun PPK
        if ($request->user() && $request->user()->role === 'ppk') {
            $query->whereHas('paket', function ($q) use ($request) {
                $q->where('id_user_ppk', $request->user()->id_user);
            });
        }

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('role_reviewer')) {
            $query->where('role_reviewer', $request->role_reviewer);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_reviu')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'id_user' => 'required|exists:users,id_user',
            'putaran_ke' => 'required|integer|min:1',
            'role_reviewer' => 'required|string|in:pp,ppk',
            'status_reviu' => 'required|string|in:disetujui,perlu_perbaikan,ditolak',
            'catatan_perbaikan' => 'nullable|string',
        ]);

        // NFR RBAC: Isolasi berkas 4 akun PPK saat mereviu
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

        if (in_array($validated['status_reviu'], ['perlu_perbaikan', 'ditolak']) && empty($validated['catatan_perbaikan'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan perbaikan wajib diisi saat meminta perbaikan atau menolak dokumen pengadaan.',
            ], 422);
        }

        $validated['tanggal_reviu'] = now();

        $reviu = ReviuDokumen::create($validated);

        // Update status paket pengadaan
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

    public function show(ReviuDokumen $reviu_dokumen): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $reviu_dokumen->load(['paket', 'user']),
        ]);
    }

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

    public function destroy(ReviuDokumen $reviu_dokumen): JsonResponse
    {
        $reviu_dokumen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reviu dokumen berhasil dihapus',
        ]);
    }
}
