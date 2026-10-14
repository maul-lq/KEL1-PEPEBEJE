<?php

namespace App\Models;

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
        'ip_address',
    ];

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
            'ip_address' => request()->ip(),
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
}
