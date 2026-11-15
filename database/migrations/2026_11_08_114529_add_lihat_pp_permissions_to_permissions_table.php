<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Menjalankan migrasi untuk menambahkan hak akses (permission) 'pp.monitoring_aktivitas' dan 'lihat_pp'.
     * Memungkinkan Koordinator PPBJ dan peran dengan hak akses terkait memonitor seluruh aktivitas PP.
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

        // Lampirkan secara otomatis ke role 'ppbj' dan 'admin'
        $roles = Role::whereIn('nama_role', ['ppbj', 'admin'])->get();
        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($createdPermissionIds);
        }
    }

    /**
     * Membalikkan migrasi (rollback).
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
