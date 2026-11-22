<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk membentuk tabel `pembayaran` guna mencatat pengajuan
 *             tagihan termin oleh PPBJ, nomor memo bayar, jatuh tempo, dan reviu akhir menuju SP2D.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `pembayaran`.
 *
 * Tujuan: mendefinisikan struktur tagihan pembayaran pengadaan berbasis primary key
 * `nomor_pembayaran` beserta tanggal jatuh tempo untuk pengingat otomatis H-2 termin.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000013_create_pembayaran_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `pembayaran`.
     *
     * Tujuan: menyediakan pelacakan termin pembayaran, pengaju PPBJ, status alur memo
     * berjenjang, serta reviewer akhir sebelum Keuangan menerbitkan pencairan SP2D.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->string('nomor_pembayaran', 100)->primary();
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user_pengaju');
            $table->integer('tahap_termin')->default(1);
            $table->decimal('nominal_pengajuan', 15, 2); // gunakan decimal(15,2) untuk akurasi nilai tagihan dan validasi batas kontrak.
            $table->date('tanggal_pengajuan');
            $table->date('jatuh_tempo');
            $table->string('status_pembayaran', 50)->default('diajukan');
            $table->string('file_tagihan_vendor', 255)->nullable();
            $table->string('file_dokumen_penunjang', 255)->nullable();
            $table->string('nomor_memo', 100)->nullable();
            $table->string('file_memo', 255)->nullable();
            $table->unsignedBigInteger('id_user_reviu')->nullable();
            $table->timestamp('tanggal_perintah_bayar')->nullable();
            $table->string('status_reviu_akhir', 50)->nullable();
            $table->text('catatan_reviu_akhir')->nullable();
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user_pengaju')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('id_user_reviu')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `pembayaran`.
     *
     * Tujuan: menghapus tabel pembayaran apabila rollback migrasi diperlukan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
