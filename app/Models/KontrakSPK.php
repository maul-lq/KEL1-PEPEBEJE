<?php

namespace App\Models;

use App\Services\PengadaanRoutingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Model Kontrak SPK (Dokumen Perikatan Terpilih: Surat Pesanan / SPK / Surat Kontrak)
 *
 * Merepresentasikan dokumen tunggal legalitas perikatan pengadaan barang/jasa (> Rp 50.000.000)
 * antara institusi dan rekanan (vendor). Untuk setiap paket > Rp 50 Juta hanya diunggah salah satu:
 * - surat_pesanan atau spk (umumnya E-Katalog / Inaproc)
 * - surat_kontrak atau spk (umumnya Non-Tender / Tender pihak ketiga)
 * Catatan: Paket <= Rp 50 Juta (Belanja Langsung) menggunakan Kwitansi tanpa Kontrak/SPK.
 * Penguncian & pembukaan kunci MAK murni menjadi wewenang Perencanaan (tidak dikunci otomatis saat SPK terbit).
 */
class KontrakSPK extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'kontrak_spk';

    public const DOKUMEN_SPK = 'spk';

    public const DOKUMEN_SURAT_PESANAN = 'surat_pesanan';

    public const DOKUMEN_SURAT_KONTRAK = 'surat_kontrak';

    public const TIPE_SEKALI_SELESAI = 'sekali_selesai';

    public const TIPE_LUMPSUM = 'lumpsum';

    public const TIPE_HARGA_SATUAN = 'harga_satuan';

    public const TIPE_TERMIN_BULANAN = 'termin_bulanan';

    public const TIPE_KONTRAK_TAHUNAN = 'kontrak_tahunan';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_TERBIT = 'terbit';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_SELESAI = 'selesai';

    /**
     * Primary key tabel berupa nomor string unik SPK.
     *
     * @var string
     */
    protected $primaryKey = 'nomor_spk';

    /**
     * Kunci utama tidak menggunakan auto-increment.
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
        'nomor_spk',
        'nomor_paket',
        'id_user',
        'tipe_dokumen',
        'sifat_kontrak',
        'status_spk',
        'tanggal_spk',
        'tanggal_mulai',
        'tanggal_selesai',
        'jadwal_pengiriman',
        'nilai_kontrak',
        'file_spk',
    ];

    /**
     * Konversi tipe data otomatis (casting) untuk kolom tanggal dan nominal.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_spk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jadwal_pengiriman' => 'date',
            'nilai_kontrak' => 'decimal:2',
        ];
    }

    /**
     * Pendaftaran hook event model Eloquent.
     * Menjalankan sinkronisasi jadwal reminder otomatis saat rekaman Kontrak SPK disimpan.
     */
    protected static function booted(): void
    {
        static::saved(function (KontrakSPK $spk): void {
            if (in_array($spk->status_spk, ['terbit', 'aktif'], true)) {
                PengadaanRoutingService::syncKontrakSchedules($spk);
            } elseif (in_array($spk->status_spk, ['selesai', 'batal'], true) && ! empty($spk->nomor_paket)) {
                PengadaanRoutingService::deactivateForPaket($spk->nomor_paket);
            }
        });
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang memayungi kontrak ini.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke User (PPK atau PP pembuat/penerbit dokumen SPK).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-one ke rekaman Penerimaan Barang / BAST (berdasarkan kesamaan nomor_paket).
     *
     * @return HasOne Relasi ke model PenerimaanBarang
     */
    public function penerimaan(): HasOne
    {
        return $this->hasOne(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi has-one-through ke model Vendor melalui PaketPengadaan.
     *
     * @return HasOneThrough Relasi ke model Vendor
     */
    public function vendor(): HasOneThrough
    {
        return $this->hasOneThrough(
            Vendor::class,
            PaketPengadaan::class,
            'nomor_paket',
            'id',
            'nomor_paket',
            'id_vendor'
        );
    }

    /**
     * Scope query untuk membatasi akses Kontrak SPK sesuai hak akses peran pengguna (NFR RBAC PPK).
     *
     * @param  Builder  $query  Query builder Eloquent
     * @param  User  $user  Objek pengguna aktif
     * @return Builder Query terisolasi
     */
    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('paket', function ($q) use ($user) {
            $q->forUser($user);
        });
    }

    /**
     * Accessor alias nomor_kontrak_spk untuk nomor_spk.
     */
    public function getNomorKontrakSpkAttribute(): ?string
    {
        return $this->nomor_spk;
    }

    /**
     * Accessor alias kode_paket untuk nomor_paket.
     */
    public function getKodePaketAttribute(): ?string
    {
        return $this->nomor_paket;
    }

    /**
     * Accessor alias status_kontrak untuk status_spk.
     */
    public function getStatusKontrakAttribute(): ?string
    {
        return $this->status_spk;
    }

    /**
     * Mengambil model Vendor rekanan penyedia melalui relasi Paket Pengadaan.
     *
     * @return Vendor|null Objek vendor rekanan
     */
    public function getVendorAttribute(): ?Vendor
    {
        return $this->paket?->vendor;
    }
}
