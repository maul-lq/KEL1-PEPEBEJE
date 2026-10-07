<?php

namespace App\Http\Controllers;

use App\Models\PaketPengadaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaketPengadaanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaketPengadaan::with(['permohonan', 'anggaranMak', 'ppbjUser', 'ppUser', 'ppkUser', 'vendor']);

        if ($request->filled('status')) {
            $query->where('status_paket', $request->status);
        }

        if ($request->filled('jalur')) {
            $query->where('jalur_routing', $request->jalur);
        }

        if ($request->filled('jenis')) {
            $query->where('jenis_pengadaan', $request->jenis);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_paket', 'like', "%{$search}%")
                    ->orWhere('nama_paket', 'like', "%{$search}%")
                    ->orWhere('nomor_surat', 'like', "%{$search}%");
            });
        }

        $paket = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $paket,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'jenis_pengadaan' => [
                PaketPengadaan::JENIS_BARANG,
                PaketPengadaan::JENIS_JASA_LAINNYA,
                PaketPengadaan::JENIS_KONSTRUKSI,
                PaketPengadaan::JENIS_JASA_KONSULTANSI,
            ],
            'metode_pengadaan' => [
                PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
                PaketPengadaan::METODE_E_PURCHASING,
                PaketPengadaan::METODE_TENDER,
                PaketPengadaan::METODE_NON_TENDER_SPSE,
                PaketPengadaan::METODE_INAPROC,
            ],
            'jalur_routing' => [
                PaketPengadaan::JALUR_LANGSUNG,
                PaketPengadaan::JALUR_PP,
                PaketPengadaan::JALUR_PPK,
            ],
            'status_paket' => [
                PaketPengadaan::STATUS_REGISTRASI_PPBJ,
                PaketPengadaan::STATUS_REVIU_DOKUMEN,
                PaketPengadaan::STATUS_SPK_TERBIT,
                PaketPengadaan::STATUS_PENGIRIMAN,
                PaketPengadaan::STATUS_PENERIMAAN,
                PaketPengadaan::STATUS_PEMBAYARAN,
                PaketPengadaan::STATUS_SELESAI,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => ['required', 'string', 'max:100', 'unique:paket_pengadaan,nomor_paket'],
            'nama_paket' => ['required', 'string', 'max:255'],
            'jenis_pengadaan' => ['required', 'string', Rule::in([
                PaketPengadaan::JENIS_BARANG,
                PaketPengadaan::JENIS_JASA_LAINNYA,
                PaketPengadaan::JENIS_KONSTRUKSI,
                PaketPengadaan::JENIS_JASA_KONSULTANSI,
            ])],
            'metode_pengadaan' => ['required', 'string', Rule::in([
                PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
                PaketPengadaan::METODE_E_PURCHASING,
                PaketPengadaan::METODE_TENDER,
                PaketPengadaan::METODE_NON_TENDER_SPSE,
                PaketPengadaan::METODE_INAPROC,
            ])],
            'nilai_hps' => ['required', 'numeric', 'min:0'],
            'status_paket' => ['required', 'string', Rule::in([
                PaketPengadaan::STATUS_REGISTRASI_PPBJ,
                PaketPengadaan::STATUS_REVIU_DOKUMEN,
                PaketPengadaan::STATUS_SPK_TERBIT,
                PaketPengadaan::STATUS_PENGIRIMAN,
                PaketPengadaan::STATUS_PENERIMAAN,
                PaketPengadaan::STATUS_PEMBAYARAN,
                PaketPengadaan::STATUS_SELESAI,
            ])],
            'jalur_routing' => ['required', 'string', Rule::in([
                PaketPengadaan::JALUR_LANGSUNG,
                PaketPengadaan::JALUR_PP,
                PaketPengadaan::JALUR_PPK,
            ])],
            'id_paket_lkpp' => ['nullable', 'string', 'max:100'],
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'ppbj_user_id' => ['required', 'exists:users,id_user'],
            'pp_user_id' => ['nullable', 'exists:users,id_user'],
            'ppk_user_id' => ['nullable', 'exists:users,id_user'],
            'npwp_vendor' => ['nullable', 'exists:vendors,npwp'],
        ]);

        $paket = PaketPengadaan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil dibuat',
            'data' => $paket,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($nomorPaket): JsonResponse
    {
        $paket = PaketPengadaan::with([
            'permohonan',
            'anggaranMak',
            'ppbjUser',
            'ppUser',
            'ppkUser',
            'vendor',
            'reviu',
            'riwayatPpk',
            'kontrakSpk',
            'penerimaanBarang',
            'pembayaran',
            'reminders',
        ])->findOrFail($nomorPaket);

        return response()->json([
            'status' => 'success',
            'data' => $paket,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($nomorPaket): JsonResponse
    {
        $paket = PaketPengadaan::findOrFail($nomorPaket);

        return response()->json([
            'status' => 'success',
            'data' => $paket,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $nomorPaket): JsonResponse
    {
        $paket = PaketPengadaan::findOrFail($nomorPaket);

        $validated = $request->validate([
            'nama_paket' => ['sometimes', 'required', 'string', 'max:255'],
            'jenis_pengadaan' => ['sometimes', 'required', 'string', Rule::in([
                PaketPengadaan::JENIS_BARANG,
                PaketPengadaan::JENIS_JASA_LAINNYA,
                PaketPengadaan::JENIS_KONSTRUKSI,
                PaketPengadaan::JENIS_JASA_KONSULTANSI,
            ])],
            'metode_pengadaan' => ['sometimes', 'required', 'string', Rule::in([
                PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
                PaketPengadaan::METODE_E_PURCHASING,
                PaketPengadaan::METODE_TENDER,
                PaketPengadaan::METODE_NON_TENDER_SPSE,
                PaketPengadaan::METODE_INAPROC,
            ])],
            'nilai_hps' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status_paket' => ['sometimes', 'required', 'string', Rule::in([
                PaketPengadaan::STATUS_REGISTRASI_PPBJ,
                PaketPengadaan::STATUS_REVIU_DOKUMEN,
                PaketPengadaan::STATUS_SPK_TERBIT,
                PaketPengadaan::STATUS_PENGIRIMAN,
                PaketPengadaan::STATUS_PENERIMAAN,
                PaketPengadaan::STATUS_PEMBAYARAN,
                PaketPengadaan::STATUS_SELESAI,
            ])],
            'jalur_routing' => ['sometimes', 'required', 'string', Rule::in([
                PaketPengadaan::JALUR_LANGSUNG,
                PaketPengadaan::JALUR_PP,
                PaketPengadaan::JALUR_PPK,
            ])],
            'id_paket_lkpp' => ['nullable', 'string', 'max:100'],
            'kode_mak' => ['sometimes', 'required', 'exists:anggaran_mak,kode_mak'],
            'pp_user_id' => ['nullable', 'exists:users,id_user'],
            'ppk_user_id' => ['nullable', 'exists:users,id_user'],
            'npwp_vendor' => ['nullable', 'exists:vendors,npwp'],
        ]);

        $paket->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil diperbarui',
            'data' => $paket,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($nomorPaket): JsonResponse
    {
        $paket = PaketPengadaan::findOrFail($nomorPaket);
        $paket->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil dihapus',
        ]);
    }
}
