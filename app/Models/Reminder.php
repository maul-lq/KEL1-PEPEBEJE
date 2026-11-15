<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Pengingat / Alarm Sistem (Reminder)
 *
 * Merepresentasikan jadwal pengingat otomatis maupun pengingat berkala dalam ekosistem SIM-PBJ:
 * - Pengingat jadwal pengiriman barang (sekali jalan maupun berkala bulanan/tahunan sesuai sifat kontrak).
 * - Pengingat H-2 batas waktu pengiriman/termin dan H-2 / Hari-H jatuh tempo pembayaran tagihan.
 * - Histori pemicu notifikasi instan pada siklus pengadaan.
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

    public const ROLE_KEUANGAN = 'keuangan';

    public const ROLE_PPK = 'ppk';

    public const ROLE_PP = 'pp';

    public const ROLE_PERLENGKAPAN = 'perlengkapan';

    public const TIPE_PENGIRIMAN = 'pengiriman';

    public const TIPE_VERIFIKASI = 'verifikasi';

    public const TIPE_PEMBAYARAN = 'pembayaran';

    public const TIPE_H_MINUS_2_TERMIN = 'h_minus_2_termin';

    public const TIPE_JATUH_TEMPO_PEMBAYARAN_H2 = 'jatuh_tempo_pembayaran_h2';

    public const TIPE_JATUH_TEMPO_PEMBAYARAN = 'jatuh_tempo_pembayaran';

    /**
     * Tipe reminder yang diproses oleh mesin penjadwal otomatis (reminder berkala / jatuh tempo).
     *
     * @var array<int, string>
     */
    public const SCHEDULED_TYPES = [
        self::TIPE_PENGIRIMAN,
        self::TIPE_H_MINUS_2_TERMIN,
        self::TIPE_JATUH_TEMPO_PEMBAYARAN_H2,
        self::TIPE_JATUH_TEMPO_PEMBAYARAN,
    ];

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_paket',
        'nomor_pembayaran',
        'tipe_reminder',
        'waktu_trigger',
        'interval_bulan',
        'is_recurring',
        'is_active',
        'last_triggered_at',
        'parent_reminder_id',
        'target_permissions',
        'judul_alert',
        'pesan_alert',
    ];

    /**
     * Konfigurasi type casting untuk boolean, JSON permissions, angka, dan timestamp.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu_trigger' => 'datetime',
            'last_triggered_at' => 'datetime',
            'interval_bulan' => 'integer',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'target_permissions' => 'array',
        ];
    }

    /**
     * Accessor untuk atribut target_role demi kompatibilitas pengujian lama.
     */
    public function getTargetRoleAttribute(): ?string
    {
        return $this->attributes['target_role'] ?? 'keuangan';
    }

    /**
     * Label frekuensi pengingat yang mudah dipahami pada halaman jadwal pengingat.
     */
    public function getFrekuensiLabelAttribute(): string
    {
        if ($this->is_recurring && $this->interval_bulan) {
            return match ((int) $this->interval_bulan) {
                1 => 'Berkala Bulanan (Setiap 1 bulan)',
                12 => 'Berkala Tahunan (Setiap 12 bulan)',
                default => "Berkala (Setiap {$this->interval_bulan} bulan)",
            };
        }

        return 'Otomatis (Sekali pada tanggal target)';
    }

    /**
     * Label jenis pengingat pada daftar jadwal pengingat.
     */
    public function getKategoriJadwalLabelAttribute(): string
    {
        return match ($this->tipe_reminder) {
            self::TIPE_PENGIRIMAN => $this->is_recurring ? 'Pengingat Berkala Kontrak' : 'Jadwal Pengiriman Barang',
            self::TIPE_H_MINUS_2_TERMIN => 'H-2 Batas Pengiriman',
            self::TIPE_JATUH_TEMPO_PEMBAYARAN_H2 => 'H-2 Jatuh Tempo Tagihan',
            self::TIPE_JATUH_TEMPO_PEMBAYARAN => 'Jatuh Tempo Pembayaran',
            default => ucwords(str_replace('_', ' ', (string) $this->tipe_reminder)),
        };
    }

    /**
     * Relasi many-to-one ke Paket Pengadaan yang terkait dengan jadwal alarm.
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi many-to-one ke Pembayaran jika pengingat terkait jatuh tempo tagihan.
     */
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * Relasi ke jadwal induk apabila record ini merupakan kemunculan (occurrence) hasil pemicu berkala.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_reminder_id', 'id_reminder');
    }

    /**
     * Relasi one-to-many ke seluruh riwayat kemunculan notifikasi dari jadwal ini.
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(self::class, 'parent_reminder_id', 'id_reminder');
    }

    /**
     * Relasi one-to-many ke daftar notifikasi user yang dibangkitkan dari reminder ini.
     */
    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_reminder', 'id_reminder');
    }
}
