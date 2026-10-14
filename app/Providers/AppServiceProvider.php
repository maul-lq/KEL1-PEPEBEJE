<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider Utama Aplikasi (AppServiceProvider)
 *
 * Tempat untuk mendaftarkan dan mem-bootstrap layanan, konfigurasi global,
 * atau macro tambahan pada framework Laravel saat aplikasi pertama kali dimuat.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Mendaftarkan binding layanan ke dalam service container aplikasi.
     * Dijalankan pada fase registrasi awal framework.
     */
    public function register(): void
    {
        // Tempat mendaftarkan singleton atau dependensi kustom jika diperlukan
    }

    /**
     * Melakukan inisialisasi / bootstrapping layanan setelah seluruh provider terdaftar.
     * Dijalankan sebelum request ditangani oleh routing dan controller.
     */
    public function boot(): void
    {
        // Tempat mengonfigurasi pengaturan global (misal: panjang string default, observer model, dll.)
    }
}
