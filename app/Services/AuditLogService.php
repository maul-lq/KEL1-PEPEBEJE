<?php

namespace App\Services;

use App\Models\Pengadaan;
use App\Models\PengadaanLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Service Pencatatan Jejak Audit Pengadaan (AuditLogService)
 *
 * Menyediakan fungsi helper terpusat untuk merekam setiap mutasi data,
 * perubahan status tahapan, dan tindakan penting yang dilakukan oleh aktor sistem.
 * Bertujuan menjamin akuntabilitas serta kemudahan pelacakan riwayat (audit trail).
 */
class AuditLogService
{
    /**
     * Mencatat satu entri log aktivitas pengadaan ke database.
     *
     * @param  Pengadaan  $pengadaan  Model entitas pengadaan yang mengalami mutasi
     * @param  string  $action  Jenis tindakan yang dilakukan (misal: 'SUBMIT', 'SETUJUI', 'TOLAK')
     * @param  string|null  $keterangan  Penjelasan detail atau catatan terkait aksi tersebut
     * @param  string|null  $statusSebelumnya  Status berkas sebelum tindakan dijalankan
     * @param  string|null  $statusBaru  Status berkas setelah tindakan berhasil disimpan
     * @param  User|null  $user  Pengguna yang melakukan aksi (opsional, default ke user login)
     */
    public static function log(
        Pengadaan $pengadaan,
        string $action,
        ?string $keterangan = null,
        ?string $statusSebelumnya = null,
        ?string $statusBaru = null,
        ?User $user = null
    ): PengadaanLog {
        // Menentukan aktor pelaksana tindakan: gunakan user yang dioper atau ambil pengguna yang sedang login
        $actor = $user ?? Auth::user();

        // Menyimpan data riwayat jejak audit ke dalam tabel pengadaan_logs
        return PengadaanLog::create([
            'pengadaan_id' => $pengadaan->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'keterangan' => $keterangan,
            'status_sebelumnya' => $statusSebelumnya,
            'status_baru' => $statusBaru ?? $pengadaan->status,
            'ip_address' => request()->ip(), // Mencatat alamat IP pengirim untuk keperluan keamanan
        ]);
    }
}
