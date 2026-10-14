<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontrakSPK extends Model
{
    use HasFactory;

    protected $table = 'kontrak_spk';

    protected $primaryKey = 'nomor_spk';

    public $incrementing = false;

    protected $keyType = 'string';

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

    protected static function booted(): void
    {
        static::saved(function (KontrakSPK $spk) {
            // Jika SPK terbit, picu penguncian permanen data MAK terkait (FR 6.4 & 11.2)
            if (in_array($spk->status_spk, ['terbit', 'aktif', 'selesai'])) {
                $paket = $spk->paket;
                if ($paket && $paket->permohonan && $paket->permohonan->anggaranMak) {
                    $paket->permohonan->anggaranMak->lock();
                }

                // Generate automatic reminder for delivery date if not already existing (FR 12.2)
                Reminder::firstOrCreate(
                    [
                        'nomor_paket' => $spk->nomor_paket,
                        'tipe_reminder' => 'pengiriman',
                    ],
                    [
                        'waktu_trigger' => $spk->jadwal_pengiriman->startOfDay(),
                        'is_recurring' => ($spk->sifat_kontrak === 'termin_bulanan' || $spk->sifat_kontrak === 'kontrak_tahunan'),
                        'interval_bulan' => ($spk->sifat_kontrak === 'termin_bulanan') ? 1 : null,
                        'judul_alert' => 'Jadwal Pengiriman Barang: ' . ($paket->nama_paket ?? $spk->nomor_spk),
                        'pesan_alert' => 'Pengingat otomatis jadwal pengiriman barang sesuai kontrak/SPK ' . $spk->nomor_spk,
                    ]
                );
            }
        });
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
