<?php

namespace App\Providers;

use App\Models\NotifikasiUser;
use App\Observers\NotifikasiUserObserver;
use App\View\DevModeViewFinder;
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
        // Mendaftarkan DevModeViewFinder untuk mendukung peralihan dinamis DEV_VIEWMODE (backend vs frontend)
        $this->app->bind('view.finder', function ($app) {
            return new DevModeViewFinder($app['files'], $app['config']['view.paths']);
        });
    }

    /**
     * Melakukan inisialisasi / bootstrapping layanan setelah seluruh provider terdaftar.
     * Dijalankan sebelum request ditangani oleh routing dan controller.
     */
    public function boot(): void
    {
        // Daftarkan observer model NotifikasiUser untuk memicu pengiriman email otomatis
        NotifikasiUser::observe(NotifikasiUserObserver::class);
    }
}
