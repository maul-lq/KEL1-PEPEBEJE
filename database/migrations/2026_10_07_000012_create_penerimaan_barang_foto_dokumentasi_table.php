<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `penerimaan_barang_foto_dokumentasi`
 *             sebagai penyimpan multi-foto bukti inspeksi fisik barang untuk tiap BAST.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `penerimaan_barang_foto_dokumentasi`.
 *
 * Tujuan: memisahkan galeri foto dokumentasi dari tabel `penerimaan_barang` agar petugas
 * Perlengkapan & BMN dapat mengunggah lebih dari satu bukti visual per penerimaan.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000012_create_penerimaan_barang_foto_dokumentasi_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `penerimaan_barang_foto_dokumentasi`.
     *
     * Tujuan: menetapkan relasi cascade ke `penerimaan_barang` sehingga apabila berita
     * acara pemeriksaan dihapus, seluruh baris fotonya ikut bersih tanpa yatim.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('penerimaan_barang_foto_dokumentasi', function (Blueprint $table) {
            $table->id('id_foto');
            $table->unsignedBigInteger('id_penerimaan');
            $table->string('foto_dokumentasi', 255);
            $table->string('keterangan_foto', 255)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            // terapkan cascadeOnDelete agar arsip foto anak ikut terhapus bersama induk penerimaannya.
            $table->foreign('id_penerimaan')
                ->references('id_penerimaan')
                ->on('penerimaan_barang')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `penerimaan_barang_foto_dokumentasi`.
     *
     * Tujuan: membongkar tabel foto dokumentasi penerimaan saat rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('penerimaan_barang_foto_dokumentasi');
    }
};
