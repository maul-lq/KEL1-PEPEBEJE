<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = Unit::withCount(['users', 'permohonanPengadaan'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $units,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_unit' => 'required|string|max:20|unique:units,kode_unit',
            'nama_unit' => 'required|string|max:150',
            'jenis_unit' => 'required|string|max:50',
        ]);

        $unit = Unit::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil ditambahkan',
            'data' => $unit,
        ], 201);
    }

    public function show(string $kode_unit): JsonResponse
    {
        $unit = Unit::with(['users', 'permohonanPengadaan'])->findOrFail($kode_unit);

        return response()->json([
            'status' => 'success',
            'data' => $unit,
        ]);
    }

    public function update(Request $request, string $kode_unit): JsonResponse
    {
        $unit = Unit::findOrFail($kode_unit);

        $validated = $request->validate([
            'nama_unit' => 'sometimes|required|string|max:150',
            'jenis_unit' => 'sometimes|required|string|max:50',
        ]);

        $unit->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil diperbarui',
            'data' => $unit,
        ]);
    }

    public function destroy(string $kode_unit): JsonResponse
    {
        $unit = Unit::findOrFail($kode_unit);
        $unit->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil dihapus',
        ]);
    }
}
