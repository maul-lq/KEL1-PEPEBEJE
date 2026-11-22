<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menghapus kolom `is_active` dari tabel `vendors`
 *             setelah seluruh penyedia terdaftar diperlakukan aktif dalam skema minimalis baru.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penghapus kolom `is_active` pada tabel `vendors`.
 *
 * Tujuan: membersihkan flag status aktif pada tabel vendor karena antarmuka pengadaan
 * tidak lagi memfilter penyedia nonaktif secara terpisah.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_082727_drop_is_active_from_vendors_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan kolom `is_active` pada `vendors`.
     *
     * Tujuan: menjaga idempotensi dengan memeriksa keberadaan kolom terlebih dahulu.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (Schema::hasColumn('vendors', 'is_active')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    /**
     * Membalikkan migrasi dengan memulihkan kolom `is_active` bertipe boolean default true.
     *
     * Tujuan: menyediakan langkah rollback simetris bila flag status vendor dibutuhkan lagi.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (! Schema::hasColumn('vendors', 'is_active')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }
    }
};
