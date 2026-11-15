<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Verifikasi Paralel / Berantai Permohonan Pengadaan
 *
 * Merepresentasikan pencatatan telaah dan keputusan persetujuan awal terhadap suatu surat usulan
 * permohonan pengadaan sesuai alur berantai Notulensi PPBJ:
 * 1. staff_bidang_2: Memeriksa kelengkapan berkas awal dari User dan mengirim Memo Cek MAK ke Perencanaan.
 * 2. perencanaan: Memverifikasi/membuat Mata Anggaran Kegiatan (MAK), memilih 1 dari 4 PPK, & mengirim Memo Ketersediaan MAK.
 * 3. wadir_2: Menyetujui kegiatan (ACC kegiatan Bidang 2) atau menolak beserta alasan wajib.
 * 4. ppk: Pejabat Pembuat Komitmen terpilih (id_user_ppk_awal) yang menyetujui usulan awal bersama Wadir 2 sebelum lanjut ke PPBJ.
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
