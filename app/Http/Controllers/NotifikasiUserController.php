<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user_id ?? auth()->id();

        $query = NotifikasiUser::with('reminder.paket');

        if ($userId) {
            $query->where('id_user', $userId);
        }

        if ($request->has('unread_only') && filter_var($request->unread_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_read', false);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('id')->get(),
        ]);
    }

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
