<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id_user';

    public const ROLE_USER = 'user';

    public const ROLE_STAFF_BIDANG_2 = 'staff_bidang_2';

    public const ROLE_WADIR_2 = 'wadir_2';

    public const ROLE_PERENCANAAN = 'perencanaan';

    public const ROLE_PPBJ = 'ppbj';

    public const ROLE_PP = 'pp';

    public const ROLE_PPK = 'ppk';

    public const ROLE_PERLENGKAPAN = 'perlengkapan';

    public const ROLE_KEUANGAN = 'keuangan';

    // Aliases for backwards compatibility
    public const ROLE_USER_PENGAJU = 'user';

    protected $fillable = [
        'nip',
        'username',
        'email',
        'nama',
        'password',
        'jabatan',
        'role',
        'status_aktif',
        'no_hp',
        'no_telp_kantor',
        'kode_unit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status_aktif' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'kode_unit', 'kode_unit');
    }

    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'id_user', 'id_user');
    }

    public function anggaranMak(): HasMany
    {
        return $this->hasMany(AnggaranMAK::class, 'id_user', 'id_user');
    }

    public function paketPpbj(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_ppbj', 'id_user');
    }

    public function paketPp(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_pp', 'id_user');
    }

    public function paketPpk(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_ppk', 'id_user');
    }

    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'id_user', 'id_user');
    }

    public function reviuDokumen(): HasMany
    {
        return $this->hasMany(ReviuDokumen::class, 'id_user', 'id_user');
    }

    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'id_user', 'id_user');
    }

    public function transaksiPencairan(): HasMany
    {
        return $this->hasMany(TransaksiPencairan::class, 'id_user', 'id_user');
    }

    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_user', 'id_user');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_user', 'id_user');
    }
}
