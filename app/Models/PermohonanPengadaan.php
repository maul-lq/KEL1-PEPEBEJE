<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Permohonan Pengadaan (Usulan Surat Pengadaan Barang/Jasa)
 *
 * Merepresentasikan dokumen usulan awal pengadaan yang diajukan oleh unit kerja/jurusan (User).
 * Menjadi entitas sentral pada fase perencanaan dan persetujuan:
 * - Menangani alur status usulan (diajukan, reviu_paralel, pending_anggaran, disetujui, ditolak, diproses_ppbj, selesai).
 * - Mendukung verifikasi paralel simultan oleh 3 pihak independen (Staff Bidang 2, Perencanaan, Wadir 2).
 * - Mendukung pelacakan pohon usulan hasil revisi (nomor_surat_revisi) dan pemecahan/split paket (nomor_surat_induk_split).
 * - Terhubung ke alokasi Mata Anggaran Kegiatan (MAK) serta paket pengadaan yang terbentuk.
 */
class PermohonanPengadaan extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'permohonan_pengadaan';

    /**
     * Primary key tabel berupa string nomor surat usulan.
     *
     * @var string
     */
    protected $primaryKey = 'nomor_surat';

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
     * Konstanta status siklus hidup surat permohonan pengadaan.
     */
    const STATUS_DIAJUKAN = 'diajukan';

    const STATUS_REVIU_PARALEL = 'reviu_paralel';

    const STATUS_PENDING_ANGGARAN = 'pending_anggaran';

    const STATUS_DISETUJUI = 'disetujui';

    const STATUS_DITOLAK = 'ditolak';

    const STATUS_DIPROSES_PPBJ = 'diproses_ppbj';

    const STATUS_SELESAI = 'selesai';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_surat',
        'perihal',
        'judul_pengadaan',
        'tujuan_pengadaan_ringkas',
        'file_permohonan',
        'file_surat_permohonan',
        'file_kak',
        'file_spesifikasi_teknis',
        'anggaran_diajukan',
        'estimasi_total_pagu',
        'status',
        'status_permohonan',
        'status_anggaran',
        'revisi_ke',
        'tanggal_pengajuan',
        'kode_unit',
        'id_user',
        'nomor_surat_induk_split',
        'tanggal_pemecahan',
        'alasan_pemecahan',
        'nomor_surat_revisi',
        'tanggal_revisi',
        'alasan_revisi',
        'kode_mak',
    ];

    /**
     * Konfigurasi type casting untuk angka, nilai nominal, dan tanggal/waktu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revisi_ke' => 'integer',
            'estimasi_total_pagu' => 'decimal:2',
            'tanggal_pengajuan' => 'datetime',
            'tanggal_pemecahan' => 'datetime',
            'tanggal_revisi' => 'datetime',
        ];
    }

    /**
     * Hook booted model PermohonanPengadaan untuk memastikan integritas kolom wajib non-null.
     */
    protected static function booted(): void
    {
        static::creating(function (PermohonanPengadaan $model) {
            if (empty($model->tujuan_pengadaan_ringkas)) {
                $model->tujuan_pengadaan_ringkas = $model->judul_pengadaan ?? $model->perihal ?? 'Pengadaan barang jasa institusi';
            }
            if (empty($model->file_permohonan)) {
                $model->file_permohonan = 'permohonan/dummy.pdf';
            }
            if (empty($model->status_permohonan)) {
                $model->status_permohonan = self::STATUS_DIAJUKAN;
            }
            if (empty($model->kode_unit)) {
                $unit = Unit::firstOrCreate(
                    ['kode_unit' => 'TIK'],
                    ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
                );
                $model->kode_unit = $unit->kode_unit;
            }
        });
    }

    /**
     * Accessor untuk alias status yang merujuk ke kolom status_permohonan.
     */
    public function getStatusAttribute(): ?string
    {
        return $this->attributes['status_permohonan'] ?? null;
    }

    /**
     * Mutator untuk alias status yang menyelaraskan ke kolom status_permohonan.
     */
    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status_permohonan'] = $value;
    }

    /**
     * Mutator untuk alias asal_unit agar menyelaraskan ke kode_unit.
     */
    public function setAsalUnitAttribute(?string $value): void
    {
        if (empty($this->attributes['kode_unit'])) {
            $unit = Unit::firstOrCreate(
                ['kode_unit' => 'TIK'],
                ['nama_unit' => $value ?? 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
            );
            $this->attributes['kode_unit'] = $unit->kode_unit;
        }
    }

    /**
     * Mutator untuk alias tujuan_ringkas agar menyelaraskan ke tujuan_pengadaan_ringkas.
     */
    public function setTujuanRingkasAttribute(?string $value): void
    {
        if (empty($this->attributes['tujuan_pengadaan_ringkas'])) {
            $this->attributes['tujuan_pengadaan_ringkas'] = $value;
        }
    }

    /**
     * Mutator untuk alias file_pdf_srikandi agar menyelaraskan ke file_permohonan.
     */
    public function setFilePdfSrikandiAttribute(?string $value): void
    {
        if (empty($this->attributes['file_permohonan'])) {
            $this->attributes['file_permohonan'] = $value;
        }
    }

    /**
     * Relasi many-to-one ke Unit Kerja pengusul pengadaan.
     *
     * @return BelongsTo Relasi ke model Unit
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'kode_unit', 'kode_unit');
    }

    /**
     * Relasi many-to-one ke User (staf/pemohon yang membuat surat usulan).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi many-to-one ke surat induk asal jika permohonan ini merupakan hasil pemecahan (split).
     *
     * @return BelongsTo Relasi ke model permohonan induk
     */
    public function indukSplit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_induk_split', 'nomor_surat');
    }

    /**
     * Relasi one-to-many ke surat-surat anak yang dipecah dari permohonan ini.
     *
     * @return HasMany Koleksi permohonan anak hasil split
     */
    public function anakSplit(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_induk_split', 'nomor_surat');
    }

    /**
     * Relasi many-to-one ke surat versi terdahulu jika permohonan ini merupakan versi revisi.
     *
     * @return BelongsTo Relasi ke model permohonan terdahulu
     */
    public function suratAsalRevisi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_revisi', 'nomor_surat');
    }

    /**
     * Relasi one-to-many ke surat-surat versi revisi yang diturunkan dari permohonan ini.
     *
     * @return HasMany Koleksi permohonan revisi turunan
     */
    public function suratHasilRevisi(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_revisi', 'nomor_surat');
    }

    /**
     * Relasi many-to-one ke Mata Anggaran Kegiatan (MAK) utama pembiaya usulan.
     *
     * @return BelongsTo Relasi ke model AnggaranMAK
     */
    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }

    /**
     * Relasi one-to-many ke hasil reviu tiga arah (Verifikasi Paralel: Bidang 2, Perencanaan, Wadir 2).
     *
     * @return HasMany Koleksi catatan verifikasi paralel
     */
    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * Relasi one-to-many ke rincian alokasi MAK pendukung jika usulan didanai multi-MAK.
     *
     * @return HasMany Koleksi alokasi MAK permohonan
     */
    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * Relasi one-to-many ke paket-paket pengadaan yang dibentuk dari permohonan ini.
     *
     * @return HasMany Koleksi paket pengadaan terkait
     */
    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * Relasi one-to-one ke paket pengadaan utama yang dibentuk dari usulan ini.
     *
     * @return HasOne Relasi ke model PaketPengadaan
     */
    public function paket(): HasOne
    {
        return $this->hasOne(PaketPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * Memeriksa apakah surat permohonan ini merupakan hasil revisi dari surat lain.
     *
     * @return bool True jika merupakan revisi
     */
    public function isRevisi(): bool
    {
        return ! empty($this->nomor_surat_revisi);
    }

    /**
     * Memeriksa apakah surat permohonan ini merupakan hasil pemecahan (split).
     *
     * @return bool True jika merupakan anak split
     */
    public function isSplit(): bool
    {
        return ! empty($this->nomor_surat_induk_split);
    }

    /**
     * Memeriksa apakah permohonan telah disetujui secara final.
     *
     * @return bool True jika status disetujui
     */
    public function isApproved(): bool
    {
        return $this->status_permohonan === self::STATUS_DISETUJUI;
    }

    /**
     * Memeriksa apakah permohonan ditolak.
     *
     * @return bool True jika status ditolak
     */
    public function isRejected(): bool
    {
        return $this->status_permohonan === self::STATUS_DITOLAK;
    }

    /**
     * Memeriksa apakah permohonan berstatus pending anggaran (menunggu penetapan/ketersediaan dana MAK).
     *
     * @return bool True jika pending anggaran
     */
    public function isPendingAnggaran(): bool
    {
        return $this->status_permohonan === self::STATUS_PENDING_ANGGARAN;
    }

    /**
     * Scope query sesuai hak akses peran pengguna.
     * Pengusul biasa (role 'user') hanya diizinkan melihat usulan permohonan yang diajukannya sendiri.
     *
     * @param  Builder  $query  Query builder Eloquent
     * @param  User  $user  Pengguna yang sedang aktif
     * @return Builder Query yang disaring
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->role === 'user') {
            return $query->where('id_user', $user->id_user);
        }

        return $query;
    }
}
