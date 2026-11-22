<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model VerifikasiParalel ini untuk mencatat reviu, memo fisik, dan keputusan setiap verifikator awal permohonan (Staf Bidang 2, Perencanaan, Wadir 2, dan PPK awal).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Catatan Verifikasi Berantai & Paralel (VerifikasiParalel)
 *
 * memakai model ini untuk merekam jejak keputusan setiap aktor verifikator awal beserta nomor memo,
 * berkas fisik memo, dan catatan alasan penolakan/revisi agar alur berantai menuju PPBJ dapat diaudit.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `verifikasi_paralel`.
 *
 * Contoh pemanggilan:
 * `VerifikasiParalel::updateOrCreate(['nomor_surat' => $no, 'role_verifikator' => 'wadir_2'], $payload);`
 */
class VerifikasiParalel extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'verifikasi_paralel';

    public const ROLE_STAFF_BIDANG_2 = 'staff_bidang_2';

    public const ROLE_PERENCANAAN = 'perencanaan';

    public const ROLE_WADIR_2 = 'wadir_2';

    public const ROLE_PPK = 'ppk';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_REVISI = 'revisi';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_user',
        'nomor_surat',
        'role_verifikator',
        'status_keputusan',
        'catatan_alasan',
        'nomor_memo',
        'file_memo',
        'tanggal_keputusan',
    ];

    /**
     * mengonversi stempel waktu `tanggal_keputusan` ke objek Carbon untuk pengurutan dan tampilan kronologis di modal tracking.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $verifikasi->tanggal_keputusan.`
     */
    protected function casts(): array
    {
        return [
            'tanggal_keputusan' => 'datetime',
        ];
    }

    /**
     * menghubungkan catatan verifikasi ke akun pejabat verifikator yang mengambil keputusan.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$verifikator = $verifikasi->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan catatan keputusan ini ke surat usulan permohonan pengadaan terkait.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PermohonanPengadaan.
     *
     * Contoh pemanggilan:
     * `$surat = $verifikasi->permohonan;`
     */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }
}
