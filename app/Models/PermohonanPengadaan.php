<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermohonanPengadaan extends Model
{
    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_REVIU_PARALEL = 'reviu_paralel';

    public const STATUS_PENDING_ANGGARAN = 'pending_anggaran';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_DIPROSES_PPBJ = 'diproses_ppbj';

    protected $table = 'permohonan_pengadaan';

    protected $primaryKey = 'nomor_surat';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_surat',
        'judul_pengadaan',
        'asal_unit',
        'tujuan_ringkas',
        'file_pdf_srikandi',
        'status_permohonan',
        'no_draft_srikandi',
        'id_user',
        'nomor_surat_induk',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_induk', 'nomor_surat');
    }

    public function anakSplit(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_induk', 'nomor_surat');
    }

    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'nomor_surat', 'nomor_surat');
    }

    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'nomor_surat', 'nomor_surat');
    }
}
