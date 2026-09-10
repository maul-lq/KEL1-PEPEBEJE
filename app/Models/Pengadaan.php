<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengadaan extends Model
{
    // Status constants
    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DITOLAK_WADIR2 = 'ditolak_wadir2';

    public const STATUS_DISETUJUI_WADIR2 = 'disetujui_wadir2';

    public const STATUS_MAK_DITETAPKAN = 'mak_ditetapkan';

    public const STATUS_TEREGISTRASI_PPBJ = 'teregistrasi_ppbj';

    public const STATUS_REVISI_PPK = 'revisi_ppk';

    public const STATUS_DISETUJUI_PPK = 'disetujui_ppk';

    public const STATUS_SPK_DITERBITKAN = 'spk_diterbitkan';

    public const STATUS_BARANG_DITERIMA = 'barang_diterima';

    public const STATUS_BARANG_PENDING = 'barang_pending';

    public const STATUS_PENGAJUAN_PEMBAYARAN = 'pengajuan_pembayaran';

    public const STATUS_MEMO_PEMBAYARAN_PPK = 'memo_pembayaran_ppk';

    public const STATUS_MEMO_PEMBAYARAN_WADIR2 = 'memo_pembayaran_wadir2';

    public const STATUS_SP_PEMBAYARAN_TERBIT = 'sp_pembayaran_terbit';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    // Jalur pengadaan constants
    public const JALUR_LANGSUNG = 'pengadaan_langsung'; // < 50jt

    public const JALUR_PP = 'pejabat_pengadaan'; // 50jt - 200jt

    public const JALUR_PPK = 'pejabat_pembuat_komitmen'; // > 200jt

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_DIAJUKAN => 'Diajukan (Menunggu Wadir 2)',
        self::STATUS_DITOLAK_WADIR2 => 'Ditolak Wadir 2',
        self::STATUS_DISETUJUI_WADIR2 => 'Disetujui Wadir 2 (Menunggu MAK)',
        self::STATUS_MAK_DITETAPKAN => 'MAK Ditetapkan (Menunggu Registrasi PBJ)',
        self::STATUS_TEREGISTRASI_PPBJ => 'Teregistrasi PBJ (Menunggu Reviu PPK/PP)',
        self::STATUS_REVISI_PPK => 'Perbaikan dari PPK/PP',
        self::STATUS_DISETUJUI_PPK => 'Disetujui PPK/PP (Siap SPK)',
        self::STATUS_SPK_DITERBITKAN => 'SPK Diterbitkan (Proses Vendor)',
        self::STATUS_BARANG_PENDING => 'Penerimaan Pending (Perlengkapan)',
        self::STATUS_BARANG_DITERIMA => 'Barang Diterima (BAST & Foto Lengkap)',
        self::STATUS_PENGAJUAN_PEMBAYARAN => 'Pengajuan Pembayaran (Menunggu ACC PPK)',
        self::STATUS_MEMO_PEMBAYARAN_PPK => 'ACC PPK (Menunggu ACC Wadir 2)',
        self::STATUS_MEMO_PEMBAYARAN_WADIR2 => 'Disetujui Wadir 2 (Menunggu SPP PBJ)',
        self::STATUS_SP_PEMBAYARAN_TERBIT => 'SPP Terbit (Menunggu Pencairan Keuangan)',
        self::STATUS_SELESAI => 'Selesai (Lunas)',
        self::STATUS_DIBATALKAN => 'Dibatalkan',
    ];

    public const JALUR_LABELS = [
        self::JALUR_LANGSUNG => 'Pengadaan Langsung (< Rp50 Juta)',
        self::JALUR_PP => 'Pejabat Pengadaan (PP: Rp50 Jt - Rp200 Jt)',
        self::JALUR_PPK => 'Pejabat Pembuat Komitmen (PPK: > Rp200 Jt)',
    ];

    protected $fillable = [
        'nomor_pengadaan',
        'user_id',
        'nama_pengadaan',
        'jenis_pengadaan',
        'jenis_belanja',
        'latar_belakang',
        'estimasi_anggaran',
        'nomor_surat_user',
        'tanggal_surat_user',
        'no_draft_srikandi',
        'file_surat_permohonan',
        'status',
        'jalur_pengadaan',
        'wadir2_user_id',
        'catatan_wadir2',
        'tanggal_persetujuan_wadir2',
        'perencanaan_user_id',
        'nomor_mak',
        'pagu_anggaran',
        'sumber_dana',
        'tanggal_mak',
        'catatan_perencanaan',
        'ppbj_user_id',
        'nomor_memo_ppbj',
        'tanggal_registrasi_ppbj',
        'vendor_id',
        'metode_pengadaan',
        'id_paket_lkpp',
        'nomor_spk',
        'tanggal_spk',
        'nilai_kontrak',
        'tanggal_mulai',
        'tanggal_selesai_jadwal',
        'file_spk',
        'tembusan_perlengkapan',
        'tembusan_keuangan',
        'klasifikasi_alarm',
        'ppk_user_id',
        'status_reviu_ppk',
        'catatan_ppk',
        'tanggal_reviu_ppk',
        'perlengkapan_user_id',
        'status_penerimaan',
        'nama_penerima',
        'unit_penerima',
        'tanggal_penerimaan',
        'foto_dokumentasi_barang',
        'ttd_digital',
        'catatan_penerimaan',
        'file_dokumen_pembayaran',
        'catatan_pengajuan_pembayaran',
        'tanggal_pengajuan_pembayaran',
        'acc_pembayaran_ppk',
        'tanggal_acc_pembayaran_ppk',
        'catatan_pembayaran_ppk',
        'acc_pembayaran_wadir2',
        'tanggal_acc_pembayaran_wadir2',
        'catatan_pembayaran_wadir2',
        'nomor_spp',
        'tanggal_spp',
        'keuangan_user_id',
        'nomor_bukti_bayar',
        'tanggal_bayar',
        'file_bukti_bayar',
        'catatan_keuangan',
    ];

    protected function casts(): array
    {
        return [
            'estimasi_anggaran' => 'decimal:2',
            'pagu_anggaran' => 'decimal:2',
            'nilai_kontrak' => 'decimal:2',
            'tanggal_surat_user' => 'date',
            'tanggal_persetujuan_wadir2' => 'datetime',
            'tanggal_mak' => 'date',
            'tanggal_registrasi_ppbj' => 'datetime',
            'tanggal_spk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai_jadwal' => 'date',
            'tanggal_reviu_ppk' => 'datetime',
            'tanggal_penerimaan' => 'datetime',
            'tanggal_pengajuan_pembayaran' => 'datetime',
            'tanggal_acc_pembayaran_ppk' => 'datetime',
            'tanggal_acc_pembayaran_wadir2' => 'datetime',
            'tanggal_spp' => 'date',
            'tanggal_bayar' => 'date',
            'tembusan_perlengkapan' => 'boolean',
            'tembusan_keuangan' => 'boolean',
            'acc_pembayaran_ppk' => 'boolean',
            'acc_pembayaran_wadir2' => 'boolean',
        ];
    }

    public static function determineJalur(float $nominal): string
    {
        if ($nominal < 50000000) {
            return self::JALUR_LANGSUNG;
        } elseif ($nominal <= 200000000) {
            return self::JALUR_PP;
        } else {
            return self::JALUR_PPK;
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getJalurLabelAttribute(): string
    {
        return self::JALUR_LABELS[$this->jalur_pengadaan] ?? '-';
    }

    public function getFormattedEstimasiAnggaranAttribute(): string
    {
        return 'Rp '.number_format($this->estimasi_anggaran, 0, ',', '.');
    }

    public function getFormattedNilaiKontrakAttribute(): string
    {
        return $this->nilai_kontrak ? 'Rp '.number_format($this->nilai_kontrak, 0, ',', '.') : '-';
    }

    public function getFormattedPaguAnggaranAttribute(): string
    {
        return $this->pagu_anggaran ? 'Rp '.number_format($this->pagu_anggaran, 0, ',', '.') : '-';
    }

    /**
     * Timeline stage index (1-8)
     */
    public function getStepIndexAttribute(): int
    {
        return match ($this->status) {
            self::STATUS_DRAFT, self::STATUS_DIAJUKAN => 1,
            self::STATUS_DISETUJUI_WADIR2 => 2,
            self::STATUS_MAK_DITETAPKAN => 3,
            self::STATUS_TEREGISTRASI_PPBJ, self::STATUS_REVISI_PPK => 4,
            self::STATUS_DISETUJUI_PPK, self::STATUS_SPK_DITERBITKAN => 5,
            self::STATUS_BARANG_DITERIMA, self::STATUS_BARANG_PENDING => 6,
            self::STATUS_PENGAJUAN_PEMBAYARAN,
            self::STATUS_MEMO_PEMBAYARAN_PPK,
            self::STATUS_MEMO_PEMBAYARAN_WADIR2,
            self::STATUS_SP_PEMBAYARAN_TERBIT => 7,
            self::STATUS_SELESAI => 8,
            default => 1,
        };
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wadir2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wadir2_user_id');
    }

    public function perencanaan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'perencanaan_user_id');
    }

    public function ppbj(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppbj_user_id');
    }

    public function ppk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_user_id');
    }

    public function perlengkapan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'perlengkapan_user_id');
    }

    public function keuangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keuangan_user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengadaanItem::class, 'pengadaan_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PengadaanLog::class, 'pengadaan_id')->latest();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'pengadaan_id');
    }
}
