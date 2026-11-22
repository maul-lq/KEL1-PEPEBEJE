<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk menjadikan kolom `nomor_paket` pada tabel `reminder`
 *             bersifat nullable sehingga tabel pengingat dapat melayani alert di luar konteks paket.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pengubah nullability `nomor_paket` dan aturan foreign key di tabel `reminder`.
 *
 * Tujuan: memisahkan penanganan DDL PostgreSQL, SQLite in-memory testing, dan driver lain agar
 * notifikasi sistem (seperti registrasi akun dan pengajuan permohonan) bebas memakai tabel `reminder`.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_22_000003_make_nomor_paket_nullable_in_reminder_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pengubahan `nomor_paket` menjadi nullable dengan `ON DELETE SET NULL`.
     *
     * Tujuan: menggunakan SQL eksplisit khusus PostgreSQL agar penggantian constraint foreign
     * key berjalan mulus tanpa hambatan abstraksi DBAL.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (! Schema::hasTable('reminder')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reminder DROP CONSTRAINT IF EXISTS reminder_nomor_paket_foreign;');
            DB::statement('ALTER TABLE reminder ALTER COLUMN nomor_paket DROP NOT NULL;');
            DB::statement('ALTER TABLE reminder ADD CONSTRAINT reminder_nomor_paket_foreign FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan(nomor_paket) ON DELETE SET NULL ON UPDATE CASCADE;');
        } elseif ($driver === 'sqlite') {
            // Pada SQLite in-memory pengujian, skema awal sudah mendefinisikan nomor_paket nullable sehingga alter tidak diperlukan.
        } else {
            Schema::table('reminder', function (Blueprint $table) {
                $table->dropForeign(['nomor_paket']);
                $table->string('nomor_paket', 100)->nullable()->change();
                $table->foreign('nomor_paket')
                    ->references('nomor_paket')
                    ->on('paket_pengadaan')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    /**
     * Membalikkan migrasi dengan mengembalikan constraint `NOT NULL` pada `nomor_paket`.
     *
     * Tujuan: membersihkan terlebih dahulu baris reminder yang tidak bertaut ke paket sebelum
     * memasang kembali `SET NOT NULL` agar rollback PostgreSQL tidak gagal karena data null.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (! Schema::hasTable('reminder')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reminder DROP CONSTRAINT IF EXISTS reminder_nomor_paket_foreign;');
            // hapus baris tanpa nomor_paket agar constraint SET NOT NULL tidak ditolak PostgreSQL saat rollback.
            DB::table('reminder')->whereNull('nomor_paket')->delete();
            DB::statement('ALTER TABLE reminder ALTER COLUMN nomor_paket SET NOT NULL;');
            DB::statement('ALTER TABLE reminder ADD CONSTRAINT reminder_nomor_paket_foreign FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan(nomor_paket) ON DELETE CASCADE ON UPDATE CASCADE;');
        } elseif ($driver === 'sqlite') {
            // Tidak ada perubahan skema pada SQLite in-memory test.
        } else {
            Schema::table('reminder', function (Blueprint $table) {
                $table->dropForeign(['nomor_paket']);
                $table->string('nomor_paket', 100)->nullable(false)->change();
                $table->foreign('nomor_paket')
                    ->references('nomor_paket')
                    ->on('paket_pengadaan')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }
};
