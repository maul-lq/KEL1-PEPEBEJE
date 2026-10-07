<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pembayaran::with(['paket', 'memoBayar', 'transaksiPencairan']);

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('status')) {
            $query->where('status_pembayaran', $request->status);
        }

        $pembayaran = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'statuses' => [
                Pembayaran::STATUS_DRAFT,
                Pembayaran::STATUS_REVIU_MEMO,
                Pembayaran::STATUS_PERINTAH_BAYAR,
                Pembayaran::STATUS_LUNAS,
                Pembayaran::STATUS_DITOLAK,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_pembayaran' => ['required', 'string', 'max:100'],
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'tahap_termin' => ['nullable', 'integer', 'min:1'],
            'nominal_pengajuan' => ['required', 'numeric', 'min:0'],
            'status_pembayaran' => ['required', 'string', Rule::in([
                Pembayaran::STATUS_DRAFT,
                Pembayaran::STATUS_REVIU_MEMO,
                Pembayaran::STATUS_PERINTAH_BAYAR,
                Pembayaran::STATUS_LUNAS,
                Pembayaran::STATUS_DITOLAK,
            ])],
            'file_dokumen_penunjang' => ['nullable', 'string', 'max:255'],
            'file_tagihan_vendor' => ['nullable', 'string', 'max:255'],
        ]);

        $exists = Pembayaran::where('nomor_pembayaran', $validated['nomor_pembayaran'])
            ->where('nomor_paket', $validated['nomor_paket'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengajuan pembayaran untuk nomor dan paket ini sudah ada',
            ], 422);
        }

        $validated['tahap_termin'] = $validated['tahap_termin'] ?? 1;

        $pembayaran = Pembayaran::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pembayaran berhasil dibuat',
            'data' => $pembayaran,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $pembayaran = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $pembayaran->load(['paket', 'memoBayar', 'transaksiPencairan']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $pembayaran = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $pembayaran = $this->findRecord($request, $id);

        $validated = $request->validate([
            'tahap_termin' => ['sometimes', 'required', 'integer', 'min:1'],
            'nominal_pengajuan' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status_pembayaran' => ['sometimes', 'required', 'string', Rule::in([
                Pembayaran::STATUS_DRAFT,
                Pembayaran::STATUS_REVIU_MEMO,
                Pembayaran::STATUS_PERINTAH_BAYAR,
                Pembayaran::STATUS_LUNAS,
                Pembayaran::STATUS_DITOLAK,
            ])],
            'file_dokumen_penunjang' => ['nullable', 'string', 'max:255'],
            'file_tagihan_vendor' => ['nullable', 'string', 'max:255'],
        ]);

        $pembayaran->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data pembayaran berhasil diperbarui',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $pembayaran = $this->findRecord($request, $id);
        $pembayaran->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data pembayaran berhasil dihapus',
        ]);
    }

    protected function findRecord(Request $request, $id): Pembayaran
    {
        if ($request->filled('nomor_pembayaran') && $request->filled('nomor_paket')) {
            return Pembayaran::where('nomor_pembayaran', $request->nomor_pembayaran)
                ->where('nomor_paket', $request->nomor_paket)
                ->firstOrFail();
        }

        $parts = explode(':', $id, 2);
        if (count($parts) === 2) {
            return Pembayaran::where('nomor_pembayaran', $parts[0])
                ->where('nomor_paket', $parts[1])
                ->firstOrFail();
        }

        return Pembayaran::where('nomor_pembayaran', $id)->firstOrFail();
    }
}
