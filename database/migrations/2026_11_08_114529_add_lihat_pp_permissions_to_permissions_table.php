<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menambahkan dua permission pemantauan aktivitas Pejabat
 *             Pengadaan (`pp.monitoring_aktivitas` dan `lihat_pp`) beserta penautan otomatis ke
 *             peran `ppbj` dan `admin`.
 */

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Kelas migrasi anonim penambah permission pemantauan aktivitas Pejabat Pengadaan (PP).
 *
 * Tujuan: menyinkronkan hak akses khusus pengawasan kinerja PP tanpa harus menjalankan ulang
 * seeder penuh pada database produksi yang sudah berjalan.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_08_114529_add_lihat_pp_permissions_to_permissions_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan permission `pp.monitoring_aktivitas` dan `lihat_pp`.
     *
     * Tujuan: memakai `updateOrCreate` dan `syncWithoutDetaching` agar penambahan permission
     * bersifat idempotent dan langsung aktif pada peran `ppbj` serta `admin`.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        $permissions = [
            [
                'nama_permission' => 'pp.monitoring_aktivitas',
                'label' => 'Memonitor Seluruh Aktivitas & Hasil Negosiasi PP',
                'kategori_modul' => 'Pengadaan',
                'deskripsi' => 'Memberikan hak akses untuk memonitor progres telaah dokumen, negosiasi harga, dan rekanan yang diproses Pejabat Pengadaan (PP).',
            ],
            [
                'nama_permission' => 'lihat_pp',
                'label' => 'Melihat & Memonitor Seluruh Berkas / Aktivitas PP (Lihat PP)',
                'kategori_modul' => 'Pengadaan',
                'deskripsi' => 'Alias wewenang RBAC untuk melihat seluruh berkas perikatan dan riwayat audit tindakan Pejabat Pengadaan (PP).',
            ],
        ];

        $createdPermissionIds = [];
        foreach ($permissions as $permData) {
            $perm = Permission::updateOrCreate(
                ['nama_permission' => $permData['nama_permission']],
                $permData
            );
            $createdPermissionIds[] = $perm->id;
        }

        // gunakan syncWithoutDetaching agar permission lain yang sudah dikonfigurasi admin tidak ikut terhapus.
        $roles = Role::whereIn('nama_role', ['ppbj', 'admin'])->get();
        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($createdPermissionIds);
        }
    }

    /**
     * Membalikkan migrasi dengan melepas dan menghapus permission pemantauan PP.
     *
     * Tujuan: melepaskan relasi pivot sebelum menghapus entri permission agar constraint FK
     * pada tabel `role_permissions` tetap bersih saat rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        $permNames = ['pp.monitoring_aktivitas', 'lihat_pp'];
        $permissions = Permission::whereIn('nama_permission', $permNames)->get();

        foreach ($permissions as $perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
