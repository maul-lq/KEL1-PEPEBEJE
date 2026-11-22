<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk membentuk tabel `riwayat_penugasan_ppk` yang mencatat
 *             perpindahan tugas Pejabat Pembuat Komitmen pada paket pengadaan (historis awal).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `riwayat_penugasan_ppk`.
 *
 * Tujuan: merekam PPK lama, PPK baru, aktor pengubah, dan alasan pengalihan tugas sebelum
 * nantinya skema ini diringkas pada migrasi pembersihan berikutnya.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000009_create_riwayat_penugasan_ppk_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembuatan tabel `riwayat_penugasan_ppk`.
     *
     * Tujuan: mendefinisikan tiga foreign key pengguna (`ppk_lama_user_id`, `ppk_baru_user_id`,
     * `diubah_oleh_user_id`) agar transisi tanggung jawab paket tercatat penuh.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('riwayat_penugasan_ppk', function (Blueprint $table) {
            $table->id('id_riwayat');
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('ppk_lama_user_id')->nullable(); // izinkan null karena penugasan pertama belum memiliki PPK sebelumnya.
            $table->unsignedBigInteger('ppk_baru_user_id');
            $table->unsignedBigInteger('diubah_oleh_user_id');
            $table->text('alasan_perubahan')->nullable();
            $table->timestamp('tanggal_penugasan')->useCurrent();
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnUpdate();

            $table->foreign('ppk_lama_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('ppk_baru_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('diubah_oleh_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `riwayat_penugasan_ppk`.
     *
     * Tujuan: menghapus tabel riwayat penugasan PPK ketika rollback dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_penugasan_ppk');
    }
};
