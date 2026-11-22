<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menghapus tabel `riwayat_penugasan_ppk` yang usang
 *             karena riwayat pengalihan PPK telah direkam di `audit_log` dan kolom paket.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penghapus tabel `riwayat_penugasan_ppk`.
 *
 * Tujuan: meniadakan tabel duplikat pencatatan pengalihan PPK agar sumber kebenaran histori
 * perubahan terpusat pada `audit_log`.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_054536_drop_riwayat_penugasan_ppk_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan tabel `riwayat_penugasan_ppk`.
     *
     * Tujuan: menggunakan `dropIfExists` supaya penghapusan aman baik di database lama maupun baru.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::dropIfExists('riwayat_penugasan_ppk');
    }

    /**
     * Membalikkan migrasi dengan membangun kembali tabel `riwayat_penugasan_ppk`.
     *
     * Tujuan: menyediakan struktur pemulihan kolom saat rollback migrasi dibutuhkan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (! Schema::hasTable('riwayat_penugasan_ppk')) {
            Schema::create('riwayat_penugasan_ppk', function (Blueprint $table) {
                $table->bigIncrements('id_riwayat');
                $table->string('kode_paket', 50)->nullable();
                $table->string('nomor_paket', 50)->nullable();
                $table->unsignedBigInteger('ppk_lama_user_id')->nullable();
                $table->unsignedBigInteger('ppk_baru_user_id')->nullable();
                $table->unsignedBigInteger('diubah_oleh_user_id')->nullable();
                $table->text('alasan_perubahan')->nullable();
                $table->timestamp('tanggal_penugasan')->useCurrent();
                $table->timestamps();
            });
        }
    }
};
