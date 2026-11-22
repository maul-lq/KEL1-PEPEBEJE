<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis model AuditLog ini untuk merekam jejak audit setiap aksi mutasi penting di SIM-PBJ beserta perbandingan nilai sebelum dan sesudah perubahan.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Jejak Audit Sistem (AuditLog)
 *
 * memakai model ini untuk mencatat histori setiap keputusan verifikasi, perubahan pagu, mutasi PPK,
 * hingga pencairan dana agar halaman Riwayat & Laporan serta audit eksternal memiliki rekam jejak tak terbantahkan.
 *
 * Parameter konstruktor: `$attributes` (opsional, diwarisi dari Eloquent Model).
 * Nilai kembali         : Instance model Eloquent yang terhubung ke tabel `audit_log` dengan primary key `id_log`.
 *
 * Contoh pemanggilan:
 * `AuditLog::catat(auth()->id(), 'ACC_WADIR_2', 'permohonan_pengadaan', $nomorSurat, 'menunggu', 'disetujui', $catatan);`
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
     * menyediakan accessor `aksi` sebagai alias kompatibilitas dari kolom `jenis_aksi` untuk consumer/view terdahulu.
     *
     * @return string|null mengembalikan kode jenis aksi atau null bila belum disetel.
     *
     * Contoh pemanggilan:
     * `$kodeAksi = $auditLog->aksi;`
     */
    public function getAksiAttribute(): ?string
    {
        return $this->attributes['jenis_aksi'] ?? null;
    }

    /**
     * menyediakan accessor `keterangan` sebagai alias kompatibilitas dari kolom `alasan`.
     *
     * @return string|null mengembalikan teks alasan/catatan mutasi atau null bila kosong.
     *
     * Contoh pemanggilan:
     * `$ket = $auditLog->keterangan;`
     */
    public function getKeteranganAttribute(): ?string
    {
        return $this->attributes['alasan'] ?? null;
    }

    /**
     * mengonversi kolom `waktu_log` ke instance Carbon agar pemformatan waktu lokal pada laporan konsisten.
     *
     * @return array<string, string> mengembalikan peta tipe cast atribut model.
     *
     * Contoh pemanggilan:
     * `// Dipanggil otomatis oleh Eloquent saat mengakses $auditLog->waktu_log.`
     */
    protected function casts(): array
    {
        return [
            'waktu_log' => 'datetime',
        ];
    }

    /**
     * menyediakan helper statis ini agar controller dan service dapat mencatat jejak audit secara konsisten dalam satu baris.
     *
     * @param  int|null  $userId  menerima ID pengguna pelaku aksi (atau null bila dipicu proses sistem).
     * @param  string  $jenisAksi  menerima kode aksi terstandar seperti 'CREATE', 'ACC_WADIR_2', atau 'REASSIGN_PPK'.
     * @param  string  $namaEntitas  menerima nama tabel/entitas sasaran mutasi.
     * @param  string  $idEntitas  menerima identifier string entitas (nomor surat, nomor paket, atau ID user).
     * @param  string|null  $nilaiLama  menyimpan potret nilai sebelum perubahan (opsional).
     * @param  string|null  $nilaiBaru  menyimpan potret nilai sesudah perubahan (opsional).
     * @param  string|null  $alasan  menyimpan catatan alasan atau deskripsi aktivitas untuk ditampilkan di tab Riwayat.
     * @return self mengembalikan instance AuditLog yang baru dibuat.
     *
     * Contoh pemanggilan:
     * `AuditLog::catat($user->id_user, 'LOCK_MAK', 'anggaran_mak', $mak->kode_mak, '0', '1', 'Kunci pagu MAK');`
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
     * menghubungkan baris log ke pengguna pelaku aksi.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User.
     *
     * Contoh pemanggilan:
     * `$pelaku = $auditLog->user;`
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menyediakan relasi alias `userPenetap` yang merujuk ke `id_user` untuk keterbacaan riwayat mutasi PPK.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User penetap.
     *
     * Contoh pemanggilan:
     * `$penetap = $riwayatPpk->userPenetap;`
     */
    public function userPenetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * menghubungkan `nilai_lama` ke model User untuk log `REASSIGN_PPK` agar nama PPK sebelumnya bisa dimuat langsung via eager loading.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User (PPK lama).
     *
     * Contoh pemanggilan:
     * `$namaLama = $riwayatPpk->ppkLama?->nama;`
     */
    public function ppkLama(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nilai_lama', 'id_user');
    }

    /**
     * menghubungkan `nilai_baru` ke model User untuk log `REASSIGN_PPK` agar nama PPK pengganti bisa ditampilkan di UI detail paket.
     *
     * @return BelongsTo mengembalikan relasi BelongsTo ke model User (PPK baru).
     *
     * Contoh pemanggilan:
     * `$namaBaru = $riwayatPpk->ppkBaru?->nama;`
     */
    public function ppkBaru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nilai_baru', 'id_user');
    }

    /**
     * menyediakan accessor alias `tanggal_penugasan` yang meneruskan `waktu_log` untuk tampilan riwayat alih tugas PPK.
     *
     * @return Carbon|null mengembalikan objek waktu log mutasi atau null.
     *
     * Contoh pemanggilan:
     * `$tgl = $riwayatPpk->tanggal_penugasan;`
     */
    public function getTanggalPenugasanAttribute(): ?Carbon
    {
        return $this->waktu_log;
    }

    /**
     * menyediakan accessor alias `alasan_pengalihan` yang meneruskan kolom `alasan` untuk tampilan riwayat mutasi PPK.
     *
     * @return string|null mengembalikan catatan alasan pengalihan atau null.
     *
     * Contoh pemanggilan:
     * `$alasan = $riwayatPpk->alasan_pengalihan;`
     */
    public function getAlasanPengalihanAttribute(): ?string
    {
        return $this->alasan;
    }
}
