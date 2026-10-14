<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reminder extends Model
{
    use HasFactory;

    protected $table = 'reminder';

    protected $primaryKey = 'id_reminder';

    protected $fillable = [
        'nomor_paket',
        'tipe_reminder',
        'waktu_trigger',
        'is_recurring',
        'interval_bulan',
        'judul_alert',
        'pesan_alert',
    ];

    protected function casts(): array
    {
        return [
            'waktu_trigger' => 'datetime',
            'is_recurring' => 'boolean',
            'interval_bulan' => 'integer',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketPengadaan::class, 'nomor_paket', 'nomor_paket');
    }

    public function notifikasiUser(): HasMany
    {
        return $this->hasMany(NotifikasiUser::class, 'id_reminder', 'id_reminder');
    }
}
