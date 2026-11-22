<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model Pembayaran ini untuk mengelola tagihan termin pengadaan, sinkronisasi jatuh tempo otomatis, dan penyusunan ringkasan Informasi Dokumen Pembayaran.
 */

namespace App\Models;

use App\Services\PengadaanRoutingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Tagihan & Pengajuan Pembayaran (Pembayaran)
 *
 * memodelkan siklus pembayaran mulai dari pengajuan tagihan PPBJ, persetujuan memo paralel Wadir 2 & PPK,
 * penerbitan SPP/Perintah Bayar, hingga penyajian atribut terstruktur `informasi_dokumen_pembayaran` untuk Keuangan.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `pembayaran` dengan primary key string `nomor_pembayaran`.
 *
 * Contoh pemanggilan:
 * `$tagihan = Pembayaran::with(['paket.kontrakSpk', 'memoBayar', 'transaksiPencairan'])->findOrFail($nomor);`
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
        'file_spp',
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
     * mengonversi termin ke integer, nominal ke desimal 2 digit, dan tanggal tagihan ke tipe date/datetime.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $pembayaran->nominal_pengajuan atau $pembayaran->jatuh_tempo.`
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
     * mengisi fallback `jatuh_tempo` (+30 hari) dan `tanggal_pengajuan` saat pembuatan tagihan serta menyinkronkan jadwal reminder jatuh tempo setiap kali tagihan disimpan.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dijalankan otomatis oleh Eloquent pada event creating dan saved.`
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

        static::saved(function (Pembayaran $pembayaran): void {
            PengadaanRoutingService::syncPembayaranSchedules($pembayaran);
        });
    }

    /**
     * menghubungkan tagihan pembayaran ke paket pengadaan induknya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paket = $pembayaran->paket;`
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan tagihan pembayaran ke pengguna (PPBJ) yang mengajukan pembayaran.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$pengaju = $pembayaran->pengaju;`
     */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_pengaju', 'id_user');
    }

    /**
     * menghubungkan tagihan pembayaran ke catatan persetujuan memo berjenjang (Wadir 2 Level 1 & PPK Level 2).
     *
     * @return HasMany mengembalikan relasi HasMany ke model MemoBayar.
     *
     * Contoh pemanggilan:
     * `$daftarMemo = $pembayaran->memoBayar;`
     */
    public function memoBayar(): HasMany
    {
        return $this->hasMany(MemoBayar::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * menghubungkan tagihan ke bukti transfer SP2D yang diterbitkan oleh Bagian Keuangan.
     *
     * @return HasOne mengembalikan relasi HasOne ke model TransaksiPencairan.
     *
     * Contoh pemanggilan:
     * `$pencairan = $pembayaran->transaksiPencairan;`
     */
    public function transaksiPencairan(): HasOne
    {
        return $this->hasOne(TransaksiPencairan::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * menyusun paket metadata dokumen pembayaran agar UI dan API menampilkan referensi perikatan (atau keterangan Belanja Langsung tanpa kontrak) beserta Kwitansi dan Invoice.
     *
     * @return array{dokumen_perikatan: array<string, mixed>, kwitansi: array<string, mixed>, invoice: array<string, mixed>, file_dokumen_penunjang: ?string, file_spp: ?string} mengembalikan struktur ringkas seluruh berkas pembayaran.
     *
     * Contoh pemanggilan:
     * `$infoDok = $pembayaran->informasi_dokumen_pembayaran;`
     */
    public function getInformasiDokumenPembayaranAttribute(): array
    {
        // memanfaatkan relasi yang sudah di-eager-load agar pemanggilan pada daftar tabel tidak memicu N+1 query.
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
            // menyertakan fallback ke kwitansi belanja paket agar Belanja Langsung <=50jt tidak perlu mengunggah ulang kwitansi yang sama.
            'kwitansi' => [
                'nomor_kwitansi' => $this->nomor_kwitansi ?? $paket?->nomor_kwitansi_belanja,
                'file_kwitansi' => $this->file_kwitansi ?? $paket?->file_kwitansi_belanja,
            ],
            'invoice' => [
                'nomor_invoice' => $this->nomor_invoice,
                'file_invoice' => $this->file_invoice,
            ],
            'file_dokumen_penunjang' => $this->file_dokumen_penunjang,
            'file_spp' => $this->file_spp,
        ];
    }
}
