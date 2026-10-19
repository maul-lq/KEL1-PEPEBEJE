<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Notifikasi Pengguna (Notifikasi User)
 *
 * Merepresentasikan pesan pemberitahuan yang dikirimkan secara personal kepada pengguna
 * sebagai tindak lanjut atas alarm pengingat sistem (Reminder), tembusan pengadaan,
 * maupun tugas verifikasi baru. Melacak status apakah notifikasi telah dibaca (is_read),
 * ditutup/diabaikan (is_dismissed), dan kapan waktu dibacanya.
 */
class NotifikasiUser extends Model
{
    use HasFactory;

    /**
     * Nama tabel database.
     *
     * @var string
     */
    protected $table = 'notifikasi_user';

    /**
     * Kolom-kolom yang dapat diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_user',
        'id_reminder',
        'is_read',
        'is_dismissed',
        'waktu_dibaca',
    ];

    /**
     * Konfigurasi type casting untuk boolean dan timestamp.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_dismissed' => 'boolean',
            'waktu_dibaca' => 'datetime',
        ];
    }

    /**
     * Relasi many-to-one ke User penerima notifikasi.
     *
     * @return BelongsTo Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi many-to-one ke master Reminder yang memicu notifikasi ini.
     *
     * @return BelongsTo Relasi ke model Reminder
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class, 'id_reminder', 'id_reminder');
    }

    /**
     * Accessor judul notifikasi dari master reminder.
     */
    public function getJudulAttribute(): string
    {
        return $this->reminder?->judul_alert ?? 'Pemberitahuan Sistem';
    }

    /**
     * Accessor pesan alert dari master reminder.
     */
    public function getPesanAttribute(): string
    {
        return $this->reminder?->pesan_alert ?? 'Pembaruan status operasional pengadaan barang dan jasa.';
    }
}
