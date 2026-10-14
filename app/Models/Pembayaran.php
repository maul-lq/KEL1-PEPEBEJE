<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Pembayaran (Pengajuan Pembayaran Tagihan / Termin)
 *
 * Merepresentasikan berkas pengajuan pembayaran tagihan pengadaan barang/jasa oleh rekanan
 * atau pejabat pelaksana. Mengelola tahapan termin (tahap_termin), nominal pengajuan dana,
 * batas waktu jatuh tempo, kelengkapan berkas tagihan & penunjang (invoice, kuitansi),
 * riwayat reviu berjenjang memo bayar, hingga realisasi pencairan dana ke rekening vendor.
 */
class Pembayaran extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'pembayaran';

    /**
     * Primary key tabel berupa nomor unik string pembayaran.
     *
     * @var string
     */
    protected $primaryKey = 'nomor_pembayaran';

    /**
     * Kunci utama tidak bersifat auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipe data primary key adalah string.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_pembayaran',
        'nomor_paket',
        'id_user_pengaju',
        'tahap_termin',
        'nominal_pengajuan',
        'tanggal_pengajuan',
        'jatuh_tempo',
        'status_pembayaran',
        'file_tagihan_vendor',
        'file_dokumen_penunjang',
        'nomor_memo',
        'file_memo',
        'id_user_reviu',
        'tanggal_perintah_bayar',
        'status_reviu_akhir',
        'catatan_reviu_akhir',
    ];

    /**
     * Konfigurasi type casting untuk angka, nominal keuangan, dan tanggal.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahap_termin' => 'integer',
            'nominal_pengajuan' => 'decimal:2',
            'tanggal_pengajuan' => 'date',
            'jatuh_tempo' => 'date',
            'tanggal_perintah_bayar' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang dibayar.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke User pengaju tagihan pembayaran.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_pengaju', 'id_user');
    }

    /**
     * Relasi many-to-one ke User pereviu akhir / verifikator keuangan.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function reviuUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_reviu', 'id_user');
    }

    /**
     * Relasi one-to-many ke tahapan persetujuan Memo Bayar berjenjang.
     *
     * @return HasMany Koleksi tahapan memo bayar
     */
    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * Relasi one-to-one ke bukti fisik transaksi pencairan dana / transfer perbankan.
     *
     * @return HasOne Relasi ke model TransaksiPencairan
     */
    public function transaksiPencairan(): HasOne
    {
        return $this->hasOne(TransaksiPencairan::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }
}
