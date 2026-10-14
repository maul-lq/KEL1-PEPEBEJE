<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumentasiPenerimaanBarang extends Model
{
    use HasFactory;

    protected $table = 'penerimaan_barang_foto_dokumentasi';

    protected $primaryKey = 'id_foto';

    protected $fillable = [
        'id_penerimaan',
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
        return $this->belongsTo(PenerimaanBarang::class, 'id_penerimaan', 'id_penerimaan');
    }
}
