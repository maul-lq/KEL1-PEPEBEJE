<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlokasiMAK extends Model
{
    protected $table = 'alokasi_mak';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nomor_surat',
        'kode_mak',
        'nominal_alokasi',
        'tanggal_alokasi',
    ];

    protected function casts(): array
    {
        return [
            'nominal_alokasi' => 'decimal:2',
            'tanggal_alokasi' => 'datetime',
        ];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }
}
