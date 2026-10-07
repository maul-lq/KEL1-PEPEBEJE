<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnggaranMAK extends Model
{
    protected $table = 'anggaran_mak';

    protected $primaryKey = 'kode_mak';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_mak',
        'uraian_mak',
        'pagu_anggaran',
        'sumber_dana',
        'tahun_anggaran',
        'is_locked',
        'tanggal_kunci',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'pagu_anggaran' => 'decimal:2',
            'tahun_anggaran' => 'integer',
            'is_locked' => 'boolean',
            'tanggal_kunci' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'kode_mak', 'kode_mak');
    }

    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'kode_mak', 'kode_mak');
    }
}
