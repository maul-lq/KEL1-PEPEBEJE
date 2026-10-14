<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $primaryKey = 'nomor_pembayaran';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_pembayaran',
        'nomor_paket',
        'id_user_pengaju',
        'tahap_termin',
        'nominal_pengajuan',
        'tanggal_pengajuan',
        'jatuh_tempo',
        'status_pembayaran',
        'file_tagihan_vendor',
        'file_dokumen_penunjang',
        'nomor_memo',
        'file_memo',
        'id_user_reviu',
        'tanggal_perintah_bayar',
        'status_reviu_akhir',
        'catatan_reviu_akhir',
    ];

    protected function casts(): array
    {
        return [
            'tahap_termin' => 'integer',
            'nominal_pengajuan' => 'decimal:2',
            'tanggal_pengajuan' => 'date',
            'jatuh_tempo' => 'date',
            'tanggal_perintah_bayar' => 'datetime',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_pengaju', 'id_user');
    }

    public function reviuUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_reviu', 'id_user');
    }

    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    public function transaksiPencairan(): HasOne
    {
        return $this->hasOne(TransaksiPencairan::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }
}
