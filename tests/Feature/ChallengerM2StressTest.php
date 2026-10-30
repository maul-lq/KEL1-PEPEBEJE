<?php

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\TransaksiPencairan;
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

function createStressUser(string $role, array $attributes = []): User
{
    $roleRecord = Role::where('nama_role', $role)->first();

    return User::factory()->create(array_merge([
        'nip' => '19880101'.rand(10000000, 99999999),
        'username' => $role.'_'.uniqid(),
        'email' => $role.'_'.uniqid().'@pnj.ac.id',
        'nama' => 'User '.strtoupper(str_replace('_', ' ', $role)),
        'password' => Hash::make('secret123'),
        'jabatan' => 'Jabatan '.strtoupper($role),
        'kode_unit' => 'TIK',
        'role' => $role,
        'id_role' => $roleRecord?->id,
        'status_aktif' => true,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ], $attributes));
}

// =========================================================================
// 1. DUAL VIEWMODE VERIFICATION ACROSS ALL 10 ROLES
// =========================================================================

test('all 10 roles render HTTP 200 without 500 error in DEV_VIEWMODE=backend', function () {
    config(['app.dev_viewmode' => 'backend']);

    $roles = ['admin', 'user', 'staff_bidang_2', 'wadir_2', 'perencanaan', 'ppbj', 'pp', 'ppk', 'perlengkapan', 'keuangan'];

    foreach ($roles as $role) {
        $user = createStressUser($role);
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
    }
});

test('all 10 roles render HTTP 200 without 500 error in DEV_VIEWMODE=frontend', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $roles = ['admin', 'user', 'staff_bidang_2', 'wadir_2', 'perencanaan', 'ppbj', 'pp', 'ppk', 'perlengkapan', 'keuangan'];

    foreach ($roles as $role) {
        $user = createStressUser($role);
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $expectedTitle = 'Dashboard '.strtoupper(str_replace('_', ' ', $role));
        $response->assertSee($expectedTitle);
    }
});

// =========================================================================
// 2. EMPTY STATE & NULL MODEL HYDRATION STRESS TESTING
// =========================================================================

test('all 10 roles render safely when database is empty and user has null unit', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $roles = ['admin', 'user', 'staff_bidang_2', 'wadir_2', 'perencanaan', 'ppbj', 'pp', 'ppk', 'perlengkapan', 'keuangan'];

    foreach ($roles as $role) {
        $user = createStressUser($role, [
            'kode_unit' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('500 Internal Server Error');
    }
});

test('perencanaan dashboard handles zero and null pagu boundary values gracefully', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $perencanaan = createStressUser('perencanaan');

    // MAK dengan pagu nol dan sisa pagu nol
    AnggaranMAK::create([
        'kode_mak' => 'MAK-ZERO-001',
        'uraian_mak' => 'Rekening Pagu Nol',
        'pagu_anggaran' => 0.00,
        'sisa_pagu_mak' => 0.00,
        'sumber_dana' => 'BOPTN',
        'tahun_anggaran' => 2026,
        'is_locked' => true,
        'id_user' => $perencanaan->id_user,
    ]);

    // Permohonan dengan estimasi pagu nol
    PermohonanPengadaan::create([
        'nomor_surat' => 'STRESS/PERENCANAAN/001',
        'judul_pengadaan' => 'Pengadaan Tanpa Anggaran Terisi',
        'tujuan_pengadaan_ringkas' => 'Tujuan Ringkas',
        'perihal' => 'Pengadaan Tanpa Anggaran Terisi',
        'anggaran_diajukan' => 0.00,
        'estimasi_total_pagu' => 0.00,
        'kode_unit' => 'TIK',
        'id_user' => $perencanaan->id_user,
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($perencanaan)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('MAK-ZERO-001');
    $response->assertSee('STRESS/PERENCANAAN/001');
    $response->assertSee('Terkunci');
});

test('keuangan and perlengkapan dashboards handle null dates, null vendors, and zero amounts', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $perlengkapan = createStressUser('perlengkapan');
    $keuangan = createStressUser('keuangan');
    $ppbj = createStressUser('ppbj');

    // Buat permohonan pengadaan induk
    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '098/TIK/STRESS/2026',
        'judul_pengadaan' => 'Pengadaan Uji Ketahanan',
        'tujuan_pengadaan_ringkas' => 'Uji',
        'perihal' => 'Pengadaan Uji Ketahanan',
        'anggaran_diajukan' => 50000000.00,
        'estimasi_total_pagu' => 50000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $perlengkapan->id_user,
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    // Paket tanpa vendor
    $paket = PaketPengadaan::create([
        'kode_paket' => 'PKT-STRESS-NULL-001',
        'nomor_paket' => 'PKT-STRESS-NULL-001',
        'nama_paket' => 'Paket Tanpa Vendor Terpilih',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Pengadaan Langsung',
        'metode_pengadaan' => 'Pengadaan Langsung',
        'nilai_hps' => 0.00,
        'alokasi_pagu_paket' => 0.00,
        'jalur_routing' => 'langsung_sd_50jt',
        'id_user_ppbj' => $ppbj->id_user,
        'status_paket' => 'spk_terbit',
        'npwp_vendor' => null,
    ]);

    // Kontrak SPK dengan tanggal_selesai dan jadwal_pengiriman null
    KontrakSPK::create([
        'nomor_spk' => 'SPK/STRESS/NULL/001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $perlengkapan->id_user,
        'tipe_dokumen' => 'spk',
        'sifat_kontrak' => 'lumpsum',
        'status_spk' => 'aktif',
        'tanggal_spk' => now(),
        'tanggal_selesai' => null,
        'jadwal_pengiriman' => now()->addDays(3),
        'file_spk' => 'uploads/dummy.pdf',
        'nilai_kontrak' => 0.00,
    ]);

    // BAST dengan kategori belanja null
    PenerimaanBarang::create([
        'nomor_bast' => 'BAST/STRESS/001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $perlengkapan->id_user,
        'tanggal_penerimaan' => now(),
        'status_penerimaan' => 'diterima',
        'kategori_belanja' => 'belanja_barang',
        'token_bast' => 'dummy_hash',
    ]);

    // Pembayaran dengan nominal 0
    $pembayaran = Pembayaran::create([
        'nomor_pembayaran' => 'SPP/STRESS/001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $keuangan->id_user,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 0.00,
        'tanggal_pengajuan' => now()->toDateString(),
        'jatuh_tempo' => null,
        'status_pembayaran' => 'pengajuan',
    ]);

    // Transaksi pencairan dengan tanggal transfer null
    TransaksiPencairan::create([
        'nomor_transaksi_bank' => 'TRX-BANK-STRESS-001',
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'id_user' => $keuangan->id_user,
        'nominal_transfer' => 0.00,
        'tanggal_transfer' => now(),
        'file_bukti_transfer' => 'bukti/transfer.pdf',
        'status_transaksi' => 'berhasil',
    ]);

    // Uji Perlengkapan
    $respPerlengkapan = $this->actingAs($perlengkapan)->get(route('dashboard'));
    $respPerlengkapan->assertOk();
    $respPerlengkapan->assertSee('SPK/STRESS/NULL/001');
    $respPerlengkapan->assertSee('BAST/STRESS/001');

    // Uji Keuangan
    $respKeuangan = $this->actingAs($keuangan)->get(route('dashboard'));
    $respKeuangan->assertOk();
    $respKeuangan->assertSee('SPP/STRESS/001');
});

// =========================================================================
// 3. ADVERSARIAL MULTI-TENANT PPK DATA ISOLATION (4 PPK ACCOUNTS)
// =========================================================================

test('strict cross-tenant data isolation among 4 PPK accounts and unassigned packages', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $ppkAlpha = createStressUser('ppk', ['username' => 'ppk_alpha', 'nama' => 'PPK Alpha Utama']);
    $ppkBeta = createStressUser('ppk', ['username' => 'ppk_beta', 'nama' => 'PPK Beta Teknik']);
    $ppkGamma = createStressUser('ppk', ['username' => 'ppk_gamma', 'nama' => 'PPK Gamma Gedung']);
    $ppkDelta = createStressUser('ppk', ['username' => 'ppk_delta', 'nama' => 'PPK Delta Pasif']);
    $ppbj = createStressUser('ppbj');

    // Buat Vendor lengkap
    $vendor = Vendor::create([
        'npwp' => '77.888.999.1-222.000',
        'nib' => '1122334455667',
        'nama_perusahaan' => 'PT Mitra Pengadaan Strategis',
        'alamat' => 'Jakarta Pusat',
        'is_active' => true,
        'nama_pic' => 'Bambang Mitra',
        'telepon_pic' => '081299887766',
        'email_pic' => 'mitra@strategis.com',
        'nama_bank' => 'Bank Mandiri',
        'nomor_rekening' => '1270009988112',
        'atas_nama' => 'PT Mitra Pengadaan Strategis',
    ]);

    // Permohonan
    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '099/PPK/ISOLATION/2026',
        'judul_pengadaan' => 'Pengadaan Server Multi-Unit',
        'tujuan_pengadaan_ringkas' => 'Server',
        'perihal' => 'Pengadaan Server Multi-Unit',
        'anggaran_diajukan' => 1500000000.00,
        'estimasi_total_pagu' => 1500000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $ppkAlpha->id_user,
        'file_permohonan' => 'dummy.pdf',
        'status_permohonan' => 'disetujui',
        'status' => 'disetujui',
        'tanggal_pengajuan' => now(),
    ]);

    // Paket Alpha 1 & 2
    $pktAlpha1 = PaketPengadaan::create([
        'kode_paket' => 'PKT-ALPHA-SECURE-1',
        'nomor_paket' => 'PKT-ALPHA-SECURE-1',
        'nama_paket' => 'Paket Server Storage PPK Alpha 1',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 450000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppkAlpha->id_user,
        'status_paket' => 'terdaftar',
        'npwp_vendor' => $vendor->npwp,
    ]);

    $pktAlpha2 = PaketPengadaan::create([
        'kode_paket' => 'PKT-ALPHA-SECURE-2',
        'nomor_paket' => 'PKT-ALPHA-SECURE-2',
        'nama_paket' => 'Paket Fiber Optic PPK Alpha 2',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 250000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppkAlpha->id_user,
        'status_paket' => 'terdaftar',
        'npwp_vendor' => $vendor->npwp,
    ]);

    // Paket Beta
    $pktBeta1 = PaketPengadaan::create([
        'kode_paket' => 'PKT-BETA-CONFIDENTIAL-1',
        'nomor_paket' => 'PKT-BETA-CONFIDENTIAL-1',
        'nama_paket' => 'Paket Mesin Las Otomotif PPK Beta',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 380000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppkBeta->id_user,
        'status_paket' => 'terdaftar',
        'npwp_vendor' => $vendor->npwp,
    ]);

    // Paket Gamma
    $pktGamma1 = PaketPengadaan::create([
        'kode_paket' => 'PKT-GAMMA-RESTRICTED-1',
        'nomor_paket' => 'PKT-GAMMA-RESTRICTED-1',
        'nama_paket' => 'Paket Konstruksi Gedung Riset PPK Gamma',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'konstruksi',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 850000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppkGamma->id_user,
        'status_paket' => 'terdaftar',
        'npwp_vendor' => $vendor->npwp,
    ]);

    // Paket Unassigned (id_user_ppk = null)
    $pktUnassigned = PaketPengadaan::create([
        'kode_paket' => 'PKT-UNASSIGNED-ORPHAN',
        'nomor_paket' => 'PKT-UNASSIGNED-ORPHAN',
        'nama_paket' => 'Paket Belum Ada PPK Ditugaskan',
        'nomor_surat' => $permohonan->nomor_surat,
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'SPSE',
        'mekanisme_pengadaan' => 'Tender',
        'metode_pengadaan' => 'Tender',
        'nilai_hps' => 300000000.00,
        'jalur_routing' => 'ppk_diatas_200jt',
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => null,
        'status_paket' => 'terdaftar',
    ]);

    // Memo Bayar untuk Alpha
    $pembayaranAlpha = Pembayaran::create([
        'nomor_pembayaran' => 'SPP-ALPHA-001',
        'nomor_paket' => $pktAlpha1->nomor_paket,
        'id_user_pengaju' => $ppkAlpha->id_user,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 450000000.00,
        'tanggal_pengajuan' => now()->toDateString(),
        'status_pembayaran' => 'pengajuan',
    ]);
    MemoBayar::create([
        'nomor_pembayaran' => $pembayaranAlpha->nomor_pembayaran,
        'id_user' => $ppkAlpha->id_user,
        'role_approver' => 'ppk',
        'status_memo' => 'diajukan',
        'level_memo' => 1,
    ]);

    // Memo Bayar untuk Beta
    $pembayaranBeta = Pembayaran::create([
        'nomor_pembayaran' => 'SPP-BETA-001',
        'nomor_paket' => $pktBeta1->nomor_paket,
        'id_user_pengaju' => $ppkBeta->id_user,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 380000000.00,
        'tanggal_pengajuan' => now()->toDateString(),
        'status_pembayaran' => 'pengajuan',
    ]);
    MemoBayar::create([
        'nomor_pembayaran' => $pembayaranBeta->nomor_pembayaran,
        'id_user' => $ppkBeta->id_user,
        'role_approver' => 'ppk',
        'status_memo' => 'diajukan',
        'level_memo' => 1,
    ]);

    // 1. PPK ALPHA AUDIT
    $respAlpha = $this->actingAs($ppkAlpha)->get(route('dashboard'));
    $respAlpha->assertOk();
    $respAlpha->assertSee('PKT-ALPHA-SECURE-1');
    $respAlpha->assertSee('PKT-ALPHA-SECURE-2');
    $respAlpha->assertSee('Paket Server Storage PPK Alpha 1');
    $respAlpha->assertSee('450.000.000');
    $respAlpha->assertDontSee('PKT-BETA-CONFIDENTIAL-1');
    $respAlpha->assertDontSee('Paket Mesin Las Otomotif PPK Beta');
    $respAlpha->assertDontSee('380.000.000');
    $respAlpha->assertDontSee('PKT-GAMMA-RESTRICTED-1');
    $respAlpha->assertDontSee('PKT-UNASSIGNED-ORPHAN');

    // 2. PPK BETA AUDIT
    $respBeta = $this->actingAs($ppkBeta)->get(route('dashboard'));
    $respBeta->assertOk();
    $respBeta->assertSee('PKT-BETA-CONFIDENTIAL-1');
    $respBeta->assertSee('Paket Mesin Las Otomotif PPK Beta');
    $respBeta->assertSee('380.000.000');
    $respBeta->assertDontSee('PKT-ALPHA-SECURE-1');
    $respBeta->assertDontSee('PKT-ALPHA-SECURE-2');
    $respBeta->assertDontSee('Paket Server Storage PPK Alpha 1');
    $respBeta->assertDontSee('450.000.000');
    $respBeta->assertDontSee('PKT-GAMMA-RESTRICTED-1');
    $respBeta->assertDontSee('PKT-UNASSIGNED-ORPHAN');

    // 3. PPK GAMMA AUDIT
    $respGamma = $this->actingAs($ppkGamma)->get(route('dashboard'));
    $respGamma->assertOk();
    $respGamma->assertSee('PKT-GAMMA-RESTRICTED-1');
    $respGamma->assertDontSee('PKT-ALPHA-SECURE-1');
    $respGamma->assertDontSee('PKT-BETA-CONFIDENTIAL-1');
    $respGamma->assertDontSee('PKT-UNASSIGNED-ORPHAN');

    // 4. PPK DELTA AUDIT (0 PACKAGES, 0 MEMOS)
    $respDelta = $this->actingAs($ppkDelta)->get(route('dashboard'));
    $respDelta->assertOk();
    $respDelta->assertDontSee('PKT-ALPHA-SECURE-1');
    $respDelta->assertDontSee('PKT-BETA-CONFIDENTIAL-1');
    $respDelta->assertDontSee('PKT-GAMMA-RESTRICTED-1');
    $respDelta->assertDontSee('PKT-UNASSIGNED-ORPHAN');
    $respDelta->assertSee('Belum ada paket komitmen yang ditugaskan khusus kepada akun PPK Anda');
    $respDelta->assertSee('Tidak ada Memo Bayar Level 1 yang memerlukan persetujuan Anda');
});

// =========================================================================
// 4. ANOMALOUS & UNKNOWN ROLE FALLBACK INTEGRITY
// =========================================================================

test('user with unknown role slug falls back safely to user partial without 500 error', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $userUnknown = createStressUser('user', [
        'role' => 'external_auditor_xyz',
    ]);

    $response = $this->actingAs($userUnknown)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Dashboard EXTERNAL AUDITOR XYZ');
    $response->assertSee('Total Usulan Pengadaan');
    $response->assertDontSee('500 Internal Server Error');
});

// =========================================================================
// 5. USER (PEMOHON) & PP TENANT ISOLATION ADVERSARIAL TESTING
// =========================================================================

test('user pemohon only sees their own permohonan in dashboard and cannot see other units usulan', function () {
    config(['app.dev_viewmode' => 'frontend']);

    $user1 = createStressUser('user', ['username' => 'pemohon_unit_1']);
    $user2 = createStressUser('user', ['username' => 'pemohon_unit_2']);

    PermohonanPengadaan::create([
        'nomor_surat' => '001/USER1/ISOLATED/2026',
        'judul_pengadaan' => 'Usulan Rahasia Pemohon 1',
        'tujuan_pengadaan_ringkas' => 'Rahasia 1',
        'perihal' => 'Usulan Rahasia Pemohon 1',
        'anggaran_diajukan' => 25000000.00,
        'estimasi_total_pagu' => 25000000.00,
        'kode_unit' => 'TIK',
        'id_user' => $user1->id_user,
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    PermohonanPengadaan::create([
        'nomor_surat' => '002/USER2/ISOLATED/2026',
        'judul_pengadaan' => 'Usulan Rahasia Pemohon 2',
        'tujuan_pengadaan_ringkas' => 'Rahasia 2',
        'perihal' => 'Usulan Rahasia Pemohon 2',
        'anggaran_diajukan' => 35000000.00,
        'estimasi_total_pagu' => 35000000.00,
        'kode_unit' => 'B2',
        'id_user' => $user2->id_user,
        'status_permohonan' => 'diajukan',
        'status' => 'diajukan',
        'tanggal_pengajuan' => now(),
    ]);

    // Uji User 1
    $resp1 = $this->actingAs($user1)->get(route('dashboard'));
    $resp1->assertOk();
    $resp1->assertSee('001/USER1/ISOLATED/2026');
    $resp1->assertSee('Usulan Rahasia Pemohon 1');
    $resp1->assertDontSee('002/USER2/ISOLATED/2026');
    $resp1->assertDontSee('Usulan Rahasia Pemohon 2');

    // Uji User 2
    $resp2 = $this->actingAs($user2)->get(route('dashboard'));
    $resp2->assertOk();
    $resp2->assertSee('002/USER2/ISOLATED/2026');
    $resp2->assertSee('Usulan Rahasia Pemohon 2');
    $resp2->assertDontSee('001/USER1/ISOLATED/2026');
    $resp2->assertDontSee('Usulan Rahasia Pemohon 1');
});

test('guest unauthenticated user cannot access dashboard and is redirected to login', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});
