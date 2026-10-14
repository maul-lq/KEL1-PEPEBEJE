<?php

namespace App\Models;

/**
 * Model Pengadaan Log (Alias untuk AuditLog)
 *
 * Berfungsi sebagai turunan dan alias dari model AuditLog untuk menjaga kompatibilitas ke belakang
 * (backward compatibility) dengan komponen atau kontroler terdahulu yang merujuk pada nama model ini.
 */
class PengadaanLog extends AuditLog
{
    public const AKSI_ASSIGN_PPK = 'ASSIGN_PPK';

    public const AKSI_CREATE = 'CREATE';

    public const AKSI_UPDATE = 'UPDATE';

    public const AKSI_DELETE = 'DELETE';
}
