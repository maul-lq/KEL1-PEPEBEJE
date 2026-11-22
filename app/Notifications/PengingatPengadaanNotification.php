<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis kelas Notification ini untuk menyusun format surel resmi peringatan dan transisi pengadaan SIM-PBJ Politeknik Negeri Jakarta.
 */

namespace App\Notifications;

use App\Models\NotifikasiUser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kelas Notifikasi Surel Pengadaan (PengingatPengadaanNotification)
 *
 * merancang kelas notifikasi ini untuk mengubah satu record `NotifikasiUser` menjadi surel terstruktur
 * lengkap dengan kategori badge, nomor referensi berkas, dan deep-link ke halaman tindak lanjut terkait.
 *
 * Parameter konstruktor: `NotifikasiUser $notifikasi` — entitas notifikasi personal sumber isi pesan.
 * Nilai kembali         : Instance Notification yang dikirim melalui `$user->notify(...)`.
 *
 * Contoh pemanggilan:
 * `$user->notify(new PengingatPengadaanNotification($notifikasi));`
 */
class PengingatPengadaanNotification extends Notification
{
    use Queueable;

    /**
     * menyimpan entitas NotifikasiUser melalui constructor property promotion agar seluruh metadata email tersedia saat build pesan.
     *
     * @param  NotifikasiUser  $notifikasi  mengikat instance notifikasi personal yang baru diterbitkan untuk dibaca oleh metode via/toMail/toArray.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `new PengingatPengadaanNotification($notifikasiUser);`
     */
    public function __construct(public NotifikasiUser $notifikasi) {}

    /**
     * menentukan kanal pengiriman surel ('mail') untuk notifikasi pengadaan ini.
     *
     * @param  object  $notifiable  menerima entitas penerima notifikasi (umumnya model User).
     * @return array<int, string> mengembalikan daftar channel pengiriman ['mail'].
     *
     * Contoh pemanggilan:
     * `$channels = $notification->via($user);`
     */
    public function via(object $notifiable): array
    {
        // cukup memakai kanal 'mail' karena pencatatan in-app sudah ditangani oleh tabel notifikasi_user sendiri.
        return ['mail'];
    }

    /**
     * menyusun tata letak MailMessage berisi kategori, nomor referensi berkas, uraian instruksi, dan tombol tautan langsung.
     *
     * @param  object  $notifiable  membaca properti `nama` milik penerima untuk sapaan email yang personal.
     * @return MailMessage mengembalikan objek MailMessage siap kirim oleh driver SMTP/log Laravel.
     *
     * Contoh pemanggilan:
     * `$mail = $notification->toMail($user);`
     */
    public function toMail(object $notifiable): MailMessage
    {
        $judul = $this->notifikasi->judul;
        $pesan = $this->notifikasi->pesan;
        $badge = $this->notifikasi->jenis_badge;
        $ref = $this->notifikasi->referensi_teks;
        $actionLabel = $this->notifikasi->action_label;
        $actionUrl = $this->notifikasi->action_url;
        // menyiapkan sapaan default apabila akun pengguna tidak memiliki kolom nama terisi.
        $namaUser = $notifiable->nama ?? 'Bapak/Ibu Pengguna';

        return (new MailMessage)
            ->subject("[SIM-PBJ PNJ] {$judul}")
            ->greeting("Yth. {$namaUser},")
            ->line('Sistem Informasi Manajemen Pengadaan Barang dan Jasa (SIM-PBJ) Politeknik Negeri Jakarta memberitahukan agenda / alarm pengadaan berikut:')
            ->line("**Peristiwa / Peringatan:** {$judul}")
            ->line("**Kategori:** {$badge}")
            ->line("**Referensi Berkas:** {$ref}")
            ->line('**Uraian Instruksi:**')
            ->line("{$pesan}")
            ->action($actionLabel, $actionUrl)
            ->line('Harap segera memeriksa dan menindaklanjuti pemberitahuan di atas demi kelancaran dan ketepatan siklus pengadaan institusi.')
            ->salutation("Salam hormat,\n**Unit Layanan Pengadaan (SIM-PBJ)**\nPoliteknik Negeri Jakarta");
    }

    /**
     * menserialisasikan ringkasan atribut notifikasi ke bentuk array untuk kebutuhan logging atau kanal cadangan.
     *
     * @param  object  $notifiable  menerima entitas penerima notifikasi sesuai kontrak Notification Laravel.
     * @return array<string, mixed> mengembalikan array asosiatif metadata notifikasi.
     *
     * Contoh pemanggilan:
     * `$payload = $notification->toArray($user);`
     */
    public function toArray(object $notifiable): array
    {
        return [
            'id_notifikasi_user' => $this->notifikasi->id,
            'id_reminder' => $this->notifikasi->id_reminder,
            'judul' => $this->notifikasi->judul,
            'pesan' => $this->notifikasi->pesan,
            'jenis_badge' => $this->notifikasi->jenis_badge,
            'action_url' => $this->notifikasi->action_url,
        ];
    }
}
