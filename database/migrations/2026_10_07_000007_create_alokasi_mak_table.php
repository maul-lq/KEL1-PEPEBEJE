<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `alokasi_mak` yang menghubungkan surat
 *             permohonan dengan rekening `anggaran_mak` beserta nominal anggarannya.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `alokasi_mak`.
 *
 * Tujuan: memisahkan histori pemotongan pagu MAK dalam tabel pivot `alokasi_mak` agar
 * satu usulan dapat dipetakan nominal alokasinya secara akuntabel.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000007_create_alokasi_mak_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `alokasi_mak`.
     *
     * Tujuan: membangun relasi cascade ke `permohonan_pengadaan` dan `anggaran_mak` agar
     * jejak alokasi nominal tiap surat dapat diaudit dengan presisi `decimal(15, 2)`.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('alokasi_mak', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 100);
            $table->string('kode_mak', 50);
            $table->decimal('nominal_alokasi', 15, 2);
            $table->timestamp('tanggal_alokasi')->useCurrent();
            $table->timestamps();

            // pasang cascadeOnDelete supaya pembatalan surat permohonan ikut membersihkan baris alokasinya.
            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('kode_mak')
                ->references('kode_mak')
                ->on('anggaran_mak')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `alokasi_mak`.
     *
     * Tujuan: membuang tabel alokasi MAK apabila skema di-rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('alokasi_mak');
    }
};
