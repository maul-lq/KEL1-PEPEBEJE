<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model Reminder ini untuk menyimpan jadwal pengingat jatuh tempo dan pengingat berkala kontrak/pembayaran beserta relasi induk-kemunculannya.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Jadwal & Master Pengingat (Reminder)
 *
 * memodelkan baik jadwal pemicu aktif (`is_active = true`, `parent_reminder_id = null`)
 * maupun rekaman riwayat kemunculannya (`occurrences`) serta notifikasi instan di siklus PBJ.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `reminder` dengan primary key `id_reminder`.
 *
 * Contoh pemanggilan:
 * `$jadwal = Reminder::whereNull('parent_reminder_id')->where('is_active', true)->get();`
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

    public const TIPE_AKUN = 'akun';

    /**
     * Daftar tipe reminder terjadwal yang dieksekusi oleh ReminderSchedulerService agar notifikasi instan tidak ikut diproses ulang.
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
     * mengonversi jadwal pemicu ke Carbon, status berkala/aktif ke boolean, serta target_permissions ke array JSON.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $reminder->waktu_trigger atau $reminder->target_permissions.`
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
     * menyediakan fallback `target_role` agar test atau consumer lama yang masih membaca atribut ini tidak menerima null.
     *
     * @return string|null mengembalikan nilai kolom lama bila ada, atau 'keuangan' sebagai fallback default.
     *
     * Contoh pemanggilan:
     * `$role = $reminder->target_role;`
     */
    public function getTargetRoleAttribute(): ?string
    {
        return $this->attributes['target_role'] ?? 'keuangan';
    }

    /**
     * menerjemahkan interval bulan dan flag `is_recurring` menjadi label frekuensi berbahasa Indonesia di halaman notifikasi.
     *
     * @return string mengembalikan deskripsi frekuensi pengingat (sekali jalan, bulanan, atau tahunan).
     *
     * Contoh pemanggilan:
     * `$frekuensi = $reminder->frekuensi_label;`
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
     * memetakan konstanta `tipe_reminder` menjadi label kategori yang ramah pengguna pada tabel jadwal pengingat.
     *
     * @return string mengembalikan label kategori jadwal pengingat.
     *
     * Contoh pemanggilan:
     * `$kategori = $reminder->kategori_jadwal_label;`
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
     * menghubungkan reminder ke paket pengadaan terkait (opsional bila pengingat bukan terikat paket).
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model PaketPengadaan.
     *
     * Contoh pemanggilan:
     * `$paket = $reminder->paket;`
     */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * menghubungkan reminder jatuh tempo ke berkas pembayaran tagihan terkait.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Pembayaran.
     *
     * Contoh pemanggilan:
     * `$tagihan = $reminder->pembayaran;`
     */
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'nomor_pembayaran', 'nomor_pembayaran');
    }

    /**
     * menghubungkan record kemunculan notifikasi (occurrence) kembali ke jadwal master induknya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model Reminder induk.
     *
     * Contoh pemanggilan:
     * `$induk = $occurrence->parent;`
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_reminder_id', 'id_reminder');
    }

    /**
     * mengambil seluruh riwayat kemunculan notifikasi yang dibangkitkan dari satu jadwal master berkala.
     *
     * @return HasMany mengembalikan relasi HasMany ke model Reminder anak.
     *
     * Contoh pemanggilan:
     * `$riwayatTrigger = $schedule->occurrences;`
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(self::class, 'parent_reminder_id', 'id_reminder');
    }

    /**
     * menghubungkan master reminder ke seluruh notifikasi personal penerima di tabel `notifikasi_user`.
     *
     * @return HasMany mengembalikan relasi HasMany ke model NotifikasiUser.
     *
     * Contoh pemanggilan:
     * `$penerimaList = $reminder->notifikasiUser;`
     */
    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_reminder', 'id_reminder');
    }
}
