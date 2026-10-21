<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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

    public const TIPE_SEKALI_SELESAI = 'sekali_selesai';

    public const TIPE_LUMPSUM = 'lumpsum';

    public const TIPE_HARGA_SATUAN = 'harga_satuan';

    public const TIPE_TERMIN_BULANAN = 'termin_bulanan';

    public const TIPE_KONTRAK_TAHUNAN = 'kontrak_tahunan';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_TERBIT = 'terbit';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_SELESAI = 'selesai';

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
                if ($paket) {
                    $paket->kunciMakTerkait();
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

                // Distribusi tembusan otomatis SPK ke Unit Perlengkapan dan Bagian Keuangan (FR 11.3 & Notulensi ke-2)
                $reminderTembusan = Reminder::firstOrCreate(
                    [
                        'nomor_paket' => $spk->nomor_paket,
                        'tipe_reminder' => 'tembusan_spk',
                    ],
                    [
                        'waktu_trigger' => now(),
                        'is_recurring' => false,
                        'interval_bulan' => null,
                        'judul_alert' => 'Tembusan SPK Terbit: '.$spk->nomor_spk,
                        'pesan_alert' => "Dokumen Kontrak/SPK {$spk->nomor_spk} untuk paket {$spk->nomor_paket} senilai Rp ".number_format((float) $spk->nilai_kontrak, 0, ',', '.').' telah diterbitkan.',
                    ]
                );

                $targetRoles = ['perlengkapan', 'keuangan'];
                $penerimaTembusan = User::whereIn('role', $targetRoles)
                    ->where('status_aktif', true)
                    ->get();

                foreach ($penerimaTembusan as $targetUser) {
                    NotifikasiUser::firstOrCreate(
                        [
                            'id_user' => $targetUser->id_user,
                            'id_reminder' => $reminderTembusan->id_reminder,
                        ],
                        [
                            'is_read' => false,
                            'is_dismissed' => false,
                        ]
                    );
                }
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

    /**
     * Relasi one-to-one ke rekaman Penerimaan Barang / BAST (berdasarkan kesamaan nomor_paket).
     *
     * @return HasOne Relasi ke model PenerimaanBarang
     */
    public function penerimaan(): HasOne
    {
        return $this->hasOne(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Relasi has-one-through ke model Vendor melalui PaketPengadaan.
     *
     * @return HasOneThrough Relasi ke model Vendor
     */
    public function vendor(): HasOneThrough
    {
        return $this->hasOneThrough(
            Vendor::class,
            PaketPengadaan::class,
            'nomor_paket',
            'npwp',
            'nomor_paket',
            'npwp_vendor'
        );
    }

    /**
     * Scope query untuk membatasi akses Kontrak SPK sesuai hak akses peran pengguna (NFR RBAC PPK).
     *
     * @param  Builder  $query  Query builder Eloquent
     * @param  User  $user  Objek pengguna aktif
     * @return Builder Query terisolasi
     */
    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('paket', function ($q) use ($user) {
            $q->forUser($user);
        });
    }

    /**
     * Accessor alias nomor_kontrak_spk untuk nomor_spk.
     */
    public function getNomorKontrakSpkAttribute(): ?string
    {
        return $this->nomor_spk;
    }

    /**
     * Accessor alias kode_paket untuk nomor_paket.
     */
    public function getKodePaketAttribute(): ?string
    {
        return $this->nomor_paket;
    }

    /**
     * Accessor alias status_kontrak untuk status_spk.
     */
    public function getStatusKontrakAttribute(): ?string
    {
        return $this->status_spk;
    }

    /**
     * Mengambil model Vendor rekanan penyedia melalui relasi Paket Pengadaan.
     *
     * @return Vendor|null Objek vendor rekanan
     */
    public function getVendorAttribute(): ?Vendor
    {
        return $this->paket?->vendor;
    }
}
