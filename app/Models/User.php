<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model Pengguna (User Model)
 *
 * Merepresentasikan akun pengguna terotentikasi pada SIM-PBJ.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_user';

    /**
     * Definisi konstanta peran/role resmi dalam sistem SIM-PBJ.
     */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public const ROLE_STAFF_BIDANG_2 = 'staff_bidang_2';

    public const ROLE_WADIR_2 = 'wadir_2';

    public const ROLE_PERENCANAAN = 'perencanaan';

    public const ROLE_PPBJ = 'ppbj';

    public const ROLE_PP = 'pp';

    public const ROLE_PPK = 'ppk';

    public const ROLE_PERLENGKAPAN = 'perlengkapan';

    public const ROLE_KEUANGAN = 'keuangan';

    /**
     * Definisi status verifikasi registrasi pengguna baru oleh Administrator.
     */
    public const STATUS_REGISTRASI_MENUNGGU = 'menunggu_persetujuan';

    public const STATUS_REGISTRASI_DISETUJUI = 'disetujui';

    public const STATUS_REGISTRASI_DITOLAK = 'ditolak';

    public const ROLE_USER_PENGAJU = 'user';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nip',
        'username',
        'email',
        'nama',
        'name',
        'unit_kerja',
        'password',
        'jabatan',
        'id_role',
        'status_aktif',
        'status_registrasi',
        'alasan_penolakan',
        'id_admin_approval',
        'tanggal_approval',
        'no_hp',
        'kode_unit',
        'google_id',
        'google_email',
        'google_avatar',
        'google_linked_at',
        'foto_profil',
        'reminder_email_aktif',
    ];

    /**
     * Kolom-kolom sensitif yang disembunyikan saat serialisasi JSON / array.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Konfigurasi type casting untuk hashing otomatis kata sandi dan status boolean.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status_aktif' => 'boolean',
            'reminder_email_aktif' => 'boolean',
            'tanggal_approval' => 'datetime',
            'google_linked_at' => 'datetime',
        ];
    }

    /**
     * Hook booted model User untuk fallback otomatis pengisian username & nama jika kosong saat register.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Generasi fallback Username jika kosong
            if (empty($user->username)) {
                $user->username = ! empty($user->email)
                    ? explode('@', $user->email)[0]
                    : (! empty($user->nip) ? $user->nip : 'user_'.uniqid());
            }

            // Generasi fallback Nama jika kosong
            if (empty($user->nama)) {
                $user->nama = $user->name ?? $user->username ?? 'Pengguna Sistem';
            }

            // Peran default apabila id_role belum ditentukan secara eksplisit
            if (empty($user->id_role)) {
                $user->id_role = Role::where('nama_role', self::ROLE_USER)->value('id');
            }

            // Default Status Aktif & Registrasi
            if ($user->status_aktif === null) {
                $user->status_aktif = true;
            }

            if (empty($user->status_registrasi)) {
                $user->status_registrasi = self::STATUS_REGISTRASI_DISETUJUI;
            }
        });

        static::saved(function (User $user): void {
            if ($user->wasChanged('id_role')) {
                $user->unsetRelation('roleEntity');
            }
        });
    }

    /**
     * Accessor untuk alias name yang merujuk ke kolom nama.
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['nama'] ?? null;
    }

    /**
     * Mutator untuk alias name yang menyelaraskan ke kolom nama.
     */
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['nama'] = $value;
    }

    /**
     * Accessor untuk alias unit_kerja yang merujuk ke nama unit atau kode_unit.
     */
    public function getUnitKerjaAttribute(): ?string
    {
        return $this->unit?->nama_unit ?? ($this->attributes['kode_unit'] ?? null);
    }

    /**
     * Mutator untuk alias unit_kerja yang memetakan ke kolom kode_unit.
     */
    public function setUnitKerjaAttribute(?string $value): void
    {
        if (! empty($value)) {
            $unit = Unit::where('kode_unit', $value)->orWhere('nama_unit', 'like', "%{$value}%")->first();
            if ($unit) {
                $this->attributes['kode_unit'] = $unit->kode_unit;
            }
        }
    }

    /**
     * Relasi many-to-one ke Unit Kerja tempat pengguna bernaung.
     */
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

    public function roleEntity(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role', 'id');
    }

    public function adminApproval(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin_approval', 'id_user');
    }

    /**
     * Scope query untuk memfilter pengguna berdasarkan satu atau beberapa slug peran pada tabel roles.
     *
     * @param  string|array<int, string>  $roles
     */
    public function scopeWithRole(Builder $query, string|array $roles): Builder
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        return $query->whereHas('roleEntity', fn (Builder $q) => $q->whereIn('nama_role', $roleList));
    }

    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];
        $roleSlug = $this->roleEntity?->nama_role;

        return $roleSlug !== null && in_array($roleSlug, $roleList, true);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roleEntity ? $this->roleEntity->hasPermission($permissionSlug) : false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah pengguna berhak memonitor seluruh aktivitas Pejabat Pengadaan (PP)
     * berdasarkan matriks wewenang peran atau hak akses fitur ('pp.monitoring_aktivitas' / 'lihat_pp').
     */
    public function canMonitorPp(): bool
    {
        return $this->hasAnyPermission(['pp.monitoring_aktivitas', 'lihat_pp']);
    }

    public function isGoogleLinked(): bool
    {
        return ! empty($this->google_id);
    }
}
