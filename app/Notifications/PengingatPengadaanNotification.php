<?php

namespace App\Notifications;

use App\Models\NotifikasiUser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi Email Peringatan & Jadwal Pengadaan (PengingatPengadaanNotification)
 *
 * Mengirimkan email resmi dari sistem SIM-PBJ Politeknik Negeri Jakarta ke akun email pengguna:
 * - Menampilkan subjek, ringkasan pengingat, kategori (Pengingat/Verifikasi/Pembayaran/SPK/dll).
 * - Menyertakan nomor surat / referensi paket pengadaan.
 * - Menyediakan tombol tautan aksi langsung (deep-link) menuju modul terkait di web SIM-PBJ.
 */
class PengingatPengadaanNotification extends Notification
{
    use Queueable;

    /**
     * Inisialisasi notifikasi dengan data entitas NotifikasiUser terkait.
     * Menggunakan constructor property promotion sesuai standar PHP 8.
     */
    public function __construct(public NotifikasiUser $notifikasi) {}

    /**
     * Menentukan channel pengiriman notifikasi (kanal surel / mail).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Membangun representasi pesan surel resmi dengan format terstruktur dan responsif.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $judul = $this->notifikasi->judul;
        $pesan = $this->notifikasi->pesan;
        $badge = $this->notifikasi->jenis_badge;
        $ref = $this->notifikasi->referensi_teks;
        $actionLabel = $this->notifikasi->action_label;
        $actionUrl = $this->notifikasi->action_url;
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
     * Representasi data array notifikasi untuk keperluan logging / database channel.
     *
     * @return array<string, mixed>
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
