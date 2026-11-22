<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `password_reset_tokens` sebagai penyimpan token reset kata sandi berbasis email pada driver PostgreSQL maupun SQLite.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `password_reset_tokens`.
 *
 * Tujuan: menyediakan tabel broker token pemulihan kata sandi sesuai konfigurasi
 * `config/auth.php` agar tautan reset memiliki batas waktu kedaluwarsa dan throttle.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_22_122901_create_password_reset_tokens_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `password_reset_tokens`.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        // memeriksa keberadaan tabel lebih dulu agar aman bila migrasi dijalankan ulang pada basis data yang sudah memilikinya.
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `password_reset_tokens`.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
