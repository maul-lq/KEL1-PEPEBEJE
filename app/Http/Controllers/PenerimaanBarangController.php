<?php

namespace App\Http\Controllers;

use App\Models\PenerimaanBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenerimaanBarangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PenerimaanBarang::with(['paket', 'user', 'fotoDokumentasi']);

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('status_fisik')) {
            $query->where('status_fisik', $request->status_fisik);
        }

        $penerimaan = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $penerimaan,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'status_fisik' => [
                PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
                PenerimaanBarang::STATUS_DITERIMA_SEBAGIAN,
                PenerimaanBarang::STATUS_PENDING_RUSAK,
                PenerimaanBarang::STATUS_DITOLAK,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_bast' => ['required', 'string', 'max:100'],
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'tanggal_penerimaan' => ['required', 'date'],
            'status_fisik' => ['required', 'string', Rule::in([
                PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
                PenerimaanBarang::STATUS_DITERIMA_SEBAGIAN,
                PenerimaanBarang::STATUS_PENDING_RUSAK,
                PenerimaanBarang::STATUS_DITOLAK,
            ])],
            'ttd_digital_bast' => ['required', 'string'],
            'file_bast_signed' => ['required', 'string', 'max:255'],
            'file_penerimaan_pihak3' => ['nullable', 'string', 'max:255'],
            'catatan_pemeriksaan' => ['nullable', 'string'],
            'id_user' => ['required', 'exists:users,id_user'],
        ]);

        $exists = PenerimaanBarang::where('nomor_bast', $validated['nomor_bast'])
            ->where('nomor_paket', $validated['nomor_paket'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'BAST untuk nomor dan paket ini sudah ada',
            ], 422);
        }

        $penerimaan = PenerimaanBarang::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Penerimaan barang BAST berhasil disimpan',
            'data' => $penerimaan,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $penerimaan = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $penerimaan->load(['paket', 'user', 'fotoDokumentasi']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $penerimaan = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $penerimaan,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $penerimaan = $this->findRecord($request, $id);

        $validated = $request->validate([
            'tanggal_penerimaan' => ['sometimes', 'required', 'date'],
            'status_fisik' => ['sometimes', 'required', 'string', Rule::in([
                PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
                PenerimaanBarang::STATUS_DITERIMA_SEBAGIAN,
                PenerimaanBarang::STATUS_PENDING_RUSAK,
                PenerimaanBarang::STATUS_DITOLAK,
            ])],
            'ttd_digital_bast' => ['sometimes', 'required', 'string'],
            'file_bast_signed' => ['sometimes', 'required', 'string', 'max:255'],
            'file_penerimaan_pihak3' => ['nullable', 'string', 'max:255'],
            'catatan_pemeriksaan' => ['nullable', 'string'],
        ]);

        $penerimaan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Penerimaan barang BAST berhasil diperbarui',
            'data' => $penerimaan,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $penerimaan = $this->findRecord($request, $id);
        $penerimaan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Penerimaan barang BAST berhasil dihapus',
        ]);
    }

    protected function findRecord(Request $request, $id): PenerimaanBarang
    {
        if ($request->filled('nomor_bast') && $request->filled('nomor_paket')) {
            return PenerimaanBarang::where('nomor_bast', $request->nomor_bast)
                ->where('nomor_paket', $request->nomor_paket)
                ->firstOrFail();
        }

        $parts = explode(':', $id, 2);
        if (count($parts) === 2) {
            return PenerimaanBarang::where('nomor_bast', $parts[0])
                ->where('nomor_paket', $parts[1])
                ->firstOrFail();
        }

        return PenerimaanBarang::where('nomor_bast', $id)->firstOrFail();
    }
}
