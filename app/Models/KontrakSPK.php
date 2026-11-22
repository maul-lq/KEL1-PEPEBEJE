<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model KontrakSPK ini untuk merepresentasikan satu dokumen perikatan terpilih (> Rp50 juta) beserta sinkronisasi otomatis jadwal pengingat pengiriman.
 */

namespace App\Models;

use App\Services\PengadaanRoutingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Model Dokumen Perikatan Kontrak/SPK (KontrakSPK)
 *
 * memodelkan satu dokumen legalitas perikatan per paket > Rp50 juta (`spk`, `surat_pesanan`, atau `surat_kontrak`)
 * dan mengaitkan event `saved` ke PengadaanRoutingService tanpa mengunci MAK secara otomatis karena penguncian MAK adalah hak eksklusif Perencanaan.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent dengan primary key string `nomor_spk` pada tabel `kontrak_spk`.
 *
 * Contoh pemanggilan:
 * `$kontrak = KontrakSPK::forUser($user)->with('paket.vendor')->get();`
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
     * mengonversi seluruh tanggal kontrak ke tipe date dan nilai kontrak ke desimal 2 digit untuk perhitungan pembayaran.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $kontrak->tanggal_spk atau $kontrak->nilai_kontrak.`
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
     * menyinkronkan jadwal pengingat pengiriman secara otomatis setiap kali Kontrak/SPK diterbitkan, atau menonaktifkannya saat kontrak selesai/batal.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Terpicu otomatis oleh Eloquent pada event saved setelah KontrakSPK::create() atau $spk->save().`
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
     * menghubungkan kontrak ke paket pengadaan induknya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paket = $kontrak->paket;`
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan kontrak ke akun pengguna yang menerbitkan/mengunggah dokumen perikatan.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$penerbit = $kontrak->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan kontrak ke catatan penerimaan barang / BAST melalui kunci bersama `nomor_paket`.
     *
     * @return HasOne mengembalikan relasi HasOne ke model PenerimaanBarang.
     *
     * Contoh pemanggilan:
     * `$bast = $kontrak->penerimaan;`
     */
    public function penerimaan(): HasOne
    {
        return $this->hasOne(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menyediakan relasi HasOneThrough menuju vendor melalui paket pengadaan agar query eager loading `with('vendor')` langsung tersedia.
     *
     * @return HasOneThrough mengembalikan relasi HasOneThrough ke model Vendor.
     *
     * Contoh pemanggilan:
     * `$kontrakList = KontrakSPK::with('vendor')->get();`
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
     * membatasi daftar Kontrak/SPK mengikuti aturan isolasi paket pada `PaketPengadaan::scopeForUser`.
     *
     * @param  Builder  $query  menerima query builder Eloquent KontrakSPK.
     * @param  User  $user  meneruskan objek pengguna aktif untuk penyaringan relasi paket.
     * @return Builder mengembalikan query yang sudah terisolasi sesuai peran/RBAC pengguna.
     *
     * Contoh pemanggilan:
     * `$kontrakSaya = KontrakSPK::forUser($user)->latest()->get();`
     */
    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('paket', function ($q) use ($user) {
            $q->forUser($user);
        });
    }

    /**
     * menyediakan accessor alias `nomor_kontrak_spk` agar penamaan lama tetap kompatibel dengan `nomor_spk`.
     *
     * @return string|null mengembalikan nomor SPK/kontrak atau null.
     *
     * Contoh pemanggilan:
     * `$no = $kontrak->nomor_kontrak_spk;`
     */
    public function getNomorKontrakSpkAttribute(): ?string
    {
        return $this->nomor_spk;
    }

    /**
     * menyediakan accessor alias `kode_paket` yang mengacu ke `nomor_paket`.
     *
     * @return string|null mengembalikan kode/nomor paket pengadaan atau null.
     *
     * Contoh pemanggilan:
     * `$kode = $kontrak->kode_paket;`
     */
    public function getKodePaketAttribute(): ?string
    {
        return $this->nomor_paket;
    }

    /**
     * menyediakan accessor alias `status_kontrak` yang mengacu ke `status_spk`.
     *
     * @return string|null mengembalikan status dokumen kontrak atau null.
     *
     * Contoh pemanggilan:
     * `$status = $kontrak->status_kontrak;`
     */
    public function getStatusKontrakAttribute(): ?string
    {
        return $this->status_spk;
    }

    /**
     * mengambil objek Vendor rekanan langsung dari relasi paket agar pemanggilan `$kontrak->vendor` tetap bekerja sebagai properti.
     *
     * @return Vendor|null mengembalikan instance Vendor atau null bila rekanan belum ditetapkan.
     *
     * Contoh pemanggilan:
     * `$namaVendor = $kontrak->vendor?->nama_vendor;`
     */
    public function getVendorAttribute(): ?Vendor
    {
        return $this->paket?->vendor;
    }
}
