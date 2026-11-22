<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model Unit ini untuk mengelola master data jurusan, unit penunjang, dan bagian kerja di Politeknik Negeri Jakarta.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Unit Kerja / Jurusan (Unit)
 *
 * memodelkan entitas organisasi pengusul di tabel `units` dengan primary key string `kode_unit`
 * untuk mengelompokkan akun pegawai dan rekapitulasi anggaran permohonan per unit kerja.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `units`.
 *
 * Contoh pemanggilan:
 * `$units = Unit::orderBy('kode_unit')->get();`
 */
class Unit extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'units';

    /**
     * Primary key tabel berupa kode string unik unit (contoh: 'JTI', 'JTM', 'UPT-TIK').
     *
     * @var string
     */
    protected $primaryKey = 'kode_unit';

    /**
     * Kunci utama tidak menggunakan auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipe data primary key adalah string.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'jenis_unit',
    ];

    /**
     * menghubungkan unit kerja ke seluruh akun pengguna yang bernaung di bawahnya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model User.
     *
     * Contoh pemanggilan:
     * `$anggotaUnit = $unit->users;`
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kode_unit', 'kode_unit');
    }

    /**
     * menghubungkan unit kerja ke seluruh usulan permohonan pengadaan yang diajukan oleh unit ini.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PermohonanPengadaan.
     *
     * Contoh pemanggilan:
     * `$usulanUnit = $unit->permohonanPengadaan;`
     */
    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'kode_unit', 'kode_unit');
    }

    /**
     * menyediakan accessor alias `tipe_unit` yang membaca `jenis_unit` demi kompatibilitas pemanggilan lama.
     *
     * @return string|null mengembalikan jenis/klasifikasi unit kerja atau null.
     *
     * Contoh pemanggilan:
     * `$klasifikasi = $unit->tipe_unit;`
     */
    public function getTipeUnitAttribute(): ?string
    {
        return $this->jenis_unit;
    }
}
