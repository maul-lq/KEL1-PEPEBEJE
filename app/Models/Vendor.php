<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Rekanan / Penyedia (Vendor)
 *
 * Merepresentasikan perusahaan atau penyedia barang/jasa yang bermitra dengan institusi.
 * Diselaraskan ke struktur minimalis: id (primary key auto-increment), nama_vendor,
 * file_dokumen_legalitas, dan status keaktifan bermitra (is_active).
 * Tetap mempertahankan kompatibilitas atribut legacy (npwp, nib, dsb).
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
        'is_active',
        'nama_perusahaan',
        'npwp',
        'nib',
        'alamat',
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
     * Accessor alias id_vendor untuk primary key id atau npwp legacy.
     */
    public function getIdVendorAttribute(): int|string|null
    {
        return $this->id ?: ($this->attributes['npwp'] ?? null);
    }

    /**
     * Accessor alias nama_vendor untuk nama_vendor atau nama_perusahaan.
     */
    public function getNamaVendorAttribute(): ?string
    {
        return $this->attributes['nama_vendor'] ?? $this->attributes['nama_perusahaan'] ?? null;
    }

    /**
     * Mutator untuk nama_vendor yang menyelaraskan ke nama_perusahaan.
     */
    public function setNamaVendorAttribute(?string $value): void
    {
        $this->attributes['nama_vendor'] = $value;
        if (empty($this->attributes['nama_perusahaan'])) {
            $this->attributes['nama_perusahaan'] = $value;
        }
    }

    /**
     * Accessor alias nama_perusahaan untuk nama_perusahaan atau nama_vendor.
     */
    public function getNamaPerusahaanAttribute(): ?string
    {
        return $this->attributes['nama_perusahaan'] ?? $this->attributes['nama_vendor'] ?? null;
    }

    /**
     * Mutator untuk nama_perusahaan yang menyelaraskan ke nama_vendor.
     */
    public function setNamaPerusahaanAttribute(?string $value): void
    {
        $this->attributes['nama_perusahaan'] = $value;
        if (empty($this->attributes['nama_vendor'])) {
            $this->attributes['nama_vendor'] = $value;
        }
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
        return $this->hasMany(PaketPengadaan::class, 'id_vendor', 'id');
    }
}
