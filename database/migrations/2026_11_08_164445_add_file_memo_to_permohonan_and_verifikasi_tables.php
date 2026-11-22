<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk menambahkan kolom unggahan berkas memo resmi pada tabel
 *             `permohonan_pengadaan`, `verifikasi_paralel`, dan `memo_bayar`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom lampiran file memo verifikasi dan memo pembayaran.
 *
 * Tujuan: memfasilitasi pengarsipan scan memo cek MAK, memo ketersediaan anggaran, memo
 * keputusan Wadir II/PPK, serta memo persetujuan bayar ke dalam basis data.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_08_164445_add_file_memo_to_permohonan_and_verifikasi_tables.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom nomor dan file memo secara bertahap dan aman.
     *
     * Tujuan: menambahkan pengecekan `hasTable` dan `hasColumn` pada setiap tabel target
     * sehingga migrasi berjalan mulus di semua state basis data.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (Schema::hasTable('permohonan_pengadaan')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                if (! Schema::hasColumn('permohonan_pengadaan', 'file_memo_cek_mak')) {
                    $table->string('file_memo_cek_mak', 255)->nullable()->after('catatan_memo_cek_mak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'file_memo_ketersediaan_mak')) {
                    $table->string('file_memo_ketersediaan_mak', 255)->nullable()->after('catatan_memo_ketersediaan_mak');
                }
            });
        }

        if (Schema::hasTable('verifikasi_paralel')) {
            Schema::table('verifikasi_paralel', function (Blueprint $table) {
                if (! Schema::hasColumn('verifikasi_paralel', 'nomor_memo')) {
                    $table->string('nomor_memo', 100)->nullable()->after('catatan_alasan');
                }
                if (! Schema::hasColumn('verifikasi_paralel', 'file_memo')) {
                    $table->string('file_memo', 255)->nullable()->after('nomor_memo');
                }
            });
        }

        if (Schema::hasTable('memo_bayar')) {
            Schema::table('memo_bayar', function (Blueprint $table) {
                if (! Schema::hasColumn('memo_bayar', 'nomor_memo')) {
                    $table->string('nomor_memo', 100)->nullable()->after('catatan_memo');
                }
                if (! Schema::hasColumn('memo_bayar', 'file_memo')) {
                    $table->string('file_memo', 255)->nullable()->after('nomor_memo');
                }
            });
        }
    }

    /**
     * Membalikkan migrasi dengan menghapus kolom nomor dan file memo dari ketiga tabel.
     *
     * Tujuan: melepas kolom dengan urutan aman dan penjagaan `hasColumn` saat rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (Schema::hasTable('permohonan_pengadaan')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                if (Schema::hasColumn('permohonan_pengadaan', 'file_memo_cek_mak')) {
                    $table->dropColumn('file_memo_cek_mak');
                }
                if (Schema::hasColumn('permohonan_pengadaan', 'file_memo_ketersediaan_mak')) {
                    $table->dropColumn('file_memo_ketersediaan_mak');
                }
            });
        }

        if (Schema::hasTable('verifikasi_paralel')) {
            Schema::table('verifikasi_paralel', function (Blueprint $table) {
                if (Schema::hasColumn('verifikasi_paralel', 'file_memo')) {
                    $table->dropColumn('file_memo');
                }
                if (Schema::hasColumn('verifikasi_paralel', 'nomor_memo')) {
                    $table->dropColumn('nomor_memo');
                }
            });
        }

        if (Schema::hasTable('memo_bayar')) {
            Schema::table('memo_bayar', function (Blueprint $table) {
                if (Schema::hasColumn('memo_bayar', 'file_memo')) {
                    $table->dropColumn('file_memo');
                }
                if (Schema::hasColumn('memo_bayar', 'nomor_memo')) {
                    $table->dropColumn('nomor_memo');
                }
            });
        }
    }
};
