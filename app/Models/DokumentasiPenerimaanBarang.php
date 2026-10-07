<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumentasiPenerimaanBarang extends Model
{
    protected $table = 'penerimaan_barang_foto_dokumentasi';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $fillable = [
        'nomor_bast',
        'nomor_paket',
        'foto_dokumentasi',
        'keterangan_foto',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function penerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'nomor_bast', 'nomor_bast');
    }
}
