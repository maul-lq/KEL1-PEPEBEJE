<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiPencairan extends Model
{
    use HasFactory;

    protected $table = 'transaksi_pencairan';

    protected $primaryKey = 'nomor_transaksi_bank';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_transaksi_bank',
        'nomor_pembayaran',
        'id_user',
        'tanggal_transfer',
        'nominal_transfer',
        'file_bukti_transfer',
        'catatan_keuangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_transfer' => 'datetime',
            'nominal_transfer' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (TransaksiPencairan $cair) {
            // Update status pembayaran menjadi lunas dan paket pengadaan menjadi selesai (FR 18.2)
            $pembayaran = $cair->pembayaran;
            if ($pembayaran) {
                $pembayaran->status_pembayaran = 'lunas';
                $pembayaran->save();

                $paket = $pembayaran->paket;
                if ($paket) {
                    $paket->status_paket = 'selesai';
                    $paket->save();

                    if ($paket->permohonan) {
                        $paket->permohonan->status_permohonan = 'selesai';
                        $paket->permohonan->save();
                    }
                }
            }
        });
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
