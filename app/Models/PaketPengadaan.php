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

    public const JENIS_BARANG = 'barang';

    public const JENIS_JASA_LAINNYA = 'jasa_lainnya';

    public const JENIS_KONSULTANSI = 'jasa_konsultansi';

    public const JENIS_PEKERJAAN_KONSTRUKSI = 'pekerjaan_konstruksi';

    public const JENIS_KONSTRUKSI = 'pekerjaan_konstruksi';

    public const METODE_TENDER = 'Tender';

    public const METODE_PENGADAAN_LANGSUNG = 'Pengadaan Langsung';

    public const METODE_E_PURCHASING = 'E-Purchasing';

    public const METODE_BELANJA_LANGSUNG = 'belanja_langsung';

    public const METODE_NON_TENDER = 'non_tender';

    public const METODE_E_KATALOG = 'e_katalog';

    public const METODE_SPSE = 'spse';

    public const METODE_TENDER_SLUG = 'tender';

    public const STATUS_TERDAFTAR = 'terdaftar';

    public const STATUS_REGISTRASI_PPBJ = 'terdaftar';

    public const STATUS_BELANJA_LANGSUNG_SELESAI = 'belanja_langsung_selesai';

    public const STATUS_MENUNGGU_APPROVAL_PPK = 'menunggu_approval_ppk';

    public const STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP = 'disetujui_ppk_siap_belanja_pp';

    public const STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ = 'siap_upload_dokumen_ppbj';

    public const STATUS_SPK_TERBIT = 'spk_terbit';

    public const STATUS_PROSES = 'proses';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_BATAL = 'batal';

    public const JALUR_LANGSUNG = 'langsung_sd_50jt';

    public const JALUR_PP = 'pp_50jt_sd_200jt';

    public const JALUR_PPK = 'ppk_diatas_200jt';

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
        'metode_pengadaan',
        'jalur_routing',
        'nilai_hps',
        'nominal_harga_perkiraan',
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
        'nominal_harga_akhir',
        'catatan_verifikasi_pp',
        'id_vendor',
        'npwp_vendor',
        'tanggal_penunjukan_vendor',
        'dieksekusi_belanja_oleh',
        'tanggal_eksekusi_belanja',
        'nomor_kwitansi_belanja',
        'file_kwitansi_belanja',
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
            'nominal_harga_perkiraan' => 'decimal:2',
            'alokasi_pagu_paket' => 'decimal:2',
            'nilai_hasil_negosiasi' => 'decimal:2',
            'nominal_harga_akhir' => 'decimal:2',
            'tanggal_registrasi_paket' => 'datetime',
            'tanggal_penugasan_ppk' => 'datetime',
            'tanggal_penugasan_pp' => 'datetime',
            'tanggal_penunjukan_vendor' => 'datetime',
            'tanggal_eksekusi_belanja' => 'datetime',
        ];
    }

    /**
     * Hook booted model PaketPengadaan untuk melengkapi kolom wajib jika tidak disediakan secara eksplisit.
     */
    protected static function booted(): void
    {
        static::creating(function (PaketPengadaan $model) {
            if (empty($model->nilai_hps) && ! empty($model->nominal_harga_perkiraan)) {
                $model->nilai_hps = $model->nominal_harga_perkiraan;
            }
            if (empty($model->nominal_harga_perkiraan) && ! empty($model->nilai_hps)) {
                $model->nominal_harga_perkiraan = $model->nilai_hps;
            }
            if (empty($model->nilai_hasil_negosiasi) && ! empty($model->nominal_harga_akhir)) {
                $model->nilai_hasil_negosiasi = $model->nominal_harga_akhir;
            }
            if (empty($model->nominal_harga_akhir) && ! empty($model->nilai_hasil_negosiasi)) {
                $model->nominal_harga_akhir = $model->nilai_hasil_negosiasi;
            }
            if (empty($model->alokasi_pagu_paket)) {
                $model->alokasi_pagu_paket = $model->nilai_hps ?? 0;
            }
            if (empty($model->platform_pengadaan)) {
                $model->platform_pengadaan = 'SPSE';
            }
            if (empty($model->mekanisme_pengadaan)) {
                $model->mekanisme_pengadaan = $model->metode_pengadaan ?? 'Tender';
            }
            if (empty($model->id_user_ppbj)) {
                $ppbjUser = User::where('role', 'ppbj')->first() ?? User::first();
                if (! $ppbjUser) {
                    $ppbjUser = User::create([
                        'nip' => '198403122008121099',
                        'username' => 'system_ppbj',
                        'email' => 'ppbj_system@kampus.ac.id',
                        'nama' => 'Staff PPBJ Default',
                        'password' => 'secret123',
                        'jabatan' => 'Staff PBJ',
                        'role' => 'ppbj',
                        'no_hp' => '081234567899',
                    ]);
                }
                $model->id_user_ppbj = $ppbjUser->id_user;
            }

            // Warisi otomatis id_user_ppk dari permohonan.id_user_ppk_awal untuk paket > 50jt jika belum ditentukan
            if (empty($model->id_user_ppk) && $model->jalur_routing !== self::ROUTING_LANGSUNG && ! empty($model->nomor_surat)) {
                $permohonanAsal = PermohonanPengadaan::find($model->nomor_surat);
                if ($permohonanAsal && $permohonanAsal->id_user_ppk_awal) {
                    $model->id_user_ppk = $permohonanAsal->id_user_ppk_awal;
                    $model->tanggal_penugasan_ppk = $model->tanggal_penugasan_ppk ?? now();
                    $model->status_penugasan_ppk = $model->status_penugasan_ppk ?? 'aktif';
                }
            }

            // Aturan bisnis Notulensi PPBJ: Paket > 200 Juta (jalur PPK) tidak menggunakan memo pengadaan PPBJ
            if ($model->jalur_routing === self::ROUTING_PPK || (float) ($model->nilai_hps ?? 0) > 200000000) {
                $model->nomor_memo_ppbj = null;
            }
        });

        static::saving(function (PaketPengadaan $model) {
            if ($model->isDirty('nominal_harga_akhir') && ! $model->isDirty('nilai_hasil_negosiasi') && $model->nominal_harga_akhir !== null) {
                $model->nilai_hasil_negosiasi = $model->nominal_harga_akhir;
            } elseif ($model->isDirty('nilai_hasil_negosiasi') && ! $model->isDirty('nominal_harga_akhir') && $model->nilai_hasil_negosiasi !== null) {
                $model->nominal_harga_akhir = $model->nilai_hasil_negosiasi;
            }

            // Pastikan paket > 200 Juta selalu tanpa nomor memo pengadaan PPBJ
            if ($model->jalur_routing === self::ROUTING_PPK || (float) ($model->nilai_hps ?? 0) > 200000000) {
                $model->nomor_memo_ppbj = null;
            }
        });
    }

    /**
     * Menghitung jalur pengadaan berdasarkan batas nilai nominal pagu atau HPS (Notulensi Pertemuan PPBJ).
     *
     * Aturan:
     * - Nilai <= Rp 50.000.000: Belanja Langsung oleh PPBJ (output Kwitansi, tanpa Kontrak/SPK -> Perlengkapan)
     * - Nilai > Rp 50.000.000 s.d. Rp 200.000.000: Melibatkan PP (Review PP + Input Harga & Vendor -> Approval PPK -> PP Belanja -> PPBJ Upload 1 Dokumen)
     * - Nilai > Rp 200.000.000: Melibatkan PPK (PPBJ Input Data Lelang -> Approval & Belanja PPK -> PPBJ Upload 1 Dokumen, Tanpa Memo PPBJ)
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
     * Mengunci secara permanen seluruh Mata Anggaran Kegiatan (MAK) yang terhubung dengan paket ini.
     * Dipanggil saat PPK menyetujui dokumen & mengunci MAK atau saat Kontrak SPK diterbitkan.
     */
    public function kunciMakTerkait(): void
    {
        $permohonan = $this->permohonan;
        if (! $permohonan) {
            return;
        }

        if ($permohonan->anggaranMak) {
            $permohonan->anggaranMak->lock();
        }

        foreach ($permohonan->alokasiMak ?? [] as $alokasi) {
            $alokasi->mak?->lock();
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
     * Relasi many-to-one ke pejabat pelaksana kewenangan belanja (PPBJ untuk <=50jt, PP untuk 50-200jt, PPK untuk >200jt).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function pelaksanaBelanja(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dieksekusi_belanja_oleh', 'id_user');
    }

    /**
     * Relasi many-to-one ke data Vendor/Penyedia terpilih.
     *
     * @return BelongsTo Relasi ke model Vendor
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'id_vendor', 'id');
    }

    /**
     * Mutator npwp_vendor yang otomatis menyelaraskan id_vendor.
     */
    public function setNpwpVendorAttribute($value): void
    {
        $this->attributes['npwp_vendor'] = $value;
        if (! empty($value) && empty($this->attributes['id_vendor'])) {
            $vendor = Vendor::where('npwp', $value)->first();
            if ($vendor) {
                $this->attributes['id_vendor'] = $vendor->id;
            }
        }
    }

    /**
     * Mutator id_vendor yang otomatis menyelaraskan npwp_vendor legacy jika ada.
     */
    public function setIdVendorAttribute($value): void
    {
        $this->attributes['id_vendor'] = $value;
        if (! empty($value) && empty($this->attributes['npwp_vendor'])) {
            $vendor = Vendor::find($value);
            if ($vendor && ! empty($vendor->npwp)) {
                $this->attributes['npwp_vendor'] = $vendor->npwp;
            }
        }
    }

    /**
     * Accessor id_vendor untuk fallback lookup jika hanya npwp_vendor yang terisi.
     */
    public function getIdVendorAttribute(): ?int
    {
        if (isset($this->attributes['id_vendor'])) {
            return (int) $this->attributes['id_vendor'];
        }
        if (! empty($this->attributes['npwp_vendor'])) {
            $vendor = Vendor::where('npwp', $this->attributes['npwp_vendor'])->first();
            if ($vendor) {
                $this->attributes['id_vendor'] = $vendor->id;

                return (int) $vendor->id;
            }
        }

        return null;
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
     * Relasi alias one-to-one ke dokumen Kontrak SPK.
     *
     * @return HasOne Relasi ke model KontrakSPK
     */
    public function kontrak(): HasOne
    {
        return $this->kontrakSpk();
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
        if ($user->hasRole('admin')) {
            return $query;
        }

        if ($user->hasRole('ppk') || ($user->hasPermission('reviu.ppk') && ! $user->hasRole('ppbj'))) {
            // Isolasi berkas 4 akun PPK: Hanya melihat paket yang ditugaskan ke ID akun PPK bersangkutan
            return $query->where('id_user_ppk', $user->id_user);
        }

        if ($user->hasRole('pp') || ($user->hasPermission('reviu.pp') && ! $user->hasRole('ppbj'))) {
            // PP hanya melihat paket yang dialokasikan ke jalur PP (50jt - 200jt) atau ditugaskan kepadanya
            return $query->where(function ($q) use ($user) {
                $q->where('jalur_routing', self::ROUTING_PP)
                    ->orWhere('id_user_pp', $user->id_user);
            });
        }

        if ($user->hasRole(['user', 'pengaju']) || $user->hasPermission('permohonan.view_own')) {
            // Pemohon hanya melihat paket dari permohonan yang diajukannya
            return $query->whereHas('permohonan', function ($q) use ($user) {
                $q->where('id_user', $user->id_user);
            });
        }

        // Role pengawas & manajemen (ppbj, perencanaan, wadir_2, perlengkapan, keuangan, staff_bidang_2) dapat melihat seluruh paket
        return $query;
    }

    /**
     * Accessor untuk alias kode_paket yang merujuk ke primary key nomor_paket.
     */
    public function getKodePaketAttribute(): ?string
    {
        return $this->attributes['nomor_paket'] ?? $this->attributes['kode_paket'] ?? null;
    }

    /**
     * Mutator untuk alias kode_paket yang menyelaraskan ke kolom nomor_paket.
     */
    public function setKodePaketAttribute(?string $value): void
    {
        if (! isset($this->attributes['nomor_paket']) && $value) {
            $this->attributes['nomor_paket'] = $value;
        }
    }

    /**
     * Mutator untuk alias ppbj_user_id yang mengarahkan ke id_user_ppbj.
     */
    public function setPpbjUserIdAttribute(?int $value): void
    {
        $this->attributes['id_user_ppbj'] = $value;
    }

    /**
     * Accessor alias metode_pengadaan yang merujuk ke kolom mekanisme_pengadaan.
     */
    public function getMetodePengadaanAttribute(): ?string
    {
        return $this->attributes['mekanisme_pengadaan'] ?? null;
    }

    /**
     * Mutator alias metode_pengadaan yang menyelaraskan ke kolom mekanisme_pengadaan.
     */
    public function setMetodePengadaanAttribute(?string $value): void
    {
        if ($value !== null) {
            $this->attributes['mekanisme_pengadaan'] = $value;
        }
    }

    /**
     * Mutator untuk alias kode_mak agar tidak error saat dipassing mass assignment lama.
     */
    public function setKodeMakAttribute(?string $value): void
    {
        // Kompatibilitas ke belakang
    }
}
