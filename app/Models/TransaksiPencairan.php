<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model TransaksiPencairan ini untuk mencatat bukti transfer SP2D Keuangan dan mengotomatiskan penutupan status lunas/selesai pada pembayaran, paket, dan permohonan.
 */

namespace App\Models;

use App\Services\PengadaanRoutingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Transaksi Pencairan SP2D (TransaksiPencairan)
 *
 * memodelkan realisasi transfer dana dari kas PNJ ke rekening penyedia dan memasang hook `created`
 * agar pelunasan tagihan, penutupan status paket/permohonan, dan penghentian reminder berjalan atomik dan konsisten.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `transaksi_pencairan` dengan primary key string `nomor_transaksi_bank`.
 *
 * Contoh pemanggilan:
 * `TransaksiPencairan::create(['nomor_transaksi_bank' => $noTrx, 'nomor_pembayaran' => $noBayar, 'nominal_transfer' => $nilai]);`
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
     * mengonversi waktu transfer ke Carbon dan nominal transfer ke desimal 2 digit untuk rekonsiliasi laporan.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $cair->tanggal_transfer atau $cair->nominal_transfer.`
     */
    protected function casts(): array
    {
        return [
            'tanggal_transfer' => 'datetime',
            'nominal_transfer' => 'decimal:2',
        ];
    }

    /**
     * mengotomatiskan pelunasan pembayaran, penutupan status paket serta surat usulan menjadi 'selesai', dan penghentian reminder begitu bukti transfer tersimpan.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Terpicu otomatis oleh Eloquent pada event created setelah TransaksiPencairan::create(...).`
     */
    protected static function booted(): void
    {
        static::created(function (TransaksiPencairan $cair) {
            $pembayaran = $cair->pembayaran;
            if ($pembayaran) {
                // menandai pembayaran lunas dan langsung mematikan jadwal jatuh tempo pembayaran agar Keuangan tidak lagi menerima alarm tunggakan.
                $pembayaran->status_pembayaran = 'lunas';
                $pembayaran->save();
                PengadaanRoutingService::deactivateForPembayaran($pembayaran->nomor_pembayaran);

                // merambatkan penutupan status ke paket dan surat permohonan supaya stepper 8 siklus langsung mencapai tahap 8 (Selesai).
                $paket = $pembayaran->paket;
                if ($paket) {
                    $paket->status_paket = 'selesai';
                    $paket->save();
                    PengadaanRoutingService::deactivateForPaket($paket->nomor_paket);

                    if ($paket->permohonan) {
                        $paket->permohonan->status_permohonan = 'selesai';
                        $paket->permohonan->save();
                    }
                }
            }
        });
    }

    /**
     * menghubungkan bukti transaksi pencairan ke berkas pembayaran yang dilunasinya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Pembayaran.
     *
     * Contoh pemanggilan:
     * `$tagihan = $transaksi->pembayaran;`
     */
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * menghubungkan transaksi pencairan ke akun staf Keuangan yang memproses transfer bank.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$petugasKeuangan = $transaksi->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
