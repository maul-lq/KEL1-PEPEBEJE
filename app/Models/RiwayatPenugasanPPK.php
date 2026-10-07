<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPenugasanPPK extends Model
{
    protected $table = 'riwayat_penugasan_ppk';

    protected $primaryKey = 'id_riwayat';

    public $timestamps = false;

    protected $fillable = [
        'nomor_paket',
        'ppk_lama_user_id',
        'ppk_baru_user_id',
        'diubah_oleh_user_id',
        'alasan_perubahan',
        'tanggal_penugasan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penugasan' => 'datetime',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function ppkLama(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_lama_user_id', 'id_user');
    }

    public function ppkBaru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_baru_user_id', 'id_user');
    }

    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh_user_id', 'id_user');
    }
}
