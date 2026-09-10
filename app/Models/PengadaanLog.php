<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengadaanLog extends Model
{
    protected $fillable = [
        'pengadaan_id',
        'user_id',
        'action',
        'keterangan',
        'status_sebelumnya',
        'status_baru',
        'ip_address',
    ];

    public function pengadaan(): BelongsTo
    {
        return $this->belongsTo(Pengadaan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
