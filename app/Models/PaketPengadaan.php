<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaketPengadaan extends Model
{
    public const JENIS_BARANG = 'barang';

    public const JENIS_JASA_LAINNYA = 'jasa_lainnya';

    public const JENIS_KONSTRUKSI = 'konstruksi';

    public const JENIS_JASA_KONSULTANSI = 'jasa_konsultansi';

    public const METODE_PENGADAAN_LANGSUNG = 'pengadaan_langsung';

    public const METODE_E_PURCHASING = 'e_purchasing';

    public const METODE_TENDER = 'tender';

    public const METODE_NON_TENDER_SPSE = 'non_tender_spse';

    public const METODE_INAPROC = 'inaproc';

    public const STATUS_REGISTRASI_PPBJ = 'registrasi_ppbj';

    public const STATUS_REVIU_DOKUMEN = 'reviu_dokumen';

    public const STATUS_SPK_TERBIT = 'spk_terbit';

    public const STATUS_PENGIRIMAN = 'pengiriman';

    public const STATUS_PENERIMAAN = 'penerimaan';

    public const STATUS_PEMBAYARAN = 'pembayaran';

    public const STATUS_SELESAI = 'selesai';

    public const JALUR_LANGSUNG = 'langsung_sd_50jt';

    public const JALUR_PP = 'pp_50jt_sd_200jt';

    public const JALUR_PPK = 'ppk_diatas_200jt';

    protected $table = 'paket_pengadaan';

    protected $primaryKey = 'nomor_paket';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_paket',
        'nama_paket',
        'jenis_pengadaan',
        'metode_pengadaan',
        'nilai_hps',
        'status_paket',
        'jalur_routing',
        'id_paket_lkpp',
        'nomor_surat',
        'kode_mak',
        'ppbj_user_id',
        'pp_user_id',
        'ppk_user_id',
        'npwp_vendor',
    ];

    protected function casts(): array
    {
        return [
            'nilai_hps' => 'decimal:2',
        ];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }

    public function ppbjUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppbj_user_id', 'id_user');
    }

    public function ppUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pp_user_id', 'id_user');
    }

    public function ppkUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_user_id', 'id_user');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'npwp_vendor', 'npwp');
    }

    public function reviu(): HasMany
    {
        return $this->hasMany(ReviuPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function riwayatPpk(): HasMany
    {
        return $this->hasMany(RiwayatPenugasanPPK::class, 'nomor_paket', 'nomor_paket');
    }

    public function kontrakSpk(): HasMany
    {
        return $this->hasMany(KontrakSPK::class, 'nomor_paket', 'nomor_paket');
    }

    public function penerimaanBarang(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'nomor_paket', 'nomor_paket');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'nomor_paket', 'nomor_paket');
    }
}
