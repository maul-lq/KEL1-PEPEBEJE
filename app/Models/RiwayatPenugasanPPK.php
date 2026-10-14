<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Riwayat Penugasan PPK (Audit Trail Mutasi Pejabat Pembuat Komitmen)
 *
 * Merepresentasikan jejak audit rekam historis pengalihan wewenang penugasan paket pengadaan
 * dari satu akun PPK ke akun PPK lainnya (FR 2.2).
 * Menyimpan identitas PPK lama, PPK baru pengganti, user pihak ketiga yang melakukan mutasi
 * (misal PPBJ atau Wadir 2), alasan yuridis/operasional pengalihan, serta stempel waktu mutasi.
 */
class RiwayatPenugasanPPK extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'riwayat_penugasan_ppk';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_riwayat';

    /**
     * Tabel mengaktifkan timestamp bawaan Eloquent (created_at dan updated_at).
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_paket',
        'ppk_lama_user_id',
        'ppk_baru_user_id',
        'diubah_oleh_user_id',
        'alasan_perubahan',
        'tanggal_penugasan',
    ];

    /**
     * Konfigurasi type casting untuk stempel waktu penugasan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_penugasan' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang dialihkan.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke User akun PPK terdahulu sebelum dialihkan.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppkLama(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_lama_user_id', 'id_user');
    }

    /**
     * Relasi many-to-one ke User akun PPK baru yang menerima pengalihan paket.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppkBaru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_baru_user_id', 'id_user');
    }

    /**
     * Relasi many-to-one ke User pengubah / pengalih penugasan (staf PPBJ / pimpinan).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh_user_id', 'id_user');
    }
}
