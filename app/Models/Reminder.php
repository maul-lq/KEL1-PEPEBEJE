<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    public const ROLE_PPBJ = 'ppbj';

    public const ROLE_PP = 'pp';

    public const ROLE_PPK = 'ppk';

    public const ROLE_KEUANGAN = 'keuangan';

    public const TIPE_PENGIRIMAN = 'pengiriman';

    public const TIPE_H_MINUS_2_TERMIN = 'h_minus_2_termin';

    public const TIPE_BULANAN_TAHUNAN = 'bulanan_tahunan';

    protected $table = 'reminder';

    protected $primaryKey = 'id_reminder';

    protected $fillable = [
        'nomor_paket',
        'target_role',
        'tipe_reminder',
        'judul_alert',
        'pesan_alert',
        'tanggal_pemicu',
        'is_read',
        'is_dismissed',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pemicu' => 'datetime',
            'is_read' => 'boolean',
            'is_dismissed' => 'boolean',
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
}
