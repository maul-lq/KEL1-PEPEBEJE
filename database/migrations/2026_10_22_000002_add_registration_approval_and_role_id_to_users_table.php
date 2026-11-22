<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menambahkan kolom alur persetujuan registrasi akun serta
 *             foreign key `id_role` pada tabel `users` menuju sistem RBAC dinamis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom approval registrasi dan `id_role` pada `users`.
 *
 * Tujuan: mengaktifkan gerbang verifikasi administrator atas pendaftaran akun baru sekaligus
 * menautkan tiap pengguna ke master `roles`.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_22_000002_add_registration_approval_and_role_id_to_users_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom approval registrasi dan relasi role pada tabel `users`.
     *
     * Tujuan: memberi default `'disetujui'` pada `status_registrasi` agar akun lama hasil
     * seeder tetap aktif normal, sementara akun pendaftar web disetel menunggu lewat controller.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default disetujui menjaga kompatibilitas pengguna seeder lama yang dibuat sebelum fitur approval.
            $table->string('status_registrasi', 50)->default('disetujui')->after('status_aktif');
            $table->text('alasan_penolakan')->nullable()->after('status_registrasi');
            $table->unsignedBigInteger('id_admin_approval')->nullable()->after('alasan_penolakan');
            $table->timestamp('tanggal_approval')->nullable()->after('id_admin_approval');
            $table->unsignedBigInteger('id_role')->nullable()->after('role');

            $table->foreign('id_admin_approval')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_role')
                ->references('id')
                ->on('roles')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan melepas foreign key dan kolom approval/role pada tabel `users`.
     *
     * Tujuan: menjatuhkan foreign key sebelum menghapus kolom agar PostgreSQL mengizinkan rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_admin_approval']);
            $table->dropForeign(['id_role']);
            $table->dropColumn([
                'status_registrasi',
                'alasan_penolakan',
                'id_admin_approval',
                'tanggal_approval',
                'id_role',
            ]);
        });
    }
};
