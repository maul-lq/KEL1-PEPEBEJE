<?php

namespace App\Http\Controllers;

use App\Models\KontrakSPK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KontrakSPKController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = KontrakSPK::with('paket');

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('tipe_kontrak')) {
            $query->where('tipe_kontrak', $request->tipe_kontrak);
        }

        $kontrak = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $kontrak,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'tipe_kontrak' => [
                KontrakSPK::TIPE_SEKALI_SELESAI,
                KontrakSPK::TIPE_KONTRAK_TAHUNAN,
                KontrakSPK::TIPE_TERMIN_BULANAN,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_spk' => ['required', 'string', 'max:100'],
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'tipe_kontrak' => ['required', 'string', Rule::in([
                KontrakSPK::TIPE_SEKALI_SELESAI,
                KontrakSPK::TIPE_KONTRAK_TAHUNAN,
                KontrakSPK::TIPE_TERMIN_BULANAN,
            ])],
            'tanggal_spk' => ['required', 'date'],
            'jadwal_pengiriman' => ['required', 'date'],
            'nilai_kontrak' => ['required', 'numeric', 'min:0'],
            'file_spk' => ['required', 'string', 'max:255'],
        ]);

        $exists = KontrakSPK::where('nomor_spk', $validated['nomor_spk'])
            ->where('nomor_paket', $validated['nomor_paket'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kontrak SPK dengan nomor dan paket ini sudah ada',
            ], 422);
        }

        $kontrak = KontrakSPK::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak SPK berhasil dibuat',
            'data' => $kontrak,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $kontrak = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $kontrak->load('paket'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $kontrak = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $kontrak,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $kontrak = $this->findRecord($request, $id);

        $validated = $request->validate([
            'tipe_kontrak' => ['sometimes', 'required', 'string', Rule::in([
                KontrakSPK::TIPE_SEKALI_SELESAI,
                KontrakSPK::TIPE_KONTRAK_TAHUNAN,
                KontrakSPK::TIPE_TERMIN_BULANAN,
            ])],
            'tanggal_spk' => ['sometimes', 'required', 'date'],
            'jadwal_pengiriman' => ['sometimes', 'required', 'date'],
            'nilai_kontrak' => ['sometimes', 'required', 'numeric', 'min:0'],
            'file_spk' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $kontrak->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak SPK berhasil diperbarui',
            'data' => $kontrak,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $kontrak = $this->findRecord($request, $id);
        $kontrak->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak SPK berhasil dihapus',
        ]);
    }

    protected function findRecord(Request $request, $id): KontrakSPK
    {
        if ($request->filled('nomor_spk') && $request->filled('nomor_paket')) {
            return KontrakSPK::where('nomor_spk', $request->nomor_spk)
                ->where('nomor_paket', $request->nomor_paket)
                ->firstOrFail();
        }

        $parts = explode(':', $id, 2);
        if (count($parts) === 2) {
            return KontrakSPK::where('nomor_spk', $parts[0])
                ->where('nomor_paket', $parts[1])
                ->firstOrFail();
        }

        return KontrakSPK::where('nomor_spk', $id)->firstOrFail();
    }
}
