<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Berkas Legalitas Rekanan (Vendor File Legalitas)
 *
 * Merepresentasikan arsip berkas digital dokumen legalitas rekanan (seperti NIB, NPWP, SIUP,
 * Akta Notaris, KTP Direktur, atau sertifikat keahlian).
 * Menyimpan tautan path penyimpanan berkas serta waktu pengunggahan.
 */
class VendorFileLegalitas extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'vendor_file_legalitas';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'npwp',
        'nama_dokumen',
        'file_legalitas',
        'uploaded_at',
    ];

    /**
     * Konfigurasi type casting untuk timestamp pengunggahan berkas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Vendor pemilik dokumen legalitas ini.
     *
     * @return BelongsTo Relasi ke model Vendor
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'npwp', 'npwp');
    }
}
