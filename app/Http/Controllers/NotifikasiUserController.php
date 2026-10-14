<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Feed Notifikasi Personal Pengguna (NotifikasiUserController)
 *
 * Mengelola feed notifikasi dan peringatan aktif yang ditujukan kepada setiap pengguna:
 * - Menampilkan daftar notifikasi (dapat disaring khusus yang belum dibaca).
 * - Menandai notifikasi telah dibaca (is_read = true, mencatat waktu_dibaca).
 * - Mengabaikan atau mengarsipkan notifikasi dari tampilan feed (is_dismissed = true).
 */
class NotifikasiUserController extends Controller
{
    /**
     * Menampilkan daftar notifikasi milik pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user_id ?? auth()->id();

        $query = NotifikasiUser::with('reminder.paket');

        // Saring khusus notifikasi yang ditujukan ke ID user tertentu
        if ($userId) {
            $query->where('id_user', $userId);
        }

        // Filter opsi hanya menampilkan notifikasi yang belum dibaca (unread_only = true)
        if ($request->has('unread_only') && filter_var($request->unread_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_read', false);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('id')->get(),
        ]);
    }

    /**
     * Menandai satu notifikasi telah dibuka dan dibaca oleh pengguna.
     */
    public function markAsRead(NotifikasiUser $notifikasi): JsonResponse
    {
        $notifikasi->is_read = true;
        $notifikasi->waktu_dibaca = now();
        $notifikasi->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi ditandai sudah dibaca',
            'data' => $notifikasi,
        ]);
    }

    /**
     * Mengabaikan (dismiss) notifikasi sehingga tidak lagi muncul di feed aktif.
     */
    public function dismiss(NotifikasiUser $notifikasi): JsonResponse
    {
        $notifikasi->is_dismissed = true;
        $notifikasi->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi diabaikan',
        ]);
    }
}
