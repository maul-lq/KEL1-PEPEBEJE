<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontrakSPK extends Model
{
    public const TIPE_SEKALI_SELESAI = 'sekali_selesai';

    public const TIPE_KONTRAK_TAHUNAN = 'kontrak_tahunan';

    public const TIPE_TERMIN_BULANAN = 'termin_bulanan';

    protected $table = 'kontrak_spk';

    protected $primaryKey = 'nomor_spk';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_spk',
        'nomor_paket',
        'tipe_kontrak',
        'tanggal_spk',
        'jadwal_pengiriman',
        'nilai_kontrak',
        'file_spk',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_spk' => 'date',
            'jadwal_pengiriman' => 'date',
            'nilai_kontrak' => 'decimal:2',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }
}
