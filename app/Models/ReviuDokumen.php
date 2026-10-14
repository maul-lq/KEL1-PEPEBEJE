<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviuDokumen extends Model
{
    use HasFactory;

    protected $table = 'reviu_dokumen';

    protected $primaryKey = 'id_reviu';

    protected $fillable = [
        'nomor_paket',
        'id_user',
        'putaran_ke',
        'role_reviewer',
        'status_reviu',
        'catatan_perbaikan',
        'tanggal_reviu',
    ];

    protected function casts(): array
    {
        return [
            'putaran_ke' => 'integer',
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
