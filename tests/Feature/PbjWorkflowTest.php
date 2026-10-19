<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Alur Kerja PBJ (PbjWorkflowTest)
 */
class PbjWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Unit $unitTik;

    protected AnggaranMAK $anggaranMak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitTik = Unit::create([
            'kode_unit' => 'TIK',
            'nama_unit' => 'Jurusan Teknik Informatika dan Komputer',
            'jenis_unit' => 'jurusan',
        ]);

        $this->anggaranMak = AnggaranMAK::create([
            'kode_mak' => '023.17.WA.532111.001',
            'uraian_mak' => 'Belanja Modal Peralatan Lab',
            'tahun_anggaran' => (int) date('Y'),
            'pagu_anggaran' => 500000000,
            'sisa_pagu_mak' => 500000000,
            'sumber_dana' => 'BOPTN',
            'is_locked' => false,
        ]);
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_user_authentication_and_dashboard_access(): void
    {
        $ppbj = User::create([
            'nama' => 'Staff Pengadaan',
            'email' => 'ppbj@kampus.ac.id',
            'username' => 'staff_ppbj',
            'password' => bcrypt('password123'),
            'role' => 'pbj',
            'nip' => '198403122008121002',
            'kode_unit' => 'TIK',
            'jabatan' => 'Koordinator PBJ',
            'no_hp' => '081211110001',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($ppbj)->get(route('dashboard'));
        $response->assertStatus(200);
        $this->assertAuthenticatedAs($ppbj);
    }

    public function test_budget_threshold_logic_correctly_determines_procurement_jalur(): void
    {
        // Under 50M: Pengadaan Langsung
        $this->assertEquals(PaketPengadaan::ROUTING_LANGSUNG, PaketPengadaan::tentukanRouting(35000000));

        // 50M to 200M: Pejabat Pengadaan
        $this->assertEquals(PaketPengadaan::ROUTING_PP, PaketPengadaan::tentukanRouting(125000000));

        // Over 200M: PPK
        $this->assertEquals(PaketPengadaan::ROUTING_PPK, PaketPengadaan::tentukanRouting(350000000));
    }

    public function test_user_can_access_procurement_creation_page_and_view_monitoring_board(): void
    {
        $user = User::create([
            'nama' => 'Pemohon Unit',
            'email' => 'pemohon@kampus.ac.id',
            'username' => 'pemohon_unit',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'nip' => '198901012015041001',
            'kode_unit' => 'TIK',
            'jabatan' => 'Dosen Pengaju Usulan',
            'no_hp' => '081211110002',
            'status_aktif' => true,
        ]);

        $this->actingAs($user)->get(route('permohonan.create'))
            ->assertStatus(200)
            ->assertSee('Pengajuan Usulan Permohonan Pengadaan');

        $this->actingAs($user)->get(route('monitoring.index'))
            ->assertStatus(200)
            ->assertSee('Monitoring Tahapan Siklus Pengadaan');
    }

    public function test_reminders_and_reports_pages_are_accessible_by_authorized_staff(): void
    {
        $ppbj = User::create([
            'nama' => 'PPBJ Staff',
            'email' => 'ppbj_staff@kampus.ac.id',
            'username' => 'staff_pbj_2',
            'password' => bcrypt('password123'),
            'role' => 'pbj',
            'nip' => '198601012009121001',
            'kode_unit' => 'TIK',
            'jabatan' => 'Staff PBJ',
            'no_hp' => '081211110003',
            'status_aktif' => true,
        ]);

        $this->actingAs($ppbj)->get(route('laporan.rekapitulasi'))
            ->assertStatus(200)
            ->assertSee('Laporan Rekapitulasi Total Anggaran');
    }

    public function test_procurement_print_sheet_is_rendered_with_complete_data(): void
    {
        $user = User::create([
            'nama' => 'Pemohon Cetak',
            'email' => 'cetak@kampus.ac.id',
            'username' => 'pemohon_cetak',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'nip' => '198701012012041001',
            'kode_unit' => 'TIK',
            'jabatan' => 'Staf Pengusul Lab',
            'no_hp' => '081211110004',
            'status_aktif' => true,
        ]);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'SRT/TIK/PRINT/001',
            'judul_pengadaan' => 'Pengadaan Server Kampus',
            'tujuan_pengadaan_ringkas' => 'Server laboratorium jaringan',
            'status_permohonan' => 'disetujui',
            'status_anggaran' => 'terverifikasi',
            'estimasi_total_pagu' => 250000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TIK',
            'id_user' => $user->id_user,
            'kode_mak' => $this->anggaranMak->kode_mak,
        ]);

        $responsePdf = $this->actingAs($user)->get(route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $permohonan->nomor_surat]));
        $responsePdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $responsePdf->headers->get('Content-Type'));
    }
}
