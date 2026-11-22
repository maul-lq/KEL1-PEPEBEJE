<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk menghapus kolom `gelar` yang tidak terpakai pada tabel
 *             `users` setelah penulisan gelar digabungkan langsung dalam kolom `nama`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penghapus kolom `gelar` pada tabel `users`.
 *
 * Tujuan: merampingkan struktur profil pegawai karena formulir registrasi dan pembaruan profil
 * telah menyatukan gelar akademik di dalam kolom nama lengkap.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_08_133731_drop_gelar_from_users_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan kolom `gelar` secara kondisional.
     *
     * Tujuan: mengecek keberadaan kolom lewat `hasColumn` agar migrasi tidak gagal pada basis
     * data baru yang sejak awal tidak mendefinisikan kolom `gelar`.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'gelar')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('gelar');
            });
        }
    }

    /**
     * Membalikkan migrasi dengan memulihkan kolom `gelar` bertipe string nullable.
     *
     * Tujuan: mengembalikan definisi kolom `gelar` secara kondisional bila dilakukan rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'gelar')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('gelar')->nullable();
            });
        }
    }
};
