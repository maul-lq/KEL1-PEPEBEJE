<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk menambahkan kolom JSON `dokumen_lampiran` pada tabel
 *             `permohonan_pengadaan` guna menyimpan metadata multi-lampiran pendukung pengajuan.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom `dokumen_lampiran` bertipe JSON pada `permohonan_pengadaan`.
 *
 * Tujuan: memfasilitasi unggahan hingga beberapa berkas lampiran sekaligus (PDF/Office/gambar)
 * dalam satu array JSON terstruktur di samping dokumen KAK utama.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_11_183254_add_dokumen_lampiran_to_permohonan_pengadaan_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom `dokumen_lampiran` bertipe JSON.
     *
     * Tujuan: menjadikan kolom ini nullable agar usulan lama sebelum fitur multi-lampiran
     * tetap valid tanpa memerlukan migrasi data manual.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->json('dokumen_lampiran')->nullable()->after('file_spesifikasi_teknis');
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus kolom `dokumen_lampiran` dari `permohonan_pengadaan`.
     *
     * Tujuan: membersihkan kolom JSON lampiran tambahan ketika dilakukan rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->dropColumn('dokumen_lampiran');
        });
    }
};
