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
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relasi many-to-one ke master Reminder yang memicu notifikasi ini.
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
     * Accessor label jenis notifikasi untuk badge UI.
     */
    public function getJenisBadgeAttribute(): string
    {
        $tipe = $this->reminder?->tipe_reminder ?? '';
        $judul = strtolower($this->judul);
        $pesan = strtolower($this->pesan);

        if (
            in_array($tipe, [Reminder::TIPE_PENGIRIMAN, Reminder::TIPE_H_MINUS_2_TERMIN], true)
            || str_contains($judul, 'pengiriman')
        ) {
            return $this->reminder?->is_recurring || $this->reminder?->parent_reminder_id ? 'Pengingat Berkala' : 'Pengiriman';
        }
        if (str_contains($judul, 'revisi') || str_contains($pesan, 'revisi')) {
            return 'Pengingat';
        }
        if ($tipe === Reminder::TIPE_VERIFIKASI || str_contains($judul, 'verifikasi') || str_contains($judul, 'telaah') || str_contains($judul, 'pendaftar')) {
            return 'Verifikasi';
        }
        if (
            in_array($tipe, [Reminder::TIPE_PEMBAYARAN, Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2, Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN], true)
            || str_contains($judul, 'bayar')
            || str_contains($judul, 'termin')
            || str_contains($judul, 'jatuh tempo')
            || str_contains($judul, 'sp2d')
            || str_contains($judul, 'spp')
            || str_contains($judul, 'cair')
        ) {
            return 'Pembayaran';
        }
        if (in_array($tipe, ['spk', 'tembusan_spk'], true) || str_contains($judul, 'spk') || str_contains($judul, 'kontrak')) {
            return 'Dokumen Kontrak';
        }
        if (str_contains($judul, 'tindakan') || str_contains($pesan, 'tindak lanjut')) {
            return 'Perlu Tindakan';
        }

        return 'Pengingat';
    }

    /**
     * Accessor teks referensi berkas dinas.
     */
    public function getReferensiTeksAttribute(): string
    {
        $paket = $this->reminder?->paket;
        if ($paket) {
            $nomor = $paket->nomor_surat ?: $paket->nomor_paket;
            $judul = $paket->permohonan?->judul_pengadaan ?: $paket->nama_paket;

            return "{$nomor} · {$judul}";
        }

        if ($this->reminder?->nomor_pembayaran) {
            return $this->reminder->nomor_pembayaran.' · Tagihan Pembayaran Pengadaan';
        }

        if (preg_match('/\b\d{3}\/[A-Z0-9\.]+\/[A-Z0-9]+\/\d{4}\b/', $this->pesan.' '.$this->judul, $matches)) {
            return $matches[0].' · Permohonan Pengadaan Terkait';
        }

        return 'Sistem SIM-PBJ Politeknik Negeri Jakarta';
    }

    /**
     * Menentukan label tombol dan rute tujuan berdasarkan jenis notifikasi serta hak akses RBAC pengguna.
     *
     * @return array{label: string, url: string}
     */
    public function resolveActionTarget(): array
    {
        $user = $this->user ?? auth()->user();
        $tipe = $this->reminder?->tipe_reminder ?? '';
        $badge = $this->jenis_badge;
        $judul = strtolower($this->judul);
        $pesan = strtolower($this->pesan);

        if (str_contains($judul, 'pendaftar') || str_contains($judul, 'registrasi')) {
            if ($user?->hasPermission('admin.approval_registrasi')) {
                return ['label' => 'Persetujuan Registrasi ↗', 'url' => route('admin.persetujuan-registrasi.index')];
            }
        }

        if (str_contains($judul, 'revisi') && $user?->hasAnyPermission(['permohonan.revisi', 'permohonan.create', 'permohonan.view_own'])) {
            return ['label' => 'Buka revisi saya ↗', 'url' => route('permohonan.index')];
        }

        if (
            in_array($tipe, [Reminder::TIPE_PEMBAYARAN, Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2, Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN], true)
            || $badge === 'Pembayaran'
            || str_contains($judul, 'bayar')
            || str_contains($judul, 'memo')
            || str_contains($judul, 'jatuh tempo')
            || str_contains($judul, 'cair')
        ) {
            if ($user?->hasPermission('pembayaran.pencairan_sp2d')) {
                return ['label' => 'Pencairan Dana ↗', 'url' => route('pembayaran.index')];
            }
            if ($user?->hasPermission('pembayaran.memo_wadir2') && (str_contains($judul, 'memo') || str_contains($judul, 'pengajuan'))) {
                return ['label' => 'Setujui Memo Bayar ↗', 'url' => route('pembayaran.index')];
            }
            if ($user?->hasPermission('pembayaran.memo_ppk') && (str_contains($judul, 'memo') || str_contains($judul, 'pengajuan'))) {
                return ['label' => 'Tinjau Memo Bayar ↗', 'url' => route('pembayaran.index')];
            }
            if ($user?->hasAnyPermission(['pembayaran.pengajuan', 'pembayaran.spp_terbitkan', 'pembayaran.memo_ppk', 'pembayaran.memo_wadir2'])) {
                return ['label' => 'Kelola Pembayaran ↗', 'url' => route('pembayaran.index')];
            }
            if ($user?->hasPermission('monitoring.view')) {
                return ['label' => 'Lihat Progres Paket ↗', 'url' => route('monitoring.index')];
            }
        }

        if (
            in_array($tipe, [Reminder::TIPE_PENGIRIMAN, Reminder::TIPE_H_MINUS_2_TERMIN], true)
            || str_contains($judul, 'pengiriman')
            || str_contains($judul, 'serah terima')
        ) {
            if ($user?->hasAnyPermission(['penerimaan.bast_create', 'penerimaan.bast_sign', 'penerimaan.view'])) {
                return ['label' => 'Periksa Serah Terima ↗', 'url' => route('penerimaan.index')];
            }
            if ($user?->hasAnyPermission(['spk.create', 'reviu.pp', 'reviu.ppk'])) {
                return ['label' => 'Pantau Jadwal Kontrak ↗', 'url' => route('kontrak.index')];
            }
            if ($user?->hasPermission('monitoring.view')) {
                return ['label' => 'Pantau Pengadaan ↗', 'url' => route('monitoring.index')];
            }
        }

        if (in_array($tipe, ['spk', 'tembusan_spk', 'belanja_langsung_kwitansi'], true) || $badge === 'Dokumen Kontrak') {
            if ($user?->hasAnyPermission(['penerimaan.bast_create', 'penerimaan.view']) && ! $user?->hasAnyPermission(['spk.create', 'reviu.pp', 'reviu.ppk'])) {
                return ['label' => 'Persiapkan Pemeriksaan ↗', 'url' => route('penerimaan.index')];
            }
            if ($user?->hasPermission('pembayaran.pencairan_sp2d') && ! $user?->hasAnyPermission(['spk.create', 'reviu.pp', 'reviu.ppk'])) {
                return ['label' => 'Pantau Tagihan ↗', 'url' => route('pembayaran.index')];
            }
            if ($user?->hasAnyPermission(['spk.create', 'reviu.pp', 'reviu.ppk'])) {
                return ['label' => 'Tinjau Kontrak ↗', 'url' => route('kontrak.index')];
            }
            if ($user?->hasPermission('permohonan.view_own')) {
                return ['label' => 'Pantau Permohonan ↗', 'url' => route('monitoring.index')];
            }
        }

        if ($tipe === Reminder::TIPE_VERIFIKASI || $badge === 'Verifikasi') {
            if ($user?->hasAnyPermission(['verifikasi.wadir_2_decide', 'verifikasi.wadir_2_view']) && (str_contains($judul, 'wadir') || str_contains($pesan, 'wadir') || ! $user->hasPermission('verifikasi.bidang_2_view'))) {
                return ['label' => 'Persetujuan Wadir 2 ↗', 'url' => route('verifikasi.wadir-2')];
            }
            if ($user?->hasPermission('verifikasi.bidang_2_view')) {
                return ['label' => 'Telaah Usulan ↗', 'url' => route('verifikasi.bidang-2')];
            }
            if ($user?->hasAnyPermission(['anggaran.mak_alokasi', 'anggaran.mak_view'])) {
                return ['label' => 'Cek Pos Anggaran ↗', 'url' => route('verifikasi.perencanaan')];
            }
            if ($user?->hasAnyPermission(['reviu.pp', 'reviu.ppk']) && (str_contains($judul, 'reviu') || str_contains($judul, 'negosiasi') || str_contains($judul, 'kontrak'))) {
                return ['label' => 'Tinjau Berkas Pengadaan ↗', 'url' => route('kontrak.index')];
            }
            if ($user?->hasAnyPermission(['paket.create', 'paket.view_all', 'paket.view_assigned'])) {
                return ['label' => 'Lihat Paket ↗', 'url' => route('paket.index')];
            }
            if ($user?->hasPermission('permohonan.view_own')) {
                return ['label' => 'Buka Permohonan ↗', 'url' => route('permohonan.index')];
            }
        }

        if ($user?->hasAnyPermission(['permohonan.view_own', 'permohonan.create'])) {
            return ['label' => 'Buka Permohonan ↗', 'url' => route('permohonan.index')];
        }
        if ($user?->hasAnyPermission(['paket.create', 'paket.view_all', 'paket.view_assigned'])) {
            return ['label' => 'Lihat Paket ↗', 'url' => route('paket.index')];
        }
        if ($user?->hasPermission('monitoring.view')) {
            return ['label' => 'Lihat Progres ↗', 'url' => route('monitoring.index')];
        }

        return ['label' => 'Lihat Rincian ↗', 'url' => route('notifikasi.index')];
    }

    /**
     * Accessor label tombol aksi.
     */
    public function getActionLabelAttribute(): string
    {
        return $this->resolveActionTarget()['label'];
    }

    /**
     * Accessor URL rute tujuan saat tombol aksi diklik.
     */
    public function getActionUrlAttribute(): string
    {
        return $this->resolveActionTarget()['url'];
    }

    /**
     * Alias kompatibilitas apabila ada pemanggil lama yang mengakses `$notif->link`.
     */
    public function getLinkAttribute(): string
    {
        return $this->action_url;
    }

    /**
     * Serialisasi payload lengkap untuk API polling notifikasi & toast in-app.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $target = $this->resolveActionTarget();

        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'pesan' => $this->pesan,
            'badge' => $this->jenis_badge,
            'referensi' => $this->referensi_teks,
            'action_label' => $target['label'],
            'action_url' => $target['url'],
            'link' => $target['url'],
            'is_read' => (bool) $this->is_read,
            'is_revisi' => str_contains(strtolower($this->judul), 'revisi'),
            'is_selesai' => str_contains(strtolower($this->judul), 'disetujui') || str_contains(strtolower($this->judul), 'selesai') || str_contains(strtolower($this->judul), 'cair'),
            'is_recurring' => (bool) ($this->reminder?->is_recurring || $this->reminder?->parent_reminder_id),
            'created_at' => $this->created_at?->diffForHumans() ?? 'Baru saja',
            'created_at_formatted' => $this->created_at?->format('d M Y, H:i') ?? '-',
        ];
    }
}
