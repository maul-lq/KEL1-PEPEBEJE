<?php

namespace App\Services;

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;

/**
 * Service Penentuan Jalur Pengadaan & Pembangkit Pengingat (PengadaanRoutingService)
 *
 * Mengimplementasikan aturan pengingat dan auto-routing paket pengadaan.
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
     * Membangkitkan pengingat (reminder) dan tembusan persiapan BAST untuk Belanja Langsung <= Rp 50 Juta.
     */
    public static function generateBelanjaLangsungReminder(PaketPengadaan $paket, ?string $jadwalPengiriman = null): void
    {
        $nominal = (float) ($paket->nominal_harga_akhir ?: $paket->nilai_hps ?: $paket->alokasi_pagu_paket);

        $reminderBelanja = Reminder::firstOrCreate(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => 'belanja_langsung_kwitansi',
            ],
            [
                'waktu_trigger' => now(),
                'is_recurring' => false,
                'interval_bulan' => null,
                'judul_alert' => 'Belanja Langsung Selesai: '.$paket->nama_paket,
                'pesan_alert' => 'Belanja Langsung (Kwitansi '.($paket->nomor_kwitansi_belanja ?? '-').') senilai Rp '.number_format($nominal, 0, ',', '.').' telah dieksekusi oleh PPBJ tanpa Kontrak/SPK. Menunggu pemeriksaan fisik dan BAST Perlengkapan.',
            ]
        );

        $users = User::whereIn('role', [User::ROLE_PERLENGKAPAN, User::ROLE_KEUANGAN])
            ->where('status_aktif', true)
            ->get();

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

            Reminder::firstOrCreate(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => 'pengiriman',
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->startOfDay(),
                    'is_recurring' => false,
                    'interval_bulan' => null,
                    'judul_alert' => 'Jadwal Pengiriman Belanja Langsung: '.$paket->nama_paket,
                    'pesan_alert' => 'Target kedatangan barang Belanja Langsung pada '.$jadwalCarbon->format('d M Y').'.',
                ]
            );

            Reminder::firstOrCreate(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => 'h_minus_2_termin',
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->subDays(2)->startOfDay(),
                    'is_recurring' => false,
                    'interval_bulan' => null,
                    'judul_alert' => 'H-2 Pengiriman Belanja Langsung: '.$paket->nama_paket,
                    'pesan_alert' => 'Memasuki H-2 target kedatangan barang Belanja Langsung ('.$jadwalCarbon->format('d M Y').').',
                ]
            );
        }
    }

    /**
     * Membangkitkan pengingat (reminder / notifikasi) jadwal pengadaan & tembusan SPK secara otomatis (FR 11.3 & FR 12.0).
     */
    public static function generateReminders(PaketPengadaan $paket, ?KontrakSPK $spk = null): void
    {
        $spk = $spk ?? $paket->kontrakSpk;
        $nominal = (float) ($spk?->nilai_kontrak ?: $paket->nilai_hps ?: $paket->alokasi_pagu_paket);

        // 1. Tembusan otomatis SPK ke Unit Perlengkapan (N42) & Bagian Keuangan (N44)
        $reminderTembusan = Reminder::firstOrCreate(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => 'tembusan_spk',
            ],
            [
                'waktu_trigger' => now(),
                'is_recurring' => false,
                'interval_bulan' => null,
                'judul_alert' => 'Tembusan SPK: '.$paket->nama_paket,
                'pesan_alert' => 'Tembusan SPK nomor '.($spk?->nomor_spk ?? '-').' dengan nilai kontrak Rp '.number_format($nominal, 0, ',', '.').' untuk persiapan penerimaan barang dan pencatatan komitmen anggaran.',
            ]
        );

        $targetUserIds = collect();
        if ($paket->id_user_ppk) {
            $targetUserIds->push($paket->id_user_ppk);
        }
        if ($paket->id_user_ppbj) {
            $targetUserIds->push($paket->id_user_ppbj);
        }

        $targetRoles = [User::ROLE_PERLENGKAPAN, User::ROLE_KEUANGAN, User::ROLE_PPBJ, User::ROLE_PPK];
        $roleUsers = User::whereIn('role', $targetRoles)->where('status_aktif', true)->pluck('id_user');
        $allRecipientIds = $targetUserIds->merge($roleUsers)->unique();

        foreach ($allRecipientIds as $userId) {
            NotifikasiUser::firstOrCreate(
                [
                    'id_user' => $userId,
                    'id_reminder' => $reminderTembusan->id_reminder,
                ],
                [
                    'is_read' => false,
                    'is_dismissed' => false,
                ]
            );
        }

        // 2. Pengingat Jadwal Pengiriman Barang & H-2 Jatuh Tempo
        $jadwal = $spk?->jadwal_pengiriman ?? $spk?->tanggal_selesai;
        if ($jadwal) {
            $jadwalCarbon = Carbon::parse($jadwal);

            Reminder::firstOrCreate(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => 'pengiriman',
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->startOfDay(),
                    'is_recurring' => in_array($spk?->sifat_kontrak, ['termin_bulanan', 'kontrak_tahunan']),
                    'interval_bulan' => ($spk?->sifat_kontrak === 'termin_bulanan') ? 1 : null,
                    'judul_alert' => 'Jadwal Pengiriman Barang: '.$paket->nama_paket,
                    'pesan_alert' => 'Tanggal target pengiriman barang oleh penyedia adalah '.$jadwalCarbon->format('d M Y').'.',
                ]
            );

            Reminder::firstOrCreate(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => 'h_minus_2_termin',
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->subDays(2)->startOfDay(),
                    'is_recurring' => false,
                    'interval_bulan' => null,
                    'judul_alert' => 'H-2 Pengiriman Barang: '.$paket->nama_paket,
                    'pesan_alert' => 'Pengadaan memasuki H-2 batas waktu pengiriman barang ('.$jadwalCarbon->format('d M Y').'). Mohon koordinasi dengan vendor.',
                ]
            );
        }
    }
}
