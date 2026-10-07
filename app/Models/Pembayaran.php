<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembayaran extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIU_MEMO = 'reviu_memo';

    public const STATUS_PERINTAH_BAYAR = 'perintah_bayar';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'pembayaran';

    protected $primaryKey = 'nomor_pembayaran';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_pembayaran',
        'nomor_paket',
        'tahap_termin',
        'nominal_pengajuan',
        'status_pembayaran',
        'file_dokumen_penunjang',
        'file_tagihan_vendor',
    ];

    protected function casts(): array
    {
        return [
            'tahap_termin' => 'integer',
            'nominal_pengajuan' => 'decimal:2',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    public function transaksiPencairan(): HasMany
    {
        return $this->hasMany(TransaksiPencairan::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }
}
