<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model Pengguna (User Model)
 *
 * Merepresentasikan akun pengguna terotentikasi pada SIM-PBJ.
 * Mengimplementasikan arsitektur Role-Based Access Control (RBAC) dengan 9 peran organisasi resmi institusi:
 * 1. admin: Administrator sistem (manajemen pengguna, hak akses, dan pemantauan penuh).
 * 2. user: Pengusul barang/jasa dari jurusan atau unit kerja.
 * 3. bidang_2: Bidang II yang terdiri atas sub-hak akses:
 *    - staff_bidang_2: Verifikator dokumen administrasi awal (read-only/pemeriksa kelengkapan).
 *    - wadir_2: Wakil Direktur II, pemegang otoritas mutlak persetujuan (ACC) atau penolakan pengadaan.
 * 4. perencanaan: Verifikator ketersediaan anggaran dan penetap/pemecah kode MAK.
 * 5. ppbj: Unit Pengadaan Barang dan Jasa, pengelola paket, pemecah jenis pengadaan, penugasan PP/PPK, dan penerbit Kontrak/SPK.
 * 6. pp: Pejabat Pengadaan untuk paket bernilai > Rp 50jt s.d < Rp 200jt (1 akun aktif).
 * 7. ppk: Pejabat Pembuat Komitmen untuk paket > Rp 50jt (4 akun PPK mandiri terisolasi).
 * 8. perlengkapan: Petugas pemeriksa fisik barang (Belanja Barang / Belanja Modal) dan penerbit BAST.
 * 9. keuangan: Verifikator tagihan SPP dan eksekutor transfer pencairan dana (SP2D).
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

    /**
     * Alias kompatibilitas ke belakang untuk peran pemohon/pengusul.
     */
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
        'role',
        'id_role',
        'status_aktif',
        'status_registrasi',
        'alasan_penolakan',
        'id_admin_approval',
        'tanggal_approval',
        'no_hp',
        'no_telp_kantor',
        'kode_unit',
        'google_id',
        'google_email',
        'google_avatar',
        'google_linked_at',
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
            'tanggal_approval' => 'datetime',
            'google_linked_at' => 'datetime',
        ];
    }

    /**
     * Hook booted model User untuk fallback otomatis pengisian username jika kosong.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->username)) {
                $user->username = ! empty($user->email)
                    ? explode('@', $user->email)[0]
                    : (! empty($user->nip) ? $user->nip : 'user_'.uniqid());
            }
            if (empty($user->nama)) {
                $user->nama = $user->name ?? $user->username ?? 'Pengguna Sistem';
            }
            if (empty($user->status_registrasi)) {
                $user->status_registrasi = ($user->status_aktif === false)
                    ? self::STATUS_REGISTRASI_MENUNGGU
                    : self::STATUS_REGISTRASI_DISETUJUI;
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
     * Mutator untuk alias unit_kerja agar tidak error saat dipassing mass assignment lama.
     */
    public function setUnitKerjaAttribute(?string $value): void
    {
        // Opsional: alias untuk unit_kerja jika ada pemanggilan lama
    }

    /**
     * Relasi many-to-one ke Unit Kerja tempat pengguna bernaung.
     *
     * @return BelongsTo Relasi ke model Unit
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'kode_unit', 'kode_unit');
    }

    /**
     * Relasi one-to-many ke usulan Permohonan Pengadaan yang diajukan oleh pengguna ini.
     *
     * @return HasMany Koleksi permohonan pengadaan
     */
    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke data Anggaran MAK yang dibuat/ditetapkan oleh staf perencanaan.
     *
     * @return HasMany Koleksi anggaran MAK
     */
    public function anggaranMak(): HasMany
    {
        return $this->hasMany(AnggaranMAK::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke Paket Pengadaan yang diregistrasikan oleh staf PPBJ ini.
     *
     * @return HasMany Koleksi paket PPBJ
     */
    public function paketPpbj(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_ppbj', 'id_user');
    }

    /**
     * Relasi one-to-many ke Paket Pengadaan yang ditugaskan kepada Pejabat Pengadaan (PP) ini.
     *
     * @return HasMany Koleksi paket PP
     */
    public function paketPp(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_pp', 'id_user');
    }

    /**
     * Relasi one-to-many ke Paket Pengadaan yang ditugaskan kepada Pejabat Pembuat Komitmen (PPK) ini.
     *
     * @return HasMany Koleksi paket PPK
     */
    public function paketPpk(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_ppk', 'id_user');
    }

    /**
     * Relasi one-to-many ke keputusan Verifikasi Paralel yang dibuat pengguna ini (Bidang 2, Perencanaan, Wadir 2).
     *
     * @return HasMany Koleksi riwayat verifikasi paralel
     */
    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke hasil reviu dokumen persiapan yang dibuat oleh pengguna ini.
     *
     * @return HasMany Koleksi reviu dokumen
     */
    public function reviuDokumen(): HasMany
    {
        return $this->hasMany(ReviuDokumen::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke persetujuan Memo Bayar berjenjang yang disahkan pengguna ini.
     *
     * @return HasMany Koleksi memo bayar
     */
    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke bukti Transaksi Pencairan yang dieksekusi oleh staf keuangan ini.
     *
     * @return HasMany Koleksi pencairan transfer
     */
    public function transaksiPencairan(): HasMany
    {
        return $this->hasMany(TransaksiPencairan::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke daftar Notifikasi User personal yang diterima pengguna.
     *
     * @return HasMany Koleksi notifikasi user
     */
    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke catatan Jejak Audit mutasi data yang dilakukan pengguna ini.
     *
     * @return HasMany Koleksi audit log
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_user', 'id_user');
    }

    /**
     * Relasi ke entitas Role dinamis dari tabel roles.
     */
    public function roleEntity(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role', 'id');
    }

    /**
     * Relasi ke Administrator yang menyetujui atau menolak pendaftaran akun ini.
     */
    public function adminApproval(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin_approval', 'id_user');
    }

    /**
     * Memeriksa apakah pengguna memiliki salah satu dari peran yang ditentukan.
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $roleList, true) ||
            ($this->roleEntity && in_array($this->roleEntity->nama_role, $roleList, true));
    }

    /**
     * Memeriksa apakah pengguna memiliki hak akses (permission) ke fitur tertentu.
     * Role Administrator ('admin') otomatis memiliki akses tak terbatas ke seluruh fitur.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->role === self::ROLE_ADMIN || ($this->roleEntity && $this->roleEntity->nama_role === self::ROLE_ADMIN)) {
            return true;
        }

        if ($this->relationLoaded('roleEntity') && $this->roleEntity) {
            return $this->roleEntity->hasPermission($permissionSlug);
        }

        $role = $this->roleEntity ?? Role::where('nama_role', $this->role)->with('permissions')->first();

        return $role ? $role->hasPermission($permissionSlug) : false;
    }

    /**
     * Memeriksa apakah pengguna memiliki salah satu dari sekumpulan permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->role === self::ROLE_ADMIN || ($this->roleEntity && $this->roleEntity->nama_role === self::ROLE_ADMIN)) {
            return true;
        }

        foreach ($permissions as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah akun pengguna lokal ini sudah terhubung dengan akun Google OAuth.
     *
     * Digunakan pada tampilan profil dan pengecekan alur otentikasi login Google.
     *
     * @return bool True jika google_id telah tersimpan, false jika belum ditautkan.
     */
    public function isGoogleLinked(): bool
    {
        return ! empty($this->google_id);
    }
}
