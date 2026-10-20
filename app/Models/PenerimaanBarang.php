<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Penerimaan Barang (Berita Acara Serah Terima / BAST)
 *
 * Merepresentasikan proses inspeksi fisik dan serah terima hasil pengadaan barang/jasa
 * oleh petugas unit Perlengkapan. Menyimpan nomor BAST resmi, catatan inspeksi fisik,
 * foto-foto dokumentasi barang, tanda tangan digital (HMAC-SHA256) untuk keabsahan hukum,
 * serta lampiran berkas BAST bertanda tangan dan dokumen pihak ketiga/ekspedisi.
 */
class PenerimaanBarang extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'penerimaan_barang';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_penerimaan';

    public const STATUS_DITERIMA_LENGKAP = 'diterima_lengkap';

    public const STATUS_TERIMA = 'terima';

    public const STATUS_PENDING = 'pending';

    public const STATUS_TIDAK_SESUAI = 'tidak_sesuai';

    /**
     * Kolom-kolom yang dapat diisi secara massal (mass assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_bast',
        'nomor_paket',
        'id_user',
        'tanggal_penerimaan',
        'status_fisik',
        'ttd_digital_bast',
        'file_bast_signed',
        'file_penerimaan_pihak3',
        'catatan_pemeriksaan',
        'waktu_selesai_inspeksi',
    ];

    /**
     * Konfigurasi type casting untuk tanggal dan waktu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_penerimaan' => 'date',
            'waktu_selesai_inspeksi' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang diserahterimakan.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-one ke Kontrak SPK yang diserahterimakan melalui nomor paket.
     *
     * @return HasOne Relasi ke model KontrakSPK
     */
    public function kontrak(): HasOne
    {
        return $this->hasOne(KontrakSPK::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke User (petugas unit Perlengkapan pemeriksa fisik).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke foto-foto dokumentasi fisik barang yang diinspeksi.
     *
     * @return HasMany Koleksi foto dokumentasi barang
     */
    public function fotoDokumentasi(): HasMany
    {
        return $this->hasMany(DokumentasiPenerimaanBarang::class, 'id_penerimaan', 'id_penerimaan');
    }

    /**
     * Menghasilkan token tanda tangan digital kriptografis (HMAC-SHA256)
     * untuk menjamin integritas dan autentisitas dokumen BAST (NFR Keabsahan BAST & TTD Digital).
     *
     * @param  string  $nomorBast  Nomor resmi BAST
     * @param  int  $userId  ID petugas pemeriksa yang menandatangani
     * @param  string|null  $timestamp  Timestamp penandatanganan (default waktu sekarang)
     * @return string Token hash kriptografis yang dihasilkan
     */
    public static function generateSignatureToken(string $nomorBast, int $userId, ?string $timestamp = null): string
    {
        $time = $timestamp ?? now()->toIso8601String();
        $payload = "BAST:{$nomorBast}|PETUGAS:{$userId}|TIME:{$time}";

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * Memverifikasi keabsahan tanda tangan digital BAST terhadap token yang diharapkan
     * menggunakan fungsi pembandingan aman tahan serangan timing attack (hash_equals).
     *
     * @param  string  $expectedToken  Token yang diharapkan
     * @return bool True jika tanda tangan valid dan belum dimanipulasi
     */
    public function verifyTtdDigital(string $expectedToken): bool
    {
        if (empty($this->ttd_digital_bast)) {
            return false;
        }

        return hash_equals($this->ttd_digital_bast, $expectedToken);
    }
}
