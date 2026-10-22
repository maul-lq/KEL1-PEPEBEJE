<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Mengembalikan daftar notifikasi belum dibaca (unread) terbaru khusus untuk pemicu notifikasi native OS Desktop.
     * Mendukung query parameter `since_id` agar peramban hanya menerima notifikasi yang lebih baru dari ID terakhir.
     */
    public function notifikasiTerbaru(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'unauthenticated', 'data' => []], 401);
        }

        $query = NotifikasiUser::where('id_user', $user->id_user)
            ->where('is_read', false)
            ->where('is_dismissed', false)
            ->with(['reminder.paket.permohonan']);

        // Saring hanya notifikasi yang terbit setelah ID tertentu jika dikirimkan oleh browser
        if ($request->filled('since_id')) {
            $query->where('id', '>', (int) $request->since_id);
        }

        $list = $query->latest('id')->take(10)->get()->map(function (NotifikasiUser $n) {
            return [
                'id' => $n->id,
                'judul' => $n->judul,
                'pesan' => $n->pesan,
                'badge' => $n->jenis_badge,
                'referensi' => $n->referensi_teks,
                'action_url' => $n->action_url,
                'action_label' => $n->action_label,
                'created_at' => $n->created_at?->translatedFormat('d M Y · H.i').' WIB' ?? 'Baru saja',
            ];
        });

        return response()->json([
            'status' => 'success',
            'count' => $list->count(),
            'data' => $list,
        ]);
    }
}
