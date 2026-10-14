<?php

namespace App\Http\Controllers;

/**
 * Controller Log Pengadaan (PengadaanLogController)
 *
 * Merupakan alias dari AuditLogController untuk menjaga kompatibilitas ke belakang
 * dengan rute-rute API terdahulu.
 */
class PengadaanLogController extends AuditLogController
{
    // Mewarisi seluruh method pembacaan log dari AuditLogController
}
