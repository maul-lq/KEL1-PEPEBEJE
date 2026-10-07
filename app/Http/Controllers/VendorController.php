<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%")
                    ->orWhere('nib', 'like', "%{$search}%")
                    ->orWhere('nama_pic', 'like', "%{$search}%");
            });
        }

        $vendors = $query->latest('created_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $vendors,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to create vendor',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'npwp' => ['required', 'string', 'max:30', 'unique:vendors,npwp'],
            'nib' => ['required', 'string', 'max:30', 'unique:vendors,nib'],
            'nama_perusahaan' => ['required', 'string', 'max:200'],
            'alamat' => ['required', 'string'],
            'file_legalitas' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'nama_pic' => ['required', 'string', 'max:100'],
            'telepon_pic' => ['required', 'string', 'max:20'],
            'email_pic' => ['required', 'email', 'max:100'],
            'nama_bank' => ['required', 'string', 'max:100'],
            'nomor_rekening' => ['required', 'string', 'max:50'],
            'atas_nama' => ['required', 'string', 'max:150'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $vendor = Vendor::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil didaftarkan',
            'data' => $vendor,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($npwp): JsonResponse
    {
        $vendor = Vendor::with('paketPengadaan')->findOrFail($npwp);

        return response()->json([
            'status' => 'success',
            'data' => $vendor,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($npwp): JsonResponse
    {
        $vendor = Vendor::findOrFail($npwp);

        return response()->json([
            'status' => 'success',
            'data' => $vendor,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $npwp): JsonResponse
    {
        $vendor = Vendor::findOrFail($npwp);

        $validated = $request->validate([
            'nib' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('vendors', 'nib')->ignore($vendor->npwp, 'npwp')],
            'nama_perusahaan' => ['sometimes', 'required', 'string', 'max:200'],
            'alamat' => ['sometimes', 'required', 'string'],
            'file_legalitas' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'nama_pic' => ['sometimes', 'required', 'string', 'max:100'],
            'telepon_pic' => ['sometimes', 'required', 'string', 'max:20'],
            'email_pic' => ['sometimes', 'required', 'email', 'max:100'],
            'nama_bank' => ['sometimes', 'required', 'string', 'max:100'],
            'nomor_rekening' => ['sometimes', 'required', 'string', 'max:50'],
            'atas_nama' => ['sometimes', 'required', 'string', 'max:150'],
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $vendor->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil diperbarui',
            'data' => $vendor,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($npwp): JsonResponse
    {
        $vendor = Vendor::findOrFail($npwp);
        $vendor->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil dihapus',
        ]);
    }
}
