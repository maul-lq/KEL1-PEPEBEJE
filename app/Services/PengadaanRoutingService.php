<?php

namespace App\Services;

use App\Models\Pengadaan;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;

class PengadaanRoutingService
{
    /**
     * Tentukan jalur pengadaan otomatis berdasarkan nilai nominal (FR 8.0)
     */
    public static function assignJalur(Pengadaan $pengadaan): void
    {
        $nominal = (float) ($pengadaan->nilai_kontrak ?: $pengadaan->pagu_anggaran ?: $pengadaan->estimasi_anggaran);

        if ($nominal < 50000000) {
            $pengadaan->jalur_pengadaan = Pengadaan::JALUR_LANGSUNG;
        } elseif ($nominal <= 200000000) {
            $pengadaan->jalur_pengadaan = Pengadaan::JALUR_PP;
        } else {
            $pengadaan->jalur_pengadaan = Pengadaan::JALUR_PPK;
        }
    }

    /**
     * Buat reminder / alarm popup pengadaan (FR 12.0)
     */
    public static function generateReminders(Pengadaan $pengadaan): void
    {
        $nominal = (float) ($pengadaan->nilai_kontrak ?: $pengadaan->pagu_anggaran ?: $pengadaan->estimasi_anggaran);

        // 1. Tembusan ke perlengkapan & keuangan jika anggaran RM > 50 juta
        if ($nominal > 50000000) {
            $pengadaan->tembusan_perlengkapan = true;
            $pengadaan->tembusan_keuangan = true;

            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_PERLENGKAPAN,
                'tipe' => 'tembusan_spk',
                'judul' => 'Tembusan SPK: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Tembusan SPK nomor '.($pengadaan->nomor_spk ?? '-').' dengan nilai kontrak Rp '.number_format($nominal, 0, ',', '.').' untuk persiapan penerimaan BMN.',
                'tanggal_ingat' => now()->toDateString(),
            ]);

            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_KEUANGAN,
                'tipe' => 'tembusan_spk',
                'judul' => 'Tembusan SPK: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Tembusan SPK nomor '.($pengadaan->nomor_spk ?? '-').' dengan nilai kontrak Rp '.number_format($nominal, 0, ',', '.').' untuk pencatatan komitmen anggaran.',
                'tanggal_ingat' => now()->toDateString(),
            ]);
        }

        // 2. Reminder Jadwal Pengiriman Barang
        if ($pengadaan->tanggal_selesai_jadwal) {
            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_PPBJ,
                'tipe' => 'jadwal_pengiriman',
                'judul' => 'Pengingat Jadwal Pengiriman: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Tanggal target pengiriman barang oleh penyedia adalah '.Carbon::parse($pengadaan->tanggal_selesai_jadwal)->format('d M Y').'.',
                'tanggal_ingat' => $pengadaan->tanggal_selesai_jadwal,
            ]);

            // H-2 Jatuh Tempo
            $hMinus2 = Carbon::parse($pengadaan->tanggal_selesai_jadwal)->subDays(2)->toDateString();
            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_PPBJ,
                'tipe' => 'h2_jatuh_tempo',
                'judul' => 'H-2 Pengiriman Barang: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Pengadaan memasuki H-2 batas waktu pengiriman barang ('.Carbon::parse($pengadaan->tanggal_selesai_jadwal)->format('d M Y').'). Mohon koordinasi dengan vendor.',
                'tanggal_ingat' => $hMinus2,
            ]);
        }
    }
}
