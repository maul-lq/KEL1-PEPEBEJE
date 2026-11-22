<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model Permission ini untuk merepresentasikan unit izin akses granular RBAC yang dapat dipasang pada setiap peran sistem maupun peran kustom.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Izin Hak Akses Granular (Permission)
 *
 * memakai model ini untuk mendefinisikan daftar kapabilitas fitur (`nama_permission`, kategori modul, dan deskripsi)
 * yang menjadi sumber tunggal pengecekan otorisasi di controller, middleware, dan Blade.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `permissions`.
 *
 * Contoh pemanggilan:
 * `$perms = Permission::orderBy('kategori_modul')->get()->groupBy('kategori_modul');`
 */
class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'nama_permission',
        'label',
        'kategori_modul',
        'deskripsi',
    ];

    /**
     * menyediakan accessor `slug` yang memetakan ke `nama_permission` agar pemanggilan di template manajemen role dan pengecekan izin seragam.
     *
     * @return string mengembalikan slug permission atau string kosong bila belum disetel.
     *
     * Contoh pemanggilan:
     * `$slug = $permission->slug;`
     */
    public function getSlugAttribute(): string
    {
        return $this->attributes['nama_permission'] ?? '';
    }

    /**
     * mendefinisikan relasi many-to-many ke model Role melalui tabel pivot `role_permissions`.
     *
     * @return BelongsToMany mengembalikan relasi BelongsToMany ke daftar Role pemegang izin ini.
     *
     * Contoh pemanggilan:
     * `$rolesPemegangIzin = $permission->roles;`
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'id_permission',
            'id_role'
        )->withTimestamps();
    }
}
