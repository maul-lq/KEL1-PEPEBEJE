<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Pengingat / Alarm Sistem (Reminder)
 *
 * Merepresentasikan jadwal pengingat otomatis (FR 12.2) dalam ekosistem SIM-PBJ:
 * - Pengingat H-2 jadwal verifikasi permohonan pengadaan untuk mencegah bottleneck persetujuan.
 * - Pengingat jadwal pengiriman barang sesuai kontrak SPK (baik pengiriman tunggal maupun termin berkala).
 * - Pengingat berulang (recurring) untuk kontrak tahunan atau termin bulanan.
 * Bertindak sebagai sumber pemicu dibuatnya rekaman notifikasi personal pada tabel `notifikasi_user`.
 */
class Reminder extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'reminder';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_reminder';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_paket',
        'tipe_reminder',
        'waktu_trigger',
        'is_recurring',
        'interval_bulan',
        'judul_alert',
        'pesan_alert',
    ];

    /**
     * Konfigurasi type casting untuk boolean, angka, dan waktu trigger.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu_trigger' => 'datetime',
            'is_recurring' => 'boolean',
            'interval_bulan' => 'integer',
        ];
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang terkait dengan jadwal alarm.
     *
     * @return BelongsTo Relasi ke model PaketPengadaan
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi one-to-many ke daftar notifikasi user yang dibangkitkan dari reminder ini.
     *
     * @return HasMany Koleksi notifikasi user
     */
    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_reminder', 'id_reminder');
    }
}
