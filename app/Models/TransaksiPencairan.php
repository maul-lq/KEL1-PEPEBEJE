<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiPencairan extends Model
{
    protected $table = 'transaksi_pencairan';

    protected $primaryKey = 'nomor_transaksi_bank';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_transaksi_bank',
        'nomor_pembayaran',
        'nomor_paket',
        'tanggal_transfer',
        'nominal_transfer',
        'file_bukti_transfer',
        'catatan_keuangan',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_transfer' => 'datetime',
            'nominal_transfer' => 'decimal:2',
        ];
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
