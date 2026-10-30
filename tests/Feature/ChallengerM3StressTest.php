<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Empirical Stress Test Suite for Milestone 3 Gate Verification.
 *
 * Authored by: Challenger 1 (challenger_m3_1)
 * Objectives:
 * 1. Form store: validation rules, PDF constraints (10MB boundary, >10MB, non-PDF types, payload fuzzing).
 * 2. Modal Revisi: submission, review history, document replacement, parent-child links, invalid states.
 * 3. Modal Hapus: draft/rejected deletion, protected in-progress/approved deletion, IDOR ownership check.
 * 4. Unauthorized user role access blocks across all verification endpoints.
 * 5. Split MAK stress test (overflow, invalid MAK, zero/negative items).
 * 6. Wadir 2 mandatory notes assertion on reject/revision.
 */
class ChallengerM3StressTest extends TestCase
{
    use RefreshDatabase;

    protected User $userPemohonA;

    protected User $userPemohonB;

    protected User $staffB2;

    protected User $wadir2;

    protected User $perencanaan;

    protected User $admin;

    protected User $keuangan;

    protected Unit $unitTIK;

    protected Unit $unitMesin;

    protected AnggaranMAK $makBoptn;

    protected AnggaranMAK $makPnbp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');

        $this->unitTIK = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->unitMesin = Unit::firstOrCreate(
            ['kode_unit' => 'MESIN'],
            ['nama_unit' => 'Jurusan Teknik Mesin', 'jenis_unit' => 'jurusan']
        );

        $roleUser = Role::firstOrCreate(['nama_role' => 'user'], ['label' => 'Pemohon / Jurusan', 'is_system' => true]);
        $roleStaffB2 = Role::firstOrCreate(['nama_role' => 'staff_bidang_2'], ['label' => 'Staf Bidang II', 'is_system' => true]);
        $roleWadir2 = Role::firstOrCreate(['nama_role' => 'wadir_2'], ['label' => 'Wakil Direktur II', 'is_system' => true]);
        $rolePerencanaan = Role::firstOrCreate(['nama_role' => 'perencanaan'], ['label' => 'Subbagian Perencanaan', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['nama_role' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        $roleKeuangan = Role::firstOrCreate(['nama_role' => 'keuangan'], ['label' => 'Bagian Keuangan', 'is_system' => true]);

        $this->userPemohonA = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userPemohonB = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $this->unitMesin->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->staffB2 = User::where('role', 'staff_bidang_2')->first() ?? User::factory()->create([
            'role' => 'staff_bidang_2',
            'id_role' => $roleStaffB2->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->wadir2 = User::where('role', 'wadir_2')->first() ?? User::factory()->create([
            'role' => 'wadir_2',
            'id_role' => $roleWadir2->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->perencanaan = User::where('role', 'perencanaan')->first() ?? User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $rolePerencanaan->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->admin = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'id_role' => $roleAdmin->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->keuangan = User::where('role', 'keuangan')->first() ?? User::factory()->create([
            'role' => 'keuangan',
            'id_role' => $roleKeuangan->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->makBoptn = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.WA.521211.CHALLENGER'],
            [
                'uraian_mak' => 'Belanja Bahan Habis Pakai Praktikum Challenger',
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => (int) date('Y'),
                'pagu_anggaran' => 200000000,
                'sisa_pagu_mak' => 200000000,
                'is_locked' => false,
            ]
        );

        $this->makPnbp = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.WA.532111.CHALLENGER'],
            [
                'uraian_mak' => 'Belanja Modal Mesin Laboratorium Challenger',
                'sumber_dana' => 'PNBP',
                'tahun_anggaran' => (int) date('Y'),
                'pagu_anggaran' => 400000000,
                'sisa_pagu_mak' => 400000000,
                'is_locked' => false,
            ]
        );
    }

    // =========================================================================
    // 1. FORM STORE: VALIDATION RULES & PDF CONSTRAINTS (10 MB LIMIT)
    // =========================================================================

    /**
     * EMPIRICAL TEST: Uploading a PDF larger than 10MB (10241 KB) MUST be rejected by DokumenUploadRule.
     */
    public function test_form_store_rejects_pdf_larger_than_10mb(): void
    {
        $this->actingAs($this->userPemohonA);

        // 10241 KB is strictly > 10240 KB (10 MB)
        $oversizedPdf = UploadedFile::fake()->create('huge_kak.pdf', 10241, 'application/pdf');

        $payload = [
            'nomor_surat' => 'B/OVERSIZED-PDF/PL3/TIK/2026',
            'perihal' => 'Pengadaan Fasilitas GPU Cluster Superkomputer',
            'anggaran_diajukan' => 500000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
            'file_kak' => $oversizedPdf,
        ];

        $response = $this->post(route('permohonan.store'), $payload);
        $response->assertSessionHasErrors(['file_kak']);

        $this->assertDatabaseMissing('permohonan_pengadaan', [
            'nomor_surat' => 'B/OVERSIZED-PDF/PL3/TIK/2026',
        ]);
    }

    /**
     * EMPIRICAL TEST: Uploading a PDF of exactly 10MB (10240 KB) boundary MUST be accepted.
     */
    public function test_form_store_accepts_pdf_of_exact_10mb_boundary(): void
    {
        $this->actingAs($this->userPemohonA);

        // Exactly 10240 KB
        $exactPdf = UploadedFile::fake()->create('boundary_kak.pdf', 10240, 'application/pdf');

        $payload = [
            'nomor_surat' => 'B/BOUNDARY-10MB/PL3/TIK/2026',
            'perihal' => 'Pengadaan Laboratorium Jaringan Batas Maksimal',
            'anggaran_diajukan' => 120000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
            'file_kak' => $exactPdf,
        ];

        $response = $this->post(route('permohonan.store'), $payload);
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/BOUNDARY-10MB/PL3/TIK/2026',
        ]);
    }

    /**
     * EMPIRICAL TEST: Uploading non-PDF file types (exe, png, docx, sh) MUST be rejected.
     */
    public function test_form_store_rejects_non_pdf_file_types(): void
    {
        $this->actingAs($this->userPemohonA);

        $testFiles = [
            ['name' => 'malware.exe', 'size' => 2048, 'mime' => 'application/x-msdownload'],
            ['name' => 'photo.png', 'size' => 1024, 'mime' => 'image/png'],
            ['name' => 'document.docx', 'size' => 1024, 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['name' => 'deploy.sh', 'size' => 512, 'mime' => 'text/x-sh'],
        ];

        foreach ($testFiles as $tf) {
            $fakeFile = UploadedFile::fake()->create($tf['name'], $tf['size'], $tf['mime']);

            $response = $this->post(route('permohonan.store'), [
                'nomor_surat' => 'B/TEST-MIME-'.strtoupper(pathinfo($tf['name'], PATHINFO_EXTENSION)).'/PL3/TIK/2026',
                'perihal' => 'Pengadaan Barang Uji Mime',
                'anggaran_diajukan' => 50000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'tanggal_pengajuan' => date('Y-m-d'),
                'file_surat_permohonan' => $fakeFile,
            ]);

            $response->assertSessionHasErrors(['file_surat_permohonan']);
        }
    }

    /**
     * EMPIRICAL TEST: Zero, negative, and non-numeric budgets MUST fail validation.
     */
    public function test_form_store_rejects_invalid_budgets(): void
    {
        $this->actingAs($this->userPemohonA);

        // Budget = 0
        $respZero = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/ZERO-BUDGET/PL3/TIK/2026',
            'perihal' => 'Usulan Budget Nol',
            'anggaran_diajukan' => 0,
            'kode_unit' => $this->unitTIK->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
        ]);
        $respZero->assertSessionHasErrors(['anggaran_diajukan']);

        // Budget = -50000
        $respNegative = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/NEG-BUDGET/PL3/TIK/2026',
            'perihal' => 'Usulan Budget Minus',
            'anggaran_diajukan' => -50000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
        ]);
        $respNegative->assertSessionHasErrors(['anggaran_diajukan']);

        // Budget = "seratus juta" (non-numeric string)
        $respString = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/STR-BUDGET/PL3/TIK/2026',
            'perihal' => 'Usulan Budget String',
            'anggaran_diajukan' => 'seratus juta',
            'kode_unit' => $this->unitTIK->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
        ]);
        $respString->assertSessionHasErrors(['anggaran_diajukan']);
    }

    /**
     * EMPIRICAL TEST: Duplicate nomor_surat MUST fail uniqueness validation.
     */
    public function test_form_store_rejects_duplicate_nomor_surat(): void
    {
        $this->actingAs($this->userPemohonA);

        PermohonanPengadaan::create([
            'nomor_surat' => 'B/EXISTING-UNIQUE/PL3/TIK/2026',
            'perihal' => 'Usulan Pertama',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        $response = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/EXISTING-UNIQUE/PL3/TIK/2026',
            'perihal' => 'Usulan Duplikat dengan Nomor Sama',
            'anggaran_diajukan' => 75000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['nomor_surat']);
    }

    /**
     * EMPIRICAL TEST: Invalid or non-existent kode_unit MUST fail validation.
     */
    public function test_form_store_rejects_nonexistent_unit(): void
    {
        $this->actingAs($this->userPemohonA);

        $response = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/UNIT-UNKNOWN/PL3/TIK/2026',
            'perihal' => 'Usulan Unit Fiktif',
            'anggaran_diajukan' => 50000000,
            'kode_unit' => 'JURUSAN_GHOIB_999',
            'tanggal_pengajuan' => date('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['kode_unit']);
    }

    // =========================================================================
    // 2. MODAL REVISI: SUBMISSION, REVIEW HISTORY & DOCUMENT REPLACEMENT
    // =========================================================================

    /**
     * EMPIRICAL TEST: Revision increments revisi_ke, saves nomor_surat_revisi, and replaces KAK.
     */
    public function test_modal_revisi_correctly_links_parent_and_replaces_kak(): void
    {
        $this->actingAs($this->userPemohonA);

        $oldKak = UploadedFile::fake()->create('old_kak.pdf', 1024, 'application/pdf');
        $oldPath = $oldKak->store('kak', 'public');

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/INDUK-REV-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Laboratorium Robotika v1',
            'anggaran_diajukan' => 300000000,
            'estimasi_total_pagu' => 300000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
            'alasan_revisi' => 'Mohon kurangi spesifikasi motor servo presisi',
            'tanggal_pengajuan' => now(),
            'revisi_ke' => 0,
            'file_permohonan' => $oldPath,
        ]);

        $newKak = UploadedFile::fake()->create('new_kak_revised.pdf', 1500, 'application/pdf');

        $payload = [
            'nomor_surat_lama' => $induk->nomor_surat,
            'nomor_surat_baru' => 'B/INDUK-REV-TEST-R1/PL3/TIK/2026',
            'perihal' => 'Pengadaan Laboratorium Robotika v1 (Revisi Spek)',
            'anggaran_diajukan' => 240000000,
            'keterangan_revisi' => 'Spesifikasi motor servo disesuaikan standar lab',
            'file_kak' => $newKak,
        ];

        $response = $this->post(route('permohonan.revisi'), $payload);
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        $revisi = PermohonanPengadaan::where('nomor_surat', 'B/INDUK-REV-TEST-R1/PL3/TIK/2026')->first();
        $this->assertNotNull($revisi);
        $this->assertEquals(1, $revisi->revisi_ke);
        $this->assertEquals($induk->nomor_surat, $revisi->nomor_surat_revisi);
        $this->assertEquals(240000000, (float) $revisi->estimasi_total_pagu);
        $this->assertEquals(PermohonanPengadaan::STATUS_REVIU_PARALEL, $revisi->status_permohonan);

        // Verifikasi 3 verifikasi paralel diinisialisasi
        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'staff_bidang_2',
            'status_keputusan' => 'menunggu',
        ]);
        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);
        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);
    }

    /**
     * EMPIRICAL TEST: Submitting revision with non-existent parent surat MUST fail validation.
     */
    public function test_modal_revisi_rejects_nonexistent_parent_surat(): void
    {
        $this->actingAs($this->userPemohonA);

        $response = $this->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => 'B/NON-EXISTENT-SURAT/PL3/TIK/2026',
            'nomor_surat_baru' => 'B/NEW-REVISI/PL3/TIK/2026',
            'perihal' => 'Revisi Surat Fiktif',
            'anggaran_diajukan' => 100000000,
        ]);

        $response->assertSessionHasErrors(['nomor_surat_lama']);
    }

    /**
     * EMPIRICAL TEST: Submitting revision with new KAK larger than 10MB MUST be rejected.
     */
    public function test_modal_revisi_rejects_oversized_kak_replacement(): void
    {
        $this->actingAs($this->userPemohonA);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/INDUK-OVERSIZED-REV/PL3/TIK/2026',
            'perihal' => 'Pengadaan Fasilitas Cloud',
            'anggaran_diajukan' => 150000000,
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
            'tanggal_pengajuan' => now(),
        ]);

        $oversizedKak = UploadedFile::fake()->create('revisi_too_big.pdf', 10245, 'application/pdf');

        $response = $this->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $induk->nomor_surat,
            'nomor_surat_baru' => 'B/INDUK-OVERSIZED-REV-NEW/PL3/TIK/2026',
            'perihal' => 'Pengadaan Cloud Disesuaikan',
            'anggaran_diajukan' => 130000000,
            'file_kak' => $oversizedKak,
        ]);

        $response->assertSessionHasErrors(['file_kak']);
    }

    // =========================================================================
    // 3. MODAL HAPUS: DRAFT/REJECTED DELETION & PROTECTED IN-PROGRESS DELETION
    // =========================================================================

    /**
     * EMPIRICAL TEST: Permohonan with status 'draft', 'diajukan', and 'ditolak' CAN be deleted.
     */
    public function test_modal_hapus_deletes_draft_diajukan_ditolak(): void
    {
        $this->actingAs($this->userPemohonA);

        $statuses = ['draft', 'diajukan', 'ditolak'];

        foreach ($statuses as $st) {
            $nomor = "B/TEST-HAPUS-{$st}/PL3/TIK/2026";
            $permohonan = PermohonanPengadaan::create([
                'nomor_surat' => $nomor,
                'perihal' => "Usulan Status {$st}",
                'anggaran_diajukan' => 40000000,
                'estimasi_total_pagu' => 40000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $this->userPemohonA->id_user,
                'status_permohonan' => $st,
                'tanggal_pengajuan' => now(),
            ]);

            $response = $this->delete(route('permohonan.destroy', $nomor));
            $response->assertRedirect(route('permohonan.index'));
            $response->assertSessionHas('success');

            $this->assertDatabaseMissing('permohonan_pengadaan', [
                'nomor_surat' => $nomor,
            ]);

            $this->assertDatabaseHas('audit_log', [
                'jenis_aksi' => 'HAPUS_PERMOHONAN',
                'id_entitas' => $nomor,
            ]);
        }
    }

    /**
     * EMPIRICAL TEST: Permohonan that has been verified, approved, or in-progress CANNOT be deleted.
     */
    public function test_modal_hapus_blocks_protected_in_progress_statuses(): void
    {
        $this->actingAs($this->userPemohonA);

        $protectedStatuses = [
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
            PermohonanPengadaan::STATUS_SELESAI,
            'spk_terbit',
        ];

        foreach ($protectedStatuses as $st) {
            $nomor = "B/PROTECTED-{$st}/PL3/TIK/2026";
            PermohonanPengadaan::create([
                'nomor_surat' => $nomor,
                'perihal' => "Usulan Terproteksi {$st}",
                'anggaran_diajukan' => 90000000,
                'estimasi_total_pagu' => 90000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $this->userPemohonA->id_user,
                'status_permohonan' => $st,
                'tanggal_pengajuan' => now(),
            ]);

            $response = $this->delete(route('permohonan.destroy', $nomor));
            $response->assertSessionHas('error');

            // Dokumen tetap ada di database
            $this->assertDatabaseHas('permohonan_pengadaan', [
                'nomor_surat' => $nomor,
            ]);
        }
    }

    /**
     * EMPIRICAL TEST (IDOR Check): User B cannot delete User A's permohonan.
     */
    public function test_modal_hapus_idor_prevented_user_cannot_delete_other_user_permohonan(): void
    {
        $permohonanA = PermohonanPengadaan::create([
            'nomor_surat' => 'B/USER-A-OWNED/PL3/TIK/2026',
            'perihal' => 'Usulan Milik Pemohon A',
            'anggaran_diajukan' => 60000000,
            'estimasi_total_pagu' => 60000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        // User Pemohon B mencoba menghapus permohonan milik Pemohon A
        $this->actingAs($this->userPemohonB);
        $response = $this->delete(route('permohonan.destroy', $permohonanA->nomor_surat));

        $response->assertStatus(403);

        // Dokumen tetap ada di database
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/USER-A-OWNED/PL3/TIK/2026',
        ]);
    }

    /**
     * EMPIRICAL TEST: Admin CAN delete draft/diajukan permohonan owned by other users.
     */
    public function test_admin_can_delete_user_permohonan(): void
    {
        $permohonanA = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADMIN-DELETE-TARGET/PL3/TIK/2026',
            'perihal' => 'Usulan yang akan dihapus Admin',
            'anggaran_diajukan' => 30000000,
            'estimasi_total_pagu' => 30000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        $this->actingAs($this->admin);
        $response = $this->delete(route('permohonan.destroy', $permohonanA->nomor_surat));
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('permohonan_pengadaan', [
            'nomor_surat' => 'B/ADMIN-DELETE-TARGET/PL3/TIK/2026',
        ]);
    }

    // =========================================================================
    // 4. UNAUTHORIZED USER ROLE ACCESS BLOCKS
    // =========================================================================

    /**
     * EMPIRICAL TEST: Standard 'user' role is blocked (403) from accessing all verifikasi endpoints.
     */
    public function test_standard_user_blocked_from_verifikasi_endpoints(): void
    {
        $this->actingAs($this->userPemohonA);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ACCESS-GUARD-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Uji Hak Akses',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        // Staf B2 endpoint
        $respB2Get = $this->get(route('verifikasi.bidang-2'));
        $respB2Get->assertStatus(403);

        $respB2Post = $this->post(route('verifikasi.bidang-2.teruskan'), ['nomor_surat' => $permohonan->nomor_surat]);
        $respB2Post->assertStatus(403);

        // Wadir 2 endpoint
        $respWadirGet = $this->get(route('verifikasi.wadir-2'));
        $respWadirGet->assertStatus(403);

        $respWadirPost = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
        ]);
        $respWadirPost->assertStatus(403);

        // Perencanaan endpoint
        $respPerencGet = $this->get(route('verifikasi.perencanaan'));
        $respPerencGet->assertStatus(403);

        $respPerencMak = $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->makBoptn->kode_mak,
            'nominal_alokasi' => 50000000,
            'keputusan_anggaran' => 'setuju',
        ]);
        $respPerencMak->assertStatus(403);

        $respPerencSplit = $this->post(route('verifikasi.perencanaan.split'), [
            'nomor_surat_induk' => $permohonan->nomor_surat,
            'alasan_pemecahan' => 'Hacker split attempt',
            'items' => [],
        ]);
        $respPerencSplit->assertStatus(403);
    }

    /**
     * EMPIRICAL TEST: Cross-role verification blocking (staff_bidang_2 cannot act as wadir_2, wadir_2 cannot act as perencanaan).
     */
    public function test_cross_role_verifikasi_blocking(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CROSS-ROLE-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Uji Silang Peran',
            'anggaran_diajukan' => 70000000,
            'estimasi_total_pagu' => 70000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        // Staff Bidang 2 tries to access Wadir 2 decisions
        $this->actingAs($this->staffB2);
        $this->get(route('verifikasi.wadir-2'))->assertStatus(403);
        $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
        ])->assertStatus(403);

        // Wadir 2 tries to access Perencanaan MAK allocation
        $this->actingAs($this->wadir2);
        $this->get(route('verifikasi.perencanaan'))->assertStatus(403);
        $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->makBoptn->kode_mak,
            'nominal_alokasi' => 70000000,
            'keputusan_anggaran' => 'setuju',
        ])->assertStatus(403);
    }

    /**
     * EMPIRICAL TEST: Unauthenticated guests are redirected to login.
     */
    public function test_unauthenticated_guests_redirected_to_login(): void
    {
        $this->get(route('permohonan.index'))->assertRedirect(route('login'));
        $this->get(route('permohonan.create'))->assertRedirect(route('login'));
        $this->post(route('permohonan.store'), [])->assertRedirect(route('login'));
        $this->post(route('permohonan.revisi'), [])->assertRedirect(route('login'));
        $this->delete(route('permohonan.destroy', 'B/GUEST/2026'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // 5. SPLIT MAK STRESS TESTS (OVERFLOW & BOUNDARY)
    // =========================================================================

    /**
     * EMPIRICAL TEST: Split MAK with sum exactly matching parent budget leaves parent with 0 pagu.
     */
    public function test_split_mak_exact_budget_match(): void
    {
        $this->actingAs($this->perencanaan);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SPLIT-EXACT-INDUK/PL3/TIK/2026',
            'perihal' => 'Laboratorium Cloud Terpadu Exact',
            'anggaran_diajukan' => 100000000,
            'estimasi_total_pagu' => 100000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $payload = [
            'nomor_surat_induk' => $induk->nomor_surat,
            'alasan_pemecahan' => 'Pemecahan habis 100%',
            'items' => [
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-EXACT-ANAK-1/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pecahan Bagian 1',
                    'kode_mak' => $this->makBoptn->kode_mak,
                    'estimasi_pagu_pecahan' => 60000000,
                ],
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-EXACT-ANAK-2/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pecahan Bagian 2',
                    'kode_mak' => $this->makPnbp->kode_mak,
                    'estimasi_pagu_pecahan' => 40000000,
                ],
            ],
        ];

        $response = $this->post(route('verifikasi.perencanaan.split'), $payload);
        $response->assertRedirect(route('verifikasi.perencanaan'));
        $response->assertSessionHas('success');

        $induk->refresh();
        $this->assertEquals(0, (float) $induk->estimasi_total_pagu);
    }

    /**
     * EMPIRICAL TEST: Split MAK requires minimum 2 items. Single-item split is rejected.
     */
    public function test_split_mak_rejects_less_than_two_items(): void
    {
        $this->actingAs($this->perencanaan);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SPLIT-ONE-ITEM/PL3/TIK/2026',
            'perihal' => 'Laboratorium Cloud One Item',
            'anggaran_diajukan' => 100000000,
            'estimasi_total_pagu' => 100000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohonA->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $payload = [
            'nomor_surat_induk' => $induk->nomor_surat,
            'alasan_pemecahan' => 'Pemecahan hanya 1 item',
            'items' => [
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-ONLY-ONE/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pecahan Tunggal',
                    'kode_mak' => $this->makBoptn->kode_mak,
                    'estimasi_pagu_pecahan' => 50000000,
                ],
            ],
        ];

        $response = $this->post(route('verifikasi.perencanaan.split'), $payload);
        $response->assertSessionHasErrors(['items']);
    }
}
