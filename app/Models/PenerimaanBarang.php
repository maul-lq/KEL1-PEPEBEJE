<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model PenerimaanBarang ini untuk mencatat inspeksi fisik barang/jasa oleh Unit Perlengkapan, pembuatan token tanda tangan digital HMAC-SHA256 BAST, dan klasifikasi aset BMN.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Penerimaan Barang & BAST (PenerimaanBarang)
 *
 * memodelkan penerbitan Berita Acara Serah Terima (BAST) yang secara domain menjadi wewenang murni Unit Perlengkapan,
 * lengkap dengan metode kriptografis `generateSignatureToken` dan `verifyTtdDigital` untuk menjaga keabsahan dokumen.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `penerimaan_barang` dengan primary key `id_penerimaan`.
 *
 * Contoh pemanggilan:
 * `$token = PenerimaanBarang::generateSignatureToken($nomorBast, auth()->id());`
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

    public const KATEGORI_BELANJA_BARANG = 'belanja_barang';

    public const KATEGORI_BELANJA_MODAL = 'belanja_modal';

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
        'kategori_belanja',
        'ttd_digital_bast',
        'file_bast_signed',
        'file_penerimaan_pihak3',
        'catatan_pemeriksaan',
        'waktu_selesai_inspeksi',
    ];

    /**
     * mengonversi tanggal penerimaan dan stempel waktu inspeksi ke Carbon untuk pelaporan dan PDF BAST.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $bast->tanggal_penerimaan.`
     */
    protected function casts(): array
    {
        return [
            'tanggal_penerimaan' => 'date',
            'waktu_selesai_inspeksi' => 'datetime',
        ];
    }

    /**
     * menghubungkan BAST ke paket pengadaan induknya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paket = $bast->paket;`
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan BAST ke dokumen Kontrak/SPK terkait melalui kunci bersama `nomor_paket`.
     *
     * @return HasOne mengembalikan relasi HasOne ke model KontrakSPK.
     *
     * Contoh pemanggilan:
     * `$kontrak = $bast->kontrak;`
     */
    public function kontrak(): HasOne
    {
        return $this->hasOne(KontrakSPK::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan catatan penerimaan ke akun petugas Perlengkapan yang memeriksa barang.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$pemeriksa = $bast->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan satu record penerimaan ke banyak foto dokumentasi fisik barang.
     *
     * @return HasMany mengembalikan relasi HasMany ke model DokumentasiPenerimaanBarang.
     *
     * Contoh pemanggilan:
     * `$fotoList = $bast->fotoDokumentasi;`
     */
    public function fotoDokumentasi(): HasMany
    {
        return $this->hasMany(DokumentasiPenerimaanBarang::class, 'id_penerimaan', 'id_penerimaan');
    }

    /**
     * membangkitkan tanda tangan digital HMAC-SHA256 berbasiskan `app.key` agar keaslian BAST tidak dapat dipalsukan di database.
     *
     * @param  string  $nomorBast  menyertakan nomor BAST resmi ke dalam payload hash.
     * @param  int  $userId  mengikat ID petugas pemeriksa ke payload agar tanda tangan teratribusi ke penanda tangan sah.
     * @param  string|null  $timestamp  menerima stempel waktu ISO-8601 deterministik (atau `now()` bila tidak diberikan).
     * @return string mengembalikan string digest heksadesimal HMAC-SHA256 sepanjang 64 karakter.
     *
     * Contoh pemanggilan:
     * `$hash = PenerimaanBarang::generateSignatureToken('BAST/PNJ/2026/001', $user->id_user, $waktu);`
     */
    public static function generateSignatureToken(string $nomorBast, int $userId, ?string $timestamp = null): string
    {
        $time = $timestamp ?? now()->toIso8601String();
        $payload = "BAST:{$nomorBast}|PETUGAS:{$userId}|TIME:{$time}";

        // memakai app.key sebagai secret HMAC agar verifikasi integritas BAST hanya bisa dilakukan oleh server aplikasi.
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * memverifikasi integritas token tanda tangan digital BAST dengan perbandingan waktu konstan.
     *
     * @param  string  $expectedToken  menerima token HMAC pembanding hasil komputasi ulang.
     * @return bool mengembalikan true jika tanda tangan identik, atau false jika kosong/berbeda.
     *
     * Contoh pemanggilan:
     * `$valid = $bast->verifyTtdDigital($expectedHash);`
     */
    public function verifyTtdDigital(string $expectedToken): bool
    {
        if (empty($this->ttd_digital_bast)) {
            return false;
        }

        // menggunakan hash_equals() alih-alih operator '===' untuk mencegah serangan timing side-channel.
        return hash_equals($this->ttd_digital_bast, $expectedToken);
    }
}
