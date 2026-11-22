<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menambahkan kolom `kategori_belanja` pada tabel
 *             `penerimaan_barang` dan `jenis_pengadaan_split` pada tabel `permohonan_pengadaan`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom kategori belanja dan pemecahan jenis pengadaan.
 *
 * Tujuan: melengkapi kebutuhan klasifikasi aset BMN (Belanja Barang vs Belanja Modal) dan
 * fitur pemecahan berkas berdasarkan jenis pengadaan oleh PPBJ dengan guard idempotent.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_21_000001_add_kategori_belanja_and_jenis_split_columns.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom `kategori_belanja` dan `jenis_pengadaan_split`.
     *
     * Tujuan: memeriksa `hasTable` dan `hasColumn` terlebih dahulu agar migrasi tetap aman
     * dijalankan pada lingkungan database yang mungkin sudah menambahkan kolom secara manual.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        // Guard hasColumn mencegah kegagalan duplikat kolom bila migrasi dijalankan ulang pada DB parsial.
        if (Schema::hasTable('penerimaan_barang') && ! Schema::hasColumn('penerimaan_barang', 'kategori_belanja')) {
            Schema::table('penerimaan_barang', function (Blueprint $table) {
                $table->string('kategori_belanja', 50)->default('belanja_barang')->after('status_fisik');
            });
        }

        if (Schema::hasTable('permohonan_pengadaan') && ! Schema::hasColumn('permohonan_pengadaan', 'jenis_pengadaan_split')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                $table->string('jenis_pengadaan_split', 50)->nullable()->after('kode_mak');
            });
        }
    }

    /**
     * Membalikkan migrasi dengan menghapus kolom `kategori_belanja` dan `jenis_pengadaan_split`.
     *
     * Tujuan: memeriksa eksistensi kolom sebelum melakukan `dropColumn` supaya proses rollback tidak error.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (Schema::hasTable('penerimaan_barang') && Schema::hasColumn('penerimaan_barang', 'kategori_belanja')) {
            Schema::table('penerimaan_barang', function (Blueprint $table) {
                $table->dropColumn('kategori_belanja');
            });
        }

        if (Schema::hasTable('permohonan_pengadaan') && Schema::hasColumn('permohonan_pengadaan', 'jenis_pengadaan_split')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                $table->dropColumn('jenis_pengadaan_split');
            });
        }
    }
};
