<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengadaanItem extends Model
{
    protected $fillable = [
        'pengadaan_id',
        'nama_barang',
        'spesifikasi',
        'volume',
        'satuan',
        'harga_satuan',
        'total_harga',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'integer',
            'harga_satuan' => 'decimal:2',
            'total_harga' => 'decimal:2',
        ];
    }

    public function getFormattedHargaSatuanAttribute(): string
    {
        return 'Rp '.number_format($this->harga_satuan, 0, ',', '.');
    }

    public function getFormattedTotalHargaAttribute(): string
    {
        return 'Rp '.number_format($this->total_harga, 0, ',', '.');
    }

    public function pengadaan(): BelongsTo
    {
        return $this->belongsTo(Pengadaan::class);
    }
}
