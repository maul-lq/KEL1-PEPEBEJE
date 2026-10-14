<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Unit Kerja (Jurusan / Bagian / Program Studi)
 *
 * Merepresentasikan unit kerja atau entitas pemohon dalam lingkup politeknik/institusi.
 * Mengelompokkan para pengguna (user pemohon) dan usulan pengadaan barang/jasa
 * berdasarkan kode unik dan klasifikasi unit (akademik, non-akademik, penunjang).
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
     * Relasi one-to-many ke seluruh User pegawai/staf yang bertugas di unit kerja ini.
     *
     * @return HasMany Koleksi pengguna dalam unit ini
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kode_unit', 'kode_unit');
    }

    /**
     * Relasi one-to-many ke seluruh usulan Permohonan Pengadaan yang diajukan oleh unit ini.
     *
     * @return HasMany Koleksi permohonan pengadaan unit ini
     */
    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'kode_unit', 'kode_unit');
    }

    /**
     * Aksesor untuk tipe unit (kompatibilitas dengan alias kolom jenis_unit).
     *
     * @return string|null Jenis/klasifikasi unit kerja
     */
    public function getTipeUnitAttribute(): ?string
    {
        return $this->jenis_unit;
    }
}
