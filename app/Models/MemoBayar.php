<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoBayar extends Model
{
    use HasFactory;

    protected $table = 'memo_bayar';

    protected $fillable = [
        'id_user',
        'nomor_pembayaran',
        'role_approver',
        'urutan_level',
        'status_memo',
        'catatan_memo',
        'tanggal_acc',
    ];

    protected function casts(): array
    {
        return [
            'urutan_level' => 'integer',
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
