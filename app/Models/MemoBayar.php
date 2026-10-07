<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoBayar extends Model
{
    public const ROLE_PPK = 'ppk';

    public const ROLE_WADIR_2 = 'wadir_2';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'memo_bayar';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nomor_pembayaran',
        'nomor_paket',
        'role_approver',
        'status_memo',
        'catatan_memo',
        'tanggal_acc',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_acc' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }
}
