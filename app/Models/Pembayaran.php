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

    public const STATUS_PENGAJUAN = 'pengajuan';

    public const STATUS_PERINTAH_BAYAR = 'perintah_bayar';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_DITOLAK = 'ditolak';

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
        'file_dokumen_penunjang',
        'nomor_kwitansi',
        'file_kwitansi',
        'nomor_invoice',
        'file_invoice',
        'tanggal_perintah_bayar',
        'catatan_reviu_akhir',
    ];

    /**
     * Atribut tambahan terstruktur untuk Informasi Dokumen Pembayaran pada serialisasi JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'informasi_dokumen_pembayaran',
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
     * Hook booted model Pembayaran untuk memastikan kolom tanggal_pengajuan dan jatuh_tempo tidak null.
     */
    protected static function booted(): void
    {
        static::creating(function (Pembayaran $model) {
            if (empty($model->jatuh_tempo)) {
                $model->jatuh_tempo = now()->addDays(30)->toDateString();
            }
            if (empty($model->tanggal_pengajuan)) {
                $model->tanggal_pengajuan = now()->toDateString();
            }
        });
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

    /**
     * Menyusun ringkasan terstruktur Informasi Dokumen Pembayaran sesuai Notulensi PPBJ:
     * Menampilkan dokumen perikatan tunggal (SPK / Surat Pesanan / Surat Kontrak untuk > 50jt,
     * atau keterangan tanpa kontrak untuk <= 50jt Belanja Langsung) ditambah Kwitansi dan Invoice.
     *
     * @return array{dokumen_perikatan: array<string, mixed>, kwitansi: array<string, mixed>, invoice: array<string, mixed>}
     */
    public function getInformasiDokumenPembayaranAttribute(): array
    {
        $paket = $this->relationLoaded('paket')
            ? $this->paket
            : $this->paket()->with('kontrakSpk')->first();
        $kontrak = ($paket && $paket->relationLoaded('kontrakSpk'))
            ? $paket->kontrakSpk
            : $paket?->kontrakSpk;

        $isBelanjaLangsung = $paket?->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG;

        return [
            'dokumen_perikatan' => $isBelanjaLangsung
                ? [
                    'jenis' => 'tanpa_kontrak_belanja_langsung',
                    'nomor' => null,
                    'file' => null,
                    'nilai_kontrak' => null,
                    'keterangan' => 'Belanja Langsung (<= Rp 50 Juta) tidak menggunakan Kontrak/SPK/Surat Pesanan.',
                ]
                : [
                    'jenis' => $kontrak?->tipe_dokumen,
                    'nomor' => $kontrak?->nomor_spk,
                    'file' => $kontrak?->file_spk,
                    'nilai_kontrak' => $kontrak?->nilai_kontrak,
                ],
            'kwitansi' => [
                'nomor_kwitansi' => $this->nomor_kwitansi ?? $paket?->nomor_kwitansi_belanja,
                'file_kwitansi' => $this->file_kwitansi ?? $paket?->file_kwitansi_belanja,
            ],
            'invoice' => [
                'nomor_invoice' => $this->nomor_invoice,
                'file_invoice' => $this->file_invoice,
            ],
        ];
    }
}
