<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk membentuk tabel `penerimaan_barang` guna mencatat
 *             inspeksi fisik barang, penandatanganan digital BAST, serta berita acara resmi.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `penerimaan_barang`.
 *
 * Tujuan: mendefinisikan struktur serah terima barang dari penyedia ke Unit Perlengkapan &
 * BMN lengkap dengan token HMAC BAST dan catatan pemeriksaan fisik.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000011_create_penerimaan_barang_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `penerimaan_barang`.
     *
     * Tujuan: menyediakan nomor BAST unik opsional sebelum penerbitan resmi serta kolom berkas
     * BAST tertandatangani untuk keperluan verifikasi pembayaran oleh Keuangan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id('id_penerimaan');
            $table->string('nomor_bast', 100)->unique()->nullable(); // buat nullable agar draf pemeriksaan fisik bisa disimpan sebelum nomor BAST resmi terbit.
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user');
            $table->date('tanggal_penerimaan');
            $table->string('status_fisik', 50)->default('terima');
            $table->text('ttd_digital_bast')->nullable();
            $table->string('file_bast_signed', 255)->nullable();
            $table->string('file_penerimaan_pihak3', 255)->nullable();
            $table->text('catatan_pemeriksaan')->nullable();
            $table->timestamp('waktu_selesai_inspeksi')->nullable();
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `penerimaan_barang`.
     *
     * Tujuan: menghapus tabel penerimaan barang saat proses rollback migrasi.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('penerimaan_barang');
    }
};
