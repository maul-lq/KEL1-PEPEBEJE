<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi bawaan Laravel ini sediakan untuk membuat tabel `jobs` sebagai antrean
 *             pekerjaan asinkron berbasis database.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `jobs`.
 *
 * Tujuan: menyiapkan tabel antrean background job Laravel agar pengiriman notifikasi atau
 * tugas terjadwal dapat dijalankan asinkron lewat worker queue apabila diaktifkan.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_111236_create_jobs_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `jobs`.
     *
     * Tujuan: mengindeks kolom `queue` agar worker antrean dapat mengambil job sesuai nama
     * salurannya secara efisien.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index(); // Indeks queue mempercepat pengambilan job berikutnya oleh queue worker.
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `jobs`.
     *
     * Tujuan: membersihkan tabel antrean database saat rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
