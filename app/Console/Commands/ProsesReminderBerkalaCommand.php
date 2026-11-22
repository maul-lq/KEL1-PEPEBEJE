<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis command Artisan ini untuk mengeksekusi pemrosesan jadwal pengingat jatuh tempo dan pengingat berkala secara terjadwal dari CLI/cron.
 */

namespace App\Console\Commands;

use App\Services\ReminderSchedulerService;
use Illuminate\Console\Command;

/**
 * Command Pemrosesan Pengingat Berkala (ProsesReminderBerkalaCommand)
 *
 * membuat command Artisan ini sebagai pintu masuk penjadwalan cron agar pengiriman pengingat H-2
 * dan termin berkala tetap berjalan deterministik di luar siklus request HTTP.
 *
 * Parameter konstruktor: Tidak ada (menggunakan properti signature/description bawaan Command Laravel).
 * Nilai kembali         : Instance command Artisan siap dijalankan oleh Console Kernel.
 *
 * Contoh pemanggilan:
 * `php artisan reminder:proses`
 */
class ProsesReminderBerkalaCommand extends Command
{
    /**
     * Signature perintah Artisan yang didaftarkan pada Console Kernel.
     *
     * @var string
     */
    protected $signature = 'reminder:proses';

    /**
     * Deskripsi ringkas perintah yang tampil pada daftar `php artisan list`.
     *
     * @var string
     */
    protected $description = 'Memproses jadwal pengingat berkala kontrak/pembayaran dan mengirim notifikasi + email untuk jadwal yang telah jatuh tempo';

    /**
     * menjalankan satu siklus penuh pemrosesan seluruh jadwal reminder yang telah jatuh tempo.
     *
     * @param  ReminderSchedulerService  $scheduler  menyuntikkan layanan penjadwal agar logika penguncian cache dan pemilihan penerima tidak diduplikasi di command.
     * @return int mengembalikan self::SUCCESS (0) setelah ringkasan eksekusi dicetak ke konsol.
     *
     * Contoh pemanggilan:
     * `$exitCode = $this->call('reminder:proses');`
     */
    public function handle(ReminderSchedulerService $scheduler): int
    {
        // mendelegasikan seluruh eksekusi ke service agar memakai cache lock yang sama dengan middleware web dan mencegah duplikasi alert.
        $summary = $scheduler->processDueReminders();

        $this->info(sprintf(
            'Reminder diproses: %d | Notifikasi dikirim: %d | Jadwal selesai/dinonaktifkan: %d',
            $summary['processed'],
            $summary['dispatched_notifications'],
            $summary['deactivated']
        ));

        return self::SUCCESS;
    }
}
