<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model MemoBayar ini untuk mencatat persetujuan memo pembayaran paralel oleh Wadir 2 dan PPK beserta berkas fisik memonya.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Memo Persetujuan Pembayaran (MemoBayar)
 *
 * memodelkan setiap memo persetujuan pembayaran beserta nomor memo dan file fisik dokumennya
 * agar penolakan oleh Wadir 2 atau PPK dan seluruh bukti memo sah selalu terlihat jelas di antrean PPBJ dan pengaju.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `memo_bayar`.
 *
 * Contoh pemanggilan:
 * `MemoBayar::where('nomor_pembayaran', $noBayar)->where('role_approver', MemoBayar::ROLE_WADIR_2)->first();`
 */
class MemoBayar extends Model
{
    use HasFactory;

    /**
     * Nama tabel database memo bayar.
     *
     * @var string
     */
    protected $table = 'memo_bayar';

    public const ROLE_PPK = 'ppk';

    public const ROLE_WADIR_2 = 'wadir_2';

    public const ROLE_KEUANGAN = 'keuangan';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_user',
        'nomor_pembayaran',
        'role_approver',
        'status_memo',
        'catatan_memo',
        'nomor_memo',
        'file_memo',
        'tanggal_acc',
    ];

    /**
     * mengonversi kolom `tanggal_acc` ke objek Carbon agar waktu persetujuan memo mudah diformat di PDF dan antarmuka tabel.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $memo->tanggal_acc.`
     */
    protected function casts(): array
    {
        return [
            'tanggal_acc' => 'datetime',
        ];
    }

    /**
     * menghubungkan memo bayar ke akun pejabat (Wadir 2 atau PPK) yang menandatangani persetujuan memo.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$approver = $memo->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan memo ini ke berkas pengajuan pembayaran induknya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Pembayaran.
     *
     * Contoh pemanggilan:
     * `$tagihan = $memo->pembayaran;`
     */
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }
}
