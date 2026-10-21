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
 * Mengimplementasikan aturan bisnis sesuai BPMN (bepemen.drawio) & Dokumen Alur Pekerjaan:
 * 1. FR 8.0: Auto-routing penentuan jalur pengadaan secara otomatis berdasarkan nilai HPS/pagu:
 *    - Nilai <= Rp 50.000.000                => Jalur Pengadaan Langsung (langsung_sd_50jt) -> PPBJ langsung terbitkan SPK -> Perlengkapan
 *    - Nilai > Rp 50.000.000 dan < 200.000.000 => Jalur Pejabat Pengadaan (pp_50jt_sd_200jt) -> PP -> PPK -> PPBJ -> Perlengkapan
 *    - Nilai >= Rp 200.000.000               => Jalur Pejabat Pembuat Komitmen (ppk_diatas_200jt) -> PPK -> PPBJ -> Perlengkapan (Tanpa Memo)
 * 2. FR 11.3 & FR 12.0: Pembangkitan alarm / reminder otomatis untuk pengiriman barang (termasuk H-2)
 *    dan distribusi tembusan otomatis SPK ke Unit Perlengkapan serta Bagian Keuangan.
 */
class PengadaanRoutingService
{
    /**
     * Menentukan jalur pengadaan secara otomatis pada model PaketPengadaan berdasarkan nilai HPS/pagu (FR 8.0).
     *
     * @param  PaketPengadaan  $paket  Model entitas paket pengadaan yang akan diset jalur routing-nya
     */
    public static function assignJalur(PaketPengadaan $paket): void
    {
        $nominal = (float) ($paket->nilai_hps ?: $paket->alokasi_pagu_paket);
        $paket->jalur_routing = PaketPengadaan::tentukanRouting($nominal);

        // Paket >= 200 juta (jalur PPK) tidak menggunakan memo pengadaan PPBJ
        if ($paket->jalur_routing === PaketPengadaan::ROUTING_PPK) {
            $paket->nomor_memo_ppbj = null;
        }
    }

    /**
     * Membangkitkan pengingat (reminder / notifikasi) jadwal pengadaan & tembusan SPK secara otomatis (FR 11.3 & FR 12.0).
     *
     * @param  PaketPengadaan  $paket  Model paket pengadaan
     * @param  KontrakSPK|null  $spk  Model kontrak SPK terkait (opsional)
     */
    public static function generateReminders(PaketPengadaan $paket, ?KontrakSPK $spk = null): void
    {
        $spk = $spk ?? $paket->kontrakSpk;
        $nominal = (float) ($spk?->nilai_kontrak ?: $paket->nilai_hps ?: $paket->alokasi_pagu_paket);

        // 1. Tembusan otomatis SPK ke Unit Perlengkapan & Bagian Keuangan (khususnya anggaran > Rp 50.000.000)
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

        $targetRoles = [User::ROLE_PERLENGKAPAN, User::ROLE_KEUANGAN];
        $users = User::whereIn('role', $targetRoles)->where('status_aktif', true)->get();

        foreach ($users as $targetUser) {
            NotifikasiUser::firstOrCreate(
                [
                    'id_user' => $targetUser->id_user,
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
