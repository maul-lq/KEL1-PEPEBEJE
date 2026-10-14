<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($request->filled('nama_entitas')) {
            $query->where('nama_entitas', $request->nama_entitas);
        }

        if ($request->filled('id_entitas')) {
            $query->where('id_entitas', $request->id_entitas);
        }

        if ($request->filled('jenis_aksi')) {
            $query->where('jenis_aksi', $request->jenis_aksi);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('waktu_log')->paginate(20),
        ]);
    }

    public function show(AuditLog $audit_log): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $audit_log->load('user'),
        ]);
    }
}
