<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Rekanan / Penyedia (Vendor)
 *
 * Merepresentasikan perusahaan atau penyedia barang/jasa yang bermitra dengan institusi.
 * Menyimpan data pokok legalitas (NPWP sebagai primary key, NIB), nama perusahaan, alamat,
 * status keaktifan bermitra (is_active), data kontak PIC (nama, telepon, email),
 * serta identitas rekening bank untuk penyaluran pembayaran non-tunai.
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
     * Primary key berupa nomor NPWP perusahaan.
     *
     * @var string
     */
    protected $primaryKey = 'npwp';

    /**
     * Kunci utama tidak bersifat auto-increment.
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
        'npwp',
        'nib',
        'nama_perusahaan',
        'alamat',
        'is_active',
        'nama_pic',
        'telepon_pic',
        'email_pic',
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
    ];

    /**
     * Konfigurasi type casting untuk boolean status keaktifan rekanan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relasi one-to-many ke berkas-berkas legalitas resmi vendor (SIUP, Akta, dsb).
     *
     * @return HasMany Koleksi berkas legalitas vendor
     */
    public function filesLegalitas(): HasMany
    {
        return $this->hasMany(VendorFileLegalitas::class, 'npwp', 'npwp');
    }

    /**
     * Relasi one-to-many ke seluruh Paket Pengadaan yang pernah atau sedang dikerjakan rekanan ini.
     *
     * @return HasMany Koleksi paket pengadaan vendor
     */
    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'npwp_vendor', 'npwp');
    }
}
