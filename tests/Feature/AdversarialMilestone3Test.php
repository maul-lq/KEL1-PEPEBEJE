<?php

namespace Tests\Feature;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Empirical Adversarial Challenge Test Suite for Milestone 3
 *
 * Authored by: Challenger 2 (challenger_m3_2)
 *
 * Stress-testing:
 * - Wadir 2 3-way disposition state machine & edge cases
 * - Perencanaan MAK allocation over-budget exploit (phantom budget)
 * - Split MAK pagu inflation exploit & duplicate key handling
 * - IDOR / RBAC authorization on deletion and verification endpoints
 * - DB Transaction rollback resiliency
 */
class AdversarialMilestone3Test extends TestCase
{
    use RefreshDatabase;

    protected User $userOwner;

    protected User $userAttacker;

    protected User $staffB2;

    protected User $wadir2;

    protected User $perencanaan;

    protected User $ppbj;

    protected Unit $unit;

    protected AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->userOwner = User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userAttacker = User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->staffB2 = User::where('role', 'staff_bidang_2')->first() ?? User::factory()->create([
            'role' => 'staff_bidang_2',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->wadir2 = User::where('role', 'wadir_2')->first() ?? User::factory()->create([
            'role' => 'wadir_2',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->perencanaan = User::where('role', 'perencanaan')->first() ?? User::factory()->create([
            'role' => 'perencanaan',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->ppbj = User::where('role', 'ppbj')->first() ?? User::factory()->create([
            'role' => 'ppbj',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->mak = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.ADV.521211'],
            [
                'uraian_mak' => 'MAK Uji Adversarial',
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => (int) date('Y'),
                'pagu_anggaran' => 100000000,
                'sisa_pagu_mak' => 100000000,
                'is_locked' => false,
            ]
        );
    }

    /**
     * ADV-1: Wadir 2 cannot submit whitespace-only notes when rejecting or requesting revision.
     */
    public function test_adv_wadir_2_whitespace_only_catatan_is_rejected(): void
    {
        $this->actingAs($this->wadir2);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADV-WADIR-WS/2026',
            'perihal' => 'Pengadaan Uji Whitespace',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userOwner->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $resp = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'revisi',
            'catatan_alasan' => '    ',
        ]);

        $resp->assertSessionHasErrors(['catatan_alasan']);
    }

    /**
     * ADV-2: Unauthorized roles cannot access Wadir 2 or Perencanaan verification endpoints.
     */
    public function test_adv_rbac_strictly_blocks_unauthorized_disposition_calls(): void
    {
        // 1. Regular user calling Wadir 2
        $this->actingAs($this->userOwner);
        $resp1 = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => 'B/TEST/1',
            'keputusan' => 'disetujui',
        ]);
        $resp1->assertStatus(403);

        // 2. Staf Bidang 2 calling Perencanaan MAK allocation
        $this->actingAs($this->staffB2);
        $resp2 = $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => 'B/TEST/1',
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 10000000,
            'keputusan_anggaran' => 'setuju',
        ]);
        $resp2->assertStatus(403);

        // 3. User calling Split MAK
        $this->actingAs($this->userOwner);
        $resp3 = $this->post(route('verifikasi.perencanaan.split'), [
            'nomor_surat_induk' => 'B/TEST/1',
        ]);
        $resp3->assertStatus(403);
    }

    /**
     * ADV-3: Over-allocation test — what happens when nominal_alokasi exceeds sisa_pagu_mak?
     * EMPIRICAL FINDING: System accepts over-allocation without deducting sisa_pagu_mak, creating phantom money.
     */
    public function test_adv_perencanaan_allocating_more_than_sisa_pagu_mak(): void
    {
        $this->actingAs($this->perencanaan);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADV-OVER-MAK/2026',
            'perihal' => 'Pengadaan Fasilitas Melebihi Sisa Pagu MAK',
            'anggaran_diajukan' => 150000000,
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userOwner->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $this->assertEquals(100000000, (float) $this->mak->sisa_pagu_mak);

        $resp = $this->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 150000000,
            'keputusan_anggaran' => 'setuju',
            'catatan_anggaran' => 'Mencoba alokasi melebihi sisa pagu',
        ]);

        $resp->assertSessionHas('error');

        // Verify: AlokasiMAK record was NOT created and MAK balance remains intact
        $alokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)->first();
        $this->assertNull($alokasi);

        $this->mak->refresh();
        $this->assertEquals(100000000, (float) $this->mak->sisa_pagu_mak);

        $permohonan->refresh();
        $this->assertNotEquals(PermohonanPengadaan::STATUS_DISETUJUI, $permohonan->status_permohonan);
    }

    /**
     * ADV-4: Single-item Split MAK pagu inflation exploit probe.
     * EMPIRICAL FINDING: Single-item fallback path allows creating child with pagu exceeding induk.
     */
    public function test_adv_single_item_split_pagu_inflation_probe(): void
    {
        $this->actingAs($this->perencanaan);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADV-SPLIT-INFLATION/2026',
            'perihal' => 'Usulan Pagu Kecil',
            'anggaran_diajukan' => 20000000,
            'estimasi_total_pagu' => 20000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userOwner->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        // Induk is 20,000,000. Requesting single split of 500,000,000.
        $resp = $this->post(route('verifikasi.perencanaan.split'), [
            'nomor_surat_induk' => $induk->nomor_surat,
            'nomor_surat_baru' => 'B/ADV-SPLIT-INFLATION.1',
            'judul_pengadaan' => 'Pecahan Menggelembung',
            'kode_mak' => $this->mak->kode_mak,
            'alasan_pemecahan' => 'Uji penggelembungan',
            'estimasi_total_pagu' => 500000000,
        ]);

        $resp->assertSessionHas('error');

        $induk->refresh();
        $anak = PermohonanPengadaan::find('B/ADV-SPLIT-INFLATION.1');

        $this->assertEquals(20000000, (float) $induk->estimasi_total_pagu);
        $this->assertNull($anak);
    }

    /**
     * ADV-5: Batch Split MAK duplicate child nomor_surat intra-request.
     * EMPIRICAL FINDING: Intra-array duplicate causes SQL 1062 duplicate key error (500) due to missing 'distinct' rule.
     */
    public function test_adv_batch_split_intra_request_duplicate_numbers(): void
    {
        $this->actingAs($this->perencanaan);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADV-SPLIT-DUP/2026',
            'perihal' => 'Usulan Split Duplikat',
            'anggaran_diajukan' => 100000000,
            'estimasi_total_pagu' => 100000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userOwner->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $payload = [
            'nomor_surat_induk' => $induk->nomor_surat,
            'alasan_pemecahan' => 'Uji duplikat nomor surat anak',
            'items' => [
                [
                    'nomor_surat_pecahan' => 'B/ADV-SPLIT-DUP.1',
                    'judul_pengadaan' => 'Pecahan 1',
                    'kode_mak' => $this->mak->kode_mak,
                    'estimasi_pagu_pecahan' => 30000000,
                ],
                [
                    'nomor_surat_pecahan' => 'B/ADV-SPLIT-DUP.1', // IDENTICAL
                    'judul_pengadaan' => 'Pecahan 2',
                    'kode_mak' => $this->mak->kode_mak,
                    'estimasi_pagu_pecahan' => 30000000,
                ],
            ],
        ];

        $resp = $this->post(route('verifikasi.perencanaan.split'), $payload);
        $resp->assertSessionHasErrors(['items.0.nomor_surat_pecahan']);
        $this->assertDatabaseMissing('permohonan_pengadaan', ['nomor_surat' => 'B/ADV-SPLIT-DUP.1']);
    }

    /**
     * ADV-6: IDOR probe: Can a non-owner, non-admin (e.g. staff_bidang_2 or ppbj) delete a user's permohonan?
     * EMPIRICAL FINDING: staff_bidang_2 bypasses ownership check and deletes another user's proposal.
     */
    public function test_adv_idor_deletion_by_staff_or_other_roles(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADV-IDOR-DEL/2026',
            'perihal' => 'Usulan Milik User A',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userOwner->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        // 1. Another regular user is properly blocked with 403
        $this->actingAs($this->userAttacker);
        $resp1 = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));
        $this->assertEquals(403, $resp1->status());
        $this->assertDatabaseHas('permohonan_pengadaan', ['nomor_surat' => $permohonan->nomor_surat]);

        // 2. Staff Bidang 2 is properly blocked with 403 (IDOR blocked)
        $this->actingAs($this->staffB2);
        $resp2 = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));
        $this->assertEquals(403, $resp2->status());
        $this->assertDatabaseHas('permohonan_pengadaan', ['nomor_surat' => $permohonan->nomor_surat]);
    }

    /**
     * ADV-7: State machine corruption: Can Wadir 2 reject an already approved permohonan that has advanced to paket/SPK?
     * EMPIRICAL FINDING: Wadir 2 can overwrite an already approved permohonan to ditolak without state guard.
     */
    public function test_adv_wadir2_altering_already_approved_permohonan(): void
    {
        $this->actingAs($this->wadir2);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ADV-APPROVED-TAMPER/2026',
            'perihal' => 'Usulan Sudah Approved',
            'anggaran_diajukan' => 80000000,
            'estimasi_total_pagu' => 80000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userOwner->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'tanggal_pengajuan' => now(),
        ]);

        // Wadir 2 attempts to reject an already approved permohonan
        $resp = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'ditolak',
            'catatan_alasan' => 'Dibatalkan sepihak setelah disetujui',
        ]);

        $resp->assertRedirect(route('verifikasi.wadir-2'));
        $resp->assertSessionHas('error');

        $permohonan->refresh();

        // Confirmed: status was preserved, stays DISETUJUI
        $this->assertEquals(PermohonanPengadaan::STATUS_DISETUJUI, $permohonan->status_permohonan);
    }
}
