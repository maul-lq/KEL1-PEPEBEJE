<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model ReviuDokumen ini untuk mencatat riwayat hasil telaah dan perbaikan dokumen persiapan pengadaan oleh PP maupun PPK.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Reviu Dokumen Pengadaan (ReviuDokumen)
 *
 * menggunakan model ini untuk merekam tiap putaran reviu teknis oleh PP (50-200jt) atau PPK (>200jt & approval 50-200jt)
 * agar alasan permintaan perbaikan berkas ke PPBJ terdokumentasi rapi per putaran.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `reviu_dokumen` dengan primary key `id_reviu`.
 *
 * Contoh pemanggilan:
 * `ReviuDokumen::create(['nomor_paket' => $no, 'id_user' => $user->id_user, 'role_reviewer' => 'pp', 'status_reviu' => 'disetujui']);`
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
     * mengonversi nomor putaran reviu ke integer dan tanggal reviu ke Carbon untuk pengurutan kronologis.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $reviu->putaran_ke atau $reviu->tanggal_reviu.`
     */
    protected function casts(): array
    {
        return [
            'putaran_ke' => 'integer',
            'tanggal_reviu' => 'datetime',
        ];
    }

    /**
     * menghubungkan catatan reviu ke paket pengadaan yang ditelaah.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paket = $reviu->paket;`
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan catatan reviu ke akun pejabat penelaah (PP atau PPK).
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$penelaah = $reviu->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
