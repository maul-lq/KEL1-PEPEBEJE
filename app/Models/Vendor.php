<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model Vendor ini dengan struktur minimalis (hanya `nama_vendor` dan `file_dokumen_legalitas`) yang diinput secara inline pada tahap belanja/perikatan.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Rekanan Penyedia (Vendor)
 *
 * menyederhanakan model Vendor agar tidak lagi menyimpan NPWP, rekening bank, atau flag aktif,
 * sehingga penetapan vendor cukup menginput nama rekanan dan mengunggah dokumen legalitasnya saat perikatan.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `vendors`.
 *
 * Contoh pemanggilan:
 * `$vendor = Vendor::firstOrCreate(['nama_vendor' => $nama], ['file_dokumen_legalitas' => $path]);`
 */
class Vendor extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'vendors';

    /**
     * Primary key model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Kunci utama bersifat auto-increment.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Tipe data primary key adalah integer.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_vendor',
        'file_dokumen_legalitas',
    ];

    /**
     * menyediakan accessor alias `id_vendor` yang mengembalikan primary key `id` demi kompatibilitas pemanggilan lama.
     *
     * @return int|string|null mengembalikan ID vendor atau null jika belum tersimpan.
     *
     * Contoh pemanggilan:
     * `$id = $vendor->id_vendor;`
     */
    public function getIdVendorAttribute(): int|string|null
    {
        return $this->id;
    }

    /**
     * menghubungkan rekanan penyedia ke seluruh paket pengadaan yang ditanganinya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paketVendor = $vendor->paketPengadaan;`
     */
    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'id_vendor', 'id');
    }
}
