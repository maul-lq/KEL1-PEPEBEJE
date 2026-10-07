<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_USER_PENGAJU = 'user_pengaju';

    public const ROLE_STAFF_BIDANG_2 = 'staff_bidang_2';

    public const ROLE_WADIR_2 = 'wadir_2';

    public const ROLE_PERENCANAAN = 'perencanaan';

    public const ROLE_PPBJ = 'ppbj';

    public const ROLE_PP = 'pp';

    public const ROLE_PPK = 'ppk';

    public const ROLE_PERLENGKAPAN = 'perlengkapan';

    public const ROLE_KEUANGAN = 'keuangan';

    protected $table = 'users';

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nip',
        'email',
        'nama',
        'password',
        'jabatan',
        'unit_kerja',
        'role',
        'status_aktif',
        'no_hp',
        'no_telp_kantor',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
            'password' => 'hashed',
        ];
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
        return $this->hasMany(PaketPengadaan::class, 'ppbj_user_id', 'id_user');
    }

    public function paketPp(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'pp_user_id', 'id_user');
    }

    public function paketPpk(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'ppk_user_id', 'id_user');
    }

    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'id_user', 'id_user');
    }

    public function reviuPengadaan(): HasMany
    {
        return $this->hasMany(ReviuPengadaan::class, 'id_user', 'id_user');
    }

    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'id_user', 'id_user');
    }

    public function transaksiPencairan(): HasMany
    {
        return $this->hasMany(TransaksiPencairan::class, 'id_user', 'id_user');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'id_user', 'id_user');
    }

    public function pengadaanLogs(): HasMany
    {
        return $this->hasMany(PengadaanLog::class, 'id_user', 'id_user');
    }
}
