<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model AnggaranMAK ini untuk mengelola pagu DIPA, saldo tersisa, serta mekanisme penguncian (lock/unlock) guna menjaga integritas data anggaran.
 */

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Master Mata Anggaran Kegiatan (AnggaranMAK)
 *
 * menerapkan penjagaan integritas di level ORM pada model ini agar akun MAK yang berstatus terkunci (`is_locked = true`)
 * tidak dapat diubah nilai pagu/uraiannya maupun dihapus, kecuali dibuka kuncinya lebih dulu oleh Perencanaan.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `anggaran_mak` dengan primary key string `kode_mak`.
 *
 * Contoh pemanggilan:
 * `$mak = AnggaranMAK::findOrFail('521211.PNJ.2026');`
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
     * mendefinisikan casting desimal keuangan, tahun integer, flag boolean kunci, serta timestamp penetapan/kunci.
     *
     * @return array<string, string> mengembalikan peta cast tipe data atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat membaca $mak->is_locked atau $mak->sisa_pagu_mak.`
     */
    protected function casts(): array
    {
        return [
            'pagu_anggaran' => 'decimal:2',
            'sisa_pagu_mak' => 'decimal:2',
            'tahun_anggaran' => 'integer',
            'is_locked' => 'boolean',
            'tanggal_kunci' => 'datetime',
            'tanggal_penetapan' => 'datetime',
        ];
    }

    /**
     * mendaftarkan hook Eloquent `updating` dan `deleting` agar aturan penguncian MAK tetap ditegakkan dari mana pun model dimodifikasi.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `// Dijalankan otomatis oleh Eloquent saat AnggaranMAK pertama kali di-boot.`
     */
    protected static function booted(): void
    {
        static::updating(function (AnggaranMAK $mak) {
            // memeriksa baik nilai asli maupun nilai baru is_locked agar operasi unlock() sendiri tidak ikut diblokir, namun perubahan pagu/uraian saat terkunci ditolak keras.
            if ($mak->getOriginal('is_locked') && $mak->is_locked) {
                if ($mak->isDirty(['uraian_mak', 'pagu_anggaran', 'sumber_dana', 'tahun_anggaran'])) {
                    throw new Exception("Data MAK {$mak->kode_mak} telah dikunci permanen karena SPK telah terbit (NFR Integritas Data MAK)!");
                }
            }
        });

        static::deleting(function (AnggaranMAK $mak) {
            if ($mak->is_locked) {
                throw new Exception("Data MAK {$mak->kode_mak} tidak dapat dihapus karena telah dikunci permanen (NFR Integritas Data MAK)!");
            }
        });
    }

    /**
     * mengecek status penguncian MAK dalam bentuk boolean eksplisit.
     *
     * @return bool mengembalikan true jika MAK berstatus terkunci, atau false jika terbuka.
     *
     * Contoh pemanggilan:
     * `if ($mak->isLocked()) { abort(403); }`
     */
    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    /**
     * mengunci akun MAK beserta stempel waktu penguncian (otoritas tunggal peran Perencanaan).
     *
     * @return bool mengembalikan true apabila perubahan status kunci berhasil disimpan.
     *
     * Contoh pemanggilan:
     * `$mak->lock();`
     */
    public function lock(): bool
    {
        $this->is_locked = true;
        $this->tanggal_kunci = now();

        return $this->save();
    }

    /**
     * membuka kembali kunci akun MAK dan membersihkan `tanggal_kunci` agar dapat disesuaikan kembali oleh Perencanaan.
     *
     * @return bool mengembalikan true apabila status buka kunci berhasil disimpan.
     *
     * Contoh pemanggilan:
     * `$mak->unlock();`
     */
    public function unlock(): bool
    {
        $this->is_locked = false;
        $this->tanggal_kunci = null;

        return $this->save();
    }

    /**
     * menghubungkan MAK ke akun staf Perencanaan yang menetapkan atau membuatnya.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$pembuat = $mak->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan MAK ke seluruh permohonan pengadaan yang menunjuk kode MAK ini sebagai pos utama.
     *
     * @return HasMany mengembalikan relasi HasMany ke model PermohonanPengadaan.
     *
     * Contoh pemanggilan:
     * `$daftarUsulan = $mak->permohonanPengadaan;`
     */
    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'kode_mak', 'kode_mak');
    }

    /**
     * menghubungkan MAK ke seluruh riwayat alokasi nominal di tabel `alokasi_mak`.
     *
     * @return HasMany mengembalikan relasi HasMany ke model AlokasiMAK.
     *
     * Contoh pemanggilan:
     * `$riwayatAlokasi = $mak->alokasiMak;`
     */
    public function alokasiMak(): HasMany
    {
        return $this->hasMany(AlokasiMAK::class, 'kode_mak', 'kode_mak');
    }
}
