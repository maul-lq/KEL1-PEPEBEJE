<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk membentuk tabel `reviu_dokumen` guna menyimpan
 *             catatan reviu kelengkapan teknis paket pengadaan oleh Pejabat Pengadaan maupun PPK.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `reviu_dokumen`.
 *
 * Tujuan: mencatat riwayat putaran reviu paket (`putaran_ke`, reviewer `pp`/`ppk`, dan
 * catatan perbaikan) agar proses revisi dokumen paket oleh PPBJ terarsip jelas.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000008_create_reviu_pengadaan_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `reviu_dokumen`.
     *
     * Tujuan: mendefinisikan primary key `id_reviu` beserta relasi paket dan pengguna
     * reviewer sehingga histori koreksi dokumen tidak saling menimpa.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('reviu_dokumen', function (Blueprint $table) {
            $table->id('id_reviu');
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user');
            $table->integer('putaran_ke')->default(1); // lacak nomor putaran reviu agar siklus perbaikan dokumen berulang terdokumentasi.
            $table->string('role_reviewer', 50);
            $table->string('status_reviu', 50);
            $table->text('catatan_perbaikan')->nullable();
            $table->timestamp('tanggal_reviu')->useCurrent();
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
     * Membalikkan migrasi dengan menghapus tabel `reviu_dokumen`.
     *
     * Tujuan: menghapus tabel reviu dokumen saat rollback migrasi dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('reviu_dokumen');
    }
};
