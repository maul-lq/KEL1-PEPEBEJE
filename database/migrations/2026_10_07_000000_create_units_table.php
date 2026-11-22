<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `units` sebagai master jurusan, unit,
 *             bagian, dan bidang kerja di lingkungan Politeknik Negeri Jakarta.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `units`.
 *
 * Tujuan: mendefinisikan tabel referensi unit kerja berbasis primary key `kode_unit`
 * untuk mengaitkan pengguna pengusul serta berkas permohonan pengadaan ke unit asalnya.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000000_create_units_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi untuk membangun tabel `units`.
     *
     * Tujuan: menyiapkan kolom `kode_unit` bertipe string sebagai primary key alami agar kode
     * jurusan atau bagian terbaca jelas pada relasi foreign key tabel lain.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->string('kode_unit', 20)->primary();
            $table->string('nama_unit', 150);
            $table->string('jenis_unit', 50); // jurusan, unit, bagian, bidang
            $table->timestamps();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `units`.
     *
     * Tujuan: membersihkan tabel master unit kerja saat rollback migrasi dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
