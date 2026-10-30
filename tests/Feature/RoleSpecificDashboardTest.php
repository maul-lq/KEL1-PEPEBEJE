<?php

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Unit::firstOrCreate(
        ['kode_unit' => 'TIK'],
        ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
    );

    Unit::firstOrCreate(
        ['kode_unit' => 'B2'],
        ['nama_unit' => 'Bidang II (Umum dan Keuangan)', 'jenis_unit' => 'bidang']
    );

    $roles = [
        'admin' => 'Administrator',
        'user' => 'Pemohon / Jurusan',
        'staff_bidang_2' => 'Staf Bidang II',
        'wadir_2' => 'Wakil Direktur II',
        'perencanaan' => 'Subbagian Perencanaan',
        'ppbj' => 'Koordinator PPBJ',
        'pp' => 'Pejabat Pengadaan',
        'ppk' => 'Pejabat Pembuat Komitmen',
        'perlengkapan' => 'Unit Perlengkapan',
        'keuangan' => 'Bagian Keuangan',
    ];

    foreach ($roles as $namaRole => $label) {
        Role::firstOrCreate(
            ['nama_role' => $namaRole],
            ['label' => $label, 'is_system' => true]
        );
    }
});

function createTestUser(string $role, ?string $username = null): User
{
    $roleRecord = Role::where('nama_role', $role)->first();

    return User::factory()->create([
        'nip' => '19850101'.rand(10000000, 99999999),
        'username' => $username ?? $role.'_'.uniqid(),
        'email' => ($username ?? $role.'_'.uniqid()).'@pnj.ac.id',
        'nama' => 'Pejabat '.strtoupper(str_replace('_', ' ', $role)),
        'password' => Hash::make('password123'),
        'jabatan' => 'Jabatan '.strtoupper($role),
        'kode_unit' => $role === 'user' ? 'TIK' : 'B2',
        'role' => $role,
        'id_role' => $roleRecord?->id,
        'status_aktif' => true,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ]);
}

test('admin dashboard renders successfully with role-specific stats and registration approval queue', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $admin = createTestUser('admin');

    // Buat pengguna menunggu persetujuan
    User::factory()->create([
        'nip' => '199901012026011001',
        'username' => 'calon_dosen_baru',
        'email' => 'calondosen@pnj.ac.id',
        'nama' => 'Calon Dosen Baru, M.T.',
        'kode_unit' => 'TIK',
        'role' => 'user',
        'status_aktif' => false,
        'status_registrasi' => 'menunggu_persetujuan',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard ADMIN');
    $response->assertSee('Menunggu Persetujuan Akun');
    $response->assertSee('Total Pengguna Aktif');
    $response->assertSee('Master Role Sistem');
    $response->assertSee('Unit Kerja', false);
    $response->assertSee('Antrean Approval Registrasi');
    $response->assertSee('Calon Dosen Baru, M.T.');
    $response->assertSee('calondosen@pnj.ac.id');
    $response->assertSee('Distribusi Pengguna Berdasarkan Role');
});

test('user dashboard renders successfully with permohonan stats and action queue', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $userPemohon = createTestUser('user');

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '001/TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Laboratorium Jaringan',
        'tujuan_pengadaan_ringkas' => 'Pengadaan switch router',
        'perihal' => 'Pengadaan Laboratorium Jaringan',
        'anggaran_diajukan' => 85000000.00,
        'estimasi_total_pagu' => 85000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $userPemohon->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($userPemohon)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard USER');
    $response->assertSee('Total Usulan Pengadaan');
    $response->assertSee('Sedang Diproses');
    $response->assertSee('Usulan Disetujui');
    $response->assertSee('Perlu Revisi / Ditolak');
    $response->assertSee('Daftar Usulan Permohonan Pengadaan Unit Anda');
    $response->assertSee('001/TIK/PG/2026');
    $response->assertSee('Pengadaan Laboratorium Jaringan');
    $response->assertSee('Ajukan Permohonan Baru');
});

test('user dashboard displays revision alert banner when an usulan is rejected', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $userPemohon = createTestUser('user');

    PermohonanPengadaan::create([
        'nomor_surat' => '002/TIK/PG/REVISI/2026',
        'judul_pengadaan' => 'Pengadaan Server Deep Learning',
        'tujuan_pengadaan_ringkas' => 'Riset AI mahasiswa',
        'perihal' => 'Pengadaan Server Deep Learning',
        'anggaran_diajukan' => 150000000.00,
        'estimasi_total_pagu' => 150000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $userPemohon->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'ditolak',
        'status' => 'ditolak',
        'alasan_revisi' => 'Brosur spesifikasi pabrikan belum dilampirkan.',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($userPemohon)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Perhatian: Terdapat 1 Usulan Pengadaan Memerlukan Revisi');
    $response->assertSee('Catatan Verifikator:');
    $response->assertSee('Brosur spesifikasi pabrikan belum dilampirkan.');
});

test('staff_bidang_2 dashboard renders successfully with examination queue', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $staffB2 = createTestUser('staff_bidang_2');

    PermohonanPengadaan::create([
        'nomor_surat' => '003/TIK/TELAAH/2026',
        'judul_pengadaan' => 'Pengadaan Smart TV Ruang Sidang',
        'tujuan_pengadaan_ringkas' => 'Fasilitas presentasi sidang',
        'perihal' => 'Pengadaan Smart TV Ruang Sidang',
        'anggaran_diajukan' => 45000000.00,
        'estimasi_total_pagu' => 45000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $staffB2->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($staffB2)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard STAFF BIDANG 2');
    $response->assertSee('Menunggu Telaah Berkas');
    $response->assertSee('Dalam Reviu Paralel');
    $response->assertSee('Selesai Diteruskan');
    $response->assertSee('Total Usulan Masuk');
    $response->assertSee('Antrean Telaah Kelengkapan Berkas Srikandi');
    $response->assertSee('003/TIK/TELAAH/2026');
    $response->assertSee('Periksa Berkas');
});

test('wadir_2 dashboard renders successfully with executive disposition and memo queues', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $wadir2 = createTestUser('wadir_2');

    PermohonanPengadaan::create([
        'nomor_surat' => '004/TIK/DISPOSISI/2026',
        'judul_pengadaan' => 'Pengadaan Genset Cadangan Kampus',
        'tujuan_pengadaan_ringkas' => 'Genset darurat',
        'perihal' => 'Pengadaan Genset Cadangan Kampus',
        'anggaran_diajukan' => 210000000.00,
        'estimasi_total_pagu' => 210000000.00,
        'kode_unit' => 'B2',
        'id_user' => $wadir2->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($wadir2)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard WADIR 2');
    $response->assertSee('Menunggu Disposisi / ACC');
    $response->assertSee('Memo Bayar Lvl 2 Pending');
    $response->assertSee('Total Disetujui (ACC)');
    $response->assertSee('Total Ditolak / Revisi');
    $response->assertSee('Antrean Disposisi Eksekutif Pengadaan');
    $response->assertSee('Antrean Memo Bayar Level 2');
    $response->assertSee('004/TIK/DISPOSISI/2026');
    $response->assertSee('Disposisi ACC/Tolak');
});

test('perencanaan dashboard renders successfully with MAK DIPA stats and pending queues', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $perencanaan = createTestUser('perencanaan');

    AnggaranMAK::create([
        'kode_mak' => 'MAK-TEST-M2-001',
        'uraian_mak' => 'Pagu Pengadaan Sarana Prasarana Kampus',
        'pagu_anggaran' => 500000000.00,
        'sisa_pagu_mak' => 350000000.00,
        'sumber_dana' => 'BOPTN',
        'tahun_anggaran' => 2026,
        'is_locked' => false,
        'id_user' => $perencanaan->id_user,
    ]);

    PermohonanPengadaan::create([
        'nomor_surat' => '005/TIK/MAK/2026',
        'judul_pengadaan' => 'Pengadaan Perangkat Audio Visual Aula',
        'tujuan_pengadaan_ringkas' => 'Sound system aula',
        'perihal' => 'Pengadaan Perangkat Audio Visual Aula',
        'anggaran_diajukan' => 75000000.00,
        'estimasi_total_pagu' => 75000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $perencanaan->id_user,
        'kode_mak' => null,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($perencanaan)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard PERENCANAAN');
    $response->assertSee('Total Pagu DIPA');
    $response->assertSee('Pagu Teralokasi');
    $response->assertSee('Sisa Pagu Bebas');
    $response->assertSee('Usulan Menunggu MAK');
    $response->assertSee('Antrean Usulan Menunggu Penetapan MAK');
    $response->assertSee('Rekening MAK DIPA Aktif');
    $response->assertSee('MAK-TEST-M2-001');
    $response->assertSee('005/TIK/MAK/2026');
});

test('ppbj dashboard renders successfully with package routing stats and registration queues', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $ppbj = createTestUser('ppbj');

    PermohonanPengadaan::create([
        'nomor_surat' => '006/TIK/PPBJ/2026',
        'judul_pengadaan' => 'Pengadaan Kursi Kuliah Gedung Baru',
        'tujuan_pengadaan_ringkas' => 'Kursi kuliah',
        'perihal' => 'Pengadaan Kursi Kuliah Gedung Baru',
        'anggaran_diajukan' => 90000000.00,
        'estimasi_total_pagu' => 90000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $ppbj->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($ppbj)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard PPBJ');
    $response->assertSee('Total Paket Terdaftar');
    $response->assertSee('Pengadaan Langsung (<=50jt)');
    $response->assertSee('Jalur PP (50jt - 200jt)');
    $response->assertSee('Jalur PPK (>200jt)');
    $response->assertSee('Siap Registrasi Paket');
    $response->assertSee('Monitoring Paket & Penugasan PPK', false);
    $response->assertSee('006/TIK/PPBJ/2026');
    $response->assertSee('+ Paket Baru');
});

test('pp dashboard renders successfully with direct negotiation and review queues', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $pp = createTestUser('pp');
    $ppbj = createTestUser('ppbj');

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '007/TIK/PP/2026',
        'judul_pengadaan' => 'Pengadaan Alat Lab Jaringan Lanjutan',
        'tujuan_pengadaan_ringkas' => 'Alat lab',
        'perihal' => 'Pengadaan Alat Lab Jaringan Lanjutan',
        'anggaran_diajukan' => 120000000.00,
        'estimasi_total_pagu' => 120000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $pp->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    PaketPengadaan::create([
        'kode_paket' => 'PKT-PP-M2-001',
        'nomor_paket' => 'PKT-PP-M2-001',
        'nama_paket' => 'Paket Pengadaan Router Cisco Lab TIK',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Pengadaan Langsung',
        'metode_pengadaan' => 'Pengadaan Langsung',
        'nilai_hps' => 115000000.00,
        'alokasi_pagu_paket' => 120000000.00,
        'jalur_routing' => 'pp_50jt_sd_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_pp' => $pp->id_user,
        'status_paket' => 'terdaftar',
    ]);

    $response = $this->actingAs($pp)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard PP');
    $response->assertSee('Paket Jalur PP Ditugaskan');
    $response->assertSee('Proses Negosiasi');
    $response->assertSee('SPK Diterbitkan');
    $response->assertSee('Paket Selesai');
    $response->assertSee('Antrean Paket Menunggu Reviu', false);
    $response->assertSee('PKT-PP-M2-001');
    $response->assertSee('Paket Pengadaan Router Cisco Lab TIK');
    $response->assertSee('Reviu / Nego');
});

test('ppk dashboard renders successfully and enforces strict data isolation', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $ppk1 = createTestUser('ppk', 'ppk_satu');
    $ppk2 = createTestUser('ppk', 'ppk_dua');
    $ppbj = createTestUser('ppbj');

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '008/TIK/PPK/2026',
        'judul_pengadaan' => 'Pengadaan Data Center Kampus',
        'tujuan_pengadaan_ringkas' => 'Server data center',
        'perihal' => 'Pengadaan Data Center Kampus',
        'anggaran_diajukan' => 450000000.00,
        'estimasi_total_pagu' => 450000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $ppk1->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    // Paket milik PPK 1
    PaketPengadaan::create([
        'kode_paket' => 'PKT-PPK1-EXCLUSIVE',
        'nomor_paket' => 'PKT-PPK1-EXCLUSIVE',
        'nama_paket' => 'Paket Server Storage Khusus PPK 1',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 400000000.00,
        'alokasi_pagu_paket' => 450000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk1->id_user,
        'status_paket' => 'terdaftar',
    ]);

    // Paket milik PPK 2
    PaketPengadaan::create([
        'kode_paket' => 'PKT-PPK2-SECRET',
        'nomor_paket' => 'PKT-PPK2-SECRET',
        'nama_paket' => 'Paket Renovasi Gedung Khusus PPK 2',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'konstruksi',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 600000000.00,
        'alokasi_pagu_paket' => 650000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk2->id_user,
        'status_paket' => 'terdaftar',
    ]);

    // Uji login sebagai PPK 1
    $responsePpk1 = $this->actingAs($ppk1)->get(route('dashboard'));
    $responsePpk1->assertOk();
    $responsePpk1->assertSee('Dashboard PPK');
    $responsePpk1->assertSee('Penegakan Isolasi Data Portofolio PPK');
    $responsePpk1->assertSee('Paket Komitmen Saya');
    $responsePpk1->assertSee('Tender Aktif (>200jt)');
    $responsePpk1->assertSee('Daftar Paket Komitmen Resmi (Terisolasi)');
    $responsePpk1->assertSee('PKT-PPK1-EXCLUSIVE');
    $responsePpk1->assertDontSee('PKT-PPK2-SECRET');

    // Uji login sebagai PPK 2
    $responsePpk2 = $this->actingAs($ppk2)->get(route('dashboard'));
    $responsePpk2->assertOk();
    $responsePpk2->assertSee('PKT-PPK2-SECRET');
    $responsePpk2->assertDontSee('PKT-PPK1-EXCLUSIVE');
});

test('perlengkapan dashboard renders successfully with vendor shipping schedule and BAST queues', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $perlengkapan = createTestUser('perlengkapan');
    $ppbj = createTestUser('ppbj');

    $vendor = Vendor::create([
        'npwp' => '99.888.777.6-555.000',
        'nib' => '1234567890123',
        'nama_perusahaan' => 'PT. Logistik Perlengkapan Utama',
        'alamat' => 'Jl. Margonda Raya No. 100, Depok',
        'is_active' => true,
        'nama_pic' => 'Andi Logistik',
        'telepon_pic' => '081299881122',
        'email_pic' => 'andi@logistikutama.com',
        'nama_bank' => 'Bank Mandiri',
        'nomor_rekening' => '1270009988776',
        'atas_nama' => 'PT Logistik Perlengkapan Utama',
    ]);

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '009/TIK/LKP/2026',
        'judul_pengadaan' => 'Pengadaan AC Split Laboratorium',
        'tujuan_pengadaan_ringkas' => 'AC pendingin server',
        'perihal' => 'Pengadaan AC Split Laboratorium',
        'anggaran_diajukan' => 48000000.00,
        'estimasi_total_pagu' => 48000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $perlengkapan->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    $paket = PaketPengadaan::create([
        'kode_paket' => 'PKT-LKP-M2-001',
        'nomor_paket' => 'PKT-LKP-M2-001',
        'nama_paket' => 'Paket Pengadaan AC Laboratorium TIK',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Pengadaan Langsung',
        'metode_pengadaan' => 'Pengadaan Langsung',
        'nilai_hps' => 45000000.00,
        'alokasi_pagu_paket' => 48000000.00,
        'jalur_routing' => 'langsung_sd_50jt',
        'id_user_ppbj' => $ppbj->id_user,
        'status_paket' => 'spk_terbit',
        'npwp_vendor' => $vendor->npwp,
    ]);

    KontrakSPK::create([
        'nomor_spk' => 'SPK/LKP/2026/001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $perlengkapan->id_user,
        'tipe_dokumen' => 'spk',
        'sifat_kontrak' => 'lumpsum',
        'status_spk' => 'aktif',
        'tanggal_spk' => now(),
        'tanggal_selesai' => now()->addDays(7),
        'jadwal_pengiriman' => now()->addDays(3),
        'file_spk' => 'uploads/spk/spk-lkp-001.pdf',
        'nilai_kontrak' => 45000000.00,
    ]);

    $response = $this->actingAs($perlengkapan)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard PERLENGKAPAN');
    $response->assertSee('Tembusan SPK Masuk');
    $response->assertSee('Menunggu Uji Fisik');
    $response->assertSee('BAST Diterbitkan');
    $response->assertSee('Barang vs Modal BMN');
    $response->assertSee('Jadwal Pengiriman Rekanan Vendor');
    $response->assertSee('Riwayat BAST Diterbitkan (HMAC)');
    $response->assertSee('SPK/LKP/2026/001');
    $response->assertSee('Paket Pengadaan AC Laboratorium TIK');
    $response->assertSee('PT. Logistik Perlengkapan Utama');
});

test('keuangan dashboard renders successfully with SPP and SP2D disbursement queues', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $keuangan = createTestUser('keuangan');
    $ppbj = createTestUser('ppbj');

    $vendor = Vendor::create([
        'npwp' => '88.777.666.5-444.000',
        'nib' => '9876543210987',
        'nama_perusahaan' => 'PT. Sarana Finance Terpadu',
        'alamat' => 'Kuningan, Jakarta Selatan',
        'is_active' => true,
        'nama_pic' => 'Budi Keuangan',
        'telepon_pic' => '081388776655',
        'email_pic' => 'budi@finance.com',
        'nama_bank' => 'Bank Mandiri',
        'nomor_rekening' => '1270008877665',
        'atas_nama' => 'PT Sarana Finance Terpadu',
    ]);

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '010/TIK/KEU/2026',
        'judul_pengadaan' => 'Pengadaan Fasilitas Server Storage',
        'tujuan_pengadaan_ringkas' => 'Server storage',
        'perihal' => 'Pengadaan Fasilitas Server Storage',
        'anggaran_diajukan' => 180000000.00,
        'estimasi_total_pagu' => 180000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $keuangan->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    $paket = PaketPengadaan::create([
        'kode_paket' => 'PKT-KEU-M2-001',
        'nomor_paket' => 'PKT-KEU-M2-001',
        'nama_paket' => 'Paket Server Storage PNJ',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Pengadaan Langsung',
        'metode_pengadaan' => 'Pengadaan Langsung',
        'nilai_hps' => 175000000.00,
        'alokasi_pagu_paket' => 180000000.00,
        'jalur_routing' => 'pp_50jt_sd_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'status_paket' => 'spk_terbit',
        'npwp_vendor' => $vendor->npwp,
    ]);

    Pembayaran::create([
        'nomor_pembayaran' => 'SPP-KEU-2026-001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $keuangan->id_user,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 175000000.00,
        'tanggal_pengajuan' => now()->toDateString(),
        'jatuh_tempo' => now()->addDays(14)->toDateString(),
        'status_pembayaran' => 'pengajuan',
    ]);

    $response = $this->actingAs($keuangan)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard KEUANGAN');
    $response->assertSee('Berkas Tagihan / SPP Masuk');
    $response->assertSee('Perintah Bayar Siap Cair');
    $response->assertSee('Realisasi Pencairan SP2D');
    $response->assertSee('Transaksi Lunas Selesai');
    $response->assertSee('Antrean Berkas Tagihan / SPP Siap Verifikasi');
    $response->assertSee('Riwayat Pencairan SP2D (Anti-Duplikasi)');
    $response->assertSee('SPP-KEU-2026-001');
    $response->assertSee('Cairkan SP2D');
});

test('auth pages render cleanly without redundant tailwind cdn script tags', function () {
    // 1. Halaman Login
    $loginResponse = $this->get('/login');
    $loginResponse->assertOk();
    $loginResponse->assertDontSee('cdn.tailwindcss.com');

    // 2. Halaman Register
    $registerResponse = $this->get('/register');
    $registerResponse->assertOk();
    $registerResponse->assertDontSee('cdn.tailwindcss.com');
});
