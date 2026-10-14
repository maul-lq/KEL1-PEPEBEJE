<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Alokasi MAK (Alokasi Mata Anggaran Kegiatan)
 *
 * Merepresentasikan pencatatan alokasi dana dari Mata Anggaran Kegiatan (MAK)
 * ke suatu surat permohonan pengadaan tertentu. Berperan melacak distribusi pagu
 * anggaran secara granular untuk setiap usulan pengadaan di SIM-PBJ.
 */
class AlokasiMAK extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang dihubungkan dengan model ini.
     *
     * @var string
     */
    protected $table = 'alokasi_mak';

    /**
     * Daftar kolom tabel yang diizinkan untuk diisi secara massal (mass assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_surat',
        'kode_mak',
        'nominal_alokasi',
        'tanggal_alokasi',
    ];

    /**
     * Konfigurasi konversi tipe data (type casting) untuk atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Memastikan nilai nominal alokasi diformat sebagai bilangan desimal 2 digit presisi
            'nominal_alokasi' => 'decimal:2',
            // Konversi string timestamp menjadi objek Carbon/Datetime
            'tanggal_alokasi' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Permohonan Pengadaan.
     * Mengindikasikan surat permohonan mana yang menerima alokasi anggaran ini.
     *
     * @return BelongsTo Relasi balik ke model PermohonanPengadaan
     */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * Relasi many-to-one ke Anggaran MAK.
     * Mengindikasikan sumber Mata Anggaran Kegiatan (MAK) asal dana yang dialokasikan.
     *
     * @return BelongsTo Relasi balik ke model AnggaranMAK
     */
    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }
}
