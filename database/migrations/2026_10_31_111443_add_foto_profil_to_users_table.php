<?php

/**
 * Informasi File
 *
 * Penulis   : DendyRafi, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini kami susun untuk menambahkan kolom `foto_profil` pada tabel `users`
 *             guna menyimpan jalur berkas foto profil unggahan pengguna.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom `foto_profil` pada tabel `users`.
 *
 * Tujuan: menambahkan penyimpanan path foto profil lokal agar avatar profil pengguna tetap
 * tersedia meskipun akun belum ditautkan ke Google OAuth.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_31_111443_add_foto_profil_to_users_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom `foto_profil` pada tabel `users`.
     *
     * Tujuan: membuat kolom bersifat nullable agar pengguna baru belum diwajibkan mengunggah
     * foto profil saat registrasi awal.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_profil')->nullable()->after('email');
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus kolom `foto_profil` dari tabel `users`.
     *
     * Tujuan: membersihkan kolom `foto_profil` saat proses rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto_profil');
        });
    }
};
