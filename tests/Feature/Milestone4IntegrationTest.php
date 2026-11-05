<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Milestone4IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $userAdmin;

    private User $userPemohon1;

    private User $userPemohon2;

    private User $userPpbj;

    private User $userPpk1;

    private User $userPpk2;

    private User $userWadir2;

    private User $userPerlengkapan;

    private User $userKeuangan;

    private Unit $unitTik;

    private Unit $unitMesin;

    private Vendor $vendor;

    private AnggaranMAK $anggaranMak;

    private PermohonanPengadaan $permohonan;

    private PaketPengadaan $paket;

    private KontrakSPK $kontrak;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->unitTik = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->unitMesin = Unit::firstOrCreate(
            ['kode_unit' => 'MSN'],
            ['nama_unit' => 'Jurusan Teknik Mesin', 'jenis_unit' => 'jurusan']
        );

        $roleAdmin = Role::firstOrCreate(['nama_role' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        $roleUser = Role::firstOrCreate(['nama_role' => 'user'], ['label' => 'Pemohon / Jurusan', 'is_system' => true]);
        $rolePpbj = Role::firstOrCreate(['nama_role' => 'ppbj'], ['label' => 'Koordinator PBJ', 'is_system' => true]);
        $rolePpk = Role::firstOrCreate(['nama_role' => 'ppk'], ['label' => 'Pejabat Pembuat Komitmen', 'is_system' => true]);
        $roleWadir2 = Role::firstOrCreate(['nama_role' => 'wadir_2'], ['label' => 'Wakil Direktur II', 'is_system' => true]);
        $rolePerlengkapan = Role::firstOrCreate(['nama_role' => 'perlengkapan'], ['label' => 'Unit Perlengkapan', 'is_system' => true]);
        $roleKeuangan = Role::firstOrCreate(['nama_role' => 'keuangan'], ['label' => 'Bagian Keuangan', 'is_system' => true]);

        $this->userAdmin = User::factory()->create(['role' => 'admin', 'id_role' => $roleAdmin->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userPemohon1 = User::factory()->create(['role' => 'user', 'id_role' => $roleUser->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userPemohon2 = User::factory()->create(['role' => 'user', 'id_role' => $roleUser->id, 'kode_unit' => $this->unitMesin->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userPpbj = User::factory()->create(['role' => 'ppbj', 'id_role' => $rolePpbj->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userPpk1 = User::factory()->create(['role' => 'ppk', 'id_role' => $rolePpk->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userPpk2 = User::factory()->create(['role' => 'ppk', 'id_role' => $rolePpk->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userWadir2 = User::factory()->create(['role' => 'wadir_2', 'id_role' => $roleWadir2->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userPerlengkapan = User::factory()->create(['role' => 'perlengkapan', 'id_role' => $rolePerlengkapan->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);
        $this->userKeuangan = User::factory()->create(['role' => 'keuangan', 'id_role' => $roleKeuangan->id, 'kode_unit' => $this->unitTik->kode_unit, 'status_aktif' => true, 'status_registrasi' => 'disetujui']);

        $this->vendor = Vendor::create([
            'npwp' => '01.999.888.7-654.000',
            'nib' => '9988776655443',
            'nama_perusahaan' => 'CV Multi Karya Utama',
            'alamat' => 'Jl. Beji No. 50, Depok',
            'nama_pic' => 'Andi Wijaya',
            'telepon_pic' => '081298765432',
            'email_pic' => 'info@multikarya.co.id',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570009988776',
            'atas_nama' => 'CV Multi Karya Utama',
            'is_active' => true,
        ]);

        $this->anggaranMak = AnggaranMAK::create([
            'kode_mak' => 'MAK-521211-M4-INT',
            'uraian_mak' => 'Belanja Modal Komputer Milestone 4',
            'pagu_anggaran' => 300000000,
            'sisa_pagu_mak' => 300000000,
            'sumber_dana' => 'RM',
            'tahun_anggaran' => 2026,
            'is_locked' => false,
            'id_user' => $this->userPpbj->id_user,
        ]);

        $this->permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'SURAT-M4-001/2026',
            'judul_pengadaan' => 'Pengadaan Laboratorium Jaringan Terpadu',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 180000000,
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'kode_mak' => $this->anggaranMak->kode_mak,
            'tanggal_pengajuan' => now(),
        ]);

        $this->paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-M4-001',
            'nomor_paket' => 'PKT-M4-001',
            'nama_paket' => 'Pengadaan Laboratorium Jaringan Terpadu',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 175000000,
            'alokasi_pagu_paket' => 175000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'spk_terbit',
        ]);

        $this->kontrak = KontrakSPK::create([
            'nomor_spk' => 'SPK/M4/PNJ/2026/01',
            'nomor_kontrak_spk' => 'SPK/M4/PNJ/2026/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user' => $this->userPpbj->id_user,
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'lump_sum',
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addDays(20),
            'jadwal_pengiriman' => now()->addDays(15),
            'nilai_kontrak' => 170000000,
            'file_spk' => 'spk/spk_m4.pdf',
        ]);
    }

    public function test_frontend_views_render_successfully_in_frontend_mode(): void
    {
        Config::set('app.dev_viewmode', 'frontend');

        // Feature 28: Penerimaan Barang & BAST
        $respPenerimaan = $this->actingAs($this->userPerlengkapan)->get(route('penerimaan.index'));
        $respPenerimaan->assertStatus(200);

        // Feature 29: Pembayaran & SP2D
        $respPembayaran = $this->actingAs($this->userPpbj)->get(route('pembayaran.index'));
        $respPembayaran->assertStatus(200);

        // Feature 30: Approval Registrasi
        $respApproval = $this->actingAs($this->userAdmin)->get(route('admin.persetujuan-registrasi.index'));
        $respApproval->assertStatus(200);

        // Feature 30: Manajemen Role
        $respRoles = $this->actingAs($this->userAdmin)->get(route('admin.roles.index'));
        $respRoles->assertStatus(200);

        // Feature 31: Monitoring Agregat
        $respMonitoring = $this->actingAs($this->userPemohon1)->get(route('monitoring.index'));
        $respMonitoring->assertStatus(200);

        // Feature 32: Laporan Rekapitulasi DIPA
        $respLaporan = $this->actingAs($this->userAdmin)->get(route('laporan.rekapitulasi'));
        $respLaporan->assertStatus(200);
    }

    public function test_perlengkapan_can_store_bast_with_photos_and_modal_classification(): void
    {
        $foto1 = UploadedFile::fake()->image('item1.jpg');
        $foto2 = UploadedFile::fake()->image('item2.jpg');

        $payload = [
            'nomor_kontrak_spk' => $this->kontrak->nomor_spk,
            'nomor_bast' => 'BAST/M4/PNJ/2026/01',
            'tanggal_terima' => now()->format('Y-m-d'),
            'kondisi_barang' => 'terima',
            'kategori_belanja' => 'belanja_modal',
            'catatan_pemeriksaan' => 'Seluruh perangkat switch dan kabel optik tiba utuh tanpa cacat',
            'foto_barang' => [$foto1, $foto2],
        ];

        $response = $this->actingAs($this->userPerlengkapan)->post(route('penerimaan.store'), $payload);

        $response->assertRedirect(route('penerimaan.index'));

        $this->assertDatabaseHas('penerimaan_barang', [
            'nomor_bast' => 'BAST/M4/PNJ/2026/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'kategori_belanja' => 'belanja_modal',
            'status_fisik' => 'terima',
        ]);

        $bast = PenerimaanBarang::where('nomor_bast', 'BAST/M4/PNJ/2026/01')->first();
        $this->assertNotNull($bast->ttd_digital_bast);
        $this->assertEquals(64, strlen($bast->ttd_digital_bast)); // SHA-256 HMAC token length

        $this->assertDatabaseHas('penerimaan_barang_foto_dokumentasi', [
            'id_penerimaan' => $bast->id_penerimaan,
        ]);
    }

    public function test_ppbj_can_submit_pembayaran_with_two_level_memo(): void
    {
        PenerimaanBarang::create([
            'nomor_bast' => 'BAST/M4/PREV/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user' => $this->userPerlengkapan->id_user,
            'tanggal_penerimaan' => now(),
            'status_fisik' => 'terima',
            'kategori_belanja' => 'belanja_modal',
            'kondisi_barang' => 'terima',
            'ttd_digital_bast' => 'token-test',
        ]);

        $payload = [
            'nomor_pembayaran' => 'SPP/M4/2026/001',
            'nomor_paket' => $this->paket->nomor_paket,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 170000000,
            'tanggal_pengajuan' => now()->format('Y-m-d'),
            'nomor_memo' => 'MEMO/BHP/M4/01',
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('pembayaran.pengajuan'), $payload);

        $response->assertRedirect(route('pembayaran.index'));

        $this->assertDatabaseHas('pembayaran', [
            'nomor_pembayaran' => 'SPP/M4/2026/001',
            'status_pembayaran' => 'reviu_memo',
        ]);

        // Verifikasi pembuatan 2 tingkat memo bayar
        $this->assertDatabaseHas('memo_bayar', [
            'nomor_pembayaran' => 'SPP/M4/2026/001',
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        $this->assertDatabaseHas('memo_bayar', [
            'nomor_pembayaran' => 'SPP/M4/2026/001',
            'role_approver' => 'wadir_2',
            'urutan_level' => 2,
            'status_memo' => 'menunggu',
        ]);
    }

    public function test_memo_bayar_two_level_approval_flow_and_ppk_isolation(): void
    {
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/M4/FLOW/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user_pengaju' => $this->userPpbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 170000000,
            'tanggal_pengajuan' => now(),
            'status_pembayaran' => 'reviu_memo',
        ]);

        $memoPpk = MemoBayar::create([
            'id_user' => $this->userPpk1->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        $memoWadir = MemoBayar::create([
            'id_user' => $this->userWadir2->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'wadir_2',
            'urutan_level' => 2,
            'status_memo' => 'menunggu',
        ]);

        // PPK 2 yang tidak ditugaskan mencoba menyetujui -> 403
        $respPpk2 = $this->actingAs($this->userPpk2)->post(route('pembayaran.memo.approve'), [
            'id_memo_bayar' => $memoPpk->id,
            'status_memo' => 'disetujui',
        ]);
        $respPpk2->assertStatus(403);

        // PPK 1 yang sah menyetujui Level 1
        $respPpk1 = $this->actingAs($this->userPpk1)->post(route('pembayaran.memo.approve'), [
            'id_memo_bayar' => $memoPpk->id,
            'status_memo' => 'disetujui',
            'catatan_memo' => 'Tagihan terverifikasi lengkap sesuai BAST',
        ]);
        $respPpk1->assertRedirect(route('pembayaran.index'));
        $memoPpk->refresh();
        $this->assertEquals('disetujui', $memoPpk->status_memo);

        // Wadir 2 menyetujui Level 2
        $respWadir = $this->actingAs($this->userWadir2)->post(route('pembayaran.memo.approve'), [
            'id_memo_bayar' => $memoWadir->id,
            'status_memo' => 'disetujui',
            'catatan_memo' => 'Disetujui pencairan anggaran institusi',
        ]);
        $respWadir->assertRedirect(route('pembayaran.index'));
        $memoWadir->refresh();
        $this->assertEquals('disetujui', $memoWadir->status_memo);
    }

    public function test_penerbitan_spp_requires_complete_memo_bayar_approval(): void
    {
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/M4/SPP/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user_pengaju' => $this->userPpbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 170000000,
            'tanggal_pengajuan' => now(),
            'status_pembayaran' => 'reviu_memo',
        ]);

        $memo1 = MemoBayar::create([
            'id_user' => $this->userPpk1->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'disetujui',
        ]);

        $memo2 = MemoBayar::create([
            'id_user' => $this->userWadir2->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'wadir_2',
            'urutan_level' => 2,
            'status_memo' => 'menunggu', // Belum disetujui Wadir
        ]);

        // Coba terbitkan SPP sebelum Wadir setuju -> Gagal
        $failResp = $this->actingAs($this->userPpbj)->post(route('pembayaran.terbitkan-spp'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        ]);
        $failResp->assertSessionHasErrors('nomor_pembayaran');

        // Setujui memo Wadir
        $memo2->status_memo = 'disetujui';
        $memo2->save();

        // Terbitkan SPP sukses
        $successResp = $this->actingAs($this->userPpbj)->post(route('pembayaran.terbitkan-spp'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'catatan_reviu_akhir' => 'SPP diterbitkan sah ke Keuangan',
        ]);
        $successResp->assertRedirect(route('pembayaran.index'));

        $pembayaran->refresh();
        $this->assertEquals('perintah_bayar', $pembayaran->status_pembayaran);
    }

    public function test_keuangan_can_execute_pencairan_sp2d_with_real_fields(): void
    {
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/M4/CAIR/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user_pengaju' => $this->userPpbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 170000000,
            'tanggal_pengajuan' => now(),
            'status_pembayaran' => 'perintah_bayar',
        ]);

        $payload = [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'nomor_transaksi' => 'TRX-BANK-M4-001',
            'tanggal_cair' => now()->format('Y-m-d'),
            'nominal_cair' => 170000000,
            'bank_tujuan' => 'Bank Mandiri',
            'keterangan_cair' => 'Transfer lunas termin 1 pekerjaan fisik',
        ];

        $response = $this->actingAs($this->userKeuangan)->post(route('pembayaran.store'), $payload);

        $response->assertRedirect(route('pembayaran.index'));

        $this->assertDatabaseHas('transaksi_pencairan', [
            'nomor_transaksi_bank' => 'TRX-BANK-M4-001',
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'nominal_transfer' => 170000000,
        ]);
    }

    public function test_anti_duplicate_pencairan_sp2d_enforcement_nfr8(): void
    {
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/M4/DUP/01',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user_pengaju' => $this->userPpbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 50000000,
            'tanggal_pengajuan' => now(),
            'status_pembayaran' => 'perintah_bayar',
        ]);

        // Pencairan pertama sukses
        $this->actingAs($this->userKeuangan)->post(route('pembayaran.store'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'nomor_transaksi' => 'TRX-FIRST-001',
            'tanggal_cair' => now()->format('Y-m-d'),
            'nominal_cair' => 50000000,
            'bank_tujuan' => 'Bank Mandiri',
        ]);

        // Pencairan kedua dengan nomor SPP yang sama wajib ditolak (NFR 8)
        $dupResponse = $this->actingAs($this->userKeuangan)->post(route('pembayaran.store'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'nomor_transaksi' => 'TRX-SECOND-002',
            'tanggal_cair' => now()->format('Y-m-d'),
            'nominal_cair' => 50000000,
            'bank_tujuan' => 'Bank Mandiri',
        ]);

        $dupResponse->assertSessionHasErrors('nomor_pembayaran');
        $this->assertDatabaseMissing('transaksi_pencairan', [
            'nomor_transaksi_bank' => 'TRX-SECOND-002',
        ]);
    }

    public function test_admin_registration_approval_and_single_pp_constraint(): void
    {
        $rolePp = Role::firstOrCreate(['nama_role' => 'pp'], ['label' => 'Pejabat Pengadaan', 'is_system' => true]);

        // Akun PP aktif pertama sudah ada
        User::factory()->create([
            'role' => 'pp',
            'id_role' => $rolePp->id,
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        // Calon user baru mendaftar
        $calonPp = User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => false,
            'status_registrasi' => 'menunggu_persetujuan',
        ]);

        // Admin mencoba menyetujui calonPp menjadi PP kedua -> Gagal karena batasan 1 akun PP aktif (FR 2.1)
        $respDuplicatePp = $this->actingAs($this->userAdmin)->post(route('admin.persetujuan-registrasi.acc', $calonPp->id_user), [
            'role_id' => $rolePp->id,
        ]);
        $respDuplicatePp->assertSessionHasErrors();

        // Penolakan akun wajib disertai catatan alasan (FR 2.1)
        $respTolakTanpaAlasan = $this->actingAs($this->userAdmin)->post(route('admin.persetujuan-registrasi.tolak', $calonPp->id_user), [
            'alasan_penolakan' => '',
        ]);
        $respTolakTanpaAlasan->assertSessionHasErrors('alasan_penolakan');
    }

    public function test_role_management_crud_and_system_role_protection(): void
    {
        // 1. Buat role kustom baru
        $storeResp = $this->actingAs($this->userAdmin)->post(route('admin.roles.store'), [
            'nama_role' => 'auditor_spi',
            'label' => 'Auditor Satuan Pengawas Internal',
            'deskripsi' => 'Pengawas kepatuhan PBJ',
        ]);
        $storeResp->assertRedirect();
        $this->assertDatabaseHas('roles', ['nama_role' => 'auditor_spi']);

        // 2. Role sistem (is_system = true) dilindungi dan tidak boleh dihapus
        $systemRole = Role::where('is_system', true)->first();
        $deleteResp = $this->actingAs($this->userAdmin)->delete(route('admin.roles.destroy', $systemRole->id));

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_monitoring_data_isolation_between_user_and_supervisor(): void
    {
        // Pemohon 1 dari Jurusan TIK
        $respPemohon1 = $this->actingAs($this->userPemohon1)->get(route('monitoring.index'));
        $respPemohon1->assertSee($this->permohonan->nomor_surat);

        // Buat usulan untuk Pemohon 2 dari Jurusan Mesin
        $permohonanMesin = PermohonanPengadaan::create([
            'nomor_surat' => 'SURAT-MSN-999/2026',
            'judul_pengadaan' => 'Pengadaan Mesin Bubut CNC',
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 90000000,
            'kode_unit' => $this->unitMesin->kode_unit,
            'id_user' => $this->userPemohon2->id_user,
            'tanggal_pengajuan' => now(),
        ]);

        // Pemohon 1 tidak boleh melihat usulan unit Mesin
        $respPemohon1Ref = $this->actingAs($this->userPemohon1)->get(route('monitoring.index'));
        $respPemohon1Ref->assertDontSee('SURAT-MSN-999/2026');

        // Admin/Pengawas dapat melihat usulan dari seluruh jurusan
        $respAdmin = $this->actingAs($this->userAdmin)->get(route('monitoring.index'));
        $respAdmin->assertSee($this->permohonan->nomor_surat);
        $respAdmin->assertSee('SURAT-MSN-999/2026');
    }

    public function test_laporan_rekapitulasi_dipa_and_pdf_download(): void
    {
        $respHtml = $this->actingAs($this->userAdmin)->get(route('laporan.rekapitulasi'));
        $respHtml->assertStatus(200);
        $respHtml->assertSee('Laporan Rekapitulasi');

        $respPdf = $this->actingAs($this->userAdmin)->get(route('laporan.rekapitulasi.pdf', [
            'tahun' => 2026,
            'bulan' => 'Semua',
            'unit' => 'Semua Unit',
        ]));
        $respPdf->assertStatus(200);
        $this->assertEquals('application/pdf', $respPdf->headers->get('Content-Type'));
    }

    public function test_milestone_4_views_contain_zero_inline_scripts(): void
    {
        $directories = [
            resource_path('views/penerimaan'),
            resource_path('views/pembayaran'),
            resource_path('views/admin'),
            resource_path('views/monitoring'),
            resource_path('views/laporan'),
            resource_path('views/paket'),
            resource_path('views/kontrak'),
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $files = glob($dir.'/*.blade.php');
            foreach ($files as $file) {
                $content = file_get_contents($file);

                $this->assertFalse(
                    (bool) preg_match('/<\s*script\b/i', $content),
                    "Pelanggaran zero-inline script: Tag <script> ditemukan pada berkas {$file}"
                );

                $this->assertFalse(
                    (bool) preg_match('/\bon(click|change|submit|load|keydown|keyup)\s*=/i', $content),
                    "Pelanggaran zero-inline handler: Handler inline ditemukan pada berkas {$file}"
                );
            }
        }
    }
}
