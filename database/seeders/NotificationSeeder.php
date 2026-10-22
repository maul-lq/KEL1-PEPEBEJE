<?php

namespace Database\Seeders;

use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder Notifikasi dan Reminder Sistem SIM-PBJ (NotificationSeeder)
 *
 * Membenihkan rekaman data notifikasi dan alarm pengingat realistis sesuai rancangan visual Figma 121:45:
 * - Notifikasi Pemohon (User): Pengingat revisi berkas usulan permohonan (032/PL3/TE/2026 · Komponen praktikum digital).
 * - Notifikasi Verifikator (Staf Bidang 2, Wadir 2, Perencanaan): Tugas telaah dan persetujuan usulan baru & MAK.
 * - Notifikasi Pengadaan (PPBJ, PP, 4 Akun PPK): Penugasan PPK, reviu teknis, dan SPK.
 * - Notifikasi Penerimaan (Perlengkapan): Peringatan jadwal pengiriman barang dan BAST HMAC.
 * - Notifikasi Keuangan: Peringatan jatuh tempo termin pembayaran dan pencairan dana SP2D.
 */
class NotificationSeeder extends Seeder
{
    /**
     * Menjalankan pembenihan data notifikasi dan pengingat ke database.
     */
    public function run(): void
    {
        // 1. Dapatkan referensi akun pengguna dari database (atau buat akun pendukung jika belum ada)
        $userPengaju = User::where('username', 'user_pengaju')->first() ?? User::where('role', 'user')->first() ?? User::factory()->create(['role' => 'user', 'username' => 'user_pengaju', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $staffB2 = User::where('username', 'staff_b2')->first() ?? User::where('role', 'staff_bidang_2')->first() ?? User::factory()->create(['role' => 'staff_bidang_2', 'username' => 'staff_b2', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $wadir2 = User::where('username', 'wadir2')->first() ?? User::where('role', 'wadir_2')->first() ?? User::factory()->create(['role' => 'wadir_2', 'username' => 'wadir2', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $perencanaan = User::where('username', 'perencanaan')->first() ?? User::where('role', 'perencanaan')->first() ?? User::factory()->create(['role' => 'perencanaan', 'username' => 'perencanaan', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $ppbj = User::where('username', 'ppbj')->first() ?? User::where('role', 'ppbj')->first() ?? User::factory()->create(['role' => 'ppbj', 'username' => 'ppbj', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $pp = User::where('username', 'pp_user')->first() ?? User::where('role', 'pp')->first() ?? User::factory()->create(['role' => 'pp', 'username' => 'pp_user', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $ppk1 = User::where('username', 'ppk_1')->first() ?? User::where('role', 'ppk')->first() ?? User::factory()->create(['role' => 'ppk', 'username' => 'ppk_1', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $perlengkapan = User::where('username', 'perlengkapan')->first() ?? User::where('role', 'perlengkapan')->first() ?? User::factory()->create(['role' => 'perlengkapan', 'username' => 'perlengkapan', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $keuangan = User::where('username', 'keuangan')->first() ?? User::where('role', 'keuangan')->first() ?? User::factory()->create(['role' => 'keuangan', 'username' => 'keuangan', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);
        $admin = User::where('username', 'admin')->first() ?? User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'username' => 'admin', 'kode_unit' => 'TIK', 'status_aktif' => 'aktif']);

        // 2. Buat atau perbarui data usulan permohonan sesuai sampel Figma: 032/PL3/TE/2026
        if ($userPengaju) {
            $permohonanFigma = PermohonanPengadaan::updateOrCreate(
                ['nomor_surat' => '032/PL3/TE/2026'],
                [
                    'judul_pengadaan' => 'Komponen praktikum digital',
                    'tujuan_pengadaan_ringkas' => 'Pengadaan modul mikrokontroler dan sensor untuk praktikum laboratorium Teknik Elektro',
                    'file_permohonan' => 'srikandi/032_PL3_TE_2026.pdf',
                    'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
                    'status_anggaran' => 'menunggu',
                    'revisi_ke' => 1,
                    'estimasi_total_pagu' => 45000000.00,
                    'tanggal_pengajuan' => Carbon::parse('2026-01-25 09:30:00'),
                    'kode_unit' => 'TE',
                    'id_user' => $userPengaju->id_user,
                    'alasan_revisi' => 'Lengkapi rincian jumlah komponen dan spesifikasi teknis datasheet modul.',
                ]
            );

            // Paket Pengadaan pendukung nomor surat 032/PL3/TE/2026
            $paketFigma = PaketPengadaan::updateOrCreate(
                ['nomor_paket' => 'PKT-TE-2026-032'],
                [
                    'nama_paket' => 'Paket Komponen Praktikum Digital Laboratorium TE',
                    'jenis_pengadaan' => 'barang',
                    'platform_pengadaan' => 'spse',
                    'mekanisme_pengadaan' => 'pengadaan_langsung',
                    'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
                    'nilai_hps' => 42000000.00,
                    'alokasi_pagu_paket' => 45000000.00,
                    'status_paket' => 'draft',
                    'nomor_surat' => $permohonanFigma->nomor_surat,
                    'tanggal_registrasi_paket' => now()->subDays(2),
                    'id_user_ppbj' => $ppbj->id_user,
                    'id_user_ppk' => $ppk1->id_user,
                ]
            );

            // Permohonan & Paket pendukung nomor surat 001/PL3.TIK/PG/2026
            $permohonanTik = PermohonanPengadaan::updateOrCreate(
                ['nomor_surat' => '001/PL3.TIK/PG/2026'],
                [
                    'judul_pengadaan' => 'Pengadaan Server dan GPU Workstation AI Lab Komputasi',
                    'tujuan_pengadaan_ringkas' => 'Mendukung riset GenAI dan pembelajaran machine learning mahasiswa TIK',
                    'file_permohonan' => 'srikandi/001_PL3_TIK_2026.pdf',
                    'status_permohonan' => 'disetujui',
                    'status_anggaran' => 'disetujui',
                    'revisi_ke' => 0,
                    'estimasi_total_pagu' => 240000000.00,
                    'tanggal_pengajuan' => now()->subDays(5),
                    'kode_unit' => 'TIK',
                    'id_user' => $userPengaju->id_user,
                ]
            );

            $paketTik = PaketPengadaan::updateOrCreate(
                ['nomor_paket' => 'PKT-TIK-2026-001'],
                [
                    'nama_paket' => 'Paket Pengadaan Server GPU Lab AI TIK',
                    'jenis_pengadaan' => 'barang',
                    'platform_pengadaan' => 'spse',
                    'mekanisme_pengadaan' => 'tender',
                    'jalur_routing' => PaketPengadaan::ROUTING_PPK,
                    'nilai_hps' => 235000000.00,
                    'alokasi_pagu_paket' => 240000000.00,
                    'status_paket' => 'spk_terbit',
                    'nomor_surat' => $permohonanTik->nomor_surat,
                    'tanggal_registrasi_paket' => now()->subDays(3),
                    'id_user_ppbj' => $ppbj->id_user,
                    'id_user_ppk' => $ppk1->id_user,
                ]
            );

            // Sampel 1 (Figma Default - Unread): Pengingat revisi permohonan saya
            $reminderFigma = Reminder::updateOrCreate(
                [
                    'judul_alert' => 'Pengingat revisi permohonan saya',
                    'nomor_paket' => $paketFigma->nomor_paket,
                ],
                [
                    'tipe_reminder' => 'pengiriman',
                    'waktu_trigger' => Carbon::parse('2026-01-29 08:00:00'),
                    'is_recurring' => false,
                    'interval_bulan' => null,
                    'pesan_alert' => 'Pengingat untuk Jerry: revisi yang diminta Bidang 2 belum dikirim. Jadwal tindak lanjut Anda: 30 Januari 2026; lengkapi rincian jumlah komponen.',
                ]
            );

            NotifikasiUser::updateOrCreate(
                [
                    'id_user' => $userPengaju->id_user,
                    'id_reminder' => $reminderFigma->id_reminder,
                ],
                [
                    'is_read' => false,
                    'is_dismissed' => false,
                    'created_at' => Carbon::parse('2026-01-29 08:00:00'),
                ]
            );

            // Sampel 2 (Unread): Alokasi Pagu & Usulan Server AI
            $reminderUser2 = Reminder::updateOrCreate(
                [
                    'judul_alert' => 'Pemberitahuan Persetujuan Usulan Pengadaan',
                    'nomor_paket' => 'PKT-TIK-2026-001',
                ],
                [
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => Carbon::parse('2026-01-28 14:15:00'),
                    'is_recurring' => false,
                    'pesan_alert' => 'Usulan 001/PL3.TIK/PG/2026 telah disetujui penuh oleh Staf Bidang 2 dan Wadir 2. Berkas telah dialihkan ke PPBJ untuk pembuatan paket tender.',
                ]
            );

            NotifikasiUser::updateOrCreate(
                [
                    'id_user' => $userPengaju->id_user,
                    'id_reminder' => $reminderUser2->id_reminder,
                ],
                [
                    'is_read' => false,
                    'is_dismissed' => false,
                    'created_at' => Carbon::parse('2026-01-28 14:15:00'),
                ]
            );

            // Sampel 3 (Read): Penguncian Kode MAK
            $reminderUser3 = Reminder::updateOrCreate(
                [
                    'judul_alert' => 'Verifikasi Anggaran MAK-TIK-2026-001 Selesai',
                    'nomor_paket' => 'PKT-TIK-2026-001',
                ],
                [
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => Carbon::parse('2026-01-27 11:00:00'),
                    'is_recurring' => false,
                    'pesan_alert' => 'Unit Perencanaan telah mengunci alokasi anggaran Rp 240.000.000 bersumber dari BOPTN 2026 untuk usulan pengadaan Anda.',
                ]
            );

            NotifikasiUser::updateOrCreate(
                [
                    'id_user' => $userPengaju->id_user,
                    'id_reminder' => $reminderUser3->id_reminder,
                ],
                [
                    'is_read' => true,
                    'is_dismissed' => false,
                    'waktu_dibaca' => Carbon::parse('2026-01-27 16:30:00'),
                    'created_at' => Carbon::parse('2026-01-27 11:00:00'),
                ]
            );
        }

        // 3. Notifikasi untuk Staf Bidang 2
        if ($staffB2) {
            $remB2 = Reminder::updateOrCreate(
                ['judul_alert' => 'Telaah Berkas Usulan Permohonan Masuk'],
                [
                    'nomor_paket' => $paketFigma->nomor_paket,
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subDay(),
                    'is_recurring' => false,
                    'pesan_alert' => 'Usulan 032/PL3/TE/2026 dari Jurusan TE memerlukan pemeriksaan kelengkapan berkas KAK dan spesifikasi harga pasar.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $staffB2->id_user, 'id_reminder' => $remB2->id_reminder],
                ['is_read' => false, 'created_at' => now()->subDay()]
            );
        }

        // 4. Notifikasi untuk Wadir 2
        if ($wadir2) {
            $remW2 = Reminder::updateOrCreate(
                ['judul_alert' => 'Permohonan Memerlukan Persetujuan Eksekutif Wadir 2'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subHours(6),
                    'is_recurring' => false,
                    'pesan_alert' => 'Usulan 001/PL3.TIK/PG/2026 telah ditelaah Staf Bidang 2. Mohon lakukan persetujuan atau penolakan resmi bersurat.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $wadir2->id_user, 'id_reminder' => $remW2->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(6)]
            );
        }

        // 5. Notifikasi untuk Perencanaan
        if ($perencanaan) {
            $remPer = Reminder::updateOrCreate(
                ['judul_alert' => 'Validasi Pagu MAK DIPA Usulan Masuk'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subHours(12),
                    'is_recurring' => false,
                    'pesan_alert' => 'Permohonan 001/PL3.TIK/PG/2026 memerlukan verifikasi pagu MAK dan ketersediaan anggaran DIPA PNJ 2026.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $perencanaan->id_user, 'id_reminder' => $remPer->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(12)]
            );
        }

        // 6. Notifikasi untuk PPBJ
        if ($ppbj) {
            $remPbj1 = Reminder::updateOrCreate(
                ['judul_alert' => 'Registrasi Paket Pengadaan & Penetapan PPK'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subHours(5),
                    'is_recurring' => false,
                    'pesan_alert' => 'Usulan 001/PL3.TIK/PG/2026 bernilai di atas Rp 200 Juta telah disetujui. Koordinator PPBJ perlu menetapkan akun PPK penanggung jawab.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $ppbj->id_user, 'id_reminder' => $remPbj1->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(5)]
            );

            $remPbj2 = Reminder::updateOrCreate(
                ['judul_alert' => 'Penerbitan Surat Perintah Pembayaran (SPP) ke Keuangan'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'pembayaran',
                    'waktu_trigger' => now()->subHours(2),
                    'is_recurring' => false,
                    'pesan_alert' => 'Seluruh memo bayar bertingkat telah disetujui oleh PPK dan Wadir 2. Silakan reviu kelengkapan berkas dan terbitkan SPP resmi.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $ppbj->id_user, 'id_reminder' => $remPbj2->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(2)]
            );
        }

        // 7. Notifikasi untuk PP (Pejabat Pengadaan)
        if ($pp) {
            $remPp = Reminder::updateOrCreate(
                ['judul_alert' => 'Verifikasi Teknis & Negosiasi Harga Pengadaan'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subHours(8),
                    'is_recurring' => false,
                    'pesan_alert' => 'Paket pengadaan jalur PP 50jt s.d 200jt telah siap. Harap lakukan verifikasi dokumen dan negosiasi harga bersama calon rekanan.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $pp->id_user, 'id_reminder' => $remPp->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(8)]
            );
        }

        // 8. Notifikasi untuk PPK 1
        if ($ppk1) {
            $remPpk = Reminder::updateOrCreate(
                ['judul_alert' => 'Penugasan PPK & Tinjauan Dokumen Kontrak'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subHours(4),
                    'is_recurring' => false,
                    'pesan_alert' => 'Anda telah ditugaskan mengelola Paket PKT-TIK-2026-001 (Pengadaan Server GPU). Silakan reviu spesifikasi teknis dan kunci Kode MAK.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $ppk1->id_user, 'id_reminder' => $remPpk->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(4)]
            );
        }

        // 9. Notifikasi untuk Unit Perlengkapan
        if ($perlengkapan) {
            $remLkp = Reminder::updateOrCreate(
                ['judul_alert' => 'Pengingat Jadwal Pengiriman Barang oleh Rekanan'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'pengiriman',
                    'waktu_trigger' => now()->subHours(3),
                    'is_recurring' => false,
                    'pesan_alert' => 'PT Tri Mitra Informatika dijadwalkan mengirimkan Server GPU esok hari. Unit Perlengkapan harap bersiap melaksanakan pemeriksaan fisik dan penerbitan BAST.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $perlengkapan->id_user, 'id_reminder' => $remLkp->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(3)]
            );
        }

        // 10. Notifikasi untuk Unit Keuangan
        if ($keuangan) {
            $remKeu = Reminder::updateOrCreate(
                ['judul_alert' => 'Pengingat H-2 Jatuh Tempo Pembayaran Termin SP2D'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'h_minus_2_termin',
                    'waktu_trigger' => now()->subHours(1),
                    'is_recurring' => false,
                    'pesan_alert' => 'Tagihan SPP Termin 1 Pengadaan Server AI mendekati batas jatuh tempo. Bendahara Pengeluaran Pembantu harap memverifikasi kelengkapan berkas transfer.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $keuangan->id_user, 'id_reminder' => $remKeu->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(1)]
            );
        }

        // 11. Notifikasi untuk Admin
        if ($admin) {
            $remAdm = Reminder::updateOrCreate(
                ['judul_alert' => 'Persetujuan Registrasi Pengguna Baru Menunggu'],
                [
                    'nomor_paket' => 'PKT-TIK-2026-001',
                    'tipe_reminder' => 'verifikasi',
                    'waktu_trigger' => now()->subHours(2),
                    'is_recurring' => false,
                    'pesan_alert' => 'Terdapat permohonan pendaftaran akun dosen dan staf baru yang memerlukan verifikasi identitas NIP dan persetujuan hak akses dinas.',
                ]
            );
            NotifikasiUser::updateOrCreate(
                ['id_user' => $admin->id_user, 'id_reminder' => $remAdm->id_reminder],
                ['is_read' => false, 'created_at' => now()->subHours(2)]
            );
        }
    }
}
