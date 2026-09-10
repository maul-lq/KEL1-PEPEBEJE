<?php

namespace App\Services;

use App\Models\Pengadaan;
use App\Models\PengadaanLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public static function log(
        Pengadaan $pengadaan,
        string $action,
        ?string $keterangan = null,
        ?string $statusSebelumnya = null,
        ?string $statusBaru = null,
        ?User $user = null
    ): PengadaanLog {
        $actor = $user ?? Auth::user();

        return PengadaanLog::create([
            'pengadaan_id' => $pengadaan->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'keterangan' => $keterangan,
            'status_sebelumnya' => $statusSebelumnya,
            'status_baru' => $statusBaru ?? $pengadaan->status,
            'ip_address' => request()->ip(),
        ]);
    }
}
