<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis AppServiceProvider ini sebagai pusat bootstrapping komponen aplikasi, khususnya pendaftaran observer pengiriman email otomatis pada model NotifikasiUser.
 */

namespace App\Providers;

use App\Models\NotifikasiUser;
use App\Observers\NotifikasiUserObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider Utama Aplikasi (AppServiceProvider)
 *
 * memakai Service Provider bawaan ini untuk mengikat lifecycle event model dan menyiapkan ekstensi layanan global saat Laravel boot.
 *
 * Parameter konstruktor: `$app` (diwarisi dari Illuminate\Support\ServiceProvider).
 * Nilai kembali         : Instance provider yang didaftarkan di bootstrap/providers.php.
 *
 * Contoh pemanggilan:
 * `// Didaftarkan otomatis pada bootstrap/providers.php`
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * menyediakan metode register ini sebagai tempat mengikat abstraksi atau singleton ke service container sebelum fase boot.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Application::registerConfiguredProviders()`
     */
    public function register(): void
    {
        // membiarkan register() kosong karena service aplikasi saat ini dapat di-resolve otomatis lewat auto-wiring container.
    }

    /**
     * mendaftarkan observer model setelah seluruh service provider selesai dimuat agar event Eloquent langsung aktif untuk semua request.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Laravel tepat sebelum request HTTP atau perintah Artisan dijalankan.`
     */
    public function boot(): void
    {
        // memasang observer di level model agar setiap pembuatan NotifikasiUser—baik dari controller maupun scheduler—otomatis mengirim email tanpa kode berulang.
        NotifikasiUser::observe(NotifikasiUserObserver::class);
    }
}
