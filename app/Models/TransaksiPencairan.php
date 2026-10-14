<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Transaksi Pencairan (Bukti Realisasi Pencairan Dana Keuangan)
 *
 * Merepresentasikan pencatatan transfer dana aktual dari kas institusi ke rekening bank vendor
 * oleh staf bagian Keuangan (FR 18.2).
 * Model ini mengimplementasikan logika otomatisasi pada event `created`:
 * Saat bukti transfer disimpan, status tagihan pembayaran otomatis berubah menjadi 'lunas',
 * serta status paket pengadaan dan surat permohonan pengadaan terkait otomatis ditandai 'selesai'.
 */
class TransaksiPencairan extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'transaksi_pencairan';

    /**
     * Primary key berupa nomor referensi/transaksi bank transfer.
     *
     * @var string
     */
    protected $primaryKey = 'nomor_transaksi_bank';

    /**
     * Kunci utama berupa string acak/kode perbankan, bukan auto-increment.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipe data primary key adalah string.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_transaksi_bank',
        'nomor_pembayaran',
        'id_user',
        'tanggal_transfer',
        'nominal_transfer',
        'file_bukti_transfer',
        'catatan_keuangan',
    ];

    /**
     * Konfigurasi type casting untuk waktu transfer dan nominal uang.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_transfer' => 'datetime',
            'nominal_transfer' => 'decimal:2',
        ];
    }

    /**
     * Pendaftaran hook event siklus hidup model Eloquent.
     * Mengotomatisasi penutupan siklus pengadaan setelah pembayaran resmi ditransfer.
     */
    protected static function booted(): void
    {
        static::created(function (TransaksiPencairan $cair) {
            // Pembaruan status pembayaran menjadi lunas dan kaskade status paket/permohonan menjadi selesai (FR 18.2)
            $pembayaran = $cair->pembayaran;
            if ($pembayaran) {
                // Tandai pembayaran saat ini lunas
                $pembayaran->status_pembayaran = 'lunas';
                $pembayaran->save();

                // Tandai paket pengadaan telah selesai seluruh prosesnya
                $paket = $pembayaran->paket;
                if ($paket) {
                    $paket->status_paket = 'selesai';
                    $paket->save();

                    // Tandai surat usulan permohonan awal pengadaan selesai paripurna
                    if ($paket->permohonan) {
                        $paket->permohonan->status_permohonan = 'selesai';
                        $paket->permohonan->save();
                    }
                }
            }
        });
    }

    /**
     * Relasi many-to-one ke tagihan Pembayaran yang dicairkan.
     *
     * @return BelongsTo Relasi ke model Pembayaran
     */
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * Relasi many-to-one ke User staf Keuangan pelaksana pencairan.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
