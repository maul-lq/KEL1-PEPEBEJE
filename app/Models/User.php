<?php

/**
 * Informasi File
 *
 * Penulis   : DendyRafi, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model User ini sebagai entitas autentikasi utama SIM-PBJ dengan sumber peran tunggal via `id_role` (`roles`), dukungan Google OAuth, serta helper otorisasi RBAC.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model Akun Pengguna (User)
 *
 * memodelkan pengguna SIM-PBJ tanpa kolom teks role usang—seluruh pengecekan peran dan izin
 * bersumber tunggal dari relasi `roleEntity` (`id_role` -> `roles.id`) agar RBAC dinamis tetap konsisten.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Authenticatable).
 * Nilai kembali         : Instance model pengguna terautentikasi yang terhubung ke tabel `users` dengan primary key `id_user`.
 *
 * Contoh pemanggilan:
 * `$ppkAktif = User::withRole(User::ROLE_PPK)->where('status_aktif', true)->get();`
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
     * mengonversi password ke hash otomatis, status boolean, serta stempel waktu persetujuan dan penautan Google ke Carbon.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat menyimpan $user->password = 'rahasia' atau membaca $user->status_aktif.`
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
     * melengkapi fallback username, nama, dan `id_role` default saat pembuatan user baru serta menghapus cache relasi role ketika `id_role` diubah.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dijalankan otomatis oleh Eloquent pada event creating dan saved.`
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

            // mengisi peran default ke 'user' hanya bila caller belum menetapkan id_role secara eksplisit.
            if (empty($user->id_role)) {
                $user->id_role = Role::where('nama_role', self::ROLE_USER)->value('id');
            }

            if ($user->status_aktif === null) {
                $user->status_aktif = true;
            }

            if (empty($user->status_registrasi)) {
                $user->status_registrasi = self::STATUS_REGISTRASI_DISETUJUI;
            }
        });

        static::saved(function (User $user): void {
            // mengosongkan relasi roleEntity yang sudah ter-cache di memori agar pengecekan hasPermission berikutnya langsung membaca role baru.
            if ($user->wasChanged('id_role')) {
                $user->unsetRelation('roleEntity');
            }
        });
    }

    /**
     * menyediakan accessor `name` sebagai alias konvensi Laravel dari kolom `nama`.
     *
     * @return string|null mengembalikan nama lengkap pengguna atau null.
     *
     * Contoh pemanggilan:
     * `$nama = $user->name;`
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['nama'] ?? null;
    }

    /**
     * menyelaraskan penulisan atribut `name` langsung ke kolom `nama`.
     *
     * @param  string|null  $value  menerima nama lengkap pengguna dari factory/Auth bawaan Laravel.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$user->name = 'Budi Santoso';`
     */
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['nama'] = $value;
    }

    /**
     * menyediakan accessor `unit_kerja` yang mengutamakan nama unit dari relasi lalu fallback ke `kode_unit`.
     *
     * @return string|null mengembalikan nama unit kerja atau kode unit pengguna.
     *
     * Contoh pemanggilan:
     * `$unit = $user->unit_kerja;`
     */
    public function getUnitKerjaAttribute(): ?string
    {
        return $this->unit?->nama_unit ?? ($this->attributes['kode_unit'] ?? null);
    }

    /**
     * memetakan input teks `unit_kerja` menjadi `kode_unit` yang valid dengan mencocokkan kode atau nama unit di database.
     *
     * @param  string|null  $value  menerima kode unit atau potongan nama unit kerja.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$user->unit_kerja = 'TIK';`
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
     * menghubungkan pengguna ke unit kerja tempat ia bertugas.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Unit.
     *
     * Contoh pemanggilan:
     * `$unit = $user->unit;`
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'kode_unit', 'kode_unit');
    }

    /**
     * menghubungkan pengguna ke seluruh permohonan pengadaan yang ia ajukan.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PermohonanPengadaan.
     *
     * Contoh pemanggilan:
     * `$usulanSaya = $user->permohonanPengadaan;`
     */
    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna (Perencanaan) ke daftar MAK yang ia buat atau tetapkan.
     *
     * @return HasMany mengembalikan relasi HasMany ke model AnggaranMAK.
     *
     * Contoh pemanggilan:
     * `$daftarMak = $user->anggaranMak;`
     */
    public function anggaranMak(): HasMany
    {
        return $this->hasMany(AnggaranMAK::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna PPBJ ke seluruh paket pengadaan yang ia registrasikan.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PaketPengadaan (`id_user_ppbj`).
     *
     * Contoh pemanggilan:
     * `$paket = $user->paketPpbj;`
     */
    public function paketPpbj(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_ppbj', 'id_user');
    }

    /**
     * menghubungkan pengguna Pejabat Pengadaan (PP) ke paket jalur 50-200jt yang ditugaskan padanya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PaketPengadaan (`id_user_pp`).
     *
     * Contoh pemanggilan:
     * `$paketPp = $user->paketPp;`
     */
    public function paketPp(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_pp', 'id_user');
    }

    /**
     * menghubungkan pengguna PPK ke paket-paket yang menjadi tanggung jawab komitmennya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PaketPengadaan (`id_user_ppk`).
     *
     * Contoh pemanggilan:
     * `$paketPpk = $user->paketPpk;`
     */
    public function paketPpk(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_user_ppk', 'id_user');
    }

    /**
     * menghubungkan pengguna verifikator ke catatan keputusan verifikasi awal yang pernah dibuatnya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model VerifikasiParalel.
     *
     * Contoh pemanggilan:
     * `$catatanVerifikasi = $user->verifikasiParalel;`
     */
    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna penelaah (PP/PPK) ke riwayat reviu dokumen yang telah ditulisnya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model ReviuDokumen.
     *
     * Contoh pemanggilan:
     * `$reviu = $user->reviuDokumen;`
     */
    public function reviuDokumen(): HasMany
    {
        return $this->hasMany(ReviuDokumen::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna (Wadir 2/PPK) ke daftar memo pembayaran yang disetujuinya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model MemoBayar.
     *
     * Contoh pemanggilan:
     * `$memo = $user->memoBayar;`
     */
    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan staf Keuangan ke daftar transaksi pencairan SP2D yang dieksekusinya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model TransaksiPencairan.
     *
     * Contoh pemanggilan:
     * `$transaksi = $user->transaksiPencairan;`
     */
    public function transaksiPencairan(): HasMany
    {
        return $this->hasMany(TransaksiPencairan::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna ke kotak masuk notifikasi personal miliknya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model NotifikasiUser.
     *
     * Contoh pemanggilan:
     * `$notifs = $user->notifikasiUser()->where('is_read', false)->get();`
     */
    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna ke seluruh jejak audit aktivitas yang dilakukannya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model AuditLog.
     *
     * Contoh pemanggilan:
     * `$logs = $user->auditLogs;`
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan pengguna ke entitas Role sebagai sumber tunggal peran dan matriks izin RBAC.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Role.
     *
     * Contoh pemanggilan:
     * `$labelRole = $user->roleEntity?->label;`
     */
    public function roleEntity(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role', 'id');
    }

    /**
     * menghubungkan akun pengguna ke Administrator yang menyetujui atau menolak pendaftarannya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo rekursif ke model User (admin penyetuju).
     *
     * Contoh pemanggilan:
     * `$adminPenyetuju = $user->adminApproval;`
     */
    public function adminApproval(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin_approval', 'id_user');
    }

    /**
     * memfilter pengguna berdasarkan satu atau lebih slug peran pada relasi `roleEntity` tanpa mengandalkan kolom teks role usang.
     *
     * @param  Builder  $query  menerima query builder Eloquent User.
     * @param  string|array<int, string>  $roles  menerima slug peran tunggal atau array slug peran sasaran.
     * @return Builder mengembalikan query yang sudah difilter melalui `whereHas('roleEntity', ...)`.
     *
     * Contoh pemanggilan:
     * `$ppkList = User::withRole([User::ROLE_PPK])->where('status_aktif', true)->get();`
     */
    public function scopeWithRole(Builder $query, string|array $roles): Builder
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        return $query->whereHas('roleEntity', fn (Builder $q) => $q->whereIn('nama_role', $roleList));
    }

    /**
     * memeriksa apakah slug peran aktif pengguna cocok dengan salah satu peran yang diminta.
     *
     * @param  string|array<int, string>  $roles  menerima slug peran atau daftar slug peran pembanding.
     * @return bool mengembalikan true jika peran pengguna cocok secara ketat.
     *
     * Contoh pemanggilan:
     * `if ($user->hasRole(User::ROLE_ADMIN)) { ... }`
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];
        $roleSlug = $this->roleEntity?->nama_role;

        return $roleSlug !== null && in_array($roleSlug, $roleList, true);
    }

    /**
     * mendelegasikan pengecekan satu izin fitur granular ke entitas Role milik pengguna.
     *
     * @param  string  $permissionSlug  menerima slug permission RBAC seperti 'paket.create' atau 'anggaran.mak_lock'.
     * @return bool mengembalikan true bila role pengguna memegang izin tersebut, atau false jika belum punya role/izin.
     *
     * Contoh pemanggilan:
     * `if ($user->hasPermission('dokumen.unduh_pdf')) { ... }`
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roleEntity ? $this->roleEntity->hasPermission($permissionSlug) : false;
    }

    /**
     * memeriksa apakah pengguna memiliki minimal satu dari daftar izin RBAC yang diberikan.
     *
     * @param  array<int, string>  $permissions  menerima array slug permission yang dianggap sah.
     * @return bool mengembalikan true segera setelah satu permission cocok (short-circuit).
     *
     * Contoh pemanggilan:
     * `if ($user->hasAnyPermission(['reviu.pp', 'reviu.ppk'])) { ... }`
     */
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
     * memeriksa hak akses monitoring aktivitas Pejabat Pengadaan (PP) secara murni berbasis RBAC permission.
     *
     * @return bool mengembalikan true jika pengguna memiliki `pp.monitoring_aktivitas` atau `lihat_pp`.
     *
     * Contoh pemanggilan:
     * `if ($user->canMonitorPp()) { ... }`
     */
    public function canMonitorPp(): bool
    {
        return $this->hasAnyPermission(['pp.monitoring_aktivitas', 'lihat_pp']);
    }

    /**
     * mengecek apakah akun pengguna sudah ditautkan ke Google OAuth untuk login sekali klik.
     *
     * @return bool mengembalikan true jika `google_id` terisi.
     *
     * Contoh pemanggilan:
     * `$terhubungGoogle = auth()->user()->isGoogleLinked();`
     */
    public function isGoogleLinked(): bool
    {
        return ! empty($this->google_id);
    }
}
