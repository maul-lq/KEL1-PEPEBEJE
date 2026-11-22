<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk membentuk tabel `transaksi_pencairan` yang menyimpan
 *             bukti transfer SP2D oleh Bagian Keuangan untuk setiap tagihan yang telah disetujui.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `transaksi_pencairan`.
 *
 * Tujuan: merekam nomor referensi transaksi bank sebagai primary key beserta bukti transfer
 * resmi agar setiap pencairan dana tidak terjadi duplikasi pembayaran (anti-duplicate payment).
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000015_create_transaksi_pencairan_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `transaksi_pencairan`.
     *
     * Tujuan: menautkan setiap transaksi bank ke `nomor_pembayaran` dan aktor staf Keuangan
     * sehingga jejak audit realisasi anggaran SP2D terlacak hingga level bukti transfer.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('transaksi_pencairan', function (Blueprint $table) {
            $table->string('nomor_transaksi_bank', 100)->primary(); // Primary key nomor transaksi bank mencegah pencatatan nomor referensi ganda.
            $table->string('nomor_pembayaran', 100);
            $table->unsignedBigInteger('id_user');
            $table->timestamp('tanggal_transfer')->useCurrent();
            $table->decimal('nominal_transfer', 15, 2);
            $table->string('file_bukti_transfer', 255);
            $table->text('catatan_keuangan')->nullable();
            $table->timestamps();

            $table->foreign('nomor_pembayaran')
                ->references('nomor_pembayaran')
                ->on('pembayaran')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `transaksi_pencairan`.
     *
     * Tujuan: menghapus tabel transaksi pencairan saat rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi_pencairan');
    }
};
