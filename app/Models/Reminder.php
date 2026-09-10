<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'pengadaan_id',
        'user_id',
        'target_role',
        'tipe',
        'judul',
        'pesan',
        'tanggal_ingat',
        'is_read',
        'is_dismissed',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_ingat' => 'date',
            'is_read' => 'boolean',
            'is_dismissed' => 'boolean',
        ];
    }

    public function pengadaan(): BelongsTo
    {
        return $this->belongsTo(Pengadaan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
