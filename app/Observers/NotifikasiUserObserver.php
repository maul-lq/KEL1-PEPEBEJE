<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis observer NotifikasiUserObserver ini untuk otomatis mengirimkan surel pengingat setiap kali rekaman notifikasi baru tersimpan bagi pengguna yang mengaktifkan email reminder.
 */

namespace App\Observers;

use App\Models\NotifikasiUser;
use App\Notifications\PengingatPengadaanNotification;
use Illuminate\Support\Facades\Log;

/**
 * Observer Model Notifikasi Pengguna (NotifikasiUserObserver)
 *
 * menghubungkan event `created` model NotifikasiUser ke Laravel Notification agar pengiriman email
 * berlangsung terpusat dan terisolasi dengan aman dari kegagalan server SMTP.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance observer yang didaftarkan melalui `NotifikasiUser::observe(...)`.
 *
 * Contoh pemanggilan:
 * `NotifikasiUser::observe(NotifikasiUserObserver::class);`
 */
class NotifikasiUserObserver
{
    /**
     * menindaklanjuti setiap record NotifikasiUser yang baru dibuat dengan mengirimkan email resmi apabila preferensi pengguna mengizinkan.
     *
     * @param  NotifikasiUser  $notifikasi  menerima instance notifikasi yang baru saja disimpan ke database.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Terpicu otomatis saat NotifikasiUser::create([...]) dipanggil.`
     */
    public function created(NotifikasiUser $notifikasi): void
    {
        $user = $notifikasi->user ?? $notifikasi->user()->first();

        // langsung berhenti jika penerima tidak memiliki alamat email valid agar mailer tidak melempar error alamat kosong.
        if (! $user || empty($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // menghormati preferensi opt-out pengguna ketika reminder_email_aktif bernilai false secara eksplisit.
        if ($user->reminder_email_aktif === false) {
            return;
        }

        try {
            $user->notify(new PengingatPengadaanNotification($notifikasi));
        } catch (\Throwable $exception) {
            // menangkap kegagalan SMTP sebagai warning log saja agar transaksi utama pengadaan (simpan permohonan/SPK/pembayaran) tidak ikut rollback.
            Log::warning("Gagal mengirimkan email notifikasi ke {$user->email}: {$exception->getMessage()}", [
                'id_notifikasi_user' => $notifikasi->id,
                'user_id' => $user->id_user,
                'judul' => $notifikasi->judul,
            ]);
        }
    }
}
