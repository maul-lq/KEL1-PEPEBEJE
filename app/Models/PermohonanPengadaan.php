<?php

/**
 * Informasi File
 *
 * Penulis   : DendyRafi, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model PermohonanPengadaan ini untuk mengelola surat usulan pengadaan awal, pemecahan (split) MAK/jenis, pelacakan 8 siklus progres, serta penghapusan berjenjang (cascade delete) beserta berkas fisiknya.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Model Surat Permohonan Pengadaan (PermohonanPengadaan)
 *
 * menjadikan model ini titik awal alur pengadaan SIM-PBJ, yang menghubungkan unit pengusul, verifikasi berantai
 * (Staf Bidang 2 -> Perencanaan -> Wadir 2 & PPK awal), alokasi/split MAK, hingga pemetaan otomatis posisi tahap 1-8.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `permohonan_pengadaan` dengan primary key string `nomor_surat`.
 *
 * Contoh pemanggilan:
 * `$permohonan = PermohonanPengadaan::with(['unit', 'verifikasiParalel', 'paketPengadaan'])->findOrFail($nomorSurat);`
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
     * Penanda internal agar pembersihan kaskade dan pengembalian saldo MAK tidak dieksekusi lebih dari sekali dalam satu siklus hapus.
     */
    protected bool $cascadeCleaned = false;

    /**
     * Konstanta status siklus hidup surat permohonan pengadaan.
     */
    const STATUS_DIAJUKAN = 'diajukan';

    const STATUS_MENUNGGU_BIDANG_2 = 'menunggu_bidang_2';

    const STATUS_CEK_MAK_PERENCANAAN = 'cek_mak_perencanaan';

    const STATUS_MENUNGGU_ACC_WADIR_PPK = 'menunggu_acc_wadir_ppk';

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
        'dokumen_lampiran',
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
        'nomor_memo_cek_mak',
        'catatan_memo_cek_mak',
        'file_memo_cek_mak',
        'nomor_memo_ketersediaan_mak',
        'catatan_memo_ketersediaan_mak',
        'file_memo_ketersediaan_mak',
        'id_user_ppk_awal',
        'jenis_pengadaan_split',
    ];

    /**
     * mengonversi kolom pagu ke desimal 2 digit, dokumen_lampiran ke array JSON, dan kolom tanggal ke instance Carbon.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $permohonan->dokumen_lampiran atau $permohonan->estimasi_total_pagu.`
     */
    protected function casts(): array
    {
        return [
            'revisi_ke' => 'integer',
            'estimasi_total_pagu' => 'decimal:2',
            'dokumen_lampiran' => 'array',
            'tanggal_pengajuan' => 'datetime',
            'tanggal_pemecahan' => 'datetime',
            'tanggal_revisi' => 'datetime',
        ];
    }

    /**
     * melengkapi nilai default kolom wajib saat pembuatan record dan memastikan file serta relasi anak ikut dibersihkan saat record dihapus.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dijalankan otomatis oleh Eloquent pada event creating dan deleting.`
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
            // membuat atau mengambil unit TIK default bila seeder/tes membuat permohonan tanpa kode_unit agar FK units tidak gagal.
            if (empty($model->kode_unit)) {
                $unit = Unit::firstOrCreate(
                    ['kode_unit' => 'TIK'],
                    ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
                );
                $model->kode_unit = $unit->kode_unit;
            }
        });

        static::deleting(function (PermohonanPengadaan $model) {
            // memanggil cleanup di hook deleting juga agar penghapusan langsung via $permohonan->delete() tetap bersih tanpa yatim file/relasi.
            $model->cleanupCascadeRelationsAndFiles();
        });
    }

    /**
     * menyediakan accessor `status` sebagai alias dari kolom `status_permohonan`.
     *
     * @return string|null mengembalikan status permohonan saat ini atau null.
     *
     * Contoh pemanggilan:
     * `$status = $permohonan->status;`
     */
    public function getStatusAttribute(): ?string
    {
        return $this->attributes['status_permohonan'] ?? null;
    }

    /**
     * menyelaraskan assignment atribut `status` ke kolom basis data `status_permohonan`.
     *
     * @param  string|null  $value  menerima kode status permohonan baru.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->status = PermohonanPengadaan::STATUS_DISETUJUI;`
     */
    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status_permohonan'] = $value;
    }

    /**
     * menyediakan accessor `perihal` sebagai alias dari kolom `judul_pengadaan`.
     *
     * @return string|null mengembalikan judul pengadaan atau null.
     *
     * Contoh pemanggilan:
     * `$judul = $permohonan->perihal;`
     */
    public function getPerihalAttribute(): ?string
    {
        return $this->attributes['judul_pengadaan'] ?? null;
    }

    /**
     * menyelaraskan penulisan `perihal` ke `judul_pengadaan` sekaligus mengisi fallback `tujuan_pengadaan_ringkas` bila masih kosong.
     *
     * @param  string|null  $value  menerima teks perihal/judul pengadaan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->perihal = 'Pengadaan Server Lab Jaringan';`
     */
    public function setPerihalAttribute(?string $value): void
    {
        $this->attributes['judul_pengadaan'] = $value;
        if (empty($this->attributes['tujuan_pengadaan_ringkas'])) {
            $this->attributes['tujuan_pengadaan_ringkas'] = $value;
        }
    }

    /**
     * menyediakan accessor `file_surat_permohonan` yang membaca kolom `file_permohonan`.
     *
     * @return string|null mengembalikan path berkas surat permohonan di storage atau null.
     *
     * Contoh pemanggilan:
     * `$path = $permohonan->file_surat_permohonan;`
     */
    public function getFileSuratPermohonanAttribute(): ?string
    {
        return $this->attributes['file_permohonan'] ?? null;
    }

    /**
     * menyelaraskan penulisan `file_surat_permohonan` ke kolom basis data `file_permohonan`.
     *
     * @param  string|null  $value  menerima path relatif berkas surat usulan pada disk public.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->file_surat_permohonan = 'permohonan/surat-001.pdf';`
     */
    public function setFileSuratPermohonanAttribute(?string $value): void
    {
        $this->attributes['file_permohonan'] = $value;
    }

    /**
     * menyediakan mutator alias `asal_unit` untuk memastikan kolom `kode_unit` selalu terisi unit valid.
     *
     * @param  string|null  $value  menerima nama asal unit dari input lama jika `kode_unit` belum ditentukan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->asal_unit = 'Jurusan Teknik Informatika dan Komputer';`
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
     * memetakan alias `tujuan_ringkas` ke kolom `tujuan_pengadaan_ringkas` bila kolom utamanya masih kosong.
     *
     * @param  string|null  $value  menerima ringkasan tujuan pengadaan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->tujuan_ringkas = 'Pembaruan perangkat laboratorium';`
     */
    public function setTujuanRingkasAttribute(?string $value): void
    {
        if (empty($this->attributes['tujuan_pengadaan_ringkas'])) {
            $this->attributes['tujuan_pengadaan_ringkas'] = $value;
        }
    }

    /**
     * memetakan alias `file_pdf_srikandi` ke kolom `file_permohonan` untuk menjaga kompatibilitas form/tes terdahulu.
     *
     * @param  string|null  $value  menerima path file PDF Srikandi.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->file_pdf_srikandi = 'permohonan/srikandi.pdf';`
     */
    public function setFilePdfSrikandiAttribute(?string $value): void
    {
        if (empty($this->attributes['file_permohonan'])) {
            $this->attributes['file_permohonan'] = $value;
        }
    }

    /**
     * menyediakan accessor `anggaran_diajukan` sebagai alias numerik dari kolom `estimasi_total_pagu`.
     *
     * @return mixed mengembalikan nilai estimasi pagu atau 0 jika belum terisi.
     *
     * Contoh pemanggilan:
     * `$pagu = $permohonan->anggaran_diajukan;`
     */
    public function getAnggaranDiajukanAttribute(): mixed
    {
        return $this->attributes['estimasi_total_pagu'] ?? 0;
    }

    /**
     * menyelaraskan penulisan `anggaran_diajukan` langsung ke kolom `estimasi_total_pagu`.
     *
     * @param  mixed  $value  menerima nominal pagu usulan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->anggaran_diajukan = 45000000;`
     */
    public function setAnggaranDiajukanAttribute(mixed $value): void
    {
        $this->attributes['estimasi_total_pagu'] = $value;
    }

    /**
     * menghubungkan surat permohonan ke unit kerja pengusulnya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Unit.
     *
     * Contoh pemanggilan:
     * `$unit = $permohonan->unit;`
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'kode_unit', 'kode_unit');
    }

    /**
     * menghubungkan surat permohonan ke akun pengguna pemohon yang mengajukannya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$pengaju = $permohonan->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan permohonan ke akun PPK yang dipilih oleh Perencanaan untuk persetujuan awal usulan bersama Wadir 2.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User (PPK).
     *
     * Contoh pemanggilan:
     * `$ppk = $permohonan->ppkAwal;`
     */
    public function ppkAwal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppk_awal', 'id_user');
    }

    /**
     * menghubungkan surat hasil pemecahan (split) kembali ke surat permohonan induknya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PermohonanPengadaan induk.
     *
     * Contoh pemanggilan:
     * `$induk = $permohonanSplit->indukSplit;`
     */
    public function indukSplit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_induk_split', 'nomor_surat');
    }

    /**
     * mengambil seluruh surat pecahan yang diturunkan dari satu surat permohonan induk.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PermohonanPengadaan anak.
     *
     * Contoh pemanggilan:
     * `$pecahanList = $permohonanInduk->anakSplit;`
     */
    public function anakSplit(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_induk_split', 'nomor_surat');
    }

    /**
     * menautkan surat versi revisi ke nomor surat versi sebelumnya untuk membentuk rantai riwayat revisi.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PermohonanPengadaan asal revisi.
     *
     * Contoh pemanggilan:
     * `$versiLama = $permohonanRevisi->suratAsalRevisi;`
     */
    public function suratAsalRevisi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nomor_surat_revisi', 'nomor_surat');
    }

    /**
     * mengambil daftar surat versi revisi terbaru yang lahir dari surat usulan ini.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PermohonanPengadaan turunan revisi.
     *
     * Contoh pemanggilan:
     * `$versiBaru = $permohonanLama->suratHasilRevisi;`
     */
    public function suratHasilRevisi(): HasMany
    {
        return $this->hasMany(self::class, 'nomor_surat_revisi', 'nomor_surat');
    }

    /**
     * menghubungkan surat permohonan ke rekening MAK utama pembiayanya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model AnggaranMAK.
     *
     * Contoh pemanggilan:
     * `$mak = $permohonan->anggaranMak;`
     */
    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }

    /**
     * menghubungkan surat permohonan ke seluruh catatan keputusan verifikasi awal (Bidang 2, Perencanaan, Wadir 2, PPK).
     *
     * @return HasMany mengembalikan relasi HasMany ke model VerifikasiParalel.
     *
     * Contoh pemanggilan:
     * `$verifikasi = $permohonan->verifikasiParalel;`
     */
    public function verifikasiParalel(): HasMany
    {
        return $this->hasMany(VerifikasiParalel::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * menghubungkan surat permohonan ke daftar alokasi MAK guna melacak pembebanan dan pengembalian saldo anggaran.
     *
     * @return HasMany mengembalikan relasi HasMany ke model AlokasiMAK.
     *
     * Contoh pemanggilan:
     * `$alokasi = $permohonan->alokasiMak;`
     */
    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * menghubungkan surat permohonan ke paket-paket pengadaan yang didaftarkan oleh PPBJ dari usulan ini.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paketList = $permohonan->paketPengadaan;`
     */
    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * menyediakan relasi tunggal ke paket pengadaan utama untuk mempermudah pembacaan status tanpa iterasi koleksi.
     *
     * @return HasOne mengembalikan relasi HasOne ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paket = $permohonan->paket;`
     */
    public function paket(): HasOne
    {
        return $this->hasOne(PaketPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * mengecek apakah permohonan ini adalah surat hasil revisi dari usulan sebelumnya.
     *
     * @return bool mengembalikan true jika `nomor_surat_revisi` terisi.
     *
     * Contoh pemanggilan:
     * `if ($permohonan->isRevisi()) { ... }`
     */
    public function isRevisi(): bool
    {
        return ! empty($this->nomor_surat_revisi);
    }

    /**
     * mengecek apakah permohonan ini berasal dari pemecahan surat induk (split MAK atau split jenis).
     *
     * @return bool mengembalikan true jika `nomor_surat_induk_split` terisi.
     *
     * Contoh pemanggilan:
     * `if ($permohonan->isSplit()) { ... }`
     */
    public function isSplit(): bool
    {
        return ! empty($this->nomor_surat_induk_split);
    }

    /**
     * memeriksa apakah permohonan telah berstatus `disetujui` secara penuh.
     *
     * @return bool mengembalikan true bila `status_permohonan` bernilai `disetujui`.
     *
     * Contoh pemanggilan:
     * `if ($permohonan->isApproved()) { ... }`
     */
    public function isApproved(): bool
    {
        return $this->status_permohonan === self::STATUS_DISETUJUI;
    }

    /**
     * memeriksa apakah permohonan ditolak pada fase verifikasi awal.
     *
     * @return bool mengembalikan true bila `status_permohonan` bernilai `ditolak`.
     *
     * Contoh pemanggilan:
     * `if ($permohonan->isRejected()) { ... }`
     */
    public function isRejected(): bool
    {
        return $this->status_permohonan === self::STATUS_DITOLAK;
    }

    /**
     * memeriksa apakah permohonan sedang ditahan sambil menunggu ketersediaan dana MAK.
     *
     * @return bool mengembalikan true bila `status_permohonan` bernilai `pending_anggaran`.
     *
     * Contoh pemanggilan:
     * `if ($permohonan->isPendingAnggaran()) { ... }`
     */
    public function isPendingAnggaran(): bool
    {
        return $this->status_permohonan === self::STATUS_PENDING_ANGGARAN;
    }

    /**
     * menghitung posisi tahap siklus pengadaan (1 sampai 8) dari status teraktual di rantai relasi paket, BAST, dan pembayaran.
     *
     * @return int mengembalikan bilangan bulat 1..8 yang menggerakkan komponen stepper 8 siklus di UI tracking.
     *
     * Contoh pemanggilan:
     * `$tahap = $permohonan->hitungTahapSiklus();`
     */
    public function hitungTahapSiklus(): int
    {
        $statusPermohonan = strtolower((string) ($this->status_permohonan ?? $this->status ?? 'diajukan'));

        // mengevaluasi dari tahap terjauh (8) mundur ke tahap awal (1) agar progres tertinggi yang sudah tercapai selalu menang.
        if (in_array($statusPermohonan, ['selesai', 'sp2d_lunas', 'cair'], true)) {
            return 8;
        }

        $paket = $this->relationLoaded('paketPengadaan')
            ? ($this->paketPengadaan->first() ?? $this->paket)
            : ($this->paket ?? $this->paketPengadaan()->first());

        if ($paket) {
            $pembayaran = $paket->relationLoaded('pembayaran')
                ? $paket->pembayaran->first()
                : $paket->pembayaran()->first();

            if ($pembayaran) {
                $cair = $pembayaran->relationLoaded('transaksiPencairan')
                    ? $pembayaran->transaksiPencairan
                    : $pembayaran->transaksiPencairan()->first();

                if ($cair || in_array(strtolower((string) $pembayaran->status_pembayaran), ['lunas', 'selesai', 'cair'], true)) {
                    return 8;
                }

                return 7;
            }

            $penerimaan = $paket->relationLoaded('penerimaanBarang')
                ? $paket->penerimaanBarang->first()
                : $paket->penerimaanBarang()->first();

            if ($penerimaan && (! empty($penerimaan->nomor_bast) || ! empty($penerimaan->ttd_digital_bast))) {
                return 7;
            }

            $hasReminders = $paket->relationLoaded('reminders')
                ? $paket->reminders->isNotEmpty()
                : $paket->reminders()->exists();

            if ($penerimaan || $hasReminders || in_array(strtolower((string) $paket->status_paket), ['pengiriman', 'pemeriksaan_fisik', 'proses_penerimaan'], true)) {
                return 6;
            }

            $spk = $paket->relationLoaded('kontrakSpk')
                ? $paket->kontrakSpk
                : $paket->kontrakSpk()->first();

            // menganggap Belanja Langsung <=50jt yang sudah punya nomor kwitansi belanja setara Tahap 5 meski tanpa Kontrak/SPK.
            $isBelanjaLangsungSelesai = ($paket->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG || (float) ($paket->nilai_hps ?? 0) <= 50000000)
                && ! empty($paket->nomor_kwitansi_belanja);

            if ($spk || $isBelanjaLangsungSelesai || in_array(strtolower((string) $paket->status_paket), [
                'disetujui_ppk_siap_belanja_pp',
                'siap_upload_dokumen_ppbj',
                'spk_terbit',
                'belanja_langsung',
                'belanja_langsung_selesai',
                'kontrak',
                'surat_pesanan_terbit',
                'surat_kontrak_terbit',
            ], true)) {
                return 5;
            }

            $reviu = $paket->relationLoaded('reviuDokumen')
                ? $paket->reviuDokumen->first()
                : $paket->reviuDokumen()->first();

            if ($reviu || in_array(strtolower((string) $paket->status_paket), [
                'menunggu_approval_ppk',
                'reviu_dokumen',
                'reviu_dokumen_disetujui',
                'menunggu_reviu_pp',
                'menunggu_reviu_ppk',
                'perlu_perbaikan',
            ], true)) {
                return 4;
            }

            return 3;
        }

        // menahan status verifikasi awal di Tahap 2 agar pengisian kode MAK oleh Perencanaan sebelum persetujuan Wadir 2 tidak melompat ke Tahap 3.
        $tahap2Statuses = [
            'reviu_paralel',
            'menunggu_bidang_2',
            'cek_mak_perencanaan',
            'menunggu_acc_wadir_ppk',
            'pending_anggaran',
            'hold',
            'perlu_revisi',
            'revisi',
            'ditolak',
        ];

        if (in_array($statusPermohonan, $tahap2Statuses, true)) {
            return 2;
        }

        if (in_array($statusPermohonan, ['disetujui', 'diproses_ppbj', 'paket_terdaftar', 'disetujui_wadir2_dan_ppk'], true)) {
            return 3;
        }

        $hasVerifikasi = $this->relationLoaded('verifikasiParalel')
            ? $this->verifikasiParalel->isNotEmpty()
            : $this->verifikasiParalel()->exists();

        if ($hasVerifikasi) {
            return 2;
        }

        return 1;
    }

    /**
     * menyediakan accessor `current_tahap` agar nomor tahap siklus tersedia langsung saat model diserialisasi ke JSON tracking SPA.
     *
     * @return int mengembalikan nomor tahap siklus 1..8.
     *
     * Contoh pemanggilan:
     * `$step = $permohonan->current_tahap;`
     */
    public function getCurrentTahapAttribute(): int
    {
        return $this->hitungTahapSiklus();
    }

    /**
     * membatasi query permohonan agar peran pemohon biasa hanya melihat surat pengajuannya sendiri.
     *
     * @param  Builder  $query  menerima query builder Eloquent PermohonanPengadaan.
     * @param  User  $user  memeriksa peran pengguna aktif.
     * @return Builder mengembalikan query yang telah disaring berdasarkan kepemilikan `id_user` bila relevan.
     *
     * Contoh pemanggilan:
     * `PermohonanPengadaan::forUser($user)->latest('tanggal_pengajuan')->get();`
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole(User::ROLE_USER)) {
            return $query->where('id_user', $user->id_user);
        }

        return $query;
    }

    /**
     * menghapus permohonan beserta seluruh turunan relasi, pengembalian saldo MAK, dan berkas fisiknya secara atomik di dalam transaksi database.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->cascadeDelete();`
     */
    public function cascadeDelete(): void
    {
        DB::transaction(function () {
            $this->cleanupCascadeRelationsAndFiles();
            $this->delete();
        });
    }

    /**
     * membersihkan seluruh entitas anak dari hilir ke hulu (pembayaran -> BAST -> SPK -> paket -> verifikasi -> alokasi MAK) beserta file fisiknya sebelum baris induk dihapus.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$permohonan->cleanupCascadeRelationsAndFiles();`
     */
    public function cleanupCascadeRelationsAndFiles(): void
    {
        // memakai guard $cascadeCleaned karena metode ini bisa dipanggil dari cascadeDelete() lalu terpicu lagi oleh event deleting model.
        if ($this->cascadeCleaned) {
            return;
        }
        $this->cascadeCleaned = true;

        $nomorSurat = $this->nomor_surat;

        $paketList = PaketPengadaan::where('nomor_surat', $nomorSurat)->get();

        foreach ($paketList as $paket) {
            $nomorPaket = $paket->nomor_paket;

            $pembayaranList = Pembayaran::where('nomor_paket', $nomorPaket)->get();
            foreach ($pembayaranList as $pembayaran) {
                $nomorPembayaran = $pembayaran->nomor_pembayaran;

                MemoBayar::where('nomor_pembayaran', $nomorPembayaran)->delete();

                $pencairan = TransaksiPencairan::where('nomor_pembayaran', $nomorPembayaran)->first();
                if ($pencairan) {
                    if (! empty($pencairan->file_bukti_transfer) && Storage::disk('public')->exists($pencairan->file_bukti_transfer)) {
                        Storage::disk('public')->delete($pencairan->file_bukti_transfer);
                    }
                    $pencairan->delete();
                }

                $pembayaranFiles = [
                    $pembayaran->file_dokumen_penunjang,
                    $pembayaran->file_spp,
                    $pembayaran->file_kwitansi,
                    $pembayaran->file_invoice,
                ];
                foreach ($pembayaranFiles as $filePath) {
                    if (! empty($filePath) && Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }

                $pembayaran->delete();
            }

            $penerimaanList = PenerimaanBarang::where('nomor_paket', $nomorPaket)->get();
            foreach ($penerimaanList as $penerimaan) {
                $fotoList = DokumentasiPenerimaanBarang::where('id_penerimaan', $penerimaan->id_penerimaan)->get();
                foreach ($fotoList as $foto) {
                    if (! empty($foto->foto_dokumentasi) && Storage::disk('public')->exists($foto->foto_dokumentasi)) {
                        Storage::disk('public')->delete($foto->foto_dokumentasi);
                    }
                    $foto->delete();
                }

                if (! empty($penerimaan->file_bast_signed) && Storage::disk('public')->exists($penerimaan->file_bast_signed)) {
                    Storage::disk('public')->delete($penerimaan->file_bast_signed);
                }
                if (! empty($penerimaan->file_penerimaan_pihak3) && Storage::disk('public')->exists($penerimaan->file_penerimaan_pihak3)) {
                    Storage::disk('public')->delete($penerimaan->file_penerimaan_pihak3);
                }

                $penerimaan->delete();
            }

            $kontrak = KontrakSPK::where('nomor_paket', $nomorPaket)->first();
            if ($kontrak) {
                if (! empty($kontrak->file_spk) && Storage::disk('public')->exists($kontrak->file_spk)) {
                    Storage::disk('public')->delete($kontrak->file_spk);
                }
                $kontrak->delete();
            }

            ReviuDokumen::where('nomor_paket', $nomorPaket)->delete();

            $reminderList = Reminder::where('nomor_paket', $nomorPaket)->get();
            foreach ($reminderList as $reminder) {
                NotifikasiUser::where('id_reminder', $reminder->id_reminder)->delete();
                $reminder->delete();
            }

            if (! empty($paket->file_dokumen_pengadaan) && Storage::disk('public')->exists($paket->file_dokumen_pengadaan)) {
                Storage::disk('public')->delete($paket->file_dokumen_pengadaan);
            }
            if (! empty($paket->file_kwitansi_belanja) && Storage::disk('public')->exists($paket->file_kwitansi_belanja)) {
                Storage::disk('public')->delete($paket->file_kwitansi_belanja);
            }

            $paket->delete();
        }

        // melepas tautan split/revisi pada surat turunan lebih dulu agar self-referencing FK tidak menghalangi penghapusan surat induk.
        self::where('nomor_surat_induk_split', $nomorSurat)->update(['nomor_surat_induk_split' => null]);
        self::where('nomor_surat_revisi', $nomorSurat)->update(['nomor_surat_revisi' => null]);

        VerifikasiParalel::where('nomor_surat', $nomorSurat)->delete();

        // memulihkan sisa_pagu_mak sebelum menghapus baris alokasi agar pembatalan permohonan tidak meninggalkan saldo MAK menggantung.
        $alokasiList = AlokasiMAK::where('nomor_surat', $nomorSurat)->get();
        foreach ($alokasiList as $alokasi) {
            if ($mak = AnggaranMAK::find($alokasi->kode_mak)) {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + (float) $alokasi->nominal_alokasi;
                $mak->save();
            }
            $alokasi->delete();
        }

        $permohonanFiles = [
            $this->file_permohonan,
            $this->file_kak,
            $this->file_spesifikasi_teknis,
        ];

        if (! empty($this->dokumen_lampiran) && is_array($this->dokumen_lampiran)) {
            foreach ($this->dokumen_lampiran as $doc) {
                if (! empty($doc['path'])) {
                    $permohonanFiles[] = $doc['path'];
                }
            }
        }

        foreach ($permohonanFiles as $filePath) {
            if (! empty($filePath) && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }

    /**
     * menormalkan daftar dokumen lampiran (baik dari kolom JSON `dokumen_lampiran` maupun 3 kolom file lawas) menjadi format seragam untuk modal pratinjau.
     *
     * @return array<int, array{name: string, path: string, url: string, type: string, colorClass: string, size: string, label: string}> mengembalikan array daftar lampiran lengkap dengan kelas warna badge tipe berkas.
     *
     * Contoh pemanggilan:
     * `$docs = $permohonan->dokumen_lampiran_list;`
     */
    public function getDokumenLampiranListAttribute(): array
    {
        if (! empty($this->dokumen_lampiran) && is_array($this->dokumen_lampiran)) {
            return array_map(function ($doc) {
                $ext = strtoupper($doc['type'] ?? pathinfo($doc['path'] ?? '', PATHINFO_EXTENSION) ?: 'PDF');
                $badgeColor = 'bg-[#0969da]';
                if ($ext === 'PDF') {
                    $badgeColor = 'bg-rose-600';
                } elseif (in_array($ext, ['XLS', 'XLSX', 'CSV'], true)) {
                    $badgeColor = 'bg-emerald-600';
                } elseif (in_array($ext, ['PNG', 'JPG', 'JPEG', 'WEBP'], true)) {
                    $badgeColor = 'bg-sky-500';
                }

                $fileName = $doc['name'] ?? basename($doc['path'] ?? 'Dokumen.pdf');

                return [
                    'name' => $fileName,
                    'path' => $doc['path'] ?? '',
                    'url' => ! empty($doc['url']) ? $doc['url'] : asset('storage/'.($doc['path'] ?? '')),
                    'type' => $ext,
                    'colorClass' => $badgeColor,
                    'size' => $doc['size'] ?? 'Berkas',
                    'label' => $fileName,
                ];
            }, $this->dokumen_lampiran);
        }

        // menyediakan fallback ke kolom file tunggal terdahulu supaya data lama sebelum kolom JSON dokumen_lampiran tetap tampil di UI.
        $list = [];
        if (! empty($this->file_surat_permohonan)) {
            $name = basename($this->file_surat_permohonan);
            $list[] = [
                'name' => $name,
                'path' => $this->file_surat_permohonan,
                'url' => asset('storage/'.$this->file_surat_permohonan),
                'type' => 'PDF',
                'colorClass' => 'bg-rose-600',
                'size' => 'Berkas',
                'label' => $name,
            ];
        }
        if (! empty($this->file_kak)) {
            $name = basename($this->file_kak);
            $list[] = [
                'name' => $name,
                'path' => $this->file_kak,
                'url' => asset('storage/'.$this->file_kak),
                'type' => 'PDF',
                'colorClass' => 'bg-rose-600',
                'size' => 'Berkas',
                'label' => $name,
            ];
        }
        if (! empty($this->file_spesifikasi_teknis)) {
            $name = basename($this->file_spesifikasi_teknis);
            $list[] = [
                'name' => $name,
                'path' => $this->file_spesifikasi_teknis,
                'url' => asset('storage/'.$this->file_spesifikasi_teknis),
                'type' => 'PDF',
                'colorClass' => 'bg-rose-600',
                'size' => 'Berkas',
                'label' => $name,
            ];
        }

        return $list;
    }

    /**
     * mengumpulkan seluruh foto dokumentasi fisik barang dari paket terkait agar dapat ditampilkan pada mode layar penuh tracking permohonan.
     *
     * @return array<int, array{url: string, path: string, keterangan: string|null, nomor_bast: string|null, tanggal: string|null}> mengembalikan daftar foto dokumentasi barang lengkap dengan nomor BAST.
     *
     * Contoh pemanggilan:
     * `$fotos = $permohonan->foto_dokumentasi_barang_list;`
     */
    public function getFotoDokumentasiBarangListAttribute(): array
    {
        $paketList = $this->relationLoaded('paketPengadaan')
            ? $this->paketPengadaan
            : $this->paketPengadaan()->with('penerimaanBarang.fotoDokumentasi')->get();

        $fotoList = [];

        foreach ($paketList as $paket) {
            $penerimaanList = $paket->penerimaanBarang;
            foreach ($penerimaanList as $penerimaan) {
                foreach ($penerimaan->fotoDokumentasi as $foto) {
                    $fotoList[] = [
                        'url' => asset('storage/'.$foto->foto_dokumentasi),
                        'path' => $foto->foto_dokumentasi,
                        'keterangan' => $foto->keterangan_foto,
                        'nomor_bast' => $penerimaan->nomor_bast,
                        'tanggal' => $penerimaan->tanggal_penerimaan ? date('d/m/Y', strtotime($penerimaan->tanggal_penerimaan)) : null,
                    ];
                }
            }
        }

        return $fotoList;
    }

    /**
     * mengumpulkan seluruh bukti keuangan (kwitansi belanja langsung, bukti transfer SP2D, kwitansi termin, dan invoice) untuk halaman detail permohonan.
     *
     * @return array<int, array{url: string, path: string, label: string, nomor: string|null, nominal: float|null, tanggal: string|null, tipe: string}> mengembalikan daftar bukti pembayaran yang terurut per paket dan termin.
     *
     * Contoh pemanggilan:
     * `$bukti = $permohonan->bukti_pembayaran_list;`
     */
    public function getBuktiPembayaranListAttribute(): array
    {
        $paketList = $this->relationLoaded('paketPengadaan')
            ? $this->paketPengadaan
            : $this->paketPengadaan()->with(['pembayaran.transaksiPencairan'])->get();

        $buktiList = [];

        foreach ($paketList as $paket) {
            if ($paket->file_kwitansi_belanja) {
                $buktiList[] = [
                    'url' => asset('storage/'.$paket->file_kwitansi_belanja),
                    'path' => $paket->file_kwitansi_belanja,
                    'label' => 'Kwitansi Belanja Langsung',
                    'nomor' => $paket->nomor_kwitansi_belanja,
                    'nominal' => (float) ($paket->nominal_harga_akhir ?: $paket->nilai_hps),
                    'tanggal' => $paket->tanggal_eksekusi_belanja ? date('d/m/Y', strtotime($paket->tanggal_eksekusi_belanja)) : null,
                    'tipe' => 'kwitansi',
                ];
            }

            $pembayaranList = $paket->relationLoaded('pembayaran')
                ? $paket->pembayaran
                : $paket->pembayaran()->with('transaksiPencairan')->get();

            foreach ($pembayaranList as $pembayaran) {
                if ($pembayaran->transaksiPencairan && $pembayaran->transaksiPencairan->file_bukti_transfer) {
                    $buktiList[] = [
                        'url' => asset('storage/'.$pembayaran->transaksiPencairan->file_bukti_transfer),
                        'path' => $pembayaran->transaksiPencairan->file_bukti_transfer,
                        'label' => 'Bukti Transfer Bank',
                        'nomor' => $pembayaran->transaksiPencairan->nomor_transaksi_bank,
                        'nominal' => (float) $pembayaran->transaksiPencairan->nominal_transfer,
                        'tanggal' => $pembayaran->transaksiPencairan->tanggal_transfer ? date('d/m/Y', strtotime($pembayaran->transaksiPencairan->tanggal_transfer)) : null,
                        'tipe' => 'sp2d',
                    ];
                }

                if ($pembayaran->file_kwitansi) {
                    $buktiList[] = [
                        'url' => asset('storage/'.$pembayaran->file_kwitansi),
                        'path' => $pembayaran->file_kwitansi,
                        'label' => 'Kwitansi Pembayaran Termin '.$pembayaran->tahap_termin,
                        'nomor' => $pembayaran->nomor_kwitansi,
                        'nominal' => (float) $pembayaran->nominal_pengajuan,
                        'tanggal' => $pembayaran->tanggal_pengajuan ? date('d/m/Y', strtotime($pembayaran->tanggal_pengajuan)) : null,
                        'tipe' => 'kwitansi',
                    ];
                }

                if ($pembayaran->file_invoice) {
                    $buktiList[] = [
                        'url' => asset('storage/'.$pembayaran->file_invoice),
                        'path' => $pembayaran->file_invoice,
                        'label' => 'Invoice Rekanan Penyedia',
                        'nomor' => $pembayaran->nomor_invoice,
                        'nominal' => (float) $pembayaran->nominal_pengajuan,
                        'tanggal' => $pembayaran->tanggal_pengajuan ? date('d/m/Y', strtotime($pembayaran->tanggal_pengajuan)) : null,
                        'tipe' => 'invoice',
                    ];
                }
            }
        }

        return $buktiList;
    }
}
