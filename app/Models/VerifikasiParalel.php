<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Verifikasi Paralel (Reviu Tiga Arah Permohonan Pengadaan)
 *
 * Merepresentasikan pencatatan telaah dan keputusan verifikasi paralel simultan
 * terhadap suatu surat usulan permohonan pengadaan oleh tiga pihak independen:
 * 1. staff_bidang_2: Memeriksa kelayakan dan kelengkapan berkas fisik/administrasi awal (readonly/penyampai).
 * 2. perencanaan: Memverifikasi ketersediaan pagu pada Mata Anggaran Kegiatan (MAK).
 * 3. wadir_2: Pemegang hak veto mutlak penentu apakah usulan disetujui (disetujui) atau ditolak (ditolak).
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
        'tanggal_keputusan',
    ];

    /**
     * Konfigurasi type casting untuk stempel waktu keputusan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_keputusan' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke User verifikator (Staff Bidang 2, Perencanaan, atau Wadir 2).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi many-to-one ke Permohonan Pengadaan yang sedang ditelaah.
     *
     * @return BelongsTo Relasi ke model PermohonanPengadaan
     */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }
}
