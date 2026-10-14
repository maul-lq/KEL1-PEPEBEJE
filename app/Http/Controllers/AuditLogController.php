<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Jejak Audit Sistem Pengadaan (AuditLogController)
 *
 * Menyajikan rekaman jejak audit forensik dan log aktivitas bagi auditor/pengawas:
 * - Menampilkan daftar mutasi data (nama_entitas, jenis_aksi, id_entitas, nilai lama, nilai baru, aktor).
 * - Mendukung penyaringan berdasarkan nama tabel entitas, jenis aksi, dan ID referensi.
 */
class AuditLogController extends Controller
{
    /**
     * Menampilkan daftar rekaman jejak audit sistem dengan paginasi 20 item per halaman.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        // Filter berdasarkan nama entitas/tabel (misal: 'permohonan_pengadaan', 'paket_pengadaan')
        if ($request->filled('nama_entitas')) {
            $query->where('nama_entitas', $request->nama_entitas);
        }

        // Filter berdasarkan identifier entitas (misal: nomor surat atau nomor paket)
        if ($request->filled('id_entitas')) {
            $query->where('id_entitas', $request->id_entitas);
        }

        // Filter berdasarkan jenis aksi (misal: 'REASSIGN_PPK', 'PENCAIRAN_SP2D_SELESAI')
        if ($request->filled('jenis_aksi')) {
            $query->where('jenis_aksi', $request->jenis_aksi);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('waktu_log')->paginate(20),
        ]);
    }

    /**
     * Menampilkan detail lengkap satu entri log audit beserta aktor pengguna yang mengeksekusinya.
     */
    public function show(AuditLog $audit_log): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $audit_log->load('user'),
        ]);
    }

    /**
     * Mencatat entri log aktivitas baru melalui API.
     */
    public function store(Request $request): JsonResponse
    {
        $log = AuditLog::catat(
            $request->input('id_user') ?? $request->user()?->id_user ?? 1,
            $request->input('aksi') ?? $request->input('jenis_aksi', 'AKTIVITAS'),
            $request->input('nama_entitas', 'paket_pengadaan'),
            $request->input('nomor_paket') ?? $request->input('id_entitas', 'GENERAL'),
            $request->input('nilai_lama'),
            $request->input('nilai_baru'),
            $request->input('keterangan') ?? $request->input('alasan')
        );

        return response()->json([
            'status' => 'success',
            'data' => $log,
        ], 201);
    }
}
