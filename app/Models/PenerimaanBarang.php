<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenerimaanBarang extends Model
{
    public const STATUS_DITERIMA_LENGKAP = 'diterima_lengkap';

    public const STATUS_DITERIMA_SEBAGIAN = 'diterima_sebagian';

    public const STATUS_PENDING_RUSAK = 'pending_rusak';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'penerimaan_barang';

    protected $primaryKey = 'nomor_bast';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_bast',
        'nomor_paket',
        'tanggal_penerimaan',
        'status_fisik',
        'ttd_digital_bast',
        'file_bast_signed',
        'file_penerimaan_pihak3',
        'catatan_pemeriksaan',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penerimaan' => 'date',
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
        return $this->hasMany(DokumentasiPenerimaanBarang::class, 'nomor_bast', 'nomor_bast');
    }
}
