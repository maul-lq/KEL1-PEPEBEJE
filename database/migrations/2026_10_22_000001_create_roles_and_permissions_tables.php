<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migrasi pembuatan tabel roles, permissions, dan role_permissions.
     */
    public function up(): void
    {
        // 1. Tabel master Roles (Peran Pengguna Dinamis)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama_role', 50)->unique(); // slug: admin, user, ppbj, auditor, dll.
            $table->string('label', 100); // Label tampilan: "Administrator Sistem", dll.
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false); // true untuk 9 role bawaan sistem agar tidak dapat dihapus
            $table->timestamps();
        });

        // 2. Tabel master Permissions (Hak Akses Fitur Granular)
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('nama_permission', 100)->unique(); // slug: permohonan.create, spk.create, dll.
            $table->string('label', 150); // Label tampilan: "Membuat Permohonan Pengadaan Baru"
            $table->string('kategori_modul', 50); // Permohonan, Verifikasi, Pengadaan, BAST, Pembayaran, Monitoring, Administrasi
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        // 3. Tabel pivot relasi Many-to-Many antara Role dan Permission
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('id_role')
                ->constrained('roles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('id_permission')
                ->constrained('permissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->primary(['id_role', 'id_permission']);
            $table->timestamps();
        });
    }

    /**
     * Membatalkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
