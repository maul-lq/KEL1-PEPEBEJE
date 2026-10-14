<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Paket Pengadaan
 *
 * Merepresentasikan paket kerja pengadaan barang/jasa yang dibentuk dari usulan permohonan.
 * Bertindak sebagai poros operasional yang menghubungkan:
 * - Penentuan jalur pengadaan otomatis (<=50jt Pengadaan Langsung, 50jt-200jt Pejabat Pengadaan / PP, >200jt PPK).
 * - Penugasan pejabat pelaksana (PPBJ, PP, dan PPK).
 * - Penegakan Non-Functional Requirement (NFR) Isolasi Berkas 4 Akun PPK via query scope `scopeForUser`.
 * - Hubungan ke Vendor, Reviu Dokumen, Kontrak SPK, Penerimaan Barang, hingga Pembayaran.
 */
class PaketPengadaan extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'paket_pengadaan';

    /**
     * Primary key tabel berupa nomor unik string paket.
     *
     * @var string
     */
    protected $primaryKey = 'nomor_paket';

    /**
     * Kunci utama tidak bersifat auto-increment.
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
     * Konstanta jalur routing nominal paket pengadaan.
     */
    const ROUTING_LANGSUNG = 'langsung_sd_50jt';

    const ROUTING_PP = 'pp_50jt_sd_200jt';

    const ROUTING_PPK = 'ppk_diatas_200jt';

    /**
     * Kolom-kolom yang dapat diisi secara massal (mass assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_paket',
        'nama_paket',
        'jenis_pengadaan',
        'platform_pengadaan',
        'mekanisme_pengadaan',
        'jalur_routing',
        'nilai_hps',
        'alokasi_pagu_paket',
        'file_dokumen_pengadaan',
        'status_paket',
        'id_paket_lkpp',
        'nomor_surat',
        'tanggal_registrasi_paket',
        'nomor_memo_ppbj',
        'id_user_ppbj',
        'id_user_ppk',
        'tanggal_penugasan_ppk',
        'status_penugasan_ppk',
        'alasan_pengalihan_ppk',
        'id_user_pp',
        'tanggal_penugasan_pp',
        'nilai_hasil_negosiasi',
        'catatan_verifikasi_pp',
        'npwp_vendor',
        'tanggal_penunjukan_vendor',
    ];

    /**
     * Konfigurasi type casting atribut ke tipe data desimal dan tanggal.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nilai_hps' => 'decimal:2',
            'alokasi_pagu_paket' => 'decimal:2',
            'nilai_hasil_negosiasi' => 'decimal:2',
            'tanggal_registrasi_paket' => 'datetime',
            'tanggal_penugasan_ppk' => 'datetime',
            'tanggal_penugasan_pp' => 'datetime',
            'tanggal_penunjukan_vendor' => 'datetime',
        ];
    }

    /**
     * Menghitung jalur pengadaan berdasarkan batas nilai nominal pagu atau HPS.
     *
     * Aturan:
     * - Nilai s.d Rp 50.000.000: Pengadaan Langsung / Pembelian Langsung
     * - Nilai Rp 50.000.001 s.d Rp 200.000.000: Pejabat Pengadaan (PP)
     * - Nilai > Rp 200.000.000: Pejabat Pembuat Komitmen (PPK)
     *
     * @param  float  $nominal  Nominal nilai paket pengadaan
     * @return string Kode jalur routing yang sesuai
     */
    public static function tentukanRouting(float $nominal): string
    {
        if ($nominal <= 50000000) {
            return self::ROUTING_LANGSUNG;
        } elseif ($nominal <= 200000000) {
            return self::ROUTING_PP;
        } else {
            return self::ROUTING_PPK;
        }
    }

    /**
     * Relasi many-to-one ke surat usulan Permohonan Pengadaan asal.
     *
     * @return BelongsTo Relasi ke model PermohonanPengadaan
     */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * Relasi many-to-one ke User staf PPBJ pengelola registrasi paket.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppbjUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppbj', 'id_user');
    }

    /**
     * Relasi many-to-one ke User Pejabat Pembuat Komitmen (PPK) yang ditugaskan.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppkUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppk', 'id_user');
    }

    /**
     * Relasi many-to-one ke User Pejabat Pengadaan (PP) yang ditugaskan.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_pp', 'id_user');
    }

    /**
     * Relasi many-to-one ke data Vendor/Penyedia terpilih.
     *
     * @return BelongsTo Relasi ke model Vendor
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'npwp_vendor', 'npwp');
    }

    /**
     * Relasi one-to-many ke catatan reviu dokumen oleh PP/PPK.
     *
     * @return HasMany Koleksi berkas reviu dokumen
     */
    public function reviuDokumen(): HasMany
    {
        return $this->hasMany(ReviuDokumen::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-many ke rekam jejak pengalihan tugas PPK.
     *
     * @return HasMany Koleksi riwayat penugasan PPK
     */
    public function riwayatPpk(): HasMany
    {
        return $this->hasMany(RiwayatPenugasanPPK::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-one ke dokumen Kontrak SPK yang terbit untuk paket ini.
     *
     * @return HasOne Relasi ke model KontrakSPK
     */
    public function kontrakSpk(): HasOne
    {
        return $this->hasOne(KontrakSPK::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-many ke pencatatan fisik barang yang diterima di gudang/lokasi.
     *
     * @return HasMany Koleksi penerimaan barang
     */
    public function penerimaanBarang(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-many ke tagihan/transaksi pembayaran paket ini.
     *
     * @return HasMany Koleksi pembayaran
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-many ke daftar reminder/pengingat terjadwal paket ini.
     *
     * @return HasMany Koleksi reminder
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Scope query untuk memfilter paket sesuai wewenang peran dan isolasi berkas 4 akun PPK (NFR RBAC).
     *
     * Memastikan:
     * - Akun PPK hanya dapat mengakses paket yang secara spesifik ditugaskan kepada ID dirinya.
     * - Akun PP hanya dapat mengakses paket di bawah jalurnya (50jt-200jt) atau yang ditugaskan padanya.
     * - Pengusul (User) hanya dapat melihat paket yang berasal dari permohonannya sendiri.
     * - Manajemen (Wadir 2, PPBJ, Perencanaan, Keuangan, Perlengkapan, Staf Bidang 2) memiliki akses menyeluruh.
     *
     * @param  Builder  $query  Query builder Eloquent
     * @param  User  $user  Objek pengguna yang sedang terautentikasi
     * @return Builder Query yang telah dibatasi hak aksesnya
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->role === 'ppk') {
            // Isolasi berkas 4 akun PPK: Hanya melihat paket yang ditugaskan ke ID akun PPK bersangkutan
            return $query->where('id_user_ppk', $user->id_user);
        }

        if ($user->role === 'pp') {
            // PP hanya melihat paket yang dialokasikan ke jalur PP (50jt - 200jt) atau ditugaskan kepadanya
            return $query->where(function ($q) use ($user) {
                $q->where('jalur_routing', self::ROUTING_PP)
                    ->orWhere('id_user_pp', $user->id_user);
            });
        }

        if ($user->role === 'user') {
            // Pemohon hanya melihat paket dari permohonan yang diajukannya
            return $query->whereHas('permohonan', function ($q) use ($user) {
                $q->where('id_user', $user->id_user);
            });
        }

        // Role pengawas & manajemen (ppbj, perencanaan, wadir_2, perlengkapan, keuangan, staff_bidang_2) dapat melihat seluruh paket
        return $query;
    }
}
