<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model DokumentasiPenerimaanBarang ini untuk menyimpan berkas foto bukti fisik barang saat inspeksi serah terima di Unit Perlengkapan.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Foto Dokumentasi Penerimaan Barang (DokumentasiPenerimaanBarang)
 *
 * memakai model ini untuk mendukung multi-unggahan foto fisik barang yang tertaut ke satu berkas BAST
 * agar riwayat inspeksi tetap memiliki bukti visual yang dapat diaudit.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `penerimaan_barang_foto_dokumentasi`.
 *
 * Contoh pemanggilan:
 * `DokumentasiPenerimaanBarang::create(['id_penerimaan' => $bast->id_penerimaan, 'foto_dokumentasi' => $path]);`
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
    ];

    /**
     * menyediakan konfigurasi casts kosong secara eksplisit sebagai titik perluasan bila kelak ada metadata JSON/waktu tambahan.
     *
     * @return array<string, string> mengembalikan array kosong karena seluruh kolom bertipe skalar standar.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat menginisialisasi atribut model.`
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * mengaitkan setiap foto dokumentasi ke record induk `PenerimaanBarang` agar foto ikut terhapus saat cascade cleanup permohonan.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PenerimaanBarang.
     *
     * Contoh pemanggilan:
     * `$bast = $foto->penerimaanBarang;`
     */
    public function penerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'id_penerimaan', 'id_penerimaan');
    }
}
