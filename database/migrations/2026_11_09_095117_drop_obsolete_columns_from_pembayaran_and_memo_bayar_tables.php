<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menghapus kolom usang pada tabel `pembayaran`
 *             (`id_user_reviu`, `status_reviu_akhir`, `file_tagihan_vendor`) dan `urutan_level`
 *             pada `memo_bayar`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembersihan kolom usang modul pembayaran dan disposisi memo bayar.
 *
 * Tujuan: menyederhanakan tabel pembayaran karena disposisi memo telah memakai kolom
 * `role_approver` langsung dan bukti tagihan dipisah ke kwitansi/invoice/SPP.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_09_095117_drop_obsolete_columns_from_pembayaran_and_memo_bayar_tables.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penghapusan kolom usang pada `pembayaran` dan `memo_bayar`.
     *
     * Tujuan: melepas foreign key `id_user_reviu` sebelum menghapus kolomnya supaya
     * engine database tidak menolak penghapusan kolom berelasi.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            // Foreign key wajib dilepas lebih dulu sebelum dropColumn pada SQLite dan PostgreSQL.
            $table->dropForeign(['id_user_reviu']);
            $table->dropColumn([
                'id_user_reviu',
                'status_reviu_akhir',
                'file_tagihan_vendor',
            ]);
        });

        Schema::table('memo_bayar', function (Blueprint $table) {
            $table->dropColumn([
                'urutan_level',
            ]);
        });
    }

    /**
     * Membalikkan migrasi dengan memulihkan kolom dan foreign key `id_user_reviu` serta `urutan_level`.
     *
     * Tujuan: menyertakan pemulihan foreign key `nullOnDelete` agar rollback tetap mempertahankan
     * konsistensi relasional ke tabel `users`.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user_reviu')->nullable();
            $table->string('status_reviu_akhir', 50)->nullable();
            $table->string('file_tagihan_vendor', 255)->nullable();

            $table->foreign('id_user_reviu')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('memo_bayar', function (Blueprint $table) {
            $table->integer('urutan_level')->default(1);
        });
    }
};
