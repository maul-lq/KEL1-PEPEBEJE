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

    /**
     * Accessor label jenis notifikasi untuk badge (Figma: "Pengingat", "Perlu Tindakan", dsb).
     */
    public function getJenisBadgeAttribute(): string
    {
        $tipe = $this->reminder?->tipe_reminder ?? '';
        $judul = strtolower($this->judul);
        $pesan = strtolower($this->pesan);

        if ($tipe === 'pengiriman' || str_contains($judul, 'pengiriman')) {
            return 'Pengiriman';
        }
        if (str_contains($judul, 'revisi') || str_contains($pesan, 'revisi')) {
            return 'Pengingat';
        }
        if ($tipe === 'verifikasi' || str_contains($judul, 'verifikasi') || str_contains($judul, 'telaah')) {
            return 'Verifikasi';
        }
        if ($tipe === 'pembayaran' || $tipe === 'h_minus_2_termin' || str_contains($judul, 'bayar') || str_contains($judul, 'termin') || str_contains($judul, 'sp2d') || str_contains($judul, 'spp')) {
            return 'Pembayaran';
        }
        if (str_contains($judul, 'spk') || str_contains($judul, 'kontrak')) {
            return 'Tembusan SPK';
        }
        if (str_contains($judul, 'tindakan') || str_contains($pesan, 'tindak lanjut')) {
            return 'Perlu Tindakan';
        }

        return 'Pengingat';
    }

    /**
     * Accessor teks referensi berkas dinas (Figma: "032/PL3/TE/2026 · Komponen praktikum digital").
     */
    public function getReferensiTeksAttribute(): string
    {
        $paket = $this->reminder?->paket;
        if ($paket) {
            $nomor = $paket->nomor_surat ?: $paket->nomor_paket;
            $judul = $paket->permohonan?->judul_pengadaan ?: $paket->nama_paket;

            return "{$nomor} · {$judul}";
        }

        if (preg_match('/\b\d{3}\/[A-Z0-9\.]+\/[A-Z0-9]+\/\d{4}\b/', $this->pesan.' '.$this->judul, $matches)) {
            return $matches[0].' · Permohonan Pengadaan Terkait';
        }

        return '032/PL3/TE/2026 · Komponen praktikum digital';
    }

    /**
     * Accessor label tombol aksi (Figma: "Buka revisi saya ↗", "Lihat Paket ↗", dsb).
     */
    public function getActionLabelAttribute(): string
    {
        $user = $this->user ?? auth()->user();
        $role = $user?->role ?? '';
        $badge = $this->jenis_badge;
        $judul = strtolower($this->judul);

        if (str_contains($judul, 'revisi')) {
            return 'Buka revisi saya ↗';
        }

        return match ($role) {
            'user' => 'Buka permohonan ↗',
            'staff_bidang_2' => 'Telaah Usulan ↗',
            'wadir_2' => str_contains($judul, 'bayar') || str_contains($judul, 'memo') ? 'ACC Memo Bayar ↗' : 'Persetujuan Wadir 2 ↗',
            'perencanaan' => 'Verifikasi MAK ↗',
            'ppbj' => match ($badge) {
                'Tembusan SPK' => 'Tinjau SPK ↗',
                'Pembayaran' => 'Terbitkan SPP ↗',
                default => 'Lihat Paket ↗',
            },
            'pp' => 'Negosiasi Harga ↗',
            'ppk' => str_contains($judul, 'bayar') || str_contains($judul, 'memo') ? 'Tinjau Memo Bayar ↗' : 'Tinjau Kontrak ↗',
            'perlengkapan' => 'Pemeriksaan BAST ↗',
            'keuangan' => 'Pencairan SP2D ↗',
            'admin' => 'Persetujuan Registrasi ↗',
            default => 'Lihat Rincian ↗',
        };
    }

    /**
     * Accessor URL rute tujuan saat tombol aksi diklik.
     */
    public function getActionUrlAttribute(): string
    {
        $user = $this->user ?? auth()->user();
        $role = $user?->role ?? '';
        $badge = $this->jenis_badge;
        $judul = strtolower($this->judul);

        if (str_contains($judul, 'revisi')) {
            return route('permohonan.index');
        }

        return match ($role) {
            'user' => route('permohonan.index'),
            'staff_bidang_2' => route('verifikasi.bidang-2'),
            'wadir_2' => (str_contains($judul, 'bayar') || str_contains($judul, 'memo')) ? route('pembayaran.index') : route('verifikasi.wadir-2'),
            'perencanaan' => route('verifikasi.perencanaan'),
            'ppbj' => match ($badge) {
                'Tembusan SPK' => route('kontrak.index'),
                'Pembayaran' => route('pembayaran.index'),
                default => route('paket.index'),
            },
            'pp' => route('kontrak.index'),
            'ppk' => (str_contains($judul, 'bayar') || str_contains($judul, 'memo')) ? route('pembayaran.index') : route('kontrak.index'),
            'perlengkapan' => route('penerimaan.index'),
            'keuangan' => route('pembayaran.index'),
            'admin' => route('admin.persetujuan-registrasi.index'),
            default => route('dashboard'),
        };
    }
}
