<?php

namespace Database\Seeders;

use App\Models\Pengadaan;
use App\Models\PengadaanItem;
use App\Models\PengadaanLog;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Seed the 7 Core Users
        $userPemohon = User::updateOrCreate(
            ['email' => 'user@kampus.ac.id'],
            [
                'name' => 'Dr. Ir. Budi Santoso, M.T.',
                'nip' => '197508152002121001',
                'password' => $password,
                'role' => User::ROLE_USER,
                'jabatan' => 'Ketua Jurusan Teknologi Informasi & Komputer',
                'unit_kerja' => 'Jurusan TIK',
                'phone' => '081234567890',
                'is_active' => true,
            ]
        );

        $userWadir2 = User::updateOrCreate(
            ['email' => 'wadir2@kampus.ac.id'],
            [
                'name' => 'Prof. Dr. Ahmad Dahlan, M.Sc.',
                'nip' => '196803121994031002',
                'password' => $password,
                'role' => User::ROLE_WADIR2,
                'jabatan' => 'Wakil Direktur 2 Bidang Keuangan dan Umum',
                'unit_kerja' => 'Pimpinan Politeknik / Kampus',
                'phone' => '081298765432',
                'is_active' => true,
            ]
        );

        $userPerencanaan = User::updateOrCreate(
            ['email' => 'perencanaan@kampus.ac.id'],
            [
                'name' => 'Dra. Siti Aminah, M.Ak.',
                'nip' => '197211041998022001',
                'password' => $password,
                'role' => User::ROLE_PERENCANAAN,
                'jabatan' => 'Koordinator Subbagian Perencanaan & Anggaran',
                'unit_kerja' => 'Bagian Perencanaan & Sistem Informasi',
                'phone' => '081377889900',
                'is_active' => true,
            ]
        );

        $userPpbj = User::updateOrCreate(
            ['email' => 'ppbj@kampus.ac.id'],
            [
                'name' => 'Rahmat Hidayat, S.T., M.Kom.',
                'nip' => '198105202008121003',
                'password' => $password,
                'role' => User::ROLE_PPBJ,
                'jabatan' => 'Koordinator Pengelola Pengadaan Barang & Jasa (PPBJ)',
                'unit_kerja' => 'Unit Layanan Pengadaan (ULP / PPBJ)',
                'phone' => '081512345678',
                'is_active' => true,
            ]
        );

        $userPpk = User::updateOrCreate(
            ['email' => 'ppk@kampus.ac.id'],
            [
                'name' => 'Ir. Hendra Gunawan, M.T.',
                'nip' => '197009181997021002',
                'password' => $password,
                'role' => User::ROLE_PPK_PP,
                'jabatan' => 'Pejabat Pembuat Komitmen (PPK)',
                'unit_kerja' => 'PPK & Pejabat Pengadaan',
                'phone' => '081734567891',
                'is_active' => true,
            ]
        );

        $userPerlengkapan = User::updateOrCreate(
            ['email' => 'perlengkapan@kampus.ac.id'],
            [
                'name' => 'Agus Setiawan, S.Sos.',
                'nip' => '198402142010121004',
                'password' => $password,
                'role' => User::ROLE_PERLENGKAPAN,
                'jabatan' => 'Pengurus BMN & Koordinator Perlengkapan',
                'unit_kerja' => 'Subbagian Perlengkapan & BMN',
                'phone' => '081856789012',
                'is_active' => true,
            ]
        );

        $userKeuangan = User::updateOrCreate(
            ['email' => 'keuangan@kampus.ac.id'],
            [
                'name' => 'Maya Indrawati, S.E., Ak.',
                'nip' => '198606302012122005',
                'password' => $password,
                'role' => User::ROLE_KEUANGAN,
                'jabatan' => 'Bendahara Pengeluaran / Bagian Keuangan',
                'unit_kerja' => 'Subbagian Keuangan',
                'phone' => '081912344321',
                'is_active' => true,
            ]
        );

        // 2. Seed Vendors
        $vendor1 = Vendor::updateOrCreate(
            ['nama_perusahaan' => 'PT Dharma Santosa Sejahtera'],
            [
                'npwp' => '01.234.567.8-012.000',
                'nib' => '9120001234567',
                'alamat' => 'Jl. Boulevard Raya No. 45, Jakarta Pusat',
                'nama_kontak' => 'Bambang Trihatmojo',
                'telepon' => '021-4567890',
                'email' => 'info@dharmasantosa.co.id',
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '123-00-9876543-2',
                'nama_rekening' => 'PT Dharma Santosa Sejahtera',
                'file_legalitas' => 'dokumen_legalitas_dharma_santosa.pdf',
                'is_active' => true,
            ]
        );

        $vendor2 = Vendor::updateOrCreate(
            ['nama_perusahaan' => 'CV Nusa Nitra Mandiri'],
            [
                'npwp' => '02.345.678.9-034.000',
                'nib' => '8120009876543',
                'alamat' => 'Kawasan Industri Cikarang Blok C-12, Bekasi',
                'nama_kontak' => 'Dewi Lestari',
                'telepon' => '021-89101112',
                'email' => 'kontak@nusanitra.com',
                'nama_bank' => 'Bank BNI',
                'nomor_rekening' => '0987654321',
                'nama_rekening' => 'CV Nusa Nitra Mandiri',
                'file_legalitas' => 'dokumen_legalitas_nusa_nitra.pdf',
                'is_active' => true,
            ]
        );

        $vendor3 = Vendor::updateOrCreate(
            ['nama_perusahaan' => 'PT Amma Karya Mandiri'],
            [
                'npwp' => '03.456.789.0-056.000',
                'nib' => '7120004567890',
                'alamat' => 'Ruko Mitra Niaga No. 8, Depok',
                'nama_kontak' => 'Faisal Rahman',
                'telepon' => '021-7788990',
                'email' => 'marketing@ammakarya.com',
                'nama_bank' => 'Bank BRI',
                'nomor_rekening' => '0123-01-001234-53-1',
                'nama_rekening' => 'PT Amma Karya Mandiri',
                'file_legalitas' => 'dokumen_legalitas_amma_karya.pdf',
                'is_active' => true,
            ]
        );

        // 3. Seed Realistic Pengadaan Cases

        // Case 1: Pengadaan Bahan Praktikum Mahasiswa TIK (Rp145.000.000 -> Pejabat Pengadaan) - Status: Barang Diterima
        $pengadaan1 = Pengadaan::updateOrCreate(
            ['nomor_pengadaan' => 'PBJ/2026/03/001'],
            [
                'user_id' => $userPemohon->id,
                'nama_pengadaan' => 'Pengadaan Bahan Habis Pakai (BHP) Praktikum Mahasiswa Jurusan TIK Tahun 2026',
                'jenis_pengadaan' => 'Barang',
                'jenis_belanja' => 'Belanja Barang',
                'latar_belakang' => 'Kebutuhan bahan praktikum pemrograman, jaringan, dan IoT bagi mahasiswa Jurusan TIK semester ganjil/genap tahun anggaran 2026.',
                'estimasi_anggaran' => 145000000,
                'nomor_surat_user' => '181/PL/TIK/BHP/2026',
                'tanggal_surat_user' => '2026-03-06',
                'no_draft_srikandi' => 'SRIKANDI-2026-TIK-00892',
                'file_surat_permohonan' => 'surat_permohonan_bhp_tik.pdf',
                'status' => Pengadaan::STATUS_BARANG_DITERIMA,
                'jalur_pengadaan' => Pengadaan::JALUR_PP,
                // Wadir 2
                'wadir2_user_id' => $userWadir2->id,
                'catatan_wadir2' => 'Disetujui. Silakan diproses oleh bagian Perencanaan dan PPBJ sesuai alur standar.',
                'tanggal_persetujuan_wadir2' => '2026-03-08 10:15:00',
                // Perencanaan
                'perencanaan_user_id' => $userPerencanaan->id,
                'nomor_mak' => '2026.024.BHP.05.521811.TIK',
                'pagu_anggaran' => 145000000,
                'sumber_dana' => 'PNBP',
                'tanggal_mak' => '2026-03-10',
                'catatan_perencanaan' => 'Alokasi MAK telah diverifikasi pada DIPA tahun berjalan.',
                // PPBJ
                'ppbj_user_id' => $userPpbj->id,
                'nomor_memo_ppbj' => 'MEMO-PPBJ/2026/03/012',
                'tanggal_registrasi_ppbj' => '2026-03-12 09:00:00',
                'vendor_id' => $vendor1->id,
                'metode_pengadaan' => 'E-Katalog LKPP',
                'nomor_spk' => 'SPK-EP-01KR2QJPZW7GDS89715GPB3Z1Q',
                'tanggal_spk' => '2026-03-15',
                'nilai_kontrak' => 143500000,
                'tanggal_mulai' => '2026-03-16',
                'tanggal_selesai_jadwal' => '2026-04-10',
                'file_spk' => 'SPK_Pengadaan_BHP_TIK.pdf',
                'tembusan_perlengkapan' => true,
                'tembusan_keuangan' => true,
                'klasifikasi_alarm' => 'H-2 Jatuh Tempo',
                // PPK review
                'ppk_user_id' => $userPpk->id,
                'status_reviu_ppk' => 'disetujui',
                'catatan_ppk' => 'Spesifikasi teknis dan HPS pembanding telah sesuai. Disetujui untuk diterbitkan SPK.',
                'tanggal_reviu_ppk' => '2026-03-14 14:30:00',
                // Perlengkapan
                'perlengkapan_user_id' => $userPerlengkapan->id,
                'status_penerimaan' => 'Terima',
                'nama_penerima' => 'Agus Setiawan, S.Sos. (Didampingi Teknisi Lab TIK)',
                'unit_penerima' => 'Bagian Perlengkapan & BMN',
                'tanggal_penerimaan' => '2026-04-08 11:20:00',
                'foto_dokumentasi_barang' => 'dokumentasi_penerimaan_bhp_tik.jpg',
                'ttd_digital' => 'Agus_Setiawan_Perlengkapan_Verified_DigitalSignature',
                'catatan_penerimaan' => 'Barang telah diperiksa fisik dan kelengkapannya 100% baik, cocok dengan spesifikasi SPK.',
            ]
        );

        // Items for Pengadaan 1
        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'nama_barang' => 'Kit Mikrokontroler Arduino Uno R4 & Sensor IoT',
            'spesifikasi' => 'Original ARM Cortex-M4 dengan modul Wifi/Bluetooth terintegrasi',
            'volume' => 50,
            'satuan' => 'Paket',
            'harga_satuan' => 650000,
            'total_harga' => 32500000,
        ]);
        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'nama_barang' => 'Raspberry Pi 5 Model B 8GB RAM',
            'spesifikasi' => 'Quad-core 2.4GHz 64-bit ARM Cortex-A76 CPU + Power Supply 27W USB-C',
            'volume' => 30,
            'satuan' => 'Unit',
            'harga_satuan' => 1750000,
            'total_harga' => 52500000,
        ]);
        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'nama_barang' => 'Kabel UTP Cat 6 (305 Meter/Roll) & RJ45 Connector',
            'spesifikasi' => 'Belden Cat6 Pure Copper + 5 Box RJ45 Cat6 Commscope',
            'volume' => 20,
            'satuan' => 'Roll',
            'harga_satuan' => 2925000,
            'total_harga' => 58500000,
        ]);

        // Case 2: Perbaikan & Servis Mesin CNC TU-3A (< Rp50.000.000 -> Pengadaan Langsung) - Status: Disetujui PPK
        $pengadaan2 = Pengadaan::updateOrCreate(
            ['nomor_pengadaan' => 'PBJ/2026/03/002'],
            [
                'user_id' => $userPemohon->id,
                'nama_pengadaan' => 'Servis dan Penggantian Sparepart Mesin CNC TU-3A Laboratorium Mesin',
                'jenis_pengadaan' => 'Jasa Lainnya',
                'jenis_belanja' => 'Belanja Barang',
                'latar_belakang' => 'Perbaikan darurat spindle controller dan kalibrasi motor servo mesin CNC TU-3A guna kelancaran praktikum manufaktur.',
                'estimasi_anggaran' => 38500000,
                'nomor_surat_user' => '045/PL/MESIN/CNC/2026',
                'tanggal_surat_user' => '2026-03-01',
                'no_draft_srikandi' => 'SRIKANDI-2026-MESIN-00124',
                'file_surat_permohonan' => 'surat_permohonan_mesin_cnc.pdf',
                'status' => Pengadaan::STATUS_DISETUJUI_PPK,
                'jalur_pengadaan' => Pengadaan::JALUR_LANGSUNG,
                'wadir2_user_id' => $userWadir2->id,
                'catatan_wadir2' => 'Setuju, prioritas praktikum semester genap.',
                'tanggal_persetujuan_wadir2' => '2026-03-02 09:30:00',
                'perencanaan_user_id' => $userPerencanaan->id,
                'nomor_mak' => '2026.012.SRV.02.523121.MSN',
                'pagu_anggaran' => 38500000,
                'sumber_dana' => 'RM',
                'tanggal_mak' => '2026-03-03',
                'ppbj_user_id' => $userPpbj->id,
                'nomor_memo_ppbj' => 'MEMO-PPBJ/2026/03/004',
                'tanggal_registrasi_ppbj' => '2026-03-04 11:00:00',
                'vendor_id' => $vendor2->id,
                'metode_pengadaan' => 'Pembelian Langsung',
                'ppk_user_id' => $userPpk->id,
                'status_reviu_ppk' => 'disetujui',
                'catatan_ppk' => 'Penawaran harga wajar, siap penerbitan SPK langsung.',
                'tanggal_reviu_ppk' => '2026-03-05 13:00:00',
            ]
        );

        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan2->id,
            'nama_barang' => 'Spindle Drive Motor & Controller Emco TU-3A',
            'spesifikasi' => 'Original replacement unit part no 223-991',
            'volume' => 1,
            'satuan' => 'Set',
            'harga_satuan' => 24500000,
            'total_harga' => 24500000,
        ]);
        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan2->id,
            'nama_barang' => 'Jasa Kalibrasi & Servis Presisi Axis X-Y-Z',
            'spesifikasi' => 'Kalibrasi laser interferometri dan uji toleransi',
            'volume' => 1,
            'satuan' => 'Pekerjaan',
            'harga_satuan' => 14000000,
            'total_harga' => 14000000,
        ]);

        // Case 3: Snack Wisuda (> Rp200.000.000 -> PPK) - Status: Diajukan (Menunggu Wadir 2)
        $pengadaan3 = Pengadaan::updateOrCreate(
            ['nomor_pengadaan' => 'PBJ/2026/03/003'],
            [
                'user_id' => $userPemohon->id,
                'nama_pengadaan' => 'Pengadaan Konsumsi & Snack Rapat Terbuka Senat Wisuda Sarjana Terapan & Diploma 2026',
                'jenis_pengadaan' => 'Jasa Lainnya',
                'latar_belakang' => 'Penyediaan konsumsi kotak berat dan snack box untuk 2.500 wisudawan, orang tua pendamping, dan panitia pelaksana selama 2 hari acara.',
                'estimasi_anggaran' => 225000000,
                'nomor_surat_user' => '015/PAN-WISUDA/KONS/2026',
                'tanggal_surat_user' => '2026-03-09',
                'no_draft_srikandi' => 'SRIKANDI-2026-WISUDA-00511',
                'file_surat_permohonan' => 'surat_permohonan_snack_wisuda.pdf',
                'status' => Pengadaan::STATUS_DIAJUKAN,
                'jalur_pengadaan' => Pengadaan::JALUR_PPK,
            ]
        );

        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan3->id,
            'nama_barang' => 'Snack Box Premium Acara Wisuda (2 Hari)',
            'spesifikasi' => '3 Macam Kue Tradisional/Modern + Mineral 330ml',
            'volume' => 5000,
            'satuan' => 'Kotak',
            'harga_satuan' => 20000,
            'total_harga' => 100000000,
        ]);
        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan3->id,
            'nama_barang' => 'Makan Siang Prasmanan Tamu VIP & Senat',
            'spesifikasi' => 'Menu Lengkap Daging/Ayam/Ikan + Dessert & Minuman Juice',
            'volume' => 500,
            'satuan' => 'Porsi',
            'harga_satuan' => 150000,
            'total_harga' => 75000000,
        ]);
        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan3->id,
            'nama_barang' => 'Makan Kotak Panitia Pelaksana & Petugas Keamanan',
            'spesifikasi' => 'Nasi Kotak Ayam Panggang + Buah + Air Mineral 600ml',
            'volume' => 1000,
            'satuan' => 'Kotak',
            'harga_satuan' => 50000,
            'total_harga' => 50000000,
        ]);

        // Case 4: Selesai & Lunas (Komputer PC All In One)
        $pengadaan4 = Pengadaan::updateOrCreate(
            ['nomor_pengadaan' => 'PBJ/2026/02/004'],
            [
                'user_id' => $userPemohon->id,
                'nama_pengadaan' => 'Pengadaan Komputer All-in-One Laboratorium Multimedia',
                'jenis_pengadaan' => 'Barang',
                'jenis_belanja' => 'Belanja Modal',
                'latar_belakang' => 'Pembaruan sarana komputer untuk praktikum desain grafis dan animasi mahasiswa.',
                'estimasi_anggaran' => 180000000,
                'nomor_surat_user' => '088/PL/TIK/LAB/2026',
                'tanggal_surat_user' => '2026-02-01',
                'no_draft_srikandi' => 'SRIKANDI-2026-TIK-00331',
                'file_surat_permohonan' => 'surat_aio_pc.pdf',
                'status' => Pengadaan::STATUS_SELESAI,
                'jalur_pengadaan' => Pengadaan::JALUR_PP,
                'wadir2_user_id' => $userWadir2->id,
                'catatan_wadir2' => 'Disetujui untuk meningkatkan akreditasi prodi.',
                'tanggal_persetujuan_wadir2' => '2026-02-03 11:00:00',
                'perencanaan_user_id' => $userPerencanaan->id,
                'nomor_mak' => '2026.045.MODAL.01.532111.TIK',
                'pagu_anggaran' => 180000000,
                'sumber_dana' => 'RM',
                'tanggal_mak' => '2026-02-05',
                'ppbj_user_id' => $userPpbj->id,
                'nomor_memo_ppbj' => 'MEMO-PPBJ/2026/02/001',
                'tanggal_registrasi_ppbj' => '2026-02-06 08:30:00',
                'vendor_id' => $vendor3->id,
                'metode_pengadaan' => 'E-Katalog LKPP',
                'nomor_spk' => 'SPK-2026-AIO-019',
                'tanggal_spk' => '2026-02-09',
                'nilai_kontrak' => 176000000,
                'tanggal_mulai' => '2026-02-10',
                'tanggal_selesai_jadwal' => '2026-02-25',
                'file_spk' => 'spk_aio_pc.pdf',
                'tembusan_perlengkapan' => true,
                'tembusan_keuangan' => true,
                'ppk_user_id' => $userPpk->id,
                'status_reviu_ppk' => 'disetujui',
                'catatan_ppk' => 'Sesuai spek teknis e-katalog.',
                'tanggal_reviu_ppk' => '2026-02-08 10:00:00',
                'perlengkapan_user_id' => $userPerlengkapan->id,
                'status_penerimaan' => 'Terima',
                'nama_penerima' => 'Agus Setiawan, S.Sos.',
                'unit_penerima' => 'Bagian Perlengkapan',
                'tanggal_penerimaan' => '2026-02-24 14:00:00',
                'foto_dokumentasi_barang' => 'foto_pc_aio_lab.jpg',
                'ttd_digital' => 'Agus_Setiawan_Perlengkapan_Verified',
                'catatan_penerimaan' => '10 unit PC AIO diterima dalam kondisi segel dan berfungsi prima.',
                'file_dokumen_pembayaran' => 'berkas_pembayaran_pc_aio.pdf',
                'catatan_pengajuan_pembayaran' => 'Kelengkapan BAST, faktur pajak, dan kuitansi lengkap.',
                'tanggal_pengajuan_pembayaran' => '2026-02-26 10:00:00',
                'acc_pembayaran_ppk' => true,
                'tanggal_acc_pembayaran_ppk' => '2026-02-26 14:00:00',
                'catatan_pembayaran_ppk' => 'ACC pembayaran.',
                'acc_pembayaran_wadir2' => true,
                'tanggal_acc_pembayaran_wadir2' => '2026-02-27 09:00:00',
                'catatan_pembayaran_wadir2' => 'Disetujui pencairan.',
                'nomor_spp' => 'SPP/2026/02/054',
                'tanggal_spp' => '2026-02-27',
                'keuangan_user_id' => $userKeuangan->id,
                'nomor_bukti_bayar' => 'TRF-BMRI-20260228-88391',
                'tanggal_bayar' => '2026-02-28',
                'file_bukti_bayar' => 'bukti_transfer_bank_mandiri.pdf',
                'catatan_keuangan' => 'Dana telah ditransfer ke rekening PT Amma Karya Mandiri via CMS Bank Mandiri.',
            ]
        );

        PengadaanItem::firstOrCreate([
            'pengadaan_id' => $pengadaan4->id,
            'nama_barang' => 'PC All-in-One Core i7 16GB RAM SSD 1TB 27 Inch Display',
            'spesifikasi' => 'Windows 11 Pro Original + Keyboard & Mouse Wireless',
            'volume' => 10,
            'satuan' => 'Unit',
            'harga_satuan' => 17600000,
            'total_harga' => 176000000,
        ]);

        // 4. Seed Audit Logs
        PengadaanLog::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'user_id' => $userPemohon->id,
            'action' => 'Pengajuan Permohonan',
            'keterangan' => 'User mengajukan permohonan pengadaan barang/jasa baru.',
            'status_sebelumnya' => null,
            'status_baru' => Pengadaan::STATUS_DIAJUKAN,
            'ip_address' => '127.0.0.1',
        ]);
        PengadaanLog::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'user_id' => $userWadir2->id,
            'action' => 'Persetujuan Wadir 2',
            'keterangan' => 'Wadir 2 menyetujui surat permohonan pengadaan.',
            'status_sebelumnya' => Pengadaan::STATUS_DIAJUKAN,
            'status_baru' => Pengadaan::STATUS_DISETUJUI_WADIR2,
            'ip_address' => '127.0.0.1',
        ]);
        PengadaanLog::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'user_id' => $userPerencanaan->id,
            'action' => 'Penetapan MAK & Anggaran',
            'keterangan' => 'Bagian Perencanaan menetapkan MAK: 2026.024.BHP.05.521811.TIK',
            'status_sebelumnya' => Pengadaan::STATUS_DISETUJUI_WADIR2,
            'status_baru' => Pengadaan::STATUS_MAK_DITETAPKAN,
            'ip_address' => '127.0.0.1',
        ]);
        PengadaanLog::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'user_id' => $userPerlengkapan->id,
            'action' => 'Penerimaan Barang & BAST',
            'keterangan' => 'Bagian Perlengkapan menerima barang dan menandatangani digital BAST.',
            'status_sebelumnya' => Pengadaan::STATUS_SPK_DITERBITKAN,
            'status_baru' => Pengadaan::STATUS_BARANG_DITERIMA,
            'ip_address' => '127.0.0.1',
        ]);

        // 5. Seed Reminder for Alarm Popup (FR 12.0)
        Reminder::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'target_role' => 'ppbj',
            'tipe' => 'jadwal_pengiriman',
            'judul' => 'Pengingat: Jadwal Penerimaan Barang Pengadaan BHP TIK',
            'pesan' => 'Jadwal penyelesaian dan pengiriman barang oleh PT Dharma Santosa Sejahtera pada 10 April 2026.',
            'tanggal_ingat' => '2026-04-10',
            'is_read' => false,
            'is_dismissed' => false,
        ]);
        Reminder::firstOrCreate([
            'pengadaan_id' => $pengadaan1->id,
            'target_role' => 'perlengkapan',
            'tipe' => 'tembusan_spk',
            'judul' => 'Tembusan SPK: Pengadaan BHP TIK > Rp50 Juta',
            'pesan' => 'Tembusan SPK otomatis untuk Bagian Perlengkapan guna persiapan penerimaan barang di gudang BMN.',
            'tanggal_ingat' => '2026-03-15',
            'is_read' => false,
            'is_dismissed' => false,
        ]);
    }
}
