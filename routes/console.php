<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis berkas ini untuk mendefinisikan command konsol ringan dan penjadwalan cron pemrosesan reminder pengadaan berkala.
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Perintah Artisan Bawaan 'inspire'
 *
 * mempertahankan sekaligus memperkaya komentar perintah bawaan Laravel ini sebagai penguji cepat bahwa Artisan CLI berjalan normal.
 *
 * Parameter    : Tidak menerima parameter CLI wajib.
 * Nilai kembali: void — mencetak satu kutipan acak ke output konsol standar.
 * Contoh pemanggilan:
 * `php artisan inspire`
 */
Artisan::command('inspire', function () {
    // mempertahankan command bawaan Laravel ini untuk verifikasi kesehatan CLI secara cepat di server.
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Penjadwalan Otomatis Pemrosesan Reminder Berkala
 *
 * menjadwalkan pemeriksaan reminder setiap 5 menit agar notifikasi H-2 dan kontrak termin tetap terkirim tepat waktu meski tidak ada request web masuk.
 *
 * Parameter    : Command signature 'reminder:proses'.
 * Nilai kembali: \Illuminate\Console\Scheduling\Event — objek jadwal cron yang mencegah eksekusi tumpang tindih.
 * Contoh pemanggilan:
 * `php artisan schedule:run`
 */
// mengaktifkan withoutOverlapping() supaya pengiriman email/notifikasi tidak terduplikasi bila satu siklus eksekusi masih berjalan lebih dari 5 menit.
Schedule::command('reminder:proses')
    ->everyFiveMinutes()
    ->withoutOverlapping();
