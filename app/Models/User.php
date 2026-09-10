<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_WADIR2 = 'wadir2';

    public const ROLE_PERENCANAAN = 'perencanaan';

    public const ROLE_PPBJ = 'ppbj';

    public const ROLE_PPK_PP = 'ppk_pp';

    public const ROLE_PERLENGKAPAN = 'perlengkapan';

    public const ROLE_KEUANGAN = 'keuangan';

    public const ROLES = [
        self::ROLE_USER => 'User (Jurusan / Unit / Pemohon)',
        self::ROLE_WADIR2 => 'Wadir 2 (Persetujuan & Kebijakan)',
        self::ROLE_PERENCANAAN => 'Perencanaan (Anggaran & MAK)',
        self::ROLE_PPBJ => 'PPBJ (Pengelola Pengadaan B/J)',
        self::ROLE_PPK_PP => 'PPK / Pejabat Pengadaan',
        self::ROLE_PERLENGKAPAN => 'Perlengkapan (BMN & Penerimaan)',
        self::ROLE_KEUANGAN => 'Keuangan (Pembayaran & Pencairan)',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nip',
        'role',
        'jabatan',
        'unit_kerja',
        'phone',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function isRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function pengadaans(): HasMany
    {
        return $this->hasMany(Pengadaan::class, 'user_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'user_id');
    }
}
