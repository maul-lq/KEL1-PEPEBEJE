<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermohonanPengadaan extends Model
{
    use HasFactory;

    protected $table = 'permohonan_pengadaan';

    protected $primaryKey = 'nomor_surat';

    public $incrementing = false;

    protected $keyType = 'string';

    const STATUS_DIAJUKAN = 'diajukan';

    const STATUS_REVIU_PARALEL = 'reviu_paralel';

    const STATUS_PENDING_ANGGARAN = 'pending_anggaran';

    const STATUS_DISETUJUI = 'disetujui';

    const STATUS_DITOLAK = 'ditolak';

    const STATUS_DIPROSES_PPBJ = 'diproses_ppbj';

    const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'nomor_surat',
        'judul_pengadaan',
        'tujuan_pengadaan_ringkas',
        'file_permohonan',
        'status_permohonan',
        'status_anggaran',
        'revisi_ke',
        'estimasi_total_pagu',
        'tanggal_pengajuan',
        'kode_unit',
        'id_user',
        'nomor_surat_induk_split',
        'tanggal_pemecahan',
        'alasan_pemecahan',
        'nomor_surat_revisi',
        'tanggal_revisi',
        'alasan_revisi',
        'kode_mak',
    ];

    protected function casts(): array
    {
        return [
            'revisi_ke' => 'integer',
            'estimasi_total_pagu' => 'decimal:2',
            'tanggal_pengajuan' => 'datetime',
            'tanggal_pemecahan' => 'datetime',
            'tanggal_revisi' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'kode_unit', 'kode_unit');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function indukSplit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_induk_split', 'nomor_surat');
    }

    public function anakSplit(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_induk_split', 'nomor_surat');
    }

    public function suratAsalRevisi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_revisi', 'nomor_surat');
    }

    public function suratHasilRevisi(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_revisi', 'nomor_surat');
    }

    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }

    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'nomor_surat', 'nomor_surat');
    }

    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'nomor_surat', 'nomor_surat');
    }

    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    public function isRevisi(): bool
    {
        return ! empty($this->nomor_surat_revisi);
    }

    public function isSplit(): bool
    {
        return ! empty($this->nomor_surat_induk_split);
    }

    public function isApproved(): bool
    {
        return $this->status_permohonan === self::STATUS_DISETUJUI;
    }

    public function isRejected(): bool
    {
        return $this->status_permohonan === self::STATUS_DITOLAK;
    }

    public function isPendingAnggaran(): bool
    {
        return $this->status_permohonan === self::STATUS_PENDING_ANGGARAN;
    }

    /**
     * Scope query sesuai hak akses user (User pemohon hanya melihat usulan miliknya).
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->role === 'user') {
            return $query->where('id_user', $user->id_user);
        }

        return $query;
    }
}
