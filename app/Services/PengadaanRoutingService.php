<?php

namespace App\Services;

use App\Models\Pengadaan;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;

/**
 * Service Penentuan Jalur Pengadaan & Pembangkit Pengingat (PengadaanRoutingService)
 *
 * Mengimplementasikan aturan bisnis:
 * 1. FR 8.0: Auto-routing penentuan jalur pengadaan secara otomatis berdasarkan nilai pagu/kontrak.
 * 2. FR 12.0: Pembangkitan alarm / reminder otomatis untuk pengiriman barang (termasuk peringatan H-2)
 *    dan tembusan otomatis ke Bagian Perlengkapan serta Bagian Keuangan jika nilai kontrak > Rp 50jt.
 */
class PengadaanRoutingService
{
    /**
     * Menentukan jalur pengadaan secara otomatis berdasarkan nilai nominal anggaran/kontrak (FR 8.0).
     *
     * Aturan ambang batas:
     * - Nilai < Rp 50.000.000                => Jalur Pengadaan Langsung (Staf PPBJ)
     * - Rp 50.000.000 <= Nilai <= 200.000.000 => Jalur Pejabat Pengadaan Tunggal (PP)
     * - Nilai > Rp 200.000.000               => Jalur Tender / Seleksi (Pejabat Pembuat Komitmen / PPK)
     *
     * @param  Pengadaan  $pengadaan  Model entitas pengadaan yang akan diset jalur routing-nya
     */
    public static function assignJalur(Pengadaan $pengadaan): void
    {
        // Mengambil nilai nominal prioritas: nilai kontrak definitif, jika kosong gunakan pagu anggaran, atau estimasi
        $nominal = (float) ($pengadaan->nilai_kontrak ?: $pengadaan->pagu_anggaran ?: $pengadaan->estimasi_anggaran);

        // Menetapkan jalur pengadaan sesuai ambang batas nominal regulasi pemerintah
        if ($nominal < 50000000) {
            $pengadaan->jalur_pengadaan = Pengadaan::JALUR_LANGSUNG;
        } elseif ($nominal <= 200000000) {
            $pengadaan->jalur_pengadaan = Pengadaan::JALUR_PP;
        } else {
            $pengadaan->jalur_pengadaan = Pengadaan::JALUR_PPK;
        }
    }

    /**
     * Membangkitkan pengingat (reminder / notifikasi) jadwal pengadaan secara otomatis (FR 12.0).
     *
     * @param  Pengadaan  $pengadaan  Model entitas pengadaan yang akan dibuatkan jadwal pengingatnya
     */
    public static function generateReminders(Pengadaan $pengadaan): void
    {
        $nominal = (float) ($pengadaan->nilai_kontrak ?: $pengadaan->pagu_anggaran ?: $pengadaan->estimasi_anggaran);

        // 1. Ketentuan Tembusan SPK ke Perlengkapan & Keuangan:
        // Jika nilai anggaran/kontrak melebihi Rp 50.000.000, sistem otomatis menerbitkan tembusan
        // agar Perlengkapan bersiap menerima BMN fisik dan Keuangan mencatat komitmen pagu.
        if ($nominal > 50000000) {
            $pengadaan->tembusan_perlengkapan = true;
            $pengadaan->tembusan_keuangan = true;

            // Pengingat untuk Bagian Perlengkapan
            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_PERLENGKAPAN,
                'tipe' => 'tembusan_spk',
                'judul' => 'Tembusan SPK: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Tembusan SPK nomor '.($pengadaan->nomor_spk ?? '-').' dengan nilai kontrak Rp '.number_format($nominal, 0, ',', '.').' untuk persiapan penerimaan BMN.',
                'tanggal_ingat' => now()->toDateString(),
            ]);

            // Pengingat untuk Bagian Keuangan
            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_KEUANGAN,
                'tipe' => 'tembusan_spk',
                'judul' => 'Tembusan SPK: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Tembusan SPK nomor '.($pengadaan->nomor_spk ?? '-').' dengan nilai kontrak Rp '.number_format($nominal, 0, ',', '.').' untuk pencatatan komitmen anggaran.',
                'tanggal_ingat' => now()->toDateString(),
            ]);
        }

        // 2. Pengingat Jadwal Pengiriman Barang oleh Vendor Rekanan
        if ($pengadaan->tanggal_selesai_jadwal) {
            // Pengingat tepat pada tanggal target batas akhir pengiriman
            Reminder::create([
                'pengadaan_id' => $pengadaan->id,
                'target_role' => User::ROLE_PPBJ,
                'tipe' => 'jadwal_pengiriman',
                'judul' => 'Pengingat Jadwal Pengiriman: '.$pengadaan->nama_pengadaan,
                'pesan' => 'Tanggal target pengiriman barang oleh penyedia adalah '.Carbon::parse($pengadaan->tanggal_selesai_jadwal)->format('d M Y').'.',
                'tanggal_ingat' => $pengadaan->tanggal_selesai_jadwal,
            ]);

            // Pengingat Dini H-2 Sebelum Jatuh Tempo Batas Akhir Pengiriman
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
