<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Jejak Audit (Audit Log)
 *
 * Bertanggung jawab merekam seluruh riwayat aktivitas mutasi penting pada sistem SIM-PBJ,
 * termasuk perubahan status permohonan, penugasan PPK, penguncian pagu MAK, penerbitan SPK,
 * hingga pencairan dana. Menyimpan potret nilai lama (before) dan nilai baru (after)
 * beserta alamat IP dan identitas pengguna untuk akuntabilitas hukum dan audit pengadaan.
 */
class AuditLog extends Model
{
    use HasFactory;

    /**
     * Nama tabel audit log pada basis data.
     *
     * @var string
     */
    protected $table = 'audit_log';

    /**
     * Nama primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_log';

    /**
     * Kolom-kolom yang diizinkan untuk diisi secara massal (mass assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_user',
        'waktu_log',
        'jenis_aksi',
        'nama_entitas',
        'id_entitas',
        'nilai_lama',
        'nilai_baru',
        'alasan',
    ];

    /**
     * Atribut tambahan yang otomatis disertakan dalam serialisasi model (JSON/Array).
     *
     * @var array<int, string>
     */
    protected $appends = [
        'aksi',
        'keterangan',
    ];

    /**
     * Accessor untuk atribut 'aksi' agar kompatibel dengan pembacaan log terdahulu.
     */
    public function getAksiAttribute(): ?string
    {
        return $this->attributes['jenis_aksi'] ?? null;
    }

    /**
     * Accessor untuk atribut 'keterangan' agar kompatibel dengan pembacaan log terdahulu.
     */
    public function getKeteranganAttribute(): ?string
    {
        return $this->attributes['alasan'] ?? null;
    }

    /**
     * Atribut yang harus dikonversi ke tipe data waktu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu_log' => 'datetime',
        ];
    }

    /**
     * Metode pembantu (helper) statis untuk mempermudah pencatatan jejak audit dari controller atau service.
     *
     * @param  int|null  $userId  ID pengguna pelaku aksi (null jika sistem/otomatis)
     * @param  string  $jenisAksi  Jenis aksi (misal: 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT')
     * @param  string  $namaEntitas  Nama tabel atau modul (misal: 'permohonan_pengadaan', 'paket_pengadaan')
     * @param  string  $idEntitas  Identifier rekaman yang diubah (misal: nomor_surat, id_paket)
     * @param  string|null  $nilaiLama  Kondisi data sebelum perubahan (format JSON atau string)
     * @param  string|null  $nilaiBaru  Kondisi data sesudah perubahan (format JSON atau string)
     * @param  string|null  $alasan  Catatan atau justifikasi perubahan
     * @return self Instance rekaman audit log yang baru saja dibuat
     */
    public static function catat(?int $userId, string $jenisAksi, string $namaEntitas, string $idEntitas, ?string $nilaiLama = null, ?string $nilaiBaru = null, ?string $alasan = null): self
    {
        return self::create([
            'id_user' => $userId,
            'waktu_log' => now(),
            'jenis_aksi' => $jenisAksi,
            'nama_entitas' => $namaEntitas,
            'id_entitas' => $idEntitas,
            'nilai_lama' => $nilaiLama,
            'nilai_baru' => $nilaiBaru,
            'alasan' => $alasan,
        ]);
    }

    /**
     * Relasi many-to-one ke User pelaksana aksi yang tercatat pada log.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi many-to-one ke User pelaksana aksi (alias userPenetap untuk kompatibilitas mutasi).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function userPenetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi ke PPK lama (khusus log REASSIGN_PPK di mana nilai_lama berisi id_user PPK terdahulu).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppkLama(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nilai_lama', 'id_user');
    }

    /**
     * Relasi ke PPK baru (khusus log REASSIGN_PPK di mana nilai_baru berisi id_user PPK penerima).
     *
     * @return BelongsTo Relasi ke model User
     */
    public function ppkBaru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nilai_baru', 'id_user');
    }

    /**
     * Accessor alias tanggal_penugasan untuk kompatibilitas waktu mutasi.
     */
    public function getTanggalPenugasanAttribute(): ?Carbon
    {
        return $this->waktu_log;
    }

    /**
     * Accessor alias alasan_pengalihan untuk alasan mutasi.
     */
    public function getAlasanPengalihanAttribute(): ?string
    {
        return $this->alasan;
    }
}
