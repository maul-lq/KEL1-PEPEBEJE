<?php

namespace Tests\Feature;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\DokumentasiPenerimaanBarang;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\Permission;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\ReviuDokumen;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AdminScopeRestrictionTest
 *
 * Verifikasi komprehensif batasan peran dan hak akses Administrator:
 * 1. Admin diizinkan mengakses RBAC (admin/roles/* dan API roles).
 * 2. Admin diizinkan mengakses persetujuan registrasi (admin/persetujuan-registrasi/*) dan API CRUD user (/api/users).
 * 3. Admin diblokir dengan HTTP 403 Forbidden saat mengakses endpoint verifikasi teknis pengadaan.
 * 4. Admin diizinkan menghapus usulan permohonan pengadaan pada status apa pun (tanpa batasan kepemilikan).
 * 5. Penghapusan oleh Admin membersihkan seluruh data relasi terkait secara tuntas (cascade deletion) dan menghapus berkas pada disk storage.
 * 6. Pengguna reguler (non-admin) dilarang menghapus pengajuan milik pengguna lain (IDOR prevention) dan dilarang menghapus pengajuan di luar status draft/diajukan/ditolak.
 */
class AdminScopeRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $userPemohonA;

    protected User $userPemohonB;

    protected User $ppk;

    protected User $pp;

    protected User $ppbj;

    protected Unit $unitTIK;

    protected Vendor $vendor;

    protected AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');

        $this->unitTIK = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $roleAdmin = Role::where('nama_role', 'admin')->firstOrFail();
        $roleUser = Role::where('nama_role', 'user')->firstOrFail();
        $rolePpk = Role::where('nama_role', 'ppk')->firstOrFail();
        $rolePp = Role::where('nama_role', 'pp')->firstOrFail();
        $rolePpbj = Role::where('nama_role', 'ppbj')->firstOrFail();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'id_role' => $roleAdmin->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Super Administrator',
            'username' => 'admin_super',
            'email' => 'admin_super@pnj.ac.id',
        ]);

        $this->userPemohonA = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Dosen Pemohon A',
            'username' => 'pemohon_a',
            'email' => 'pemohon_a@pnj.ac.id',
        ]);

        $this->userPemohonB = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Dosen Pemohon B',
            'username' => 'pemohon_b',
            'email' => 'pemohon_b@pnj.ac.id',
        ]);

        $this->ppk = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $rolePpk->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Pejabat Pembuat Komitmen',
            'username' => 'ppk_pejabat',
            'email' => 'ppk_pejabat@pnj.ac.id',
        ]);

        $this->pp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $rolePp->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Pejabat Pengadaan',
            'username' => 'pp_pejabat',
            'email' => 'pp_pejabat@pnj.ac.id',
        ]);

        $this->ppbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $rolePpbj->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Koordinator PPBJ',
            'username' => 'ppbj_koordinator',
            'email' => 'ppbj_koordinator@pnj.ac.id',
        ]);

        $this->vendor = Vendor::firstOrCreate(
            ['npwp' => '01.234.567.8-901.000'],
            [
                'nib' => '1234567890123',
                'nama_perusahaan' => 'PT Mitra Pengadaan Mandiri',
                'alamat' => 'Jl. Margonda Raya No. 100 Depok',
                'is_active' => true,
                'nama_pic' => 'Budi Santoso',
                'email_pic' => 'info@mitrapengadaan.co.id',
                'telepon_pic' => '021-77889900',
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '1570001234567',
                'atas_nama' => 'PT Mitra Pengadaan Mandiri',
            ]
        );

        $this->mak = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.WA.6251.EBA.994.051.A.521811'],
            [
                'uraian_mak' => 'Belanja Bahan Pendukung Pembelajaran Komputer',
                'pagu_anggaran' => 250000000,
                'sisa_pagu_mak' => 250000000,
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => (int) date('Y'),
                'is_locked' => false,
            ]
        );
    }

    /**
     * 1. Verifikasi Admin DAPAT mengakses rute manajemen RBAC (admin/roles/* dan API roles).
     */
    public function test_admin_can_access_rbac_management_endpoints(): void
    {
        $this->actingAs($this->admin);

        // Akses halaman daftar roles
        $responseIndex = $this->get(route('admin.roles.index'));
        $responseIndex->assertOk();
        $responseIndex->assertSee('Manajemen Role');

        // Akses pembuatan role baru via form web
        $permission = Permission::first();
        $rolePayload = [
            'nama_role' => 'auditor_mutu_internal',
            'label' => 'Auditor Mutu Internal',
            'deskripsi' => 'Pengawas kepatuhan audit internal kampus',
            'permissions' => $permission ? [$permission->id] : [],
        ];
        $responseStore = $this->post(route('admin.roles.store'), $rolePayload);
        $responseStore->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['nama_role' => 'auditor_mutu_internal']);

        // Akses API REST roles
        $responseApi = $this->getJson('/api/roles');
        $responseApi->assertOk();
        $responseApi->assertJsonStructure(['status', 'data']);
    }

    /**
     * 2. Verifikasi Admin DAPAT mengakses persetujuan registrasi dan API manajemen pengguna (/api/users).
     */
    public function test_admin_can_access_user_registration_approval_and_user_crud_api(): void
    {
        // Buat user calon pemohon yang baru mendaftar
        $calonUser = User::factory()->create([
            'role' => 'user',
            'status_aktif' => false,
            'status_registrasi' => 'menunggu_persetujuan',
            'nama' => 'Pendaftar Akun Baru',
            'username' => 'pendaftar_baru',
            'email' => 'pendaftar_baru@pnj.ac.id',
        ]);

        $this->actingAs($this->admin);

        // Akses halaman daftar persetujuan registrasi
        $responseWeb = $this->get(route('admin.persetujuan-registrasi.index'));
        $responseWeb->assertOk();
        $responseWeb->assertSee('Persetujuan Registrasi Pengguna');
        $responseWeb->assertSee('Pendaftar Akun Baru');

        // Akses endpoint API persetujuan registrasi
        $responseApiPending = $this->getJson('/api/admin/persetujuan-registrasi');
        $responseApiPending->assertOk();
        $responseApiPending->assertJsonFragment(['username' => 'pendaftar_baru']);

        // Setujui pendaftaran user
        $responseAcc = $this->post(route('admin.persetujuan-registrasi.acc', $calonUser->id_user), [
            'role' => 'user',
        ]);
        $responseAcc->assertRedirect(route('admin.persetujuan-registrasi.index'));
        $calonUser->refresh();
        $this->assertTrue((bool) $calonUser->status_aktif);
        $this->assertEquals('disetujui', $calonUser->status_registrasi);

        // Akses endpoint API manajemen pengguna (UserController)
        $responseUsersList = $this->getJson('/api/users');
        $responseUsersList->assertOk();
        $responseUsersList->assertJsonStructure(['status', 'data']);

        // Akses halaman antarmuka web CRUD User Admin (/admin/users)
        $responseWebUsers = $this->get(route('admin.users.index'));
        $responseWebUsers->assertOk();
        $responseWebUsers->assertSee('Manajemen Pengguna');
        $responseWebUsers->assertSee('Pendaftar Akun Baru');

        // Buat user baru via Web Admin UserController (admin.users.store)
        $webNewUserPayload = [
            'nip' => '199202022020121008',
            'nama' => 'Staf Perlengkapan Baru',
            'username' => 'staf_perlengkapan_web',
            'email' => 'staf_perlengkapan_web@pnj.ac.id',
            'no_hp' => '081298765432',
            'password' => 'Rahasia123!',
            'role' => 'perlengkapan',
            'kode_unit' => $this->unitTIK->kode_unit,
            'jabatan' => 'Pemeriksa Fisik BMN',
            'status_aktif' => 1,
        ];
        $responseWebStore = $this->post(route('admin.users.store'), $webNewUserPayload);
        $responseWebStore->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['username' => 'staf_perlengkapan_web']);

        $createdWebUser = User::where('username', 'staf_perlengkapan_web')->first();
        $this->assertNotNull($createdWebUser);

        // Update user via Web Admin UserController (admin.users.update)
        $responseWebUpdate = $this->put(route('admin.users.update', $createdWebUser->id_user), [
            'nip' => $createdWebUser->nip,
            'nama' => 'Staf Perlengkapan Senior',
            'username' => $createdWebUser->username,
            'email' => $createdWebUser->email,
            'no_hp' => '081298765999',
            'jabatan' => 'Koordinator Pemeriksa Fisik BMN',
            'role' => 'perlengkapan',
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => 1,
        ]);
        $responseWebUpdate->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['nama' => 'Staf Perlengkapan Senior', 'no_hp' => '081298765999']);

        // Hapus user via Web Admin UserController (admin.users.destroy)
        $responseWebDestroy = $this->delete(route('admin.users.destroy', $createdWebUser->id_user));
        $responseWebDestroy->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id_user' => $createdWebUser->id_user]);

        // Verifikasi proteksi: Admin tidak boleh menghapus akun sendiri
        $responseSelfDelete = $this->delete(route('admin.users.destroy', $this->admin->id_user));
        $responseSelfDelete->assertRedirect(route('admin.users.index'));
        $responseSelfDelete->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id_user' => $this->admin->id_user]);

        // Buat user baru via API UserController
        $newUserPayload = [
            'nip' => '199501012020121005',
            'nama' => 'Staf Akuntansi Baru',
            'username' => 'staf_akuntansi_baru',
            'email' => 'staf_akuntansi_baru@pnj.ac.id',
            'no_hp' => '081234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'keuangan',
            'kode_unit' => $this->unitTIK->kode_unit,
            'jabatan' => 'Staf Verifikasi SP2D',
        ];
        $responseStoreUser = $this->postJson('/api/users', $newUserPayload);
        $responseStoreUser->assertStatus(201);
        $this->assertDatabaseHas('users', ['username' => 'staf_akuntansi_baru']);
    }

    /**
     * 3. Verifikasi Admin DILARANG (403 Forbidden) mengakses endpoint verifikasi teknis pengadaan.
     */
    public function test_admin_is_forbidden_from_technical_procurement_verification_endpoints(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST-ADMIN-RESTRICTION/PL3/TIK/2026',
            'perihal' => 'Pengadaan Server Laboratorium TIK',
            'tujuan_pengadaan_ringkas' => 'Server Laboratorium TIK',
            'anggaran_diajukan' => 150000000,
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'id_user_ppk_awal' => $this->ppk->id_user,
            'kode_mak' => $this->mak->kode_mak,
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/dummy.pdf',
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-TEST-ADMIN-FORBIDDEN-001',
            'nama_paket' => 'Pengadaan Paket Uji Otorisasi Admin',
            'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
            'metode_pengadaan' => PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'nominal_harga_perkiraan' => 75000000,
            'alokasi_pagu_paket' => 75000000,
            'status_paket' => PaketPengadaan::STATUS_TERDAFTAR,
            'nomor_surat' => $permohonan->nomor_surat,
            'tanggal_registrasi_paket' => now(),
            'id_user_ppbj' => $this->ppbj->id_user,
            'id_user_ppk' => $this->ppk->id_user,
            'id_user_pp' => $this->pp->id_user,
        ]);

        $this->actingAs($this->admin);

        // a. POST /api/paket-pengadaan/{id}/review-pp -> 403 Forbidden
        $respReviewPp = $this->postJson("/api/paket-pengadaan/{$paket->nomor_paket}/review-pp", [
            'nominal_harga_akhir' => 74000000,
            'catatan_verifikasi_pp' => 'Reviu teknis oleh admin terlarang',
        ]);
        $respReviewPp->assertStatus(403);
        $respReviewPp->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // b. POST /api/paket-pengadaan/{id}/belanja-langsung -> 403 Forbidden
        $respBelanjaLangsung = $this->postJson("/api/paket-pengadaan/{$paket->nomor_paket}/belanja-langsung", [
            'nomor_kwitansi_belanja' => 'KW-ADMIN-ILLEGAL',
        ]);
        $respBelanjaLangsung->assertStatus(403);
        $respBelanjaLangsung->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // c. POST /api/paket-pengadaan/{id}/eksekusi-belanja -> 403 Forbidden
        $respEksekusi = $this->postJson("/api/paket-pengadaan/{$paket->nomor_paket}/eksekusi-belanja", [
            'npwp_vendor' => $this->vendor->npwp,
        ]);
        $respEksekusi->assertStatus(403);
        $respEksekusi->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // d. POST /api/verifikasi-paralel/memo-cek-mak -> 403 Forbidden
        $respMemoCekMak = $this->postJson('/api/verifikasi-paralel/memo-cek-mak', [
            'nomor_surat' => $permohonan->nomor_surat,
            'nomor_memo_cek_mak' => 'MEMO-CEK-MAK-ADMIN',
            'catatan_memo_cek_mak' => 'Uji coba memo admin',
        ]);
        $respMemoCekMak->assertStatus(403);
        $respMemoCekMak->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // e. POST /api/verifikasi-paralel/memo-ketersediaan-mak -> 403 Forbidden
        $respMemoKetersediaan = $this->postJson('/api/verifikasi-paralel/memo-ketersediaan-mak', [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'id_user_ppk' => $this->ppk->id_user,
            'nomor_memo_ketersediaan_mak' => 'MEMO-MAK-ADMIN',
        ]);
        $respMemoKetersediaan->assertStatus(403);
        $respMemoKetersediaan->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // f. POST /api/verifikasi-paralel/keputusan-awal -> 403 Forbidden
        $respKeputusanAwal = $this->postJson('/api/verifikasi-paralel/keputusan-awal', [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
            'catatan' => 'Keputusan awal oleh admin',
        ]);
        $respKeputusanAwal->assertStatus(403);
        $respKeputusanAwal->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // g. GET /paket-pengadaan/reassign-ppk -> 403 Forbidden
        $respReassignPpkGet = $this->get('/paket-pengadaan/reassign-ppk');
        $respReassignPpkGet->assertStatus(403);

        // h. POST /api/permohonan-pengadaan/{id}/split -> 403 Forbidden
        $respSplitMak = $this->postJson("/api/permohonan-pengadaan/{$permohonan->nomor_surat}/split", [
            'pemecahan' => [
                ['kode_mak' => $this->mak->kode_mak, 'nominal' => 75000000],
                ['kode_mak' => $this->mak->kode_mak, 'nominal' => 75000000],
            ],
        ]);
        $respSplitMak->assertStatus(403);
        $respSplitMak->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // i. POST /api/permohonan/{id}/split-ppbj -> 403 Forbidden
        $respSplitPpbj = $this->postJson("/api/permohonan/{$permohonan->nomor_surat}/split-ppbj", [
            'alasan_pemecahan' => 'Split paket oleh admin terlarang',
        ]);
        $respSplitPpbj->assertStatus(403);
        $respSplitPpbj->assertJsonFragment([
            'status' => 'error',
            'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
        ]);

        // j. Web Technical Routes: verifikasi bidang-2, wadir-2, perencanaan, ppk
        $this->get('/verifikasi/bidang-2')->assertStatus(403);
        $this->get('/verifikasi/wadir-2')->assertStatus(403);
        $this->get('/verifikasi/perencanaan')->assertStatus(403);
        $this->get('/verifikasi/ppk')->assertStatus(403);
    }

    /**
     * 4. Verifikasi Admin DAPAT menghapus usulan permohonan dalam status APA PUN tanpa pembatasan kepemilikan.
     */
    public function test_admin_can_delete_procurement_proposal_in_any_status_regardless_of_ownership(): void
    {
        $statusesToTest = [
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            PermohonanPengadaan::STATUS_SELESAI,
        ];

        foreach ($statusesToTest as $index => $status) {
            $nomorSurat = "B/TEST-ADMIN-DEL-{$index}/PL3/TIK/2026";

            // Permohonan dibuat oleh user biasa (userPemohonA)
            $permohonan = PermohonanPengadaan::create([
                'nomor_surat' => $nomorSurat,
                'perihal' => "Usulan Status {$status} Milik Pemohon A",
                'tujuan_pengadaan_ringkas' => "Pengadaan {$status}",
                'anggaran_diajukan' => 45000000,
                'estimasi_total_pagu' => 45000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $this->userPemohonA->id_user,
                'status_permohonan' => $status,
                'tanggal_pengajuan' => now(),
                'file_permohonan' => 'permohonan/dummy.pdf',
            ]);

            // Admin login dan menghapus usulan
            $this->actingAs($this->admin);
            $response = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));

            $response->assertRedirect(route('permohonan.index'));
            $response->assertSessionHas('success');

            // Pastikan data benar-benar terhapus dari basis data
            $this->assertDatabaseMissing('permohonan_pengadaan', [
                'nomor_surat' => $nomorSurat,
            ]);

            // Pastikan audit log tercatat
            $this->assertTrue(
                AuditLog::where('jenis_aksi', 'HAPUS_PERMOHONAN')
                    ->where('id_entitas', $nomorSurat)
                    ->exists()
            );
        }
    }

    /**
     * 5. Verifikasi penghapusan oleh Admin melakukan cascade delete tuntas ke seluruh relasi dan membersihkan berkas fisik di disk storage.
     */
    public function test_admin_deletion_cascade_cleans_up_all_relational_records_and_storage_files(): void
    {
        $nomorSurat = 'B/LIFECYCLE-CASCADE-FULL/PL3/TIK/2026';
        $nomorPaket = 'PKT-LIFECYCLE-001';
        $nomorSpk = 'SPK/TIK/2026/001';
        $nomorPembayaran = 'BYR-TIK-2026-001';

        // 1. Simpan berkas-berkas mock pada storage disk public
        $filePermohonan = 'permohonan/surat_resmi.pdf';
        $fileKak = 'permohonan/kak_resmi.pdf';
        $fileSpek = 'permohonan/spesifikasi.pdf';
        $fileDokPaket = 'paket/dokumen_pengadaan.pdf';
        $fileKwitansiPaket = 'paket/kwitansi_belanja.pdf';
        $fileSpk = 'kontrak/spk_resmi.pdf';
        $fileBast = 'penerimaan/bast_signed.pdf';
        $fileFoto = 'penerimaan/foto_barang.jpg';
        $fileInvoice = 'pembayaran/invoice.pdf';
        $fileBuktiTransfer = 'pembayaran/bukti_transfer.pdf';

        Storage::disk('public')->put($filePermohonan, 'content permohonan');
        Storage::disk('public')->put($fileKak, 'content kak');
        Storage::disk('public')->put($fileSpek, 'content spek');
        Storage::disk('public')->put($fileDokPaket, 'content dok paket');
        Storage::disk('public')->put($fileKwitansiPaket, 'content kwitansi paket');
        Storage::disk('public')->put($fileSpk, 'content spk');
        Storage::disk('public')->put($fileBast, 'content bast');
        Storage::disk('public')->put($fileFoto, 'content foto barang');
        Storage::disk('public')->put($fileInvoice, 'content invoice');
        Storage::disk('public')->put($fileBuktiTransfer, 'content transfer');

        // 2. Buat relasi lengkap di database
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $nomorSurat,
            'perihal' => 'Pengadaan Lengkap Siklus 1 sd 8',
            'tujuan_pengadaan_ringkas' => 'Siklus Lengkap',
            'anggaran_diajukan' => 120000000,
            'estimasi_total_pagu' => 120000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_SELESAI,
            'file_permohonan' => $filePermohonan,
            'file_kak' => $fileKak,
            'file_spesifikasi_teknis' => $fileSpek,
            'tanggal_pengajuan' => now(),
        ]);

        // Anak split & revisi
        $anakSplit = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SPLIT-CHILD/PL3/TIK/2026',
            'perihal' => 'Anak Pemecahan MAK',
            'tujuan_pengadaan_ringkas' => 'Anak Pemecahan',
            'anggaran_diajukan' => 40000000,
            'estimasi_total_pagu' => 40000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'nomor_surat_induk_split' => $nomorSurat,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        // Verifikasi Paralel
        VerifikasiParalel::create([
            'id_user' => $this->admin->id_user,
            'nomor_surat' => $nomorSurat,
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => VerifikasiParalel::STATUS_DISETUJUI,
            'catatan_alasan' => 'Telaah berkas lengkap',
            'tanggal_keputusan' => now(),
        ]);

        // Alokasi MAK
        AlokasiMAK::create([
            'nomor_surat' => $nomorSurat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 120000000,
            'tanggal_alokasi' => now(),
        ]);

        // Paket Pengadaan
        $paket = PaketPengadaan::create([
            'nomor_paket' => $nomorPaket,
            'nama_paket' => 'Paket Pengadaan Komprehensif',
            'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
            'metode_pengadaan' => PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'nominal_harga_perkiraan' => 110000000,
            'alokasi_pagu_paket' => 120000000,
            'file_dokumen_pengadaan' => $fileDokPaket,
            'file_kwitansi_belanja' => $fileKwitansiPaket,
            'status_paket' => PaketPengadaan::STATUS_SELESAI,
            'nomor_surat' => $nomorSurat,
            'tanggal_registrasi_paket' => now(),
            'id_user_ppbj' => $this->ppbj->id_user,
            'id_user_ppk' => $this->ppk->id_user,
            'id_user_pp' => $this->pp->id_user,
        ]);

        // Reviu Dokumen

        ReviuDokumen::create([
            'nomor_paket' => $nomorPaket,
            'id_user' => $this->pp->id_user,
            'role_reviewer' => 'pp',
            'catatan_perbaikan' => 'Dokumen spesifikasi terverifikasi',
            'status_reviu' => 'disetujui',
            'tanggal_reviu' => now(),
        ]);

        // Kontrak SPK
        KontrakSPK::create([
            'nomor_spk' => $nomorSpk,
            'nomor_paket' => $nomorPaket,
            'id_user' => $this->ppk->id_user,
            'tipe_dokumen' => KontrakSPK::DOKUMEN_SPK,
            'sifat_kontrak' => KontrakSPK::TIPE_SEKALI_SELESAI,
            'status_spk' => KontrakSPK::STATUS_SELESAI,
            'tanggal_spk' => now(),
            'jadwal_pengiriman' => now()->addDays(14),
            'nilai_kontrak' => 110000000,
            'file_spk' => $fileSpk,
        ]);

        // Penerimaan Barang & Dokumentasi Foto
        $penerimaan = PenerimaanBarang::create([
            'nomor_bast' => 'BAST/TIK/2026/001',
            'nomor_paket' => $nomorPaket,
            'id_user' => $this->admin->id_user,
            'tanggal_penerimaan' => now(),
            'status_fisik' => PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
            'file_bast_signed' => $fileBast,
        ]);

        DokumentasiPenerimaanBarang::create([
            'id_penerimaan' => $penerimaan->id_penerimaan,
            'foto_dokumentasi' => $fileFoto,
            'keterangan_foto' => 'Foto fisik server',
            'uploaded_at' => now(),
        ]);

        // Pembayaran, Memo Bayar, Transaksi Pencairan
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => $nomorPembayaran,
            'nomor_paket' => $nomorPaket,
            'id_user_pengaju' => $this->ppbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 110000000,
            'tanggal_pengajuan' => now(),
            'jatuh_tempo' => now()->addDays(30),
            'status_pembayaran' => Pembayaran::STATUS_LUNAS,
            'file_invoice' => $fileInvoice,
        ]);

        MemoBayar::create([
            'id_user' => $this->ppk->id_user,
            'nomor_pembayaran' => $nomorPembayaran,
            'role_approver' => MemoBayar::ROLE_PPK,
            'urutan_level' => 1,
            'status_memo' => MemoBayar::STATUS_DISETUJUI,
            'catatan_memo' => 'Memo bayar level 1 disetujui',
            'tanggal_acc' => now(),
        ]);

        TransaksiPencairan::create([
            'nomor_transaksi_bank' => 'TRX-BANK-998877',
            'nomor_pembayaran' => $nomorPembayaran,
            'id_user' => $this->admin->id_user,
            'tanggal_transfer' => now(),
            'nominal_transfer' => 110000000,
            'file_bukti_transfer' => $fileBuktiTransfer,
            'catatan_keuangan' => 'Dana berhasil ditransfer ke vendor',
        ]);

        // Reminder & Notifikasi User
        $reminder = Reminder::create([
            'nomor_paket' => $nomorPaket,
            'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
            'waktu_trigger' => now()->addDays(5),
            'judul_alert' => 'Pengingat Pengiriman Barang',
            'pesan_alert' => 'Batas pengiriman server laboratorium',
        ]);

        NotifikasiUser::create([
            'id_user' => $this->userPemohonA->id_user,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        // 3. Eksekusi penghapusan oleh Admin
        $this->actingAs($this->admin);
        $response = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        // 4. Verifikasi seluruh tabel relasi bersih dari basis data
        $this->assertDatabaseMissing('permohonan_pengadaan', ['nomor_surat' => $nomorSurat]);
        $this->assertDatabaseMissing('verifikasi_paralel', ['nomor_surat' => $nomorSurat]);
        $this->assertDatabaseMissing('alokasi_mak', ['nomor_surat' => $nomorSurat]);
        $this->assertDatabaseMissing('paket_pengadaan', ['nomor_paket' => $nomorPaket]);
        $this->assertDatabaseMissing('kontrak_spk', ['nomor_spk' => $nomorSpk]);
        $this->assertDatabaseMissing('penerimaan_barang', ['nomor_paket' => $nomorPaket]);
        $this->assertDatabaseMissing('penerimaan_barang_foto_dokumentasi', ['id_penerimaan' => $penerimaan->id_penerimaan]);
        $this->assertDatabaseMissing('pembayaran', ['nomor_pembayaran' => $nomorPembayaran]);
        $this->assertDatabaseMissing('memo_bayar', ['nomor_pembayaran' => $nomorPembayaran]);
        $this->assertDatabaseMissing('transaksi_pencairan', ['nomor_transaksi_bank' => 'TRX-BANK-998877']);
        $this->assertDatabaseMissing('reminder', ['nomor_paket' => $nomorPaket]);
        $this->assertDatabaseMissing('notifikasi_user', ['id_reminder' => $reminder->id_reminder]);

        // Surat anak split tetap ada namun relasi induknya ter-unlink (null)
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/SPLIT-CHILD/PL3/TIK/2026',
            'nomor_surat_induk_split' => null,
        ]);

        // 5. Verifikasi seluruh berkas fisik pada disk storage telah dihapus (purged)
        Storage::disk('public')->assertMissing($filePermohonan);
        Storage::disk('public')->assertMissing($fileKak);
        Storage::disk('public')->assertMissing($fileSpek);
        Storage::disk('public')->assertMissing($fileDokPaket);
        Storage::disk('public')->assertMissing($fileKwitansiPaket);
        Storage::disk('public')->assertMissing($fileSpk);
        Storage::disk('public')->assertMissing($fileBast);
        Storage::disk('public')->assertMissing($fileFoto);
        Storage::disk('public')->assertMissing($fileInvoice);
        Storage::disk('public')->assertMissing($fileBuktiTransfer);
    }

    /**
     * 6. Verifikasi proteksi IDOR: Pengguna reguler DILARANG menghapus permohonan milik pengguna lain.
     */
    public function test_regular_user_cannot_delete_other_users_proposal(): void
    {
        $permohonanA = PermohonanPengadaan::create([
            'nomor_surat' => 'B/PROT-USER-A/PL3/TIK/2026',
            'perihal' => 'Usulan Eksklusif Pemohon A',
            'tujuan_pengadaan_ringkas' => 'Usulan A',
            'anggaran_diajukan' => 20000000,
            'estimasi_total_pagu' => 20000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/dummy.pdf',
        ]);

        // User B mencoba menghapus permohonan milik User A
        $this->actingAs($this->userPemohonB);
        $response = $this->delete(route('permohonan.destroy', $permohonanA->nomor_surat));

        $response->assertStatus(403);

        // Dokumen tetap utuh di basis data
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/PROT-USER-A/PL3/TIK/2026',
        ]);
    }

    /**
     * 7. Verifikasi batasan pengguna reguler: Pengguna reguler DILARANG menghapus usulan di luar status draft/diajukan/ditolak.
     */
    public function test_regular_user_cannot_delete_own_proposal_outside_draft_diajukan_ditolak(): void
    {
        $restrictedStatuses = [
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            PermohonanPengadaan::STATUS_SELESAI,
        ];

        foreach ($restrictedStatuses as $idx => $status) {
            $nomorSurat = "B/RESTRICTED-USER-{$idx}/PL3/TIK/2026";

            $permohonan = PermohonanPengadaan::create([
                'nomor_surat' => $nomorSurat,
                'perihal' => "Pengajuan Status {$status}",
                'tujuan_pengadaan_ringkas' => "Pengajuan {$status}",
                'anggaran_diajukan' => 35000000,
                'estimasi_total_pagu' => 35000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $this->userPemohonA->id_user,
                'status_permohonan' => $status,
                'tanggal_pengajuan' => now(),
                'file_permohonan' => 'permohonan/dummy.pdf',
            ]);

            // User A mencoba menghapus usulan miliknya sendiri yang sudah berstatus lanjut
            $this->actingAs($this->userPemohonA);
            $response = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));

            // Sistem menolak penghapusan dengan redirect dan pesan error
            $response->assertRedirect(route('permohonan.index'));
            $response->assertSessionHas('error', 'Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');

            // Dokumen tetap aman di database
            $this->assertDatabaseHas('permohonan_pengadaan', [
                'nomor_surat' => $nomorSurat,
            ]);
        }
    }

    /**
     * 8. Verifikasi pengguna reguler DAPAT menghapus usulannya sendiri pada status draft, diajukan, atau ditolak.
     */
    public function test_regular_user_can_delete_own_proposal_in_draft_diajukan_or_ditolak(): void
    {
        $allowedStatuses = [
            'draft',
            PermohonanPengadaan::STATUS_DIAJUKAN,
            PermohonanPengadaan::STATUS_DITOLAK,
        ];

        foreach ($allowedStatuses as $idx => $status) {
            $nomorSurat = "B/ALLOWED-DEL-USER-{$idx}/PL3/TIK/2026";

            $permohonan = PermohonanPengadaan::create([
                'nomor_surat' => $nomorSurat,
                'perihal' => "Pengajuan Awal Status {$status}",
                'tujuan_pengadaan_ringkas' => "Pengajuan {$status}",
                'anggaran_diajukan' => 15000000,
                'estimasi_total_pagu' => 15000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $this->userPemohonA->id_user,
                'status_permohonan' => $status,
                'tanggal_pengajuan' => now(),
                'file_permohonan' => 'permohonan/dummy.pdf',
            ]);

            $this->actingAs($this->userPemohonA);
            $response = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));

            $response->assertRedirect(route('permohonan.index'));
            $response->assertSessionHas('success');

            $this->assertDatabaseMissing('permohonan_pengadaan', [
                'nomor_surat' => $nomorSurat,
            ]);
        }
    }
}
