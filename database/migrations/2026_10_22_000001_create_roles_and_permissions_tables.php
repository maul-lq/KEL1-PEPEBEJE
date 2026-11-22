<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk membentuk tiga tabel utama RBAC dinamis yaitu `roles`,
 *             `permissions`, dan tabel pivot `role_permissions`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel infrastruktur RBAC (`roles`, `permissions`, `role_permissions`).
 *
 * Tujuan: memindahkan sistem hak akses dari pengecekan role statis menjadi matriks permission
 * granular di database sehingga administrator dapat menambah atau mengubah peran tanpa ubah kode.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_22_000001_create_roles_and_permissions_tables.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembuatan tabel `roles`, `permissions`, dan `role_permissions`.
     *
     * Tujuan: memberi penanda `is_system` pada role bawaan agar role inti sistem terlindungi
     * dari penghapusan, serta composite primary key di tabel pivot untuk mencegah izin duplikat.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama_role', 50)->unique();
            $table->string('label', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false); // Melindungi peran inti BPMN dari aksi hapus di panel admin.
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('nama_permission', 100)->unique();
            $table->string('label', 150);
            $table->string('kategori_modul', 50);
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('id_role')
                ->constrained('roles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('id_permission')
                ->constrained('permissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Composite primary key memastikan pasangan role dan permission tidak mungkin tersimpan ganda.
            $table->primary(['id_role', 'id_permission']);
            $table->timestamps();
        });
    }

    /**
     * Membalikkan migrasi dengan menjatuhkan tabel pivot RBAC lalu tabel master `permissions` dan `roles`.
     *
     * Tujuan: menghapus tabel anak lebih dulu agar tidak terhalang constraint foreign key saat rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
