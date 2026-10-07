<?php

namespace App\Http\Controllers;

use App\Models\VerifikasiParalel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerifikasiParalelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VerifikasiParalel::with(['user', 'permohonan']);

        if ($request->filled('nomor_surat')) {
            $query->where('nomor_surat', $request->nomor_surat);
        }

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->id_user);
        }

        if ($request->filled('status')) {
            $query->where('status_keputusan', $request->status);
        }

        $verifikasi = $query->latest('tanggal_keputusan')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $verifikasi,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'roles' => ['staff_bidang_2', 'wadir_2', 'perencanaan'],
            'statuses' => [
                VerifikasiParalel::STATUS_PENDING,
                VerifikasiParalel::STATUS_DISETUJUI,
                VerifikasiParalel::STATUS_DITOLAK,
                VerifikasiParalel::STATUS_HOLD_ANGGARAN,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'role_verifikator' => ['required', 'string', Rule::in(['staff_bidang_2', 'wadir_2', 'perencanaan'])],
            'status_keputusan' => ['required', 'string', Rule::in([
                VerifikasiParalel::STATUS_PENDING,
                VerifikasiParalel::STATUS_DISETUJUI,
                VerifikasiParalel::STATUS_DITOLAK,
                VerifikasiParalel::STATUS_HOLD_ANGGARAN,
            ])],
            'catatan_alasan' => ['nullable', 'string'],
            'tanggal_keputusan' => ['nullable', 'date'],
        ]);

        $exists = VerifikasiParalel::where('id_user', $validated['id_user'])
            ->where('nomor_surat', $validated['nomor_surat'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verifikasi paralel untuk user dan permohonan ini sudah ada',
            ], 422);
        }

        $validated['tanggal_keputusan'] = $validated['tanggal_keputusan'] ?? now();

        $verifikasi = VerifikasiParalel::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi paralel berhasil dicatat',
            'data' => $verifikasi,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $verifikasi = $this->findComposite($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $verifikasi->load(['user', 'permohonan']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $verifikasi = $this->findComposite($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $verifikasi,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $verifikasi = $this->findComposite($request, $id);

        $validated = $request->validate([
            'role_verifikator' => ['sometimes', 'required', 'string', Rule::in(['staff_bidang_2', 'wadir_2', 'perencanaan'])],
            'status_keputusan' => ['sometimes', 'required', 'string', Rule::in([
                VerifikasiParalel::STATUS_PENDING,
                VerifikasiParalel::STATUS_DISETUJUI,
                VerifikasiParalel::STATUS_DITOLAK,
                VerifikasiParalel::STATUS_HOLD_ANGGARAN,
            ])],
            'catatan_alasan' => ['nullable', 'string'],
            'tanggal_keputusan' => ['nullable', 'date'],
        ]);

        VerifikasiParalel::where('id_user', $verifikasi->id_user)
            ->where('nomor_surat', $verifikasi->nomor_surat)
            ->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi paralel berhasil diperbarui',
            'data' => $verifikasi->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $verifikasi = $this->findComposite($request, $id);

        VerifikasiParalel::where('id_user', $verifikasi->id_user)
            ->where('nomor_surat', $verifikasi->nomor_surat)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi paralel berhasil dihapus',
        ]);
    }

    /**
     * Helper to resolve composite primary key record.
     */
    protected function findComposite(Request $request, $id): VerifikasiParalel
    {
        if ($request->filled('id_user') && $request->filled('nomor_surat')) {
            return VerifikasiParalel::where('id_user', $request->id_user)
                ->where('nomor_surat', $request->nomor_surat)
                ->firstOrFail();
        }

        // Support composite key encoded as id_user:nomor_surat
        $parts = explode(':', $id, 2);
        if (count($parts) === 2) {
            return VerifikasiParalel::where('id_user', $parts[0])
                ->where('nomor_surat', $parts[1])
                ->firstOrFail();
        }

        return VerifikasiParalel::where('nomor_surat', $id)->firstOrFail();
    }
}
