<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model PaketPengadaan ini sebagai poros operasional paket pengadaan, auto-routing nominal 3 jalur, sinkronisasi atribut HPS/negosiasi, serta isolasi berkas RBAC PPK/PP.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Paket Pengadaan (PaketPengadaan)
 *
 * memusatkan aturan penentuan jalur nilai (`<=50jt`, `50-200jt`, `>200jt`), pewarisan otomatis PPK awal,
 * serta query scope isolasi berkas 4 akun PPK pada model ini agar tidak tersebar di banyak controller.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `paket_pengadaan` dengan primary key string `nomor_paket`.
 *
 * Contoh pemanggilan:
 * `$pakets = PaketPengadaan::forUser($user)->with(['permohonan', 'vendor'])->latest('tanggal_registrasi_paket')->get();`
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
        'nomor_surat',
        'tanggal_registrasi_paket',
        'nomor_memo_ppbj',
        'id_user_ppbj',
        'id_user_ppk',
        'alasan_pengalihan_ppk',
        'id_user_pp',
        'nilai_hasil_negosiasi',
        'nominal_harga_akhir',
        'catatan_verifikasi_pp',
        'id_vendor',
        'dieksekusi_belanja_oleh',
        'tanggal_eksekusi_belanja',
        'nomor_kwitansi_belanja',
        'file_kwitansi_belanja',
    ];

    /**
     * mengonversi kolom nominal HPS/pagu/negosiasi ke desimal 2 digit serta tanggal registrasi dan belanja ke Carbon.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $paket->nilai_hps atau $paket->tanggal_registrasi_paket.`
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
            'tanggal_eksekusi_belanja' => 'datetime',
        ];
    }

    /**
     * menyinkronkan kolom kembar (HPS vs harga perkiraan, harga akhir vs negosiasi), pewarisan PPK awal, dan aturan bebas memo PPBJ pada >200jt.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dijalankan otomatis oleh Eloquent pada event creating dan saving.`
     */
    protected static function booted(): void
    {
        static::creating(function (PaketPengadaan $model) {
            // menjaga sinkronisasi dua pasang kolom nominal agar modul lama maupun notulensi terbaru selalu membaca angka yang sama.
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
            // menyediakan fallback PPBJ agar seeder/test yang membuat paket langsung tanpa id_user_ppbj tetap memenuhi FK NOT NULL.
            if (empty($model->id_user_ppbj)) {
                $ppbjUser = User::withRole(User::ROLE_PPBJ)->first() ?? User::first();
                if (! $ppbjUser) {
                    $ppbjRoleId = Role::where('nama_role', User::ROLE_PPBJ)->value('id');
                    $ppbjUser = User::create([
                        'nip' => '198403122008121099',
                        'username' => 'system_ppbj',
                        'email' => 'ppbj_system@kampus.ac.id',
                        'nama' => 'Staff PPBJ Default',
                        'password' => 'secret123',
                        'jabatan' => 'Staff PBJ',
                        'id_role' => $ppbjRoleId,
                        'no_hp' => '081234567899',
                    ]);
                }
                $model->id_user_ppbj = $ppbjUser->id_user;
            }

            // mewariskan id_user_ppk_awal dari permohonan hanya untuk jalur >50jt karena Belanja Langsung <=50jt tidak melibatkan PPK.
            if (empty($model->id_user_ppk) && $model->jalur_routing !== self::ROUTING_LANGSUNG && ! empty($model->nomor_surat)) {
                $permohonanAsal = PermohonanPengadaan::find($model->nomor_surat);
                if ($permohonanAsal && $permohonanAsal->id_user_ppk_awal) {
                    $model->id_user_ppk = $permohonanAsal->id_user_ppk_awal;
                }
            }

            // mengosongkan nomor_memo_ppbj secara paksa pada jalur >200jt karena proses lelang PPK tidak menerbitkan memo PPBJ.
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

            if ($model->jalur_routing === self::ROUTING_PPK || (float) ($model->nilai_hps ?? 0) > 200000000) {
                $model->nomor_memo_ppbj = null;
            }
        });
    }

    /**
     * mengklasifikasikan jalur pengadaan berdasarkan ambang batas nominal pagu/HPS sesuai aturan domain PBJ.
     *
     * @param  float  $nominal  menerima nilai pagu atau HPS paket dalam Rupiah.
     * @return string mengembalikan konstanta jalur routing (`langsung_sd_50jt`, `pp_50jt_sd_200jt`, atau `ppk_diatas_200jt`).
     *
     * Contoh pemanggilan:
     * `$jalur = PaketPengadaan::tentukanRouting(125000000);`
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
     * menyediakan utilitas untuk mengunci seluruh MAK yang terhubung ke permohonan asal paket ini.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$paket->kunciMakTerkait();`
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
     * menghubungkan paket ke surat permohonan pengadaan asalnya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PermohonanPengadaan.
     *
     * Contoh pemanggilan:
     * `$surat = $paket->permohonan;`
     */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * menghubungkan paket ke petugas PPBJ yang mendaftarkan dan mengoordinasikan paket.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$ppbj = $paket->ppbjUser;`
     */
    public function ppbjUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppbj', 'id_user');
    }

    /**
     * menghubungkan paket ke akun PPK yang ditugaskan mengawal paket ini.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$ppk = $paket->ppkUser;`
     */
    public function ppkUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppk', 'id_user');
    }

    /**
     * menghubungkan paket ke akun Pejabat Pengadaan (PP) untuk jalur Rp50jt-200jt.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$pp = $paket->ppUser;`
     */
    public function ppUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_pp', 'id_user');
    }

    /**
     * menghubungkan paket ke pengguna yang mengeksekusi proses belanja (PPBJ, PP, atau PPK).
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$eksekutor = $paket->pelaksanaBelanja;`
     */
    public function pelaksanaBelanja(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dieksekusi_belanja_oleh', 'id_user');
    }

    /**
     * menghubungkan paket ke entitas Vendor rekanan setelah rekanan ditetapkan pada tahap perikatan/belanja.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Vendor.
     *
     * Contoh pemanggilan:
     * `$rekanan = $paket->vendor;`
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'id_vendor', 'id');
    }

    /**
     * menghubungkan paket ke riwayat catatan reviu dokumen oleh PP maupun PPK.
     *
     * @return HasMany mengembalikan relasi HasMany ke model ReviuDokumen.
     *
     * Contoh pemanggilan:
     * `$daftarReviu = $paket->reviuDokumen;`
     */
    public function reviuDokumen(): HasMany
    {
        return $this->hasMany(ReviuDokumen::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * mengambil riwayat mutasi pengalihan PPK dari tabel `audit_log` agar tidak perlu tabel riwayat terpisah.
     *
     * @return HasMany mengembalikan relasi HasMany ke model AuditLog khusus aksi `REASSIGN_PPK`.
     *
     * Contoh pemanggilan:
     * `$riwayat = $paket->riwayatPpk;`
     */
    public function riwayatPpk(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_entitas', 'nomor_paket')
            ->where('jenis_aksi', 'REASSIGN_PPK');
    }

    /**
     * menghubungkan paket (>50jt) ke satu dokumen perikatan Kontrak/SPK/Surat Pesanan miliknya.
     *
     * @return HasOne mengembalikan relasi HasOne ke model KontrakSPK.
     *
     * Contoh pemanggilan:
     * `$spk = $paket->kontrakSpk;`
     */
    public function kontrakSpk(): HasOne
    {
        return $this->hasOne(KontrakSPK::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menyediakan relasi alias `kontrak` yang meneruskan ke `kontrakSpk()` demi kompatibilitas pemanggilan ringkas.
     *
     * @return HasOne mengembalikan relasi HasOne ke model KontrakSPK.
     *
     * Contoh pemanggilan:
     * `$spk = $paket->kontrak;`
     */
    public function kontrak(): HasOne
    {
        return $this->kontrakSpk();
    }

    /**
     * menghubungkan paket ke rekaman inspeksi fisik dan penerbitan BAST oleh Unit Perlengkapan.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PenerimaanBarang.
     *
     * Contoh pemanggilan:
     * `$daftarBast = $paket->penerimaanBarang;`
     */
    public function penerimaanBarang(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan paket ke pengajuan pembayaran termin beserta status pencairannya.
     *
     * @return HasMany mengembalikan relasi HasMany ke model Pembayaran.
     *
     * Contoh pemanggilan:
     * `$daftarTagihan = $paket->pembayaran;`
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan paket ke seluruh jadwal dan histori alarm pengingat pengiriman terkait.
     *
     * @return HasMany mengembalikan relasi HasMany ke model Reminder.
     *
     * Contoh pemanggilan:
     * `$alarmList = $paket->reminders;`
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menyaring daftar paket berdasarkan izin RBAC dan aturan isolasi 4 akun PPK agar tidak terjadi kebocoran berkas lintas PPK.
     *
     * @param  Builder  $query  menerima query builder Eloquent PaketPengadaan.
     * @param  User  $user  memeriksa kombinasi permission `paket.view_all`, `reviu.ppk`, `reviu.pp`, dan `permohonan.view_own`.
     * @return Builder mengembalikan query yang sudah dibatasi sesuai hak akses pengguna.
     *
     * Contoh pemanggilan:
     * `PaketPengadaan::forUser(auth()->user())->paginate(15);`
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->hasPermission('paket.view_all')) {
            return $query;
        }

        if ($user->hasPermission('reviu.ppk') || ($user->hasPermission('paket.view_assigned') && ! $user->hasPermission('reviu.pp'))) {
            // mengisolasi peninjau PPK agar hanya melihat paket yang ditugaskan kepadanya.
            return $query->where('id_user_ppk', $user->id_user);
        }

        if ($user->hasPermission('reviu.pp')) {
            return $query->where(function ($q) use ($user) {
                $q->where('jalur_routing', self::ROUTING_PP)
                    ->orWhere('id_user_pp', $user->id_user);
            });
        }

        if ($user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')) {
            return $query->whereHas('permohonan', function ($q) use ($user) {
                $q->where('id_user', $user->id_user);
            });
        }

        return $query;
    }

    /**
     * menyediakan accessor `kode_paket` sebagai alias dari primary key `nomor_paket`.
     *
     * @return string|null mengembalikan nomor/kode paket atau null bila belum terisi.
     *
     * Contoh pemanggilan:
     * `$kode = $paket->kode_paket;`
     */
    public function getKodePaketAttribute(): ?string
    {
        return $this->attributes['nomor_paket'] ?? $this->attributes['kode_paket'] ?? null;
    }

    /**
     * menyelaraskan assignment atribut `kode_paket` ke kolom asli `nomor_paket` bila `nomor_paket` belum diisi.
     *
     * @param  string|null  $value  menerima nilai kode paket dari caller/seeder lama.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$paket->kode_paket = 'PKT-2026-001';`
     */
    public function setKodePaketAttribute(?string $value): void
    {
        if (! isset($this->attributes['nomor_paket']) && $value) {
            $this->attributes['nomor_paket'] = $value;
        }
    }

    /**
     * menyediakan mutator alias `ppbj_user_id` untuk memetakan input lama langsung ke kolom `id_user_ppbj`.
     *
     * @param  int|null  $value  menerima ID user PPBJ penanggung jawab.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$paket->ppbj_user_id = 5;`
     */
    public function setPpbjUserIdAttribute(?int $value): void
    {
        $this->attributes['id_user_ppbj'] = $value;
    }

    /**
     * menyediakan accessor `metode_pengadaan` yang membaca kolom basis data `mekanisme_pengadaan`.
     *
     * @return string|null mengembalikan metode pengadaan paket atau null.
     *
     * Contoh pemanggilan:
     * `$metode = $paket->metode_pengadaan;`
     */
    public function getMetodePengadaanAttribute(): ?string
    {
        return $this->attributes['mekanisme_pengadaan'] ?? null;
    }

    /**
     * menyelaraskan penulisan properti `metode_pengadaan` ke kolom `mekanisme_pengadaan` di tabel `paket_pengadaan`.
     *
     * @param  string|null  $value  menerima slug/nama metode pengadaan yang dipilih PPBJ.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$paket->metode_pengadaan = PaketPengadaan::METODE_NON_TENDER;`
     */
    public function setMetodePengadaanAttribute(?string $value): void
    {
        if ($value !== null) {
            $this->attributes['mekanisme_pengadaan'] = $value;
        }
    }

    /**
     * menyediakan mutator no-op untuk `kode_mak` agar mass assignment lama yang masih mengirim kunci `kode_mak` tidak melempar MassAssignmentException.
     *
     * @param  string|null  $value  mengabaikan nilai ini karena MAK kini disimpan di tabel permohonan dan alokasi_mak.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PaketPengadaan::create(['nomor_paket' => '...', 'kode_mak' => '521211']);`
     */
    public function setKodeMakAttribute(?string $value): void
    {
        // biarkan kosong demi kompatibilitas ke belakang tanpa menyimpan kolom duplikat di paket_pengadaan.
    }
}
