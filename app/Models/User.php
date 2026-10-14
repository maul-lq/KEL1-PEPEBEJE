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
 * Mengimplementasikan arsitektur Role-Based Access Control (RBAC) dengan 9 peran resmi institusi:
 * 1. user: Pengusul barang/jasa dari jurusan atau unit kerja.
 * 2. staff_bidang_2: Verifikator dokumen administrasi awal (read-only/pemeriksa).
 * 3. wadir_2: Wakil Direktur II, pemegang otoritas mutlak persetujuan atau penolakan pengadaan.
 * 4. perencanaan: Verifikator ketersediaan anggaran dan penetap kode MAK.
 * 5. ppbj: Pejabat Pengadaan Barang dan Jasa, pengelola paket & penugasan PP/PPK.
 * 6. pp: Pejabat Pengadaan untuk paket bernilai Rp 50jt s.d Rp 200jt.
 * 7. ppk: Pejabat Pembuat Komitmen untuk paket di atas Rp 200jt (terisolasi 4 akun).
 * 8. perlengkapan: Petugas pemeriksa fisik barang dan penerbit BAST.
 * 9. keuangan: Verifikator tagihan, otorisasi memo bayar, dan eksekutor transfer pencairan dana.
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
     * Definisi konstanta 9 peran/role resmi dalam sistem SIM-PBJ.
     */
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
        'password',
        'jabatan',
        'role',
        'status_aktif',
        'no_hp',
        'no_telp_kantor',
        'kode_unit',
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
        ];
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
}
