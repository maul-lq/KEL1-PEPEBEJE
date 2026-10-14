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
    // Mewarisi seluruh fungsionalitas pencatatan log audit dari AuditLog
}
