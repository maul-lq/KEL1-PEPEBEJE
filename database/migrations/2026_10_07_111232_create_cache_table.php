<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi bawaan Laravel ini gunakan untuk membuat tabel `cache` dan `cache_locks`
 *             agar caching dan atomic lock berjalan di database tanpa ketergantungan Redis eksternal.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `cache` dan `cache_locks`.
 *
 * Tujuan: menyediakan penyimpanan cache aplikasi dan lock atomik (digunakan antara lain oleh
 * rate limiter dan throttle reminder berkala) di backend database.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_111232_create_cache_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `cache` dan `cache_locks`.
     *
     * Tujuan: menambahkan indeks pada kolom `expiration` di kedua tabel supaya pembersihan
     * entri kedaluwarsa tidak memicu full table scan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index(); // Indeks expiration mempercepat invalidasi cache kadaluwarsa.
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `cache` dan `cache_locks`.
     *
     * Tujuan: menghapus kedua tabel infrastruktur cache saat rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
