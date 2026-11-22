<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menghapus kolom `no_telp_kantor` pada tabel `users`
 *             karena kontak pegawai telah diseragamkan pada nomor HP aktif.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penghapus kolom `no_telp_kantor` dari tabel `users`.
 *
 * Tujuan: meniadakan field kontak kantor yang redundan agar formulir profil pengguna dan
 * validasi registrasi lebih ringkas.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_064736_drop_no_telp_kantor_from_users_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan kolom `no_telp_kantor` secara aman.
     *
     * Tujuan: memeriksa keberadaan kolom lebih dahulu lewat `hasColumn` agar migrasi
     * bersifat idempotent di semua lingkungan database.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'no_telp_kantor')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('no_telp_kantor');
            });
        }
    }

    /**
     * Membalikkan migrasi dengan mengembalikan kolom `no_telp_kantor` nullable.
     *
     * Tujuan: menyediakan jalur pemulihan kolom apabila skema sebelumnya perlu dikembalikan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'no_telp_kantor')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('no_telp_kantor', 20)->nullable();
            });
        }
    }
};
