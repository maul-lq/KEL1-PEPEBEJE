<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Foto Dokumentasi Penerimaan Barang (Dokumentasi Penerimaan Barang)
 *
 * Merepresentasikan berkas foto fisik barang yang diterima di gudang/lapangan oleh unit Perlengkapan.
 * Menjadi bukti visual autentik kondisi fisik barang, spesifikasi teknis, atau kemasan sebelum
 * Berita Acara Serah Terima (BAST) ditandatangani.
 */
class DokumentasiPenerimaanBarang extends Model
{
    use HasFactory;

    /**
     * Nama tabel relasi dokumentasi penerimaan barang.
     *
     * @var string
     */
    protected $table = 'penerimaan_barang_foto_dokumentasi';

    /**
     * Nama primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_foto';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_penerimaan',
        'foto_dokumentasi',
        'keterangan_foto',
        'uploaded_at',
    ];

    /**
     * Konfigurasi type casting atribut waktu unggah foto.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke Penerimaan Barang.
     * Mengaitkan berkas foto dengan berkas pencatatan penerimaan fisik barang terkait.
     *
     * @return BelongsTo Relasi ke model PenerimaanBarang
     */
    public function penerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'id_penerimaan', 'id_penerimaan');
    }
}
