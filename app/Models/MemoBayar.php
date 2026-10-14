<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Memo Bayar (Persetujuan Memo Pembayaran Berjenjang)
 *
 * Merepresentasikan tahapan persetujuan/verifikasi berjenjang pembayaran tagihan pengadaan
 * (misalnya Level 1 oleh PPK, Level 2 oleh Pejabat Keuangan / Wadir).
 * Menyimpan catatan verifikasi, status persetujuan (draft, diajukan, disetujui, ditolak),
 * dan stempel waktu persetujuan (tanggal_acc).
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
        'urutan_level',
        'status_memo',
        'catatan_memo',
        'tanggal_acc',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data numerik dan tanggal.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan_level' => 'integer',
            'tanggal_acc' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke User penyetujui (approver) memo pembayaran.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi many-to-one ke induk transaksi Pembayaran.
     *
     * @return BelongsTo Relasi ke model Pembayaran
     */
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }
}
