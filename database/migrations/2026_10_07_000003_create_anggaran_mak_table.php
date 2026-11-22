<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk membentuk tabel `anggaran_mak` sebagai penyimpan
 *             rekening Mata Anggaran Kegiatan (MAK), pagu DIPA, sisa saldo, serta status penguncian.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `anggaran_mak`.
 *
 * Tujuan: menyediakan master rekening anggaran PNJ dengan `kode_mak` sebagai primary key
 * serta kolom `is_locked` untuk kendali penguncian oleh Bagian Perencanaan.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000003_create_anggaran_mak_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi untuk membuat skema tabel `anggaran_mak`.
     *
     * Tujuan: mendefinisikan presisi `decimal(15, 2)` untuk nominal pagu dan menyambungkan
     * `id_user` ke penetap dari Bagian Perencanaan secara opsional.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('anggaran_mak', function (Blueprint $table) {
            $table->string('kode_mak', 50)->primary();
            $table->string('uraian_mak', 255);
            $table->decimal('pagu_anggaran', 15, 2); // gunakan tipe decimal(15,2) agar kalkulasi pagu rupiah bebas galat floating-point.
            $table->decimal('sisa_pagu_mak', 15, 2)->default(0);
            $table->string('sumber_dana', 100);
            $table->integer('tahun_anggaran');
            $table->text('catatan_mak')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('tanggal_kunci')->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('tanggal_penetapan')->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `anggaran_mak`.
     *
     * Tujuan: membersihkan tabel master rekening MAK ketika rollback skema dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('anggaran_mak');
    }
};
