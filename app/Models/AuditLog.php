<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_log';

    protected $primaryKey = 'id_log';

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

    protected function casts(): array
    {
        return [
            'waktu_log' => 'datetime',
        ];
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
