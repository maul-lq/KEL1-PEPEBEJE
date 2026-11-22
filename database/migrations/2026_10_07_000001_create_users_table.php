<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `users` sebagai master akun pengguna
 *             SIM-PBJ lengkap dengan NIP, username, email unik, peran awal, dan relasi unit kerja.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `users`.
 *
 * Tujuan: mendefinisikan struktur dasar autentikasi dan identitas kepegawaian PNJ yang
 * terhubung ke tabel `units` melalui foreign key `kode_unit`.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000001_create_users_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi untuk membangun skema tabel `users`.
     *
     * Tujuan: menyiapkan primary key `id_user`, indeks unik NIP/username/email, serta relasi
     * `nullOnDelete` ke unit kerja agar akun tidak terhapus paksa bila master unit disesuaikan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('nip', 30)->unique();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('nama', 150);
            $table->string('password', 255);
            $table->string('jabatan', 100);
            $table->string('role', 50); // simpan kolom role string pada skema awal sebelum nantinya digantikan relasi id_role.
            $table->boolean('status_aktif')->default(true);
            $table->string('no_hp', 20);
            $table->string('no_telp_kantor', 20)->nullable();
            $table->string('kode_unit', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();

            // gunakan nullOnDelete agar penghapusan unit kerja tidak memicu kegagalan integritas akun historis.
            $table->foreign('kode_unit')
                ->references('kode_unit')
                ->on('units')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `users`.
     *
     * Tujuan: membongkar tabel `users` saat rollback migrasi dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
