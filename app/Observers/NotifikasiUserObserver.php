<?php

namespace App\Observers;

use App\Models\NotifikasiUser;
use App\Notifications\PengingatPengadaanNotification;
use Illuminate\Support\Facades\Log;

/**
 * Pengamat Perubahan Model Notifikasi Pengguna (NotifikasiUserObserver)
 *
 * Bertugas mengotomatisasi pengiriman email notifikasi secara langsung
 * setiap kali rekaman `notifikasi_user` baru disimpan ke basis data,
 * selama pengguna terkait mengaktifkan opsi pengingat email (`reminder_email_aktif`).
 */
class NotifikasiUserObserver
{
    /**
     * Menangani event 'created' saat satu record NotifikasiUser baru berhasil disimpan.
     */
    public function created(NotifikasiUser $notifikasi): void
    {
        $user = $notifikasi->user ?? $notifikasi->user()->first();

        if (! $user || empty($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if ($user->reminder_email_aktif === false) {
            return;
        }

        try {
            $user->notify(new PengingatPengadaanNotification($notifikasi));
        } catch (\Throwable $exception) {
            Log::warning("Gagal mengirimkan email notifikasi ke {$user->email}: {$exception->getMessage()}", [
                'id_notifikasi_user' => $notifikasi->id,
                'user_id' => $user->id_user,
                'judul' => $notifikasi->judul,
            ]);
        }
    }
}
