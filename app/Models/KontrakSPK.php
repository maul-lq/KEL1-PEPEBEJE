<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Kontrak SPK (Surat Perintah Kerja / Kontrak Pengadaan)
 *
 * Merepresentasikan berkas legalitas perjanjian kerja atau kontrak pengadaan barang/jasa
 * antara institusi dan rekanan (vendor).
 * Model ini mengimplementasikan logika pemicu otomatis penting dalam sistem:
 * 1. Penguncian permanen data MAK (FR 6.4, FR 11.2, NFR Integritas Data MAK) saat status
 *    SPK diterbitkan/aktif agar anggaran tidak bisa dimanipulasi setelah komitmen dibuat.
 * 2. Pembuatan pengingat/alarm otomatis (Reminder) untuk tanggal jatuh tempo pengiriman barang (FR 12.2).
 */
class KontrakSPK extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'kontrak_spk';

    /**
     * Primary key tabel berupa nomor string unik SPK.
     *
     * @var string
     */
    protected $primaryKey = 'nomor_spk';

    /**
     * Kunci utama tidak menggunakan auto-increment.
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
        'nomor_spk',
        'nomor_paket',
        'id_user',
        'tipe_dokumen',
        'sifat_kontrak',
        'status_spk',
        'tanggal_spk',
        'tanggal_mulai',
        'tanggal_selesai',
        'jadwal_pengiriman',
        'nilai_kontrak',
        'file_spk',
    ];

    /**
     * Konversi tipe data otomatis (casting) untuk kolom tanggal dan nominal.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_spk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jadwal_pengiriman' => 'date',
            'nilai_kontrak' => 'decimal:2',
        ];
    }

    /**
     * Pendaftaran hook event model Eloquent.
     * Menjalankan efek samping bisnis otomatis saat rekaman Kontrak SPK disimpan.
     */
    protected static function booted(): void
    {
        static::saved(function (KontrakSPK $spk) {
            // Jika SPK terbit/aktif, picu penguncian permanen data MAK terkait (FR 6.4, FR 11.2, NFR Integritas Data MAK)
            if (in_array($spk->status_spk, ['terbit', 'aktif', 'selesai'])) {
                $paket = $spk->paket;
                if ($paket && $paket->permohonan) {
                    // Kunci MAK utama pada surat permohonan
                    if ($paket->permohonan->anggaranMak) {
                        $paket->permohonan->anggaranMak->lock();
                    }
                    // Kunci seluruh pecahan alokasi MAK jika usulan didukung multi-MAK
                    foreach ($paket->permohonan->alokasiMak ?? [] as $alokasi) {
                        $alokasi->mak?->lock();
                    }
                }

                // Buat rekaman pengingat otomatis (Reminder) jadwal pengiriman barang jika belum ada (FR 12.2)
                Reminder::firstOrCreate(
                    [
                        'nomor_paket' => $spk->nomor_paket,
                        'tipe_reminder' => 'pengiriman',
                    ],
                    [
                        'waktu_trigger' => $spk->jadwal_pengiriman->startOfDay(),
                        'is_recurring' => ($spk->sifat_kontrak === 'termin_bulanan' || $spk->sifat_kontrak === 'kontrak_tahunan'),
                        'interval_bulan' => ($spk->sifat_kontrak === 'termin_bulanan') ? 1 : null,
                        'judul_alert' => 'Jadwal Pengiriman Barang: '.($paket->nama_paket ?? $spk->nomor_spk),
                        'pesan_alert' => 'Pengingat otomatis jadwal pengiriman barang sesuai kontrak/SPK '.$spk->nomor_spk,
                    ]
                );
            }
        });
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang memayungi kontrak ini.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke User (PPK atau PP pembuat/penerbit dokumen SPK).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
