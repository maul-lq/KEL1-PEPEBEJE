<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membentuk tabel `kontrak_spk` yang menyimpan dokumen
 *             kontrak/SPK/surat pesanan, nilai kontrak, jadwal pelaksanaan, dan unggahan berkas.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `kontrak_spk`.
 *
 * Tujuan: mendefinisikan struktur dokumen perikatan pengadaan yang terhubung ke paket dan
 * menjadi dasar jadwal penerimaan barang maupun pengajuan pembayaran.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000010_create_kontrak_spk_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `kontrak_spk`.
     *
     * Tujuan: menetapkan `nomor_spk` sebagai primary key, tipe dan sifat kontrak, tanggal
     * jadwal pengiriman untuk pemicu reminder otomatis, serta tautan ke paket terkait.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('kontrak_spk', function (Blueprint $table) {
            $table->string('nomor_spk', 100)->primary();
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user')->nullable();
            $table->string('tipe_dokumen', 50)->default('spk');
            $table->string('sifat_kontrak', 50)->default('sekali_selesai'); // Mendukung skema kontrak tunggal maupun termin berkala.
            $table->string('status_spk', 50)->default('draft');
            $table->date('tanggal_spk');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->date('jadwal_pengiriman');
            $table->decimal('nilai_kontrak', 15, 2);
            $table->string('file_spk', 255);
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `kontrak_spk`.
     *
     * Tujuan: membersihkan tabel kontrak SPK ketika rollback skema dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('kontrak_spk');
    }
};
