<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk menyalin `nama_perusahaan` yang belum tersinkron ke
 *             `nama_vendor` lalu menghapus enam kolom profil vendor legacy pada tabel `vendors`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembersihan kolom kontak dan identitas perusahaan legacy pada `vendors`.
 *
 * Tujuan: merampingkan data vendor agar sejalan dengan desain input minimalis penyedia pada
 * paket pengadaan sekaligus menjaga tidak ada nama perusahaan lama yang hilang saat migrasi.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_053338_drop_obsolete_columns_from_vendors_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penyinkronan nama vendor dan penghapusan enam kolom legacy.
     *
     * Tujuan: memindahkan isi `nama_perusahaan` ke `nama_vendor` terlebih dahulu sebelum
     * menghapus kolom lama supaya data penyedia historis tetap utuh.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        // salin lebih dulu nilai nama_perusahaan yang belum masuk ke nama_vendor sebelum kolom lamanya dihapus.
        if (Schema::hasColumn('vendors', 'nama_perusahaan') && Schema::hasColumn('vendors', 'nama_vendor')) {
            DB::statement('UPDATE vendors SET nama_vendor = nama_perusahaan WHERE (nama_vendor IS NULL OR nama_vendor = \'\') AND nama_perusahaan IS NOT NULL');
        }

        $columnsToDrop = ['nib', 'alamat', 'nama_pic', 'telepon_pic', 'email_pic', 'nama_perusahaan'];

        foreach ($columnsToDrop as $col) {
            if (Schema::hasColumn('vendors', $col)) {
                Schema::table('vendors', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }

    /**
     * Membalikkan migrasi dengan menambahkan kembali enam kolom legacy pada `vendors`.
     *
     * Tujuan: memulihkan kolom-kolom tersebut beserta penyalinan balik `nama_vendor` ke
     * `nama_perusahaan` apabila rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('nib', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_pic', 100)->nullable();
            $table->string('telepon_pic', 20)->nullable();
            $table->string('email_pic', 100)->nullable();
            $table->string('nama_perusahaan', 200)->nullable();
        });

        if (Schema::hasColumn('vendors', 'nama_perusahaan') && Schema::hasColumn('vendors', 'nama_vendor')) {
            DB::statement('UPDATE vendors SET nama_perusahaan = nama_vendor WHERE nama_perusahaan IS NULL AND nama_vendor IS NOT NULL');
        }
    }
};
