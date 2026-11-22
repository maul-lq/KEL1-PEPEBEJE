<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis kelas Notification ini untuk mengirimkan email pemberitahuan resmi ketika Administrator menonaktifkan atau mengaktifkan kembali akun pengguna SIM-PBJ.
 */

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kelas Notifikasi Status Akun (AccountStatusNotification)
 *
 * mengirimkan pemberitahuan status akun secara sinkron tanpa `ShouldQueue` supaya pengguna
 * langsung menerima kepastian alasan penonaktifan atau pengaktifan ulang akun terlepas dari preferensi pengingat paket.
 *
 * Parameter konstruktor: `bool $isActive` (status keaktifan akun terbaru).
 * Nilai kembali         : Instance Notification yang dikirim lewat `$user->notify(...)`.
 *
 * Contoh pemanggilan:
 * `$user->notify(new AccountStatusNotification(false));`
 */
class AccountStatusNotification extends Notification
{
    /**
     * menyimpan status keaktifan terbaru melalui constructor property promotion.
     *
     * @param  bool  $isActive  menentukan apakah isi surel menyatakan akun diaktifkan kembali (`true`) atau dinonaktifkan (`false`).
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `new AccountStatusNotification(false);`
     */
    public function __construct(public bool $isActive) {}

    /**
     * menentukan kanal pengiriman email untuk pemberitahuan perubahan status akun.
     *
     * @param  object  $notifiable  menerima model pengguna sasaran email.
     * @return array<int, string> mengembalikan daftar kanal `['mail']`.
     *
     * Contoh pemanggilan:
     * `$channels = $notification->via($user);`
     */
    public function via(object $notifiable): array
    {
        // memakai kanal email saja agar pencatatan kotak masuk in-app tetap terpisah tanpa memicu ulang observer pengadaan.
        return ['mail'];
    }

    /**
     * menyusun isi surel Bahasa Indonesia yang jelas ketika akun dinonaktifkan atau diaktifkan kembali oleh Administrator.
     *
     * @param  object  $notifiable  membaca nama dan username pengguna untuk sapaan serta identitas akun.
     * @return MailMessage mengembalikan pesan email siap kirim lewat driver SMTP atau log aplikasi.
     *
     * Contoh pemanggilan:
     * `$mail = $notification->toMail($user);`
     */
    public function toMail(object $notifiable): MailMessage
    {
        // menyiapkan nama default bila profil pengguna belum memiliki nama lengkap.
        $namaUser = $notifiable->nama ?? 'Bapak/Ibu Pengguna';
        $username = $notifiable->username ?? '-';
        $loginUrl = route('login');

        if (! $this->isActive) {
            return (new MailMessage)
                ->subject('[SIM-PBJ PNJ] Akun Anda Dinonaktifkan')
                ->greeting("Yth. {$namaUser},")
                ->line("Akun SIM-PBJ Anda ({$username}) telah dinonaktifkan oleh Administrator.")
                ->line('Selama akun dinonaktifkan, Anda tidak dapat masuk ke sistem dan sesi yang sedang berjalan akan ditutup.')
                ->line('Jika Anda perlu menggunakan akun kembali, silakan hubungi Administrator.')
                ->action('Buka Halaman Masuk', $loginUrl)
                ->salutation("Salam hormat,\nUnit Layanan Pengadaan (SIM-PBJ)\nPoliteknik Negeri Jakarta");
        }

        return (new MailMessage)
            ->subject('[SIM-PBJ PNJ] Akun Anda Diaktifkan Kembali')
            ->greeting("Yth. {$namaUser},")
            ->line("Akun SIM-PBJ Anda ({$username}) telah diaktifkan kembali oleh Administrator.")
            ->line('Anda sekarang sudah dapat masuk kembali ke sistem menggunakan akun dan kata sandi Anda.')
            ->action('Masuk ke SIM-PBJ', $loginUrl)
            ->salutation("Salam hormat,\nUnit Layanan Pengadaan (SIM-PBJ)\nPoliteknik Negeri Jakarta");
    }

    /**
     * menyusun representasi array ringkas status perubahan akun untuk kebutuhan pencatatan internal.
     *
     * @param  object  $notifiable  menerima entitas penerima notifikasi.
     * @return array<string, mixed> mengembalikan status aktif terbaru beserta alamat email penerima.
     *
     * Contoh pemanggilan:
     * `$data = $notification->toArray($user);`
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status_aktif' => $this->isActive,
            'email' => $notifiable->email ?? null,
        ];
    }
}
