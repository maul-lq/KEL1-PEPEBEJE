<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviuPengadaan extends Model
{
    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_PERLU_PERBAIKAN = 'perlu_perbaikan';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'reviu_pengadaan';

    protected $primaryKey = 'id_reviu';

    public $timestamps = false;

    protected $fillable = [
        'nomor_paket',
        'id_user',
        'status_reviu',
        'catatan_perbaikan',
        'tanggal_reviu',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_reviu' => 'datetime',
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
