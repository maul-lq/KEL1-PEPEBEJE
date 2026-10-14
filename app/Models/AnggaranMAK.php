<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnggaranMAK extends Model
{
    use HasFactory;

    protected $table = 'anggaran_mak';

    protected $primaryKey = 'kode_mak';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_mak',
        'uraian_mak',
        'pagu_anggaran',
        'sisa_pagu_mak',
        'sumber_dana',
        'tahun_anggaran',
        'catatan_mak',
        'is_locked',
        'tanggal_kunci',
        'id_user',
        'tanggal_penetapan',
    ];

    protected function casts(): array
    {
        return [
            'pagu_anggaran' => 'decimal:2',
            'sisa_pagu_mak' => 'decimal:2',
            'tahun_anggaran' => 'integer',
            'is_locked' => 'boolean',
            'tanggal_kunci' => 'datetime',
            'tanggal_penetapan' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AnggaranMAK $mak) {
            if ($mak->getOriginal('is_locked') && $mak->is_locked) {
                // Cegah pengubahan data inti anggaran jika sudah terkunci (NFR Integritas Data MAK)
                if ($mak->isDirty(['uraian_mak', 'pagu_anggaran', 'sumber_dana', 'tahun_anggaran'])) {
                    throw new Exception("Data MAK {$mak->kode_mak} telah dikunci permanen karena SPK telah terbit (NFR Integritas Data MAK)!");
                }
            }
        });

        static::deleting(function (AnggaranMAK $mak) {
            if ($mak->is_locked) {
                throw new Exception("Data MAK {$mak->kode_mak} tidak dapat dihapus karena telah dikunci permanen (NFR Integritas Data MAK)!");
            }
        });
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function lock(): bool
    {
        $this->is_locked = true;
        $this->tanggal_kunci = now();

        return $this->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'kode_mak', 'kode_mak');
    }

    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'kode_mak', 'kode_mak');
    }
}
