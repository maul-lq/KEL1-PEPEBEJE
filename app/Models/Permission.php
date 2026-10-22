<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Permission (Hak Akses Fitur Granular)
 *
 * Mengelola unit wewenang/fitur dalam siklus pengadaan barang & jasa kampus.
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
     * Relasi many-to-many ke peran yang memiliki hak akses ini.
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
