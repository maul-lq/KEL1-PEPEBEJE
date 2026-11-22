<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menghapus tabel usang `vendor_file_legalitas` dan
 *             kolom `npwp_vendor` pada `paket_pengadaan` setelah skema vendor diseragamkan ke `id_vendor`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembersihan tabel legalitas vendor terpisah dan kolom `npwp_vendor`.
 *
 * Tujuan: menyederhanakan relasi vendor pada paket pengadaan sehingga hanya bertumpu pada
 * primary key surrogate `id` melalui kolom `id_vendor`.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_050236_cleanup_obsolete_database_tables_and_columns.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembersihan `vendor_file_legalitas` dan `npwp_vendor`.
     *
     * Tujuan: melepas foreign key khusus pada driver SQLite sebelum drop kolom karena SQLite
     * menuntut penjatuhan constraint secara eksplisit sebelum pengubahan kolom.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        // hapus tabel terpisah karena berkas legalitas sudah disatukan menjadi kolom di tabel vendors.
        Schema::dropIfExists('vendor_file_legalitas');

        if (Schema::hasColumn('paket_pengadaan', 'npwp_vendor')) {
            // SQLite memerlukan pelepasan foreign key secara eksplisit sebelum menghapus kolom npwp_vendor.
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                Schema::table('paket_pengadaan', function (Blueprint $table) {
                    $table->dropForeign(['npwp_vendor']);
                });
            }

            Schema::table('paket_pengadaan', function (Blueprint $table) {
                $table->dropColumn('npwp_vendor');
            });
        }
    }

    /**
     * Membalikkan migrasi dengan membuat ulang tabel `vendor_file_legalitas` dan kolom `npwp_vendor`.
     *
     * Tujuan: menyediakan jalur rollback struktural bila diperlukan pemulihan kolom lama.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (! Schema::hasTable('vendor_file_legalitas')) {
            Schema::create('vendor_file_legalitas', function (Blueprint $table) {
                $table->id();
                $table->string('npwp', 30)->nullable();
                $table->string('nama_dokumen', 150);
                $table->string('file_legalitas', 255);
                $table->timestamp('uploaded_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('paket_pengadaan', 'npwp_vendor')) {
            Schema::table('paket_pengadaan', function (Blueprint $table) {
                $table->string('npwp_vendor', 30)->nullable();
            });
        }
    }
};
