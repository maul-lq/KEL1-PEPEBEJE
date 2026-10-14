<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiUser extends Model
{
    use HasFactory;

    protected $table = 'notifikasi_user';

    protected $fillable = [
        'id_user',
        'id_reminder',
        'is_read',
        'is_dismissed',
        'waktu_dibaca',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_dismissed' => 'boolean',
            'waktu_dibaca' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class, 'id_reminder', 'id_reminder');
    }
}
