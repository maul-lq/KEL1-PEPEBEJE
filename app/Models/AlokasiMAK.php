<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model AlokasiMAK ini untuk merekam keterkaitan nominal alokasi dana dari rekening MAK ke setiap surat permohonan pengadaan.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Alokasi Mata Anggaran Kegiatan (AlokasiMAK)
 *
 * menggunakan model ini untuk melacak pecahan atau alokasi nominal anggaran dari tabel `anggaran_mak`
 * ke surat usulan di `permohonan_pengadaan` sehingga sinkronisasi pengurangan dan pengembalian sisa pagu tetap akurat.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `alokasi_mak`.
 *
 * Contoh pemanggilan:
 * `AlokasiMAK::create(['nomor_surat' => $no, 'kode_mak' => $mak, 'nominal_alokasi' => 15000000, 'tanggal_alokasi' => now()]);`
 */
class AlokasiMAK extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang dihubungkan dengan model ini.
     *
     * @var string
     */
    protected $table = 'alokasi_mak';

    /**
     * Daftar kolom tabel yang diizinkan untuk diisi secara massal (mass assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_surat',
        'kode_mak',
        'nominal_alokasi',
        'tanggal_alokasi',
    ];

    /**
     * mengonversi kolom nominal ke desimal presisi 2 digit dan tanggal ke Carbon agar perhitungan selisih HPS bebas galat pembulatan float mentah.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $alokasi->nominal_alokasi atau $alokasi->tanggal_alokasi.`
     */
    protected function casts(): array
    {
        return [
            'nominal_alokasi' => 'decimal:2',
            'tanggal_alokasi' => 'datetime',
        ];
    }

    /**
     * mendefinisikan relasi balik ke permohonan pengadaan agar setiap alokasi dapat ditelusuri ke surat usulannya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo menuju model PermohonanPengadaan.
     *
     * Contoh pemanggilan:
     * `$surat = $alokasi->permohonan;`
     */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    /**
     * menghubungkan record alokasi ini ke master MAK sumber dana agar pemulihan saldo dan penguncian MAK mudah ditelusuri.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo menuju model AnggaranMAK.
     *
     * Contoh pemanggilan:
     * `$mak = $alokasi->anggaranMak;`
     */
    public function anggaranMak(): BelongsTo
    {
        return $this->belongsTo(AnggaranMAK::class, 'kode_mak', 'kode_mak');
    }
}
