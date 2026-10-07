<?php

namespace App\Http\Controllers;

use App\Models\MemoBayar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemoBayarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemoBayar::with(['user', 'pembayaran']);

        if ($request->filled('nomor_pembayaran')) {
            $query->where('nomor_pembayaran', $request->nomor_pembayaran);
        }

        if ($request->filled('status')) {
            $query->where('status_memo', $request->status);
        }

        if ($request->filled('role')) {
            $query->where('role_approver', $request->role);
        }

        $memos = $query->latest('tanggal_acc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $memos,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'approvers' => [
                MemoBayar::ROLE_PPK,
                MemoBayar::ROLE_WADIR_2,
            ],
            'statuses' => [
                MemoBayar::STATUS_DISETUJUI,
                MemoBayar::STATUS_DITOLAK,
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
            'nomor_pembayaran' => ['required', 'string', 'max:100'],
            'nomor_paket' => ['required', 'string', 'max:100'],
            'role_approver' => ['required', 'string', Rule::in([
                MemoBayar::ROLE_PPK,
                MemoBayar::ROLE_WADIR_2,
            ])],
            'status_memo' => ['required', 'string', Rule::in([
                MemoBayar::STATUS_DISETUJUI,
                MemoBayar::STATUS_DITOLAK,
            ])],
            'catatan_memo' => ['nullable', 'string'],
            'tanggal_acc' => ['nullable', 'date'],
        ]);

        $exists = MemoBayar::where('id_user', $validated['id_user'])
            ->where('nomor_pembayaran', $validated['nomor_pembayaran'])
            ->where('nomor_paket', $validated['nomor_paket'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Memo persetujuan ini sudah pernah dicatat oleh approver tersebut',
            ], 422);
        }

        $validated['tanggal_acc'] = $validated['tanggal_acc'] ?? now();

        $memo = MemoBayar::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Memo persetujuan bayar berhasil dicatat',
            'data' => $memo,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $memo = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $memo->load(['user', 'pembayaran']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $memo = $this->findRecord($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $memo,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $memo = $this->findRecord($request, $id);

        $validated = $request->validate([
            'status_memo' => ['sometimes', 'required', 'string', Rule::in([
                MemoBayar::STATUS_DISETUJUI,
                MemoBayar::STATUS_DITOLAK,
            ])],
            'catatan_memo' => ['nullable', 'string'],
            'tanggal_acc' => ['nullable', 'date'],
        ]);

        MemoBayar::where('id_user', $memo->id_user)
            ->where('nomor_pembayaran', $memo->nomor_pembayaran)
            ->where('nomor_paket', $memo->nomor_paket)
            ->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Memo persetujuan bayar berhasil diperbarui',
            'data' => $memo->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $memo = $this->findRecord($request, $id);

        MemoBayar::where('id_user', $memo->id_user)
            ->where('nomor_pembayaran', $memo->nomor_pembayaran)
            ->where('nomor_paket', $memo->nomor_paket)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Memo persetujuan bayar berhasil dihapus',
        ]);
    }

    protected function findRecord(Request $request, $id): MemoBayar
    {
        if ($request->filled('id_user') && $request->filled('nomor_pembayaran') && $request->filled('nomor_paket')) {
            return MemoBayar::where('id_user', $request->id_user)
                ->where('nomor_pembayaran', $request->nomor_pembayaran)
                ->where('nomor_paket', $request->nomor_paket)
                ->firstOrFail();
        }

        $parts = explode(':', $id, 3);
        if (count($parts) === 3) {
            return MemoBayar::where('id_user', $parts[0])
                ->where('nomor_pembayaran', $parts[1])
                ->where('nomor_paket', $parts[2])
                ->firstOrFail();
        }

        return MemoBayar::where('nomor_pembayaran', $id)->firstOrFail();
    }
}
