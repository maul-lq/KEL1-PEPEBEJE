<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengadaanLog extends Model
{
    public const AKSI_SPLIT_MAK = 'SPLIT_MAK';

    public const AKSI_ASSIGN_PPK = 'ASSIGN_PPK';

    public const AKSI_LOCK_MAK = 'LOCK_MAK';

    public const AKSI_ACC_WADIR2 = 'ACC_WADIR2';

    protected $table = 'pengadaan_logs';

    protected $primaryKey = 'id_log';

    public $timestamps = false;

    protected $fillable = [
        'nomor_surat',
        'nomor_paket',
        'id_user',
        'aksi',
        'keterangan',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
