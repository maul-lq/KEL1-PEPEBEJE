<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom approval registrasi dan relasi role pada tabel users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Status persetujuan registrasi pengguna: 'menunggu_persetujuan', 'disetujui', 'ditolak'
            // Default 'disetujui' agar seluruh pengguna eksisting dari seeder/database tetap aktif normal
            $table->string('status_registrasi', 50)->default('disetujui')->after('status_aktif');
            $table->text('alasan_penolakan')->nullable()->after('status_registrasi');

            // ID Administrator yang memberikan keputusan ACC / Tolak
            $table->unsignedBigInteger('id_admin_approval')->nullable()->after('alasan_penolakan');
            $table->timestamp('tanggal_approval')->nullable()->after('id_admin_approval');

            // Relasi opsional ke tabel roles (id_role) berdampingan dengan kolom 'role' (string) untuk kompatibilitas
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
     * Membatalkan migrasi.
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
