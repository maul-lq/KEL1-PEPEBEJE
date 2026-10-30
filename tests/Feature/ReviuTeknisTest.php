<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviuTeknisTest extends TestCase
{
    use RefreshDatabase;

    private User $userPp;

    private User $userPpk1;

    private User $userPpk2;

    private AnggaranMAK $anggaranMak;

    private PermohonanPengadaan $permohonan;

    private PaketPengadaan $paket;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $rolePp = Role::firstOrCreate(['nama_role' => 'pp'], ['label' => 'Pejabat Pengadaan', 'is_system' => true]);
        $rolePpk = Role::firstOrCreate(['nama_role' => 'ppk'], ['label' => 'Pejabat Pembuat Komitmen', 'is_system' => true]);

        $this->userPp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $rolePp->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userPpk1 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $rolePpk->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userPpk2 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $rolePpk->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->anggaranMak = AnggaranMAK::create([
            'kode_mak' => 'MAK-521211-REVIU-01',
            'uraian_mak' => 'Belanja Modal Server Riset',
            'pagu_anggaran' => 200000000,
            'sisa_pagu_mak' => 200000000,
            'sumber_dana' => 'RM',
            'tahun_anggaran' => 2026,
            'is_locked' => false,
            'id_user' => $this->userPp->id_user,
        ]);

        $this->permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'SURAT-REVIU-001/2026',
            'judul_pengadaan' => 'Pengadaan Server Riset Komputasi',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $unit->kode_unit,
            'id_user' => $this->userPp->id_user,
            'kode_mak' => $this->anggaranMak->kode_mak,
        ]);

        $this->paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-REVIU-001',
            'nomor_paket' => 'PKT-REVIU-001',
            'nama_paket' => 'Pengadaan Server Riset Komputasi',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 120000000,
            'alokasi_pagu_paket' => 120000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_pp' => $this->userPp->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'terdaftar',
        ]);
    }

    public function test_pejabat_pengadaan_can_submit_review_with_negotiated_price_for_50jt_to_200jt(): void
    {
        $payload = [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'nilai_hasil_negosiasi' => 115000000,
            'catatan_perbaikan' => 'Hasil negosiasi harga dengan rekanan berhasil menghemat anggaran sebesar Rp 5.000.000',
        ];

        $response = $this->actingAs($this->userPp)->post(route('kontrak.reviu'), $payload);

        $response->assertRedirect(route('kontrak.index'));

        $this->paket->refresh();
        $this->assertEquals(115000000, (float) $this->paket->nilai_hasil_negosiasi);
        $this->assertEquals('reviu_dokumen_disetujui', $this->paket->status_paket);

        $this->assertDatabaseHas('reviu_dokumen', [
            'nomor_paket' => $this->paket->nomor_paket,
            'role_reviewer' => 'pp',
            'status_reviu' => 'disetujui',
            'putaran_ke' => 1,
        ]);
    }

    public function test_ppk_can_approve_package_and_permanently_lock_mak(): void
    {
        $payload = [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Spesifikasi teknis dan HPS diverifikasi sesuai KAK, disetujui terbit SPK',
        ];

        $response = $this->actingAs($this->userPpk1)->post(route('kontrak.reviu'), $payload);

        $response->assertRedirect(route('kontrak.index'));

        $this->paket->refresh();
        $this->assertEquals('reviu_dokumen_disetujui', $this->paket->status_paket);

        $this->anggaranMak->refresh();
        $this->assertTrue($this->anggaranMak->is_locked);
    }

    public function test_review_rejection_requires_correction_notes(): void
    {
        $payload = [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'perlu_perbaikan',
            'catatan_perbaikan' => '', // Kosong saat perlu_perbaikan
        ];

        $response = $this->actingAs($this->userPpk1)->post(route('kontrak.reviu'), $payload);

        $response->assertSessionHasErrors('catatan_perbaikan');
    }

    public function test_strict_ppk_isolation_returns_403_for_unassigned_ppk(): void
    {
        // PPK 2 bukan PPK yang ditugaskan pada paket ini ($this->paket->id_user_ppk adalah PPK 1)
        $payload = [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Upaya reviu tanpa hak penugasan',
        ];

        $response = $this->actingAs($this->userPpk2)->post(route('kontrak.reviu'), $payload);

        $response->assertStatus(403);
    }

    public function test_review_rounds_putaran_ke_increments_sequentially(): void
    {
        // Putaran 1: Perlu perbaikan
        $this->actingAs($this->userPpk1)->post(route('kontrak.reviu'), [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'perlu_perbaikan',
            'catatan_perbaikan' => 'Spesifikasi prosesor belum mencantumkan generasi arsitektur',
        ]);

        $this->assertDatabaseHas('reviu_dokumen', [
            'nomor_paket' => $this->paket->nomor_paket,
            'putaran_ke' => 1,
            'status_reviu' => 'perlu_perbaikan',
        ]);

        // Putaran 2: Disetujui
        $this->actingAs($this->userPpk1)->post(route('kontrak.reviu'), [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Revisi spesifikasi telah diperbaiki dan disetujui',
        ]);

        $this->assertDatabaseHas('reviu_dokumen', [
            'nomor_paket' => $this->paket->nomor_paket,
            'putaran_ke' => 2,
            'status_reviu' => 'disetujui',
        ]);
    }

    public function test_audit_log_records_reviu_actions(): void
    {
        $this->actingAs($this->userPpk1)->post(route('kontrak.reviu'), [
            'nomor_paket' => $this->paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Audit log testing catatan verifikasi PPK',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'id_entitas' => $this->paket->nomor_paket,
            'jenis_aksi' => 'REVIU_DOKUMEN_DISETUJUI',
        ]);
    }
}
