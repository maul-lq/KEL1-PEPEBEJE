<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `memo_bayar` guna mencatat disposisi
 *             persetujuan pembayaran dua level secara berurutan oleh PPK (level 1) dan Wadir II (level 2).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `memo_bayar`.
 *
 * Tujuan: menampung status persetujuan disposisi memo bayar terpisah dari tabel tagihan
 * induk agar riwayat ACC PPK dan Wakil Direktur II mudah diverifikasi per tingkatannya.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000014_create_memo_bayar_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `memo_bayar`.
     *
     * Tujuan: mendefinisikan kolom `role_approver` dan `urutan_level` untuk memaksakan
     * urutan persetujuan PPK terlebih dahulu sebelum diteruskan ke Wadir II.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('memo_bayar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_pembayaran', 100);
            $table->string('role_approver', 50);
            $table->integer('urutan_level')->default(1); // Level 1 khusus PPK dan level 2 khusus Wadir II agar hierarki persetujuan terjaga.
            $table->string('status_memo', 50)->default('menunggu');
            $table->text('catatan_memo')->nullable();
            $table->timestamp('tanggal_acc')->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('nomor_pembayaran')
                ->references('nomor_pembayaran')
                ->on('pembayaran')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `memo_bayar`.
     *
     * Tujuan: membersihkan tabel disposisi memo bayar ketika rollback migrasi dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_bayar');
    }
};
