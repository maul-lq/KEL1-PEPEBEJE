<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorFileLegalitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::with('filesLegalitas');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%")
                    ->orWhere('nib', 'like', "%{$search}%");
            });
        }

        $vendors = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $vendors,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'npwp' => 'required|string|max:30|unique:vendors,npwp',
            'nib' => 'required|string|max:30|unique:vendors,nib',
            'nama_perusahaan' => 'required|string|max:200',
            'alamat' => 'required|string',
            'is_active' => 'boolean',
            'nama_pic' => 'required|string|max:100',
            'telepon_pic' => 'required|string|max:20',
            'email_pic' => 'required|email|max:100',
            'nama_bank' => 'required|string|max:100',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama' => 'required|string|max:150',
            'file_legalitas' => 'nullable|string|max:255',
            'nama_dokumen' => 'nullable|string|max:150',
        ]);

        $vendor = Vendor::create($validated);

        if ($request->filled('file_legalitas')) {
            VendorFileLegalitas::create([
                'npwp' => $vendor->npwp,
                'nama_dokumen' => $request->nama_dokumen ?? 'Dokumen Legalitas Utama',
                'file_legalitas' => $request->file_legalitas,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil ditambahkan',
            'data' => $vendor->load('filesLegalitas'),
        ], 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $vendor->load(['filesLegalitas', 'paketPengadaan']),
        ]);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'nib' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('vendors', 'nib')->ignore($vendor->npwp, 'npwp')],
            'nama_perusahaan' => 'sometimes|required|string|max:200',
            'alamat' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
            'nama_pic' => 'sometimes|required|string|max:100',
            'telepon_pic' => 'sometimes|required|string|max:20',
            'email_pic' => 'sometimes|required|email|max:100',
            'nama_bank' => 'sometimes|required|string|max:100',
            'nomor_rekening' => 'sometimes|required|string|max:50',
            'atas_nama' => 'sometimes|required|string|max:150',
        ]);

        $vendor->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil diperbarui',
            'data' => $vendor->load('filesLegalitas'),
        ]);
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        $vendor->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil dihapus',
        ]);
    }
}
