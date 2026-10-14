<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Jadwal Pengingat / Alarm Sistem (ReminderController)
 *
 * Mengelola sistem alarm otomatis jadwal pengadaan (NFR 5 Ketepatan Notifikasi):
 * - Pengingat batas akhir pengiriman barang oleh vendor rekanan.
 * - Pengingat H-2 sebelum jatuh tempo pembayaran termin kepada penyedia.
 * - Pengingat berkala bulanan/tahunan untuk kontrak pemeliharaan berkelanjutan.
 * - Mendistribusikan notifikasi personal ke pengguna terkait (NotifikasiUser).
 */
class ReminderController extends Controller
{
    /**
     * Menampilkan daftar pengingat jadwal dengan filter nomor paket dan tipe reminder.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reminder::with(['paket', 'notifikasiUser.user']);

        // Filter berdasarkan nomor paket pengadaan tertentu
        if ($request->filled('nomor_paket')) {
            $query->where('nomor_paket', $request->nomor_paket);
        }

        // Filter berdasarkan tipe pengingat ('pengiriman', 'h_minus_2_termin', 'bulanan_tahunan')
        if ($request->filled('tipe_reminder')) {
            $query->where('tipe_reminder', $request->tipe_reminder);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('waktu_trigger')->paginate(15),
        ]);
    }

    /**
     * Menyimpan jadwal pengingat baru dan menyebarkannya ke feed notifikasi pengguna penerima.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->filled('waktu_trigger')) {
            $request->merge(['waktu_trigger' => $request->input('tanggal_pemicu', now()->addDays(7))]);
        }
        if (! $request->filled('tipe_reminder') || in_array($request->input('tipe_reminder'), ['verifikasi', 'pembayaran'])) {
            $request->merge(['tipe_reminder' => 'pengiriman']);
        }

        // 1. Validasi konfigurasi waktu picu, judul, pesan, dan daftar penerima
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

        // 2. Simpan record reminder ke database
        $reminder = Reminder::create($validated);
        if ($request->filled('target_role')) {
            $reminder->target_role = $request->input('target_role');
        }

        // 3. Buat feed entri notifikasi ke setiap ID pengguna yang menjadi target penerima
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

    /**
     * Menampilkan detail satu jadwal pengingat beserta daftar pengguna yang menerima alert.
     */
    public function show(Reminder $reminder): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $reminder->load(['paket', 'notifikasiUser.user']),
        ]);
    }

    /**
     * Memperbarui jadwal waktu picu atau pesan alarm pengingat.
     */
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

    /**
     * Menghapus jadwal pengingat dari database.
     */
    public function destroy(Reminder $reminder): JsonResponse
    {
        $reminder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reminder berhasil dihapus',
        ]);
    }
}
