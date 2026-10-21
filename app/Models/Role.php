<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Role (Peran Pengguna Dinamis)
 *
 * Mengelola peran pengguna dalam sistem SIM-PBJ, mencakup 9 peran institusi bawaan
 * (is_system = true) serta peran kustom baru yang dapat dibuat oleh Administrator.
 */
class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'nama_role',
        'label',
        'deskripsi',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Relasi many-to-many ke master permissions (hak akses fitur).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'id_role',
            'id_permission'
        )->withTimestamps();
    }

    /**
     * Relasi one-to-many ke pengguna yang mengemban peran ini.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_role', 'id');
    }

    /**
     * Memeriksa apakah role ini memiliki permission tertentu.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Role 'admin' memiliki hak akses tak terbatas ke seluruh fitur
        if ($this->nama_role === 'admin') {
            return true;
        }

        return $this->permissions->contains('nama_permission', $permissionSlug)
            || $this->permissions->contains('slug', $permissionSlug);
    }

    /**
     * Menyinkronkan daftar permission ID ke role ini.
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
