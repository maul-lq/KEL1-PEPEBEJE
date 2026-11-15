<?php

namespace App\Services;

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\Reminder;
use Carbon\Carbon;

/**
 * Service Penentuan Jalur Pengadaan & Sinkronisasi Jadwal Pengingat (PengadaanRoutingService)
 *
 * Mengelola routing paket berdasarkan nominal dan membangkitkan jadwal reminder berkala
 * maupun reminder jatuh tempo untuk Kontrak/SPK, Belanja Langsung, dan Pembayaran.
 */
class PengadaanRoutingService
{
    /**
     * Menentukan jalur pengadaan secara otomatis pada model PaketPengadaan berdasarkan nilai HPS/pagu (FR 8.0).
     */
    public static function assignJalur(PaketPengadaan $paket): void
    {
        $nominal = (float) ($paket->nilai_hps ?: $paket->alokasi_pagu_paket);
        $paket->jalur_routing = PaketPengadaan::tentukanRouting($nominal);

        if ($paket->jalur_routing === PaketPengadaan::ROUTING_PPK) {
            $paket->nomor_memo_ppbj = null;
        }
    }

    /**
     * Membangkitkan pemberitahuan awal & jadwal pengingat pengiriman untuk Belanja Langsung <= Rp 50 Juta.
     */
    public static function generateBelanjaLangsungReminder(PaketPengadaan $paket, ?string $jadwalPengiriman = null): void
    {
        $nominal = (float) ($paket->nominal_harga_akhir ?: $paket->nilai_hps ?: $paket->alokasi_pagu_paket);

        $reminderBelanja = Reminder::firstOrCreate(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => 'belanja_langsung_kwitansi',
                'parent_reminder_id' => null,
            ],
            [
                'waktu_trigger' => now(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => false,
                'last_triggered_at' => now(),
                'target_permissions' => ['penerimaan.bast_create', 'pembayaran.pencairan_sp2d'],
                'judul_alert' => 'Belanja Langsung Selesai: '.$paket->nama_paket,
                'pesan_alert' => 'Belanja Langsung (Kwitansi '.($paket->nomor_kwitansi_belanja ?? '-').') senilai Rp '.number_format($nominal, 0, ',', '.').' telah dicatat tanpa kontrak. Menunggu pemeriksaan barang Perlengkapan.',
            ]
        );

        $users = PengadaanNotificationService::resolveActiveUsersByLogicalRoles(['perlengkapan', 'keuangan']);

        foreach ($users as $targetUser) {
            NotifikasiUser::firstOrCreate(
                [
                    'id_user' => $targetUser->id_user,
                    'id_reminder' => $reminderBelanja->id_reminder,
                ],
                [
                    'is_read' => false,
                    'is_dismissed' => false,
                ]
            );
        }

        if ($jadwalPengiriman) {
            $jadwalCarbon = Carbon::parse($jadwalPengiriman);

            self::upsertSchedule(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
                    'parent_reminder_id' => null,
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->startOfDay(),
                    'interval_bulan' => null,
                    'is_recurring' => false,
                    'is_active' => true,
                    'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                    'judul_alert' => 'Jadwal Pengiriman Belanja Langsung: '.$paket->nama_paket,
                    'pesan_alert' => 'Target kedatangan barang Belanja Langsung pada '.$jadwalCarbon->format('d M Y').'.',
                ]
            );

            self::upsertSchedule(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => Reminder::TIPE_H_MINUS_2_TERMIN,
                    'parent_reminder_id' => null,
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->subDays(2)->startOfDay(),
                    'interval_bulan' => null,
                    'is_recurring' => false,
                    'is_active' => true,
                    'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                    'judul_alert' => 'H-2 Pengiriman Belanja Langsung: '.$paket->nama_paket,
                    'pesan_alert' => 'Memasuki H-2 target kedatangan barang Belanja Langsung ('.$jadwalCarbon->format('d M Y').').',
                ]
            );
        }
    }

    /**
     * Sinkronisasi jadwal reminder pengiriman & pengingat berkala berdasarkan Kontrak/SPK.
     */
    public static function syncKontrakSchedules(KontrakSPK $spk, ?PaketPengadaan $paket = null): void
    {
        $paket = $paket ?? $spk->paket;
        if (! $paket || in_array($paket->status_paket, ['selesai', 'batal'], true)) {
            return;
        }

        if (! in_array($spk->status_spk, ['terbit', 'aktif'], true)) {
            return;
        }

        $jadwal = $spk->jadwal_pengiriman ?? $spk->tanggal_selesai;
        if (! $jadwal) {
            return;
        }

        $jadwalCarbon = Carbon::parse($jadwal);
        $intervalBulan = match ($spk->sifat_kontrak) {
            'termin_bulanan' => 1,
            'kontrak_tahunan' => 12,
            default => null,
        };
        $isRecurring = $intervalBulan !== null;
        $labelSifat = match ($spk->sifat_kontrak) {
            'termin_bulanan' => ' (Kontrak Termin Bulanan)',
            'kontrak_tahunan' => ' (Kontrak Tahunan)',
            default => '',
        };

        self::upsertSchedule(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
                'parent_reminder_id' => null,
            ],
            [
                'waktu_trigger' => $jadwalCarbon->copy()->startOfDay(),
                'interval_bulan' => $intervalBulan,
                'is_recurring' => $isRecurring,
                'is_active' => true,
                'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                'judul_alert' => ($isRecurring ? 'Pengingat Berkala Kontrak: ' : 'Jadwal Pengiriman Barang: ').$paket->nama_paket,
                'pesan_alert' => "Jadwal pengiriman / termin{$labelSifat} kontrak {$spk->nomor_spk} pada tanggal ".$jadwalCarbon->format('d M Y').'.',
            ]
        );

        self::upsertSchedule(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => Reminder::TIPE_H_MINUS_2_TERMIN,
                'parent_reminder_id' => null,
            ],
            [
                'waktu_trigger' => $jadwalCarbon->copy()->subDays(2)->startOfDay(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => true,
                'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                'judul_alert' => 'H-2 Pengiriman Barang: '.$paket->nama_paket,
                'pesan_alert' => 'Pengadaan memasuki H-2 batas waktu pengiriman barang sesuai kontrak '.$spk->nomor_spk.' ('.$jadwalCarbon->format('d M Y').'). Mohon koordinasi dengan vendor.',
            ]
        );
    }

    /**
     * Sinkronisasi jadwal reminder H-2 dan Hari-H jatuh tempo pembayaran tagihan.
     */
    public static function syncPembayaranSchedules(Pembayaran $pembayaran): void
    {
        if (in_array($pembayaran->status_pembayaran, [Pembayaran::STATUS_LUNAS, Pembayaran::STATUS_DITOLAK], true)) {
            self::deactivateForPembayaran($pembayaran->nomor_pembayaran);

            return;
        }

        if (empty($pembayaran->jatuh_tempo)) {
            return;
        }

        $jatuhTempo = Carbon::parse($pembayaran->jatuh_tempo);
        $namaPaket = $pembayaran->paket?->nama_paket ?? $pembayaran->nomor_paket;
        $nominal = number_format((float) ($pembayaran->nominal_pengajuan ?? 0), 0, ',', '.');
        $targetPermissions = ['pembayaran.pencairan_sp2d', 'pembayaran.spp_terbitkan', 'pembayaran.pengajuan'];

        self::upsertSchedule(
            [
                'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
                'tipe_reminder' => Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2,
                'parent_reminder_id' => null,
            ],
            [
                'nomor_paket' => $pembayaran->nomor_paket,
                'waktu_trigger' => $jatuhTempo->copy()->subDays(2)->startOfDay(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => true,
                'target_permissions' => $targetPermissions,
                'judul_alert' => "H-2 Jatuh Tempo Tagihan: {$pembayaran->nomor_pembayaran}",
                'pesan_alert' => "Tagihan pembayaran termin {$pembayaran->tahap_termin} paket '{$namaPaket}' senilai Rp {$nominal} memasuki H-2 jatuh tempo ({$jatuhTempo->format('d M Y')}).",
            ]
        );

        self::upsertSchedule(
            [
                'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
                'tipe_reminder' => Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN,
                'parent_reminder_id' => null,
            ],
            [
                'nomor_paket' => $pembayaran->nomor_paket,
                'waktu_trigger' => $jatuhTempo->copy()->startOfDay(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => true,
                'target_permissions' => $targetPermissions,
                'judul_alert' => "Jatuh Tempo Pembayaran Hari Ini: {$pembayaran->nomor_pembayaran}",
                'pesan_alert' => "Tagihan pembayaran '{$namaPaket}' nomor {$pembayaran->nomor_pembayaran} senilai Rp {$nominal} jatuh tempo pada {$jatuhTempo->format('d M Y')}. Segera proses penyelesaian SP2D.",
            ]
        );
    }

    /**
     * Menonaktifkan jadwal reminder yang terkait dengan suatu paket pengadaan.
     */
    public static function deactivateForPaket(string $nomorPaket, bool $onlyNonRecurringDelivery = false): void
    {
        $query = Reminder::query()
            ->where('nomor_paket', $nomorPaket)
            ->whereNull('parent_reminder_id')
            ->where('is_active', true);

        if ($onlyNonRecurringDelivery) {
            $query->where('is_recurring', false)
                ->whereIn('tipe_reminder', [Reminder::TIPE_PENGIRIMAN, Reminder::TIPE_H_MINUS_2_TERMIN]);
        }

        $query->update(['is_active' => false]);
    }

    /**
     * Menonaktifkan seluruh jadwal pengingat jatuh tempo pada tagihan pembayaran tertentu.
     */
    public static function deactivateForPembayaran(string $nomorPembayaran): void
    {
        Reminder::query()
            ->where('nomor_pembayaran', $nomorPembayaran)
            ->whereNull('parent_reminder_id')
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Membangkitkan pengingat jadwal pengadaan kontrak/SPK secara idempoten (FR 12.0).
     */
    public static function generateReminders(PaketPengadaan $paket, ?KontrakSPK $spk = null): void
    {
        $spk = $spk ?? $paket->kontrakSpk;
        if ($spk) {
            self::syncKontrakSchedules($spk, $paket);
        }
    }

    /**
     * Membuat atau menyinkronkan jadwal pengingat tanpa mereset jadwal yang sudah berjalan.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    protected static function upsertSchedule(array $attributes, array $values): Reminder
    {
        $existing = Reminder::query()->where($attributes)->first();

        if (! $existing) {
            return Reminder::create(array_merge($attributes, $values));
        }

        $existing->interval_bulan = $values['interval_bulan'] ?? $existing->interval_bulan;
        $existing->is_recurring = $values['is_recurring'] ?? $existing->is_recurring;
        $existing->target_permissions = $values['target_permissions'] ?? $existing->target_permissions;

        if ($existing->last_triggered_at === null) {
            $existing->waktu_trigger = $values['waktu_trigger'];
            $existing->judul_alert = $values['judul_alert'];
            $existing->pesan_alert = $values['pesan_alert'];
            if (array_key_exists('nomor_paket', $values)) {
                $existing->nomor_paket = $values['nomor_paket'];
            }
        }

        $existing->save();

        return $existing;
    }
}
