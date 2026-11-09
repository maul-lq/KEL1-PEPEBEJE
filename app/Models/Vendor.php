<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Rekanan / Penyedia (Vendor)
 *
 * Merepresentasikan perusahaan atau penyedia barang/jasa yang bermitra dengan institusi.
 * Diselaraskan ke struktur minimalis dan operasional:
 * - Identitas: id (primary key auto-increment), nama_vendor
 * - Legalitas: file_dokumen_legalitas (unggah PDF/gambar berkas izin/NIB/SIUP)
 * - Rekening Bayar (BPMN N69/N71): npwp, nama_bank, nomor_rekening, atas_nama
 * - Status: is_active (status keaktifan bermitra)
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
        'npwp',
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
     * Accessor alias id_vendor untuk primary key id.
     */
    public function getIdVendorAttribute(): int|string|null
    {
        return $this->id;
    }

    /**
     * Accessor alias nama_perusahaan untuk kompatibilitas kode legacy yang merujuk nama_vendor.
     */
    public function getNamaPerusahaanAttribute(): ?string
    {
        return $this->attributes['nama_vendor'] ?? null;
    }

    /**
     * Mutator alias nama_perusahaan yang otomatis diarahkan ke nama_vendor.
     */
    public function setNamaPerusahaanAttribute(?string $value): void
    {
        $this->attributes['nama_vendor'] = $value;
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
