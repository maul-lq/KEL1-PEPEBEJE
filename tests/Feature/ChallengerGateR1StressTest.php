<?php

namespace Tests\Feature;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\ReviuDokumen;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Empirical Adversarial Challenge Test Suite for Gate Verification (R1).
 *
 * Verifies:
 * 1. MAK Allocation Boundary:
 *    - Allocating more than sisa_pagu_mak in HomeController::alokasiMakPerencanaan is safely blocked,
 *      returns redirect with session error, and does NOT throw an uncaught exception or error 500.
 *    - Exact boundary allocation (nominal == sisa_pagu_mak) succeeds cleanly.
 *    - Extreme numerical overflow does not crash the application.
 * 2. Chained Workflow Integrity:
 *    - New proposals and revisions begin at status 'menunggu_bidang_2'.
 *    - Bidang 2 forwards to Perencanaan ('cek_mak_perencanaan').
 *    - Perencanaan assigns 1 of 4 PPK accounts and issues memo to Wadir 2 & PPK ('menunggu_acc_wadir_ppk').
 *    - Both Wadir 2 and the assigned PPK must approve to reach PPBJ ('disetujui').
 *    - Unauthorized PPK accounts cannot approve proposals assigned to another PPK (403 Forbidden).
 * 3. Role PP Restrictions:
 *    - Submitting status_reviu = disetujui as role 'pp' is rejected via Web, API, and review-pp endpoints.
 *    - PP submitting valid verification transitions status to menunggu_approval_ppk.
 */
class ChallengerGateR1StressTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $pemohon;

    private User $staffB2;

    private User $wadir2;

    private User $perencanaan;

    private User $ppbj;

    private User $pp;

    private User $ppk1;

    private User $ppk2;

    private Vendor $vendor;

    private AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();

        $this->seed(RolePermissionSeeder::class);
        $roles = Role::all()->keyBy('nama_role');

        $this->unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->pemohon = User::factory()->create([
            'role' => 'user',
            'id_role' => $roles['user']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Dosen Pengusul TIK',
        ]);

        $this->staffB2 = User::factory()->create([
            'role' => 'staff_bidang_2',
            'id_role' => $roles['staff_bidang_2']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Staf Bidang II PNJ',
        ]);

        $this->wadir2 = User::factory()->create([
            'role' => 'wadir_2',
            'id_role' => $roles['wadir_2']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Wakil Direktur II PNJ',
        ]);

        $this->perencanaan = User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $roles['perencanaan']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Analis Perencanaan PNJ',
        ]);

        $this->ppbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $roles['ppbj']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Koordinator PPBJ PNJ',
        ]);

        $this->pp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $roles['pp']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Pejabat Pengadaan Tunggal PNJ',
        ]);

        $this->ppk1 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'PPK 1 Bidang Akademik',
        ]);

        $this->ppk2 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'PPK 2 Bidang Sarana Prasarana',
        ]);

        $this->vendor = Vendor::create([
            'npwp' => '01.234.567.8-901.000',
            'nib' => '1234567890123',
            'nama_perusahaan' => 'CV Sumber Makmur Digital',
            'alamat' => 'Jl. Kukusan Raya No. 45 Depok',
            'is_active' => true,
            'nama_pic' => 'Ahmad Fauzi',
            'telepon_pic' => '08123456789',
            'email_pic' => 'ahmad@sumbermakmur.id',
            'nama_bank' => 'Mandiri',
            'nomor_rekening' => '1570001234567',
            'atas_nama' => 'CV Sumber Makmur Digital',
        ]);

        $this->mak = AnggaranMAK::create([
            'kode_mak' => '023.18.WA.521211.999',
            'uraian_mak' => 'Pagu Anggaran Stress Testing Challenger',
            'tahun_anggaran' => 2026,
            'pagu_anggaran' => 50000000,
            'sisa_pagu_mak' => 50000000,
            'sumber_dana' => 'RM',
            'is_locked' => false,
            'id_user' => $this->perencanaan->id_user,
        ]);
    }

    // =========================================================================
    // 1. MAK ALLOCATION BOUNDARY EMPIRICAL CHALLENGES
    // =========================================================================

    public function test_mak_allocation_exceeding_sisa_pagu_is_safely_blocked_with_session_error_and_no_500(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHALLENGE-MAK-01/TIK/2026',
            'judul_pengadaan' => 'Uji Batas Alokasi MAK Overflow',
            'tujuan_pengadaan_ringkas' => 'Stress test alokasi MAK',
            'file_permohonan' => 'permohonan/dummy.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 60000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
        ]);

        $this->actingAs($this->perencanaan);

        // Attempt to allocate sisa_pagu_mak + 1 (50_000_001 vs 50_000_000)
        $response = $this->from(route('verifikasi.perencanaan'))
            ->post(route('verifikasi.perencanaan.mak'), [
                'nomor_surat' => $permohonan->nomor_surat,
                'kode_mak' => $this->mak->kode_mak,
                'nominal_alokasi' => 50000001,
                'keputusan_anggaran' => 'setuju',
                'id_user_ppk_awal' => $this->ppk1->id_user,
                'nomor_memo_ketersediaan_mak' => 'MEMO/TEST/001',
                'catatan_anggaran' => 'Percobaan alokasi melebihi pagu',
            ]);

        // Must redirect back without throwing 500
        $response->assertStatus(302);
        $response->assertRedirect(route('verifikasi.perencanaan'));
        $response->assertSessionHas('error', 'Nominal alokasi melebihi sisa pagu MAK yang tersedia.');

        // Sisa pagu MAK must remain untouched
        $this->mak->refresh();
        $this->assertEquals(50000000, (float) $this->mak->sisa_pagu_mak);

        // No AlokasiMAK should be recorded
        $this->assertDatabaseMissing('alokasi_mak', [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
        ]);

        // Permohonan status must remain unchanged
        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN, $permohonan->status_permohonan);
    }

    public function test_mak_allocation_extreme_overflow_is_safely_blocked_without_uncaught_exception(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHALLENGE-MAK-02/TIK/2026',
            'judul_pengadaan' => 'Uji Batas Alokasi MAK Extreme Overflow',
            'tujuan_pengadaan_ringkas' => 'Stress test extreme numerical overflow',
            'file_permohonan' => 'permohonan/dummy.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 999999999999,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
        ]);

        $this->actingAs($this->perencanaan);

        $response = $this->from(route('verifikasi.perencanaan'))
            ->post(route('verifikasi.perencanaan.mak'), [
                'nomor_surat' => $permohonan->nomor_surat,
                'kode_mak' => $this->mak->kode_mak,
                'nominal_alokasi' => 999999999999,
                'keputusan_anggaran' => 'setuju',
                'id_user_ppk_awal' => $this->ppk1->id_user,
                'nomor_memo_ketersediaan_mak' => 'MEMO/TEST/002',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertEquals(50000000, (float) $this->mak->fresh()->sisa_pagu_mak);
    }

    public function test_mak_allocation_exact_boundary_consumes_entire_sisa_pagu_cleanly(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHALLENGE-MAK-03/TIK/2026',
            'judul_pengadaan' => 'Uji Batas Tepat Sisa Pagu MAK',
            'tujuan_pengadaan_ringkas' => 'Stress test exact boundary allocation',
            'file_permohonan' => 'permohonan/dummy.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
        ]);

        $this->actingAs($this->perencanaan);

        $response = $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 50000000,
            'keputusan_anggaran' => 'setuju',
            'id_user_ppk_awal' => $this->ppk1->id_user,
            'nomor_memo_ketersediaan_mak' => 'MEMO/TEST/EXACT/003',
            'catatan_anggaran' => 'Alokasi tepat sesuai sisa pagu',
        ]);

        $response->assertRedirect(route('verifikasi.perencanaan'));
        $this->mak->refresh();
        $this->assertEquals(0, (float) $this->mak->sisa_pagu_mak);

        $this->assertDatabaseHas('alokasi_mak', [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 50000000,
        ]);

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, $permohonan->status_permohonan);
    }

    // =========================================================================
    // 2. CHAINED WORKFLOW INTEGRITY EMPIRICAL CHALLENGES
    // =========================================================================

    public function test_new_proposal_begins_at_menunggu_bidang_2(): void
    {
        $this->actingAs($this->pemohon);

        $response = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/CHAIN-NEW-01/TIK/2026',
            'perihal' => 'Pengadaan Alat Praktikum Jaringan Baru',
            'tujuan_pengadaan_ringkas' => 'Untuk modul perkuliahan TIK',
            'anggaran_diajukan' => 45000000,
            'kode_unit' => 'TIK',
            'tanggal_pengajuan' => '2026-11-01',
            'file_surat_permohonan' => UploadedFile::fake()->create('usulan_chain.pdf', 256, 'application/pdf'),
        ]);

        $response->assertRedirect(route('permohonan.index'));

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/CHAIN-NEW-01/TIK/2026',
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        ]);

        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => 'B/CHAIN-NEW-01/TIK/2026',
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => 'menunggu',
        ]);
    }

    public function test_proposal_revision_begins_at_menunggu_bidang_2_and_increments_revisi_ke(): void
    {
        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/INDUK-REV-01/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Awal yang Ditolak/Revisi',
            'tujuan_pengadaan_ringkas' => 'KAK perlu diperbaiki',
            'file_permohonan' => 'permohonan/induk.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 40000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'revisi_ke' => 0,
        ]);

        $this->actingAs($this->pemohon);

        $response = $this->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $induk->nomor_surat,
            'nomor_surat_baru' => 'B/REVISI-01/TIK/2026',
            'perihal' => 'Pengadaan Revisi Alat Praktikum',
            'tujuan_pengadaan_ringkas' => 'Spesifikasi telah disesuaikan',
            'anggaran_diajukan' => 38000000,
            'keterangan_revisi' => 'Perbaikan KAK sesuai arahan Staf Bidang 2',
            'file_kak' => UploadedFile::fake()->create('kak_revisi.pdf', 300, 'application/pdf'),
        ]);

        $response->assertRedirect(route('permohonan.index'));

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/REVISI-01/TIK/2026',
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            'revisi_ke' => 1,
            'nomor_surat_revisi' => 'B/INDUK-REV-01/TIK/2026',
        ]);

        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => 'B/REVISI-01/TIK/2026',
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => 'menunggu',
        ]);
    }

    public function test_chained_progression_bidang2_forwards_to_perencanaan(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHAIN-B2-01/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Lanjutan Bidang 2',
            'tujuan_pengadaan_ringkas' => 'Verifikasi staf bidang 2',
            'file_permohonan' => 'permohonan/chain_b2.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 30000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
        ]);

        VerifikasiParalel::create([
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user' => $this->pemohon->id_user,
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => 'menunggu',
        ]);

        $this->actingAs($this->staffB2);

        $response = $this->post(route('verifikasi.bidang-2.teruskan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'nomor_memo_cek_mak' => 'MEMO/B2/CHAIN/001',
            'catatan_staff' => 'Berkas lengkap, mohon cek ketersediaan MAK ke Perencanaan.',
        ]);

        $response->assertRedirect(route('verifikasi.bidang-2'));

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN, $permohonan->status_permohonan);
        $this->assertEquals('MEMO/B2/CHAIN/001', $permohonan->nomor_memo_cek_mak);
    }

    public function test_chained_progression_both_wadir2_and_assigned_ppk_must_approve_to_reach_ppbj(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHAIN-PARALEL-01/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Laboratorium Jaringan Bersama',
            'tujuan_pengadaan_ringkas' => 'Uji chained approval',
            'file_permohonan' => 'permohonan/chain_paralel.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 25000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
        ]);

        // 1. Perencanaan assigns 1 of 4 PPK accounts (PPK 1) and sends Memo Ketersediaan MAK
        $this->actingAs($this->perencanaan);
        $resPerencanaan = $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 25000000,
            'keputusan_anggaran' => 'setuju',
            'id_user_ppk_awal' => $this->ppk1->id_user,
            'nomor_memo_ketersediaan_mak' => 'MEMO/PRC/CHAIN/001',
            'catatan_anggaran' => 'MAK siap, diteruskan ke Wadir 2 dan PPK 1.',
        ]);
        $resPerencanaan->assertRedirect(route('verifikasi.perencanaan'));

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, $permohonan->status_permohonan);
        $this->assertEquals($this->ppk1->id_user, $permohonan->id_user_ppk_awal);

        // 2. PPK 2 (unassigned) tries to approve -> rejected with 403 Forbidden
        $this->actingAs($this->ppk2);
        $resPpk2 = $this->postJson(route('verifikasi-paralel.keputusan-awal'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Mencoba approve lintas akun PPK',
        ]);
        $resPpk2->assertStatus(403);

        // 3. Wadir 2 approves first -> status MUST remain menunggu_acc_wadir_ppk because PPK 1 hasn't approved
        $this->actingAs($this->wadir2);
        $resWadir = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
            'catatan_alasan' => 'Kegiatan disetujui Wadir 2.',
        ]);
        $resWadir->assertRedirect(route('verifikasi.wadir-2'));

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, $permohonan->status_permohonan);

        // 4. Assigned PPK 1 approves -> status NOW transitions to disetujui (ready for PPBJ)
        $this->actingAs($this->ppk1);
        $resPpk1 = $this->post(route('verifikasi.ppk.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
            'catatan_alasan' => 'Spesifikasi disetujui PPK 1.',
        ]);
        $resPpk1->assertRedirect(route('verifikasi.ppk'));

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_DISETUJUI, $permohonan->status_permohonan);
    }

    public function test_chained_progression_ppk_approving_before_wadir2_maintains_status_until_both_approve(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHAIN-REVERSE-01/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Reverse Approval Order',
            'tujuan_pengadaan_ringkas' => 'Uji urutan persetujuan PPK sebelum Wadir 2',
            'file_permohonan' => 'permohonan/chain_rev.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 20000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
        ]);

        $this->actingAs($this->perencanaan);
        $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 20000000,
            'keputusan_anggaran' => 'setuju',
            'id_user_ppk_awal' => $this->ppk1->id_user,
            'nomor_memo_ketersediaan_mak' => 'MEMO/PRC/REV/001',
        ]);

        // PPK 1 approves first
        $this->actingAs($this->ppk1);
        $this->post(route('verifikasi.ppk.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
            'catatan_alasan' => 'PPK 1 approve terlebih dahulu',
        ]);

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, $permohonan->status_permohonan);

        // Wadir 2 approves second -> status advances to disetujui
        $this->actingAs($this->wadir2);
        $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
            'catatan_alasan' => 'Wadir 2 approve kedua',
        ]);

        $permohonan->refresh();
        $this->assertEquals(PermohonanPengadaan::STATUS_DISETUJUI, $permohonan->status_permohonan);
    }

    private function createPermohonanDisetujui(string $nomorSurat, float $pagu = 150000000): PermohonanPengadaan
    {
        return PermohonanPengadaan::create([
            'nomor_surat' => $nomorSurat,
            'judul_pengadaan' => 'Pengadaan Terverifikasi',
            'tujuan_pengadaan_ringkas' => 'Untuk paket pengadaan',
            'file_permohonan' => 'permohonan/dummy.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => $pagu,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'kode_mak' => $this->mak->kode_mak,
            'id_user_ppk_awal' => $this->ppk1->id_user,
        ]);
    }

    // =========================================================================
    // 3. ROLE PP RESTRICTIONS EMPIRICAL CHALLENGES
    // =========================================================================

    public function test_role_pp_cannot_submit_status_reviu_disetujui_on_web_route(): void
    {
        $permohonan = $this->createPermohonanDisetujui('B/PP-SURAT-WEB/2026', 100000000);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-PP-RESTRICT-WEB',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Paket Pengadaan 100 Juta Jalur PP',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'e_katalog',
            'nilai_hps' => 100000000,
            'alokasi_pagu_paket' => 100000000,
            'status_paket' => PaketPengadaan::STATUS_TERDAFTAR,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_pp' => $this->pp->id_user,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $this->actingAs($this->pp);

        $response = $this->from(route('kontrak.index'))
            ->post(route('kontrak.reviu'), [
                'nomor_paket' => $paket->nomor_paket,
                'status_reviu' => 'disetujui',
                'catatan_perbaikan' => 'PP mencoba approve web',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['status_reviu']);

        // Assert no approved review was recorded in ReviuDokumen
        $this->assertDatabaseMissing('reviu_dokumen', [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'disetujui',
        ]);
    }

    public function test_role_pp_cannot_submit_status_reviu_disetujui_on_api_route(): void
    {
        $permohonan = $this->createPermohonanDisetujui('B/PP-SURAT-API/2026', 150000000);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-PP-RESTRICT-API',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Paket Pengadaan 150 Juta Jalur PP',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'e_katalog',
            'nilai_hps' => 150000000,
            'alokasi_pagu_paket' => 150000000,
            'status_paket' => PaketPengadaan::STATUS_TERDAFTAR,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_pp' => $this->pp->id_user,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $this->actingAs($this->pp);

        $response = $this->postJson(route('reviu-dokumen.store'), [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'PP mencoba approve via API',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_reviu']);

        $this->assertDatabaseMissing('reviu_dokumen', [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'disetujui',
        ]);
    }

    public function test_role_pp_cannot_submit_disetujui_on_review_pp_endpoint(): void
    {
        $permohonan = $this->createPermohonanDisetujui('B/PP-SURAT-REV/2026', 120000000);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-PP-RESTRICT-REVIEW-PP',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Paket Pengadaan 120 Juta Jalur PP',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'e_katalog',
            'nilai_hps' => 120000000,
            'alokasi_pagu_paket' => 120000000,
            'status_paket' => PaketPengadaan::STATUS_TERDAFTAR,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_pp' => $this->pp->id_user,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $this->actingAs($this->pp);

        $response = $this->postJson(route('paket-pengadaan.review-pp', $paket->nomor_paket), [
            'hasil_verifikasi' => 'disetujui',
            'nominal_harga_perkiraan' => 118000000,
            'nominal_harga_akhir' => 115000000,
            'npwp_vendor' => $this->vendor->npwp,
            'catatan_verifikasi_pp' => 'Mencoba bypass hasil_verifikasi disetujui',
        ]);

        $response->assertStatus(422);

        $paket->refresh();
        $this->assertNotEquals(PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK, $paket->status_paket);
    }

    public function test_role_pp_valid_submission_transitions_to_menunggu_approval_ppk(): void
    {
        $permohonan = $this->createPermohonanDisetujui('B/PP-SURAT-VALID/2026', 180000000);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-PP-VALID-FLOW',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Paket Pengadaan 180 Juta Valid PP Review',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'e_katalog',
            'nilai_hps' => 180000000,
            'alokasi_pagu_paket' => 180000000,
            'status_paket' => PaketPengadaan::STATUS_TERDAFTAR,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_pp' => $this->pp->id_user,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $this->actingAs($this->pp);

        $response = $this->postJson(route('paket-pengadaan.review-pp', $paket->nomor_paket), [
            'hasil_verifikasi' => 'lengkap',
            'nominal_harga_perkiraan' => 175000000,
            'nominal_harga_akhir' => 172000000,
            'npwp_vendor' => $this->vendor->npwp,
            'catatan_verifikasi_pp' => 'Spesifikasi lengkap, negosiasi harga tercapai. Mohon approval PPK.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
            ->assertJsonPath('data.npwp_vendor', $this->vendor->npwp);

        $this->assertEquals(172000000, (float) $response->json('data.nominal_harga_akhir'));

        $paket->refresh();
        $this->assertEquals(PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK, $paket->status_paket);
    }
}
