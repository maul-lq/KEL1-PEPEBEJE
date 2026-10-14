<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reminder::with(['paket', 'notifikasiUser.user']);

        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        if ($request->filled('tipe_reminder')) {
            $query->where('tipe_reminder', $request->tipe_reminder);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('waktu_trigger')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'tipe_reminder' => 'required|string|in:pengiriman,h_minus_2_termin,bulanan_tahunan',
            'waktu_trigger' => 'required|date',
            'is_recurring' => 'boolean',
            'interval_bulan' => 'nullable|integer',
            'judul_alert' => 'required|string|max:150',
            'pesan_alert' => 'required|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id_user',
        ]);

        $reminder = Reminder::create($validated);

        if (! empty($validated['user_ids'])) {
            foreach ($validated['user_ids'] as $uid) {
                NotifikasiUser::create([
                    'id_user' => $uid,
                    'id_reminder' => $reminder->id_reminder,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengingat jadwal (reminder) berhasil dibuat',
            'data' => $reminder->load(['paket', 'notifikasiUser']),
        ], 201);
    }

    public function show(Reminder $reminder): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $reminder->load(['paket', 'notifikasiUser.user']),
        ]);
    }

    public function update(Request $request, Reminder $reminder): JsonResponse
    {
        $validated = $request->validate([
            'waktu_trigger' => 'sometimes|required|date',
            'judul_alert' => 'sometimes|required|string|max:150',
            'pesan_alert' => 'sometimes|required|string',
        ]);

        $reminder->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Reminder berhasil diperbarui',
            'data' => $reminder,
        ]);
    }

    public function destroy(Reminder $reminder): JsonResponse
    {
        $reminder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reminder berhasil dihapus',
        ]);
    }
}
