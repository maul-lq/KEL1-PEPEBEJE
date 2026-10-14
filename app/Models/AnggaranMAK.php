<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Anggaran MAK (Mata Anggaran Kegiatan)
 *
 * Merepresentasikan data pagu anggaran, sisa dana, dan status penguncian (locking) akun MAK.
 * Model ini menegakkan Non-Functional Requirement (NFR) Integritas Data MAK:
 * Jika anggaran telah dikunci (misalnya saat Kontrak SPK terbit), maka data pokok anggaran
 * (uraian, pagu anggaran, sumber dana, tahun) dilarang keras diubah atau dihapus untuk
 * mencegah manipulasi atau ketidakkonsistenan pembukuan keuangan institusi.
 */
class AnggaranMAK extends Model
{
    use HasFactory;

    /**
     * Nama tabel database yang terhubung.
     *
     * @var string
     */
    protected $table = 'anggaran_mak';

    /**
     * Primary key tabel bukan id integer melainkan kode string MAK.
     *
     * @var string
     */
    protected $primaryKey = 'kode_mak';

    /**
     * Kunci utama tidak bersifat auto-incrementing.
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
     * Daftar kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode_mak',
        'uraian_mak',
        'pagu_anggaran',
        'sisa_pagu_mak',
        'sumber_dana',
        'tahun_anggaran',
        'catatan_mak',
        'is_locked',
        'tanggal_kunci',
        'id_user',
        'tanggal_penetapan',
    ];

    /**
     * Konfigurasi type casting atribut ke tipe data PHP/Carbon.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Format desimal presisi tinggi untuk data keuangan
            'pagu_anggaran' => 'decimal:2',
            'sisa_pagu_mak' => 'decimal:2',
            'tahun_anggaran' => 'integer',
            // Flag penguncian anggaran boolean
            'is_locked' => 'boolean',
            'tanggal_kunci' => 'datetime',
            'tanggal_penetapan' => 'datetime',
        ];
    }

    /**
     * Boot model Eloquent untuk mendaftarkan hook event siklus hidup model.
     * Menegakkan batasan NFR Integritas Data MAK secara otomatis pada level basis data/ORM.
     */
    protected static function booted(): void
    {
        // Hook event updating: cegah manipulasi nilai pagu/uraian jika anggaran sudah terkunci
        static::updating(function (AnggaranMAK $mak) {
            if ($mak->getOriginal('is_locked') && $mak->is_locked) {
                // Cegah pengubahan data inti anggaran jika sudah terkunci (NFR Integritas Data MAK)
                if ($mak->isDirty(['uraian_mak', 'pagu_anggaran', 'sumber_dana', 'tahun_anggaran'])) {
                    throw new Exception("Data MAK {$mak->kode_mak} telah dikunci permanen karena SPK telah terbit (NFR Integritas Data MAK)!");
                }
            }
        });

        // Hook event deleting: tolak penghapusan MAK yang sudah dalam kondisi terkunci
        static::deleting(function (AnggaranMAK $mak) {
            if ($mak->is_locked) {
                throw new Exception("Data MAK {$mak->kode_mak} tidak dapat dihapus karena telah dikunci permanen (NFR Integritas Data MAK)!");
            }
        });
    }

    /**
     * Memeriksa apakah anggaran MAK saat ini berada dalam status terkunci.
     *
     * @return bool True jika terkunci, false jika masih bebas diubah
     */
    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    /**
     * Mengunci anggaran MAK secara permanen dan mencatat timestamp penguncian.
     *
     * @return bool True jika penyimpanan status berhasil
     */
    public function lock(): bool
    {
        $this->is_locked = true;
        $this->tanggal_kunci = now();

        return $this->save();
    }

    /**
     * Relasi many-to-one ke User (petugas/staf perencanaan pembuat/penetap MAK).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi one-to-many ke Permohonan Pengadaan.
     * Menghubungkan seluruh usulan pengadaan yang membebankan anggarannya pada MAK ini.
     *
     * @return HasMany Koleksi usulan pengadaan terkait
     */
    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'kode_mak', 'kode_mak');
    }

    /**
     * Relasi one-to-many ke riwayat Alokasi MAK.
     * Menghubungkan log alokasi nominal permohonan terhadap pagu MAK ini.
     *
     * @return HasMany Koleksi riwayat alokasi permohonan
     */
    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'kode_mak', 'kode_mak');
    }
}
