<?php

namespace App\Http\Controllers;

use App\Models\PengadaanLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengadaanLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PengadaanLog::with('user');

        if ($request->filled('nomor_surat')) {
            $query->where('nomor_surat', $request->nomor_surat);
        }

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('aksi')) {
            $query->where('aksi', $request->aksi);
        }

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->id_user);
        }

        $logs = $query->latest('created_at')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Ready to record log audit',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_surat' => ['nullable', 'string', 'max:100'],
            'nomor_paket' => ['nullable', 'string', 'max:100'],
            'id_user' => ['required', 'exists:users,id_user'],
            'aksi' => ['required', 'string', 'max:100'],
            'keterangan' => ['required', 'string'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'created_at' => ['nullable', 'date'],
        ]);

        $validated['ip_address'] = $validated['ip_address'] ?? $request->ip();
        $validated['created_at'] = $validated['created_at'] ?? now();

        $log = PengadaanLog::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Log audit berhasil dicatat',
            'data' => $log,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $log = PengadaanLog::with('user')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $log,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        $log = PengadaanLog::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $log,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $log = PengadaanLog::findOrFail($id);

        $validated = $request->validate([
            'aksi' => ['sometimes', 'required', 'string', 'max:100'],
            'keterangan' => ['sometimes', 'required', 'string'],
        ]);

        $log->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Log audit berhasil diperbarui',
            'data' => $log,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $log = PengadaanLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Log audit berhasil dihapus',
        ]);
    }
}
