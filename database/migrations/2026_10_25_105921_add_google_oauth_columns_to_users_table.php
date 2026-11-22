<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk menambahkan kolom identitas Google OAuth (`google_id`,
 *             `google_email`, `google_avatar`, `google_linked_at`) pada tabel `users`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom penautan Google OAuth pada tabel `users`.
 *
 * Tujuan: mendukung login SSO Google dengan mengikat `google_id` unik ke satu akun SIM-PBJ
 * yang telah disetujui administrator tanpa menghilangkan login NIP/username konvensional.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_25_105921_add_google_oauth_columns_to_users_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom identitas Google OAuth pada tabel `users`.
     *
     * Tujuan: menjadikan `google_id` unik sekaligus nullable agar akun yang belum ditautkan
     * ke Google tetap valid namun satu akun Google tidak pernah tertaut ke dua akun berbeda.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Indeks unique pada google_id mencegah satu akun Google tertaut ke lebih dari satu akun pegawai.
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('google_email')->nullable()->after('google_id');
            $table->string('google_avatar')->nullable()->after('google_email');
            $table->timestamp('google_linked_at')->nullable()->after('google_avatar');
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus seluruh kolom identitas Google OAuth pada `users`.
     *
     * Tujuan: membersihkan empat kolom OAuth Google apabila dilakukan rollback skema.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'google_email',
                'google_avatar',
                'google_linked_at',
            ]);
        });
    }
};
