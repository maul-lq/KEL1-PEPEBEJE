<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Reviu Dokumen (Reviu Dokumen Persiapan Pengadaan)
 *
 * Merepresentasikan pencatatan hasil telaah/reviu berkas persiapan pengadaan (HPS, spesifikasi teknis,
 * rancangan kontrak) oleh Pejabat Pengadaan (PP) atau Pejabat Pembuat Komitmen (PPK).
 * Melacak siklus revisi (putaran_ke), peran reviewer, status reviu (draft, perlu_revisi, disetujui),
 * catatan perbaikan, serta stempel waktu pelaksanaan reviu.
 */
class ReviuDokumen extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'reviu_dokumen';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PERLU_REVISI = 'perlu_revisi';

    public const STATUS_DISETUJUI = 'disetujui';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_reviu';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_paket',
        'id_user',
        'putaran_ke',
        'role_reviewer',
        'status_reviu',
        'catatan_perbaikan',
        'tanggal_reviu',
    ];

    /**
     * Konfigurasi type casting untuk angka dan timestamp reviu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'putaran_ke' => 'integer',
            'tanggal_reviu' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang direviu.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke User pejabat penelaah/reviewer (PP atau PPK).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
