<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi bawaan Laravel ini aktifkan untuk membuat tabel `sessions` sebagai
 *             penyimpanan sesi pengguna berbasis database lengkap dengan IP, User-Agent, dan indeks aktivitas.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `sessions`.
 *
 * Tujuan: menggunakan driver sesi database sehingga pemeriksaan batas idle SessionTimeout
 * dan pencatatan aktivitas login bekerja persisten antar permintaan HTTP.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_111218_create_sessions_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `sessions`.
     *
     * Tujuan: membuat indeks pada `user_id` dan `last_activity` agar pembersihan sesi kedaluwarsa
     * dan pencarian sesi pengguna berlangsung cepat.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index(); // Indeks membantu garbage collector Laravel menghapus sesi kadaluwarsa dengan efisien.
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `sessions`.
     *
     * Tujuan: menghapus tabel penyimpanan sesi database saat rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
