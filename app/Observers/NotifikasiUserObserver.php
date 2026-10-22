<?php

namespace App\Observers;

use App\Models\NotifikasiUser;
use App\Notifications\PengingatPengadaanNotification;
use Illuminate\Support\Facades\Log;

/**
 * Pengamat Perubahan Model Notifikasi Pengguna (NotifikasiUserObserver)
 *
 * Bertugas mengotomatisasi pengiriman notifikasi eksternal (email) secara langsung
 * setiap kali sistem SIM-PBJ mencatat rekaman peringatan baru pada tabel `notifikasi_user`.
 */
class NotifikasiUserObserver
{
    /**
     * Menangani event 'created' saat satu record NotifikasiUser baru berhasil disimpan.
     * Memicu pengiriman surel pemberitahuan ke alamat email pengguna yang dituju.
     */
    public function created(NotifikasiUser $notifikasi): void
    {
        // 1. Pastikan relasi user penerima beserta alamat surel tersedia
        $user = $notifikasi->user ?? $notifikasi->user()->first();

        if ($user && ! empty($user->email)) {
            try {
                // 2. Kirimkan notifikasi email via Laravel Notification System
                $user->notify(new PengingatPengadaanNotification($notifikasi));
            } catch (\Throwable $exception) {
                // 3. Catat kegagalan koneksi SMTP/jaringan tanpa memutus alur transaksi sistem utama
                Log::warning("Gagal mengirimkan email notifikasi ke {$user->email}: {$exception->getMessage()}", [
                    'id_notifikasi_user' => $notifikasi->id,
                    'user_id' => $user->id_user,
                    'judul' => $notifikasi->judul,
                ]);
            }
        }
    }
}
