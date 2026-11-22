<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis kelas Notification ini untuk menyusun email tautan pengaturan ulang kata sandi SIM-PBJ dalam Bahasa Indonesia resmi tanpa antrean asinkron tersembunyi.
 */

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kelas Notifikasi Tautan Reset Kata Sandi (ResetPasswordNotification)
 *
 * mengirim tautan pemulihan kata sandi secara langsung (sinkron) tanpa `ShouldQueue`
 * agar tautan tetap terkirim seketika meski worker antrean latar tidak dijalankan terpisah.
 *
 * Parameter konstruktor: `#[\SensitiveParameter] public string $token` — token pemulihan mentah dari PasswordBroker.
 * Nilai kembali         : Instance Notification yang dikirim melalui `$user->notify(...)`.
 *
 * Contoh pemanggilan:
 * `$user->notify(new ResetPasswordNotification($token));`
 */
class ResetPasswordNotification extends Notification
{
    /**
     * menyimpan token reset kata sandi melalui constructor property promotion.
     *
     * @param  string  $token  menerima token pemulihan mentah untuk disisipkan ke tautan rute `password.reset`.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `new ResetPasswordNotification($token);`
     */
    public function __construct(#[\SensitiveParameter] public string $token) {}

    /**
     * menentukan kanal pengiriman email untuk notifikasi pemulihan kata sandi.
     *
     * @param  object  $notifiable  menerima model pengguna sasaran pengiriman email.
     * @return array<int, string> mengembalikan daftar kanal `['mail']`.
     *
     * Contoh pemanggilan:
     * `$channels = $notification->via($user);`
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * menyusun isi pesan email Bahasa Indonesia berisi tombol tautan pengaturan ulang kata sandi dan batas waktunya.
     *
     * @param  object  $notifiable  membaca nama pengguna serta alamat email pemulihan resmi dari model `User`.
     * @return MailMessage mengembalikan objek pesan email siap kirim oleh driver SMTP atau log Laravel.
     *
     * Contoh pemanggilan:
     * `$mail = $notification->toMail($user);`
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        // menyiapkan sapaan baku apabila kolom nama pengguna kosong di basis data.
        $namaUser = $notifiable->nama ?? 'Bapak/Ibu Pengguna';

        return (new MailMessage)
            ->subject('[SIM-PBJ PNJ] Atur Ulang Kata Sandi Akun')
            ->greeting("Yth. {$namaUser},")
            ->line('Permintaan untuk mengatur ulang kata sandi akun SIM-PBJ Anda telah diterima.')
            ->action('Atur Ulang Kata Sandi', $resetUrl)
            ->line("Tautan ini berlaku selama {$expireMinutes} menit sejak email ini dikirimkan.")
            ->line('Jika Anda tidak mengajukan permintaan ini, abaikan email ini dan kata sandi Anda tidak akan berubah.')
            ->salutation("Salam hormat,\nUnit Layanan Pengadaan (SIM-PBJ)\nPoliteknik Negeri Jakarta");
    }

    /**
     * menyediakan representasi array metadata notifikasi tanpa membocorkan token mentah.
     *
     * @param  object  $notifiable  menerima model penerima notifikasi sesuai kontrak Laravel.
     * @return array<string, mixed> mengembalikan metadata ringkas pengiriman tautan reset.
     *
     * Contoh pemanggilan:
     * `$data = $notification->toArray($user);`
     */
    public function toArray(object $notifiable): array
    {
        // sengaja tidak menyertakan nilai $this->token agar rahasia pemulihan tidak terekam di array/log notifikasi.
        return [
            'email' => method_exists($notifiable, 'getEmailForPasswordReset')
                ? $notifiable->getEmailForPasswordReset()
                : ($notifiable->email ?? null),
        ];
    }
}
