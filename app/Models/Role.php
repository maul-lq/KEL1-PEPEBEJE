<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model Role ini untuk mengelola peran sistem dan peran dinamis kustom beserta relasi matriks permission RBAC-nya.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Peran Pengguna RBAC (Role)
 *
 * memodelkan peran pengguna di tabel `roles` dengan penanda `is_system` untuk melindungi peran inti bawaan
 * dari penghapusan tidak sengaja, sekaligus menyediakan pengecekan dan sinkronisasi permission secara dinamis.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `roles`.
 *
 * Contoh pemanggilan:
 * `$role = Role::with('permissions')->where('nama_role', 'ppbj')->firstOrFail();`
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

    /**
     * mengonversi kolom `is_system` ke boolean agar proteksi hapus peran bawaan di RoleController bisa dievaluasi secara ketat.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `if ($role->is_system) { return back()->with('error', 'Peran sistem tidak boleh dihapus.'); }`
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * mendefinisikan relasi many-to-many ke seluruh permission yang dimiliki peran ini melalui tabel `role_permissions`.
     *
     * @return BelongsToMany mengembalikan relasi BelongsToMany ke model Permission.
     *
     * Contoh pemanggilan:
     * `$izin = $role->permissions;`
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
     * menghubungkan peran ke seluruh akun pengguna yang memegangnya melalui FK `id_role`.
     *
     * @return HasMany mengembalikan relasi HasMany ke model User.
     *
     * Contoh pemanggilan:
     * `$jumlahPemegangRole = $role->users()->count();`
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_role', 'id');
    }

    /**
     * memeriksa apakah peran ini memiliki slug permission tertentu pada koleksi permissions yang sudah dimuat.
     *
     * @param  string  $permissionSlug  menerima nama slug permission seperti 'spk.create' atau 'laporan.rekapitulasi'.
     * @return bool mengembalikan true jika permission ditemukan pada peran ini.
     *
     * Contoh pemanggilan:
     * `$bolehCetak = $role->hasPermission('dokumen.unduh_pdf');`
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // mengecek kedua key ('nama_permission' dan accessor 'slug') agar kompatibel dengan pembacaan koleksi maupun JSON.
        return $this->permissions->contains('nama_permission', $permissionSlug)
            || $this->permissions->contains('slug', $permissionSlug);
    }

    /**
     * menyinkronkan daftar ID permission yang dicentang admin ke tabel pivot `role_permissions`.
     *
     * @param  array<int, int|string>  $permissionIds  menerima daftar ID integer permission yang aktif untuk peran ini.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$role->syncPermissions($request->input('permissions', []));`
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
