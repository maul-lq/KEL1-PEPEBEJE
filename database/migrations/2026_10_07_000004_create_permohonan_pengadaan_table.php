<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `permohonan_pengadaan` sebagai tabel
 *             transaksi utama usulan surat pengadaan dari jurusan/unit kerja PNJ.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `permohonan_pengadaan`.
 *
 * Tujuan: membangun skema surat permohonan pengadaan berbasis primary key `nomor_surat`,
 * lengkap dengan relasi self-referencing untuk pemecahan (split) dan revisi surat.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000004_create_permohonan_pengadaan_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembuatan tabel `permohonan_pengadaan` dan self-FK pendukungnya.
     *
     * Tujuan: memisahkan blok `Schema::table` kedua untuk foreign key ke tabel yang sama
     * agar PostgreSQL mengevaluasi referensi diri setelah tabel selesai dibuat.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('permohonan_pengadaan', function (Blueprint $table) {
            $table->string('nomor_surat', 100)->primary();
            $table->string('judul_pengadaan', 255);
            $table->text('tujuan_pengadaan_ringkas');
            $table->string('file_permohonan', 255);
            $table->string('status_permohonan', 50)->default('diajukan'); // Status siklus awal usulan hingga disetujui atau digantikan revisi.
            $table->string('status_anggaran', 50)->default('pending');
            $table->integer('revisi_ke')->default(0);
            $table->decimal('estimasi_total_pagu', 15, 2)->nullable();
            $table->timestamp('tanggal_pengajuan')->useCurrent();
            $table->string('kode_unit', 20);
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_surat_induk_split', 100)->nullable();
            $table->timestamp('tanggal_pemecahan')->nullable();
            $table->text('alasan_pemecahan')->nullable();
            $table->string('nomor_surat_revisi', 100)->nullable();
            $table->timestamp('tanggal_revisi')->nullable();
            $table->text('alasan_revisi')->nullable();
            $table->string('kode_mak', 50)->nullable();
            $table->timestamps();

            $table->foreign('kode_unit')
                ->references('kode_unit')
                ->on('units')
                ->cascadeOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('kode_mak')
                ->references('kode_mak')
                ->on('anggaran_mak')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // daftarkan self-referencing foreign key secara terpisah setelah tabel terbuat agar migrasi aman di berbagai engine DB.
        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->foreign('nomor_surat_induk_split')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('nomor_surat_revisi')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `permohonan_pengadaan`.
     *
     * Tujuan: menghapus tabel permohonan pengadaan beserta constraint-nya saat rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('permohonan_pengadaan');
    }
};
