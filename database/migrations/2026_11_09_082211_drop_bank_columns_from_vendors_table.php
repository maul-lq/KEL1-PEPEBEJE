<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk menghapus kolom rekening perbankan (`nama_bank`,
 *             `nomor_rekening`, `atas_nama`) dari tabel `vendors`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penghapus kolom informasi bank pada tabel `vendors`.
 *
 * Tujuan: memfokuskan master vendor pada nama vendor dan dokumen legalitas karena detail
 * rekening transfer penyedia diverifikasi langsung dari dokumen invoice/SP2D Keuangan.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_082211_drop_bank_columns_from_vendors_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan kolom bank yang masih ada pada tabel `vendors`.
     *
     * Tujuan: mengumpulkan hanya kolom yang masih eksis sebelum `dropColumn` agar proses
     * migrasi tidak gagal ketika sebagian kolom sudah terhapus sebelumnya.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['nama_bank', 'nomor_rekening', 'atas_nama'] as $col) {
                if (Schema::hasColumn('vendors', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Membalikkan migrasi dengan menambahkan kembali kolom perbankan nullable ke `vendors`.
     *
     * Tujuan: menyertakan pengecekan `hasColumn` saat pemulihan kolom agar rollback berjalan bersih.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'nama_bank')) {
                $table->string('nama_bank', 100)->nullable();
            }
            if (! Schema::hasColumn('vendors', 'nomor_rekening')) {
                $table->string('nomor_rekening', 50)->nullable();
            }
            if (! Schema::hasColumn('vendors', 'atas_nama')) {
                $table->string('atas_nama', 150)->nullable();
            }
        });
    }
};
