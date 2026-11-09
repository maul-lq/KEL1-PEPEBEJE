<?php

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
     * Penanda bahwa proses pembersihan cascade telah dilakukan untuk menghindari eksekusi ganda.
     */
    protected bool $cascadeCleaned = false;

    /**
     * Konstanta status siklus hidup surat permohonan pengadaan.
     * Mencakup alur berantai terbaru: menunggu_bidang_2 -> cek_mak_perencanaan -> menunggu_acc_wadir_ppk -> disetujui -> diproses_ppbj -> selesai.
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

        static::deleting(function (PermohonanPengadaan $model) {
            $model->cleanupCascadeRelationsAndFiles();
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
     * Accessor untuk alias perihal yang merujuk ke kolom judul_pengadaan.
     */
    public function getPerihalAttribute(): ?string
    {
        return $this->attributes['judul_pengadaan'] ?? null;
    }

    /**
     * Mutator untuk alias perihal yang menyelaraskan ke kolom judul_pengadaan.
     */
    public function setPerihalAttribute(?string $value): void
    {
        $this->attributes['judul_pengadaan'] = $value;
        if (empty($this->attributes['tujuan_pengadaan_ringkas'])) {
            $this->attributes['tujuan_pengadaan_ringkas'] = $value;
        }
    }

    /**
     * Accessor untuk alias file_surat_permohonan yang merujuk ke kolom file_permohonan.
     */
    public function getFileSuratPermohonanAttribute(): ?string
    {
        return $this->attributes['file_permohonan'] ?? null;
    }

    /**
     * Mutator untuk alias file_surat_permohonan yang menyelaraskan ke kolom file_permohonan.
     */
    public function setFileSuratPermohonanAttribute(?string $value): void
    {
        $this->attributes['file_permohonan'] = $value;
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
     * Accessor untuk alias anggaran_diajukan yang merujuk ke estimasi_total_pagu.
     */
    public function getAnggaranDiajukanAttribute(): mixed
    {
        return $this->attributes['estimasi_total_pagu'] ?? 0;
    }

    /**
     * Mutator untuk alias anggaran_diajukan yang menyelaraskan ke estimasi_total_pagu.
     */
    public function setAnggaranDiajukanAttribute(mixed $value): void
    {
        $this->attributes['estimasi_total_pagu'] = $value;
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
     * Relasi many-to-one ke akun PPK (1 dari 4 akun PPK) yang dipilih oleh Perencanaan
     * untuk memberikan persetujuan awal usulan berdampingan dengan Wadir 2.
     *
     * @return BelongsTo Relasi ke model User (role ppk)
     */
    public function ppkAwal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppk_awal', 'id_user');
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
     * Menghitung posisi tahap siklus pengadaan (1 s.d. 8) secara akurat berdasarkan
     * relasi database dan status progres operasional yang tercatat.
     *
     * 1: Pengajuan Usulan Srikandi (Unit Pemohon)
     * 2: Persetujuan 1 (Verifikasi Berantai: Bidang 2, Perencanaan, Wadir 2 & PPK)
     * 3: Penyusunan Dokumen & Level Anggaran (Alokasi MAK & Registrasi Paket PPBJ)
     * 4: Persetujuan 2 (Kondisional: Reviu Dokumen & HPS oleh PP/PPK)
     * 5: Pembelian & Pembuatan Surat Kontrak (SPK / Kontrak / Kwitansi Belanja Langsung)
     * 6: Penerimaan Barang/Jasa (Pengiriman & Pemeriksaan Fisik di Gudang)
     * 7: Pembayaran (Penerbitan BAST & Pengajuan SPP)
     * 8: Selesai (Pencairan Dana SP2D Lunas)
     *
     * @return int Nomor tahap siklus aktif (1 - 8)
     */
    public function hitungTahapSiklus(): int
    {
        $statusPermohonan = strtolower((string) ($this->status_permohonan ?? $this->status ?? 'diajukan'));

        // Jika status permohonan sudah final selesai
        if (in_array($statusPermohonan, ['selesai', 'sp2d_lunas', 'cair'], true)) {
            return 8;
        }

        // Cek paket pengadaan terkait
        $paket = $this->relationLoaded('paketPengadaan')
            ? ($this->paketPengadaan->first() ?? $this->paket)
            : ($this->paket ?? $this->paketPengadaan()->first());

        if ($paket) {
            // Tahap 8: Cek Pembayaran Selesai / Pencairan SP2D Lunas
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

                // Tahap 7: Berkas pembayaran diajukan ke Keuangan
                return 7;
            }

            // Tahap 7: BAST resmi sudah terbit dari Bagian Perlengkapan
            $penerimaan = $paket->relationLoaded('penerimaanBarang')
                ? $paket->penerimaanBarang->first()
                : $paket->penerimaanBarang()->first();

            if ($penerimaan && (! empty($penerimaan->nomor_bast) || ! empty($penerimaan->ttd_digital_bast))) {
                return 7;
            }

            // Tahap 6: Pemeriksaan fisik barang di gudang / pengiriman / pengingat rekanan
            $hasReminders = $paket->relationLoaded('reminders')
                ? $paket->reminders->isNotEmpty()
                : $paket->reminders()->exists();

            if ($penerimaan || $hasReminders || in_array(strtolower((string) $paket->status_paket), ['pengiriman', 'pemeriksaan_fisik', 'proses_penerimaan'], true)) {
                return 6;
            }

            // Tahap 5: Dokumen perikatan terbit (Kontrak / SPK / Surat Pesanan) atau Kwitansi Belanja Langsung (<= 50jt)
            $spk = $paket->relationLoaded('kontrakSpk')
                ? $paket->kontrakSpk
                : $paket->kontrakSpk()->first();

            $isBelanjaLangsungSelesai = ($paket->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG || (float) ($paket->nilai_hps ?? 0) <= 50000000)
                && ! empty($paket->nomor_kwitansi_belanja);

            if ($spk || $isBelanjaLangsungSelesai || in_array(strtolower((string) $paket->status_paket), ['spk_terbit', 'belanja_langsung_selesai', 'kontrak', 'surat_pesanan_terbit', 'surat_kontrak_terbit'], true)) {
                return 5;
            }

            // Tahap 4: Reviu dokumen teknis & evaluasi HPS oleh PP (50-200jt) atau PPK (> 200jt)
            $reviu = $paket->relationLoaded('reviuDokumen')
                ? $paket->reviuDokumen->first()
                : $paket->reviuDokumen()->first();

            if ($reviu || in_array(strtolower((string) $paket->status_paket), ['menunggu_approval_ppk', 'disetujui_ppk_siap_belanja_pp', 'siap_upload_dokumen_ppbj', 'reviu_dokumen', 'menunggu_reviu_pp', 'menunggu_reviu_ppk'], true)) {
                return 4;
            }

            // Tahap 3: Paket pengadaan sudah terdaftar di PPBJ
            return 3;
        }

        // Jika belum terbentuk paket pengadaan:
        // Cek Tahap 3: Permohonan sudah disetujui / MAK dialokasikan / siap diproses PPBJ
        $hasAlokasi = $this->relationLoaded('alokasiMak')
            ? $this->alokasiMak->isNotEmpty()
            : $this->alokasiMak()->exists();

        if (in_array($statusPermohonan, ['disetujui', 'diproses_ppbj', 'paket_terdaftar', 'disetujui_wadir2_dan_ppk'], true)
            || ! empty($this->kode_mak)
            || $hasAlokasi) {
            return 3;
        }

        // Cek Tahap 2: Sedang dalam telaah / verifikasi berantai / penelaahan MAK / minta revisi / ditolak
        $hasVerifikasi = $this->relationLoaded('verifikasiParalel')
            ? $this->verifikasiParalel->isNotEmpty()
            : $this->verifikasiParalel()->exists();

        if (in_array($statusPermohonan, [
            'reviu_paralel',
            'menunggu_bidang_2',
            'cek_mak_perencanaan',
            'menunggu_acc_wadir_ppk',
            'pending_anggaran',
            'hold',
            'perlu_revisi',
            'revisi',
            'ditolak',
        ], true) || $hasVerifikasi) {
            return 2;
        }

        // Default: Tahap 1 (Pengajuan baru oleh Unit Pemohon)
        return 1;
    }

    /**
     * Accessor atribut current_tahap yang secara reaktif menghitung nomor tahap siklus aktif.
     */
    public function getCurrentTahapAttribute(): int
    {
        return $this->hitungTahapSiklus();
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

    /**
     * Menghapus seluruh relasi dan berkas terkait secara kaskade (cascade deletion) tuntas dalam transaksi database.
     */
    public function cascadeDelete(): void
    {
        DB::transaction(function () {
            $this->cleanupCascadeRelationsAndFiles();
            $this->delete();
        });
    }

    /**
     * Membersihkan seluruh relasi anak dan berkas fisik pada storage public sebelum permohonan dihapus.
     */
    public function cleanupCascadeRelationsAndFiles(): void
    {
        if ($this->cascadeCleaned) {
            return;
        }
        $this->cascadeCleaned = true;

        $nomorSurat = $this->nomor_surat;

        // 1. Ambil seluruh paket pengadaan yang terhubung dengan permohonan ini
        $paketList = PaketPengadaan::where('nomor_surat', $nomorSurat)->get();

        foreach ($paketList as $paket) {
            $nomorPaket = $paket->nomor_paket;

            // a. Hapus Pembayaran dan relasi anaknya (MemoBayar, TransaksiPencairan, berkas)
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
                    $pembayaran->file_tagihan_vendor,
                    $pembayaran->file_dokumen_penunjang,
                    $pembayaran->file_memo,
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

            // b. Hapus PenerimaanBarang dan dokumentasi foto
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

            // c. Hapus KontrakSPK dan berkas
            $kontrak = KontrakSPK::where('nomor_paket', $nomorPaket)->first();
            if ($kontrak) {
                if (! empty($kontrak->file_spk) && Storage::disk('public')->exists($kontrak->file_spk)) {
                    Storage::disk('public')->delete($kontrak->file_spk);
                }
                $kontrak->delete();
            }

            // d. Hapus ReviuDokumen
            ReviuDokumen::where('nomor_paket', $nomorPaket)->delete();

            // f. Hapus Reminder dan NotifikasiUser
            $reminderList = Reminder::where('nomor_paket', $nomorPaket)->get();
            foreach ($reminderList as $reminder) {
                NotifikasiUser::where('id_reminder', $reminder->id_reminder)->delete();
                $reminder->delete();
            }

            // g. Bersihkan berkas paket dan hapus paket
            if (! empty($paket->file_dokumen_pengadaan) && Storage::disk('public')->exists($paket->file_dokumen_pengadaan)) {
                Storage::disk('public')->delete($paket->file_dokumen_pengadaan);
            }
            if (! empty($paket->file_kwitansi_belanja) && Storage::disk('public')->exists($paket->file_kwitansi_belanja)) {
                Storage::disk('public')->delete($paket->file_kwitansi_belanja);
            }

            $paket->delete();
        }

        // 2. Unlink permohonan anak hasil split / revisi
        self::where('nomor_surat_induk_split', $nomorSurat)->update(['nomor_surat_induk_split' => null]);
        self::where('nomor_surat_revisi', $nomorSurat)->update(['nomor_surat_revisi' => null]);

        // 3. Hapus VerifikasiParalel
        VerifikasiParalel::where('nomor_surat', $nomorSurat)->delete();

        // 4. Hapus AlokasiMAK dan kembalikan sisa pagu ke anggaran_mak
        $alokasiList = AlokasiMAK::where('nomor_surat', $nomorSurat)->get();
        foreach ($alokasiList as $alokasi) {
            if ($mak = AnggaranMAK::find($alokasi->kode_mak)) {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + (float) $alokasi->nominal_alokasi;
                $mak->save();
            }
            $alokasi->delete();
        }

        // 5. Bersihkan berkas permohonan
        $permohonanFiles = [
            $this->file_permohonan,
            $this->file_kak,
            $this->file_spesifikasi_teknis,
        ];
        foreach ($permohonanFiles as $filePath) {
            if (! empty($filePath) && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }
}
