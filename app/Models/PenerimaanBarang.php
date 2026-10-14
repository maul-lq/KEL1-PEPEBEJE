<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenerimaanBarang extends Model
{
    use HasFactory;

    protected $table = 'penerimaan_barang';

    protected $primaryKey = 'id_penerimaan';

    protected $fillable = [
        'nomor_bast',
        'nomor_paket',
        'id_user',
        'tanggal_penerimaan',
        'status_fisik',
        'ttd_digital_bast',
        'file_bast_signed',
        'file_penerimaan_pihak3',
        'catatan_pemeriksaan',
        'waktu_selesai_inspeksi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penerimaan' => 'date',
            'waktu_selesai_inspeksi' => 'datetime',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function fotoDokumentasi(): HasMany
    {
        return $this->hasMany(DokumentasiPenerimaanBarang::class, 'id_penerimaan', 'id_penerimaan');
    }
}
