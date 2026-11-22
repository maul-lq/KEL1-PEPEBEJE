<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk membersihkan kolom-kolom usang lintas tabel transaksi
 *             (`paket_pengadaan`, `pembayaran`, `reminder`, `penerimaan_barang_foto_dokumentasi`,
 *             dan `audit_log`) pasca penyederhanaan model domain.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembersihan mendalam kolom usang di lima tabel pengadaan.
 *
 * Tujuan: menghapus atribut yang sudah tidak direferensikan oleh alur kerja maupun kode
 * aplikasi supaya skema PostgreSQL tetap bersih dan bebas kolom yatim.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_085552_deep_clean_drop_obsolete_columns_from_tables.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan kolom-kolom usang pada lima tabel terkait.
     *
     * Tujuan: menyertakan pemeriksaan `hasColumn` khusus untuk `reminder.is_recurring` agar
     * migrasi aman terhadap kemungkinan pemindahan jadwal reminder di versi berikutnya.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('paket_pengadaan', function (Blueprint $table) {
            $table->dropColumn([
                'id_paket_lkpp',
                'tanggal_penugasan_ppk',
                'status_penugasan_ppk',
                'tanggal_penugasan_pp',
                'tanggal_penunjukan_vendor',
            ]);
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_memo',
                'file_memo',
            ]);
        });

        // cek eksistensi kolom sebelum drop karena is_recurring sempat dibersihkan sebelum dipulihkan lagi di migrasi penjadwalan.
        if (Schema::hasTable('reminder') && Schema::hasColumn('reminder', 'is_recurring')) {
            Schema::table('reminder', function (Blueprint $table) {
                $table->dropColumn([
                    'is_recurring',
                ]);
            });
        }

        Schema::table('penerimaan_barang_foto_dokumentasi', function (Blueprint $table) {
            $table->dropColumn([
                'uploaded_at',
            ]);
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
            ]);
        });
    }

    /**
     * Membalikkan migrasi dengan menambahkan kembali kolom-kolom yang telah dihapus.
     *
     * Tujuan: memulihkan seluruh kolom pada kelima tabel tersebut agar migrasi bersifat dapat
     * dibalik penuh jika dilakukan rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('paket_pengadaan', function (Blueprint $table) {
            $table->string('id_paket_lkpp')->nullable();
            $table->dateTime('tanggal_penugasan_ppk')->nullable();
            $table->string('status_penugasan_ppk')->nullable();
            $table->dateTime('tanggal_penugasan_pp')->nullable();
            $table->dateTime('tanggal_penunjukan_vendor')->nullable();
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->string('nomor_memo', 100)->nullable();
            $table->string('file_memo')->nullable();
        });

        if (Schema::hasTable('reminder') && ! Schema::hasColumn('reminder', 'is_recurring')) {
            Schema::table('reminder', function (Blueprint $table) {
                $table->boolean('is_recurring')->default(false);
            });
        }

        Schema::table('penerimaan_barang_foto_dokumentasi', function (Blueprint $table) {
            $table->dateTime('uploaded_at')->nullable();
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
        });
    }
};
