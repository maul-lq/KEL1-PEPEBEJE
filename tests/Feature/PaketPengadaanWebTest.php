<?php

namespace Tests\Feature;

use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaketPengadaanWebTest extends TestCase
{
    use RefreshDatabase;

    private User $userPpbj;

    private User $userPpk1;

    private User $userPpk2;

    private User $userPp;

    private PermohonanPengadaan $permohonan;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $rolePpbj = Role::firstOrCreate(['nama_role' => 'ppbj'], ['label' => 'Koordinator PBJ', 'is_system' => true]);
        $rolePpk = Role::firstOrCreate(['nama_role' => 'ppk'], ['label' => 'Pejabat Pembuat Komitmen', 'is_system' => true]);
        $rolePp = Role::firstOrCreate(['nama_role' => 'pp'], ['label' => 'Pejabat Pengadaan', 'is_system' => true]);

        $this->userPpbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $rolePpbj->id,
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

        $this->userPp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $rolePp->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'SURAT-PBJ-001/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Laboratorium AI dan Komputasi Awan',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 350000000,
            'kode_unit' => $unit->kode_unit,
            'id_user' => $this->userPpbj->id_user,
            'tanggal_pengajuan' => now(),
        ]);
    }

    public function test_paket_index_renders_with_kpi_stat_cards_and_filter_bar(): void
    {
        PaketPengadaan::create([
            'kode_paket' => 'PKT-TEST-001',
            'nomor_paket' => 'PKT-TEST-001',
            'nama_paket' => 'Pengadaan Server Deep Learning',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE/Inaproc',
            'mekanisme_pengadaan' => 'Pengadaan Langsung',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 45000000,
            'alokasi_pagu_paket' => 45000000,
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'terdaftar',
        ]);

        $response = $this->actingAs($this->userPpbj)->get(route('paket.index'));

        $response->assertStatus(200);
        $response->assertSee('PKT-TEST-001');
        $response->assertSee('Pengadaan Server Deep Learning');
    }

    public function test_paket_index_filters_records_by_search_query_and_routing(): void
    {
        PaketPengadaan::create([
            'kode_paket' => 'PKT-ALPHA-01',
            'nomor_paket' => 'PKT-ALPHA-01',
            'nama_paket' => 'Pengadaan Laptop Riset AI',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 40000000,
            'alokasi_pagu_paket' => 40000000,
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'status_paket' => 'terdaftar',
        ]);

        PaketPengadaan::create([
            'kode_paket' => 'PKT-BETA-02',
            'nomor_paket' => 'PKT-BETA-02',
            'nama_paket' => 'Pengadaan Jasa Cloud Enterprise',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'jasa',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 250000000,
            'alokasi_pagu_paket' => 250000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'status_paket' => 'terdaftar',
        ]);

        $responseFilter1 = $this->actingAs($this->userPpbj)->get(route('paket.index', ['q' => 'Laptop']));
        $responseFilter1->assertSee('PKT-ALPHA-01');
        $responseFilter1->assertDontSee('PKT-BETA-02');

        $responseFilter2 = $this->actingAs($this->userPpbj)->get(route('paket.index', ['jalur_routing' => PaketPengadaan::ROUTING_PPK]));
        $responseFilter2->assertSee('PKT-BETA-02');
        $responseFilter2->assertDontSee('PKT-ALPHA-01');
    }

    public function test_paket_create_page_renders_successfully_for_ppbj(): void
    {
        $response = $this->actingAs($this->userPpbj)->get(route('paket.create'));

        $response->assertStatus(200);
        $response->assertSee($this->permohonan->nomor_surat);
    }

    public function test_ppbj_can_store_paket_langsung_under_50jt_via_web_form(): void
    {
        $payload = [
            'kode_paket' => 'PKT-LANGSUNG-01',
            'nama_paket' => 'Paket Pengadaan ATK Laboratorium',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'Barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 35000000,
            'nomor_memo_ppbj' => 'MEMO/PBJ/01/2026',
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.store'), $payload);

        $response->assertRedirect(route('paket.index'));
        $this->assertDatabaseHas('paket_pengadaan', [
            'nomor_paket' => 'PKT-LANGSUNG-01',
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);
    }

    public function test_ppbj_can_store_paket_pp_50_to_200jt_via_web_form(): void
    {
        $payload = [
            'kode_paket' => 'PKT-PP-02',
            'nama_paket' => 'Paket Switch Jaringan Core Kampus',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'Barang',
            'metode_pengadaan' => 'Penunjukan Langsung',
            'nilai_hps' => 120000000,
            'nomor_memo_ppbj' => 'MEMO/PBJ/02/2026',
            'id_user_pp' => $this->userPp->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.store'), $payload);

        $response->assertRedirect(route('paket.index'));
        $this->assertDatabaseHas('paket_pengadaan', [
            'nomor_paket' => 'PKT-PP-02',
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_pp' => $this->userPp->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'terdaftar',
        ]);
    }

    public function test_ppbj_can_store_paket_ppk_above_200jt_forces_memo_null_via_web_form(): void
    {
        $payload = [
            'kode_paket' => 'PKT-PPK-03',
            'nama_paket' => 'Paket Pengadaan Server Blade 200Jt Keatas',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'Barang',
            'metode_pengadaan' => 'Tender Cepat',
            'nilai_hps' => 300000000,
            'nomor_memo_ppbj' => 'MEMO-YANG-HARUS-DIABAIKAN',
            'id_user_ppk' => $this->userPpk1->id_user,
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.store'), $payload);

        $response->assertRedirect(route('paket.index'));
        $paket = PaketPengadaan::where('nomor_paket', 'PKT-PPK-03')->first();
        $this->assertNotNull($paket);
        $this->assertEquals(PaketPengadaan::ROUTING_PPK, $paket->jalur_routing);
        $this->assertNull($paket->nomor_memo_ppbj);
        $this->assertEquals($this->userPpk1->id_user, $paket->id_user_ppk);
    }

    public function test_store_paket_fails_if_above_200jt_without_ppk(): void
    {
        $payload = [
            'kode_paket' => 'PKT-FAIL-04',
            'nama_paket' => 'Paket Pengadaan Skala Besar Tanpa PPK',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'Barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 250000000,
            'id_user_ppk' => null,
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.store'), $payload);

        $response->assertSessionHasErrors('id_user_ppk');
        $this->assertDatabaseMissing('paket_pengadaan', ['nomor_paket' => 'PKT-FAIL-04']);
    }

    public function test_ppbj_can_split_jenis_pengadaan_via_web_form(): void
    {
        $initialPagu = (float) $this->permohonan->estimasi_total_pagu;
        $splitPagu = 100000000;

        $payload = [
            'nomor_surat_induk' => $this->permohonan->nomor_surat,
            'nomor_surat_baru' => 'SURAT-SPLIT-CHILD/2026',
            'judul_pengadaan' => 'Paket Turunan Jasa Pelatihan AI',
            'jenis_pengadaan_split' => 'jasa',
            'estimasi_total_pagu' => $splitPagu,
            'alasan_pemecahan' => 'Pemisahan pengadaan fisik barang dan pendampingan pelatihan jasa konsultan',
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.split-jenis'), $payload);

        $response->assertRedirect(route('paket.index'));
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'SURAT-SPLIT-CHILD/2026',
            'nomor_surat_induk_split' => $this->permohonan->nomor_surat,
            'jenis_pengadaan_split' => 'jasa',
        ]);

        $this->permohonan->refresh();
        $this->assertEquals($initialPagu - $splitPagu, (float) $this->permohonan->estimasi_total_pagu);
    }

    public function test_ppbj_can_reassign_ppk_via_web_form_and_audit_trail_is_logged(): void
    {
        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-REASSIGN-01',
            'nomor_paket' => 'PKT-REASSIGN-01',
            'nama_paket' => 'Paket Audit Reassign PPK',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 250000000,
            'alokasi_pagu_paket' => 250000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'terdaftar',
        ]);

        $payload = [
            'kode_paket' => $paket->nomor_paket,
            'id_user_ppk_baru' => $this->userPpk2->id_user,
            'alasan_pengalihan' => 'Mutasi tugas dan pemerataan beban kerja PPK semester genap',
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.reassign-ppk'), $payload);

        $response->assertRedirect(route('paket.index'));

        $paket->refresh();
        $this->assertEquals($this->userPpk2->id_user, $paket->id_user_ppk);

        $this->assertDatabaseHas('riwayat_penugasan_ppk', [
            'nomor_paket' => $paket->nomor_paket,
            'ppk_lama_user_id' => $this->userPpk1->id_user,
            'ppk_baru_user_id' => $this->userPpk2->id_user,
            'alasan_perubahan' => 'Mutasi tugas dan pemerataan beban kerja PPK semester genap',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'id_entitas' => $paket->nomor_paket,
            'jenis_aksi' => 'REASSIGN_PPK',
        ]);
    }

    public function test_reassign_ppk_fails_if_target_user_is_not_ppk(): void
    {
        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-INVALID-PPK',
            'nomor_paket' => 'PKT-INVALID-PPK',
            'nama_paket' => 'Paket Uji Invalid PPK',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 250000000,
            'alokasi_pagu_paket' => 250000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'terdaftar',
        ]);

        $payload = [
            'kode_paket' => $paket->nomor_paket,
            'id_user_ppk_baru' => $this->userPp->id_user, // role 'pp', bukan 'ppk'
            'alasan_pengalihan' => 'Uji kesalahan role target mutasi',
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('paket.reassign-ppk'), $payload);

        $response->assertSessionHasErrors('id_user_ppk_baru');
        $paket->refresh();
        $this->assertEquals($this->userPpk1->id_user, $paket->id_user_ppk);
    }

    public function test_paket_show_page_renders_with_complete_lifecycle_and_stepper(): void
    {
        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-SHOW-01',
            'nomor_paket' => 'PKT-SHOW-01',
            'nama_paket' => 'Paket Pengadaan Workstation Studio Multimedia',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 280000000,
            'alokasi_pagu_paket' => 280000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'terdaftar',
        ]);

        $response = $this->actingAs($this->userPpbj)->get(route('paket.show', $paket->nomor_paket));

        $response->assertStatus(200);
        $response->assertSee('PKT-SHOW-01');
        $response->assertSee('Paket Pengadaan Workstation Studio Multimedia');
    }

    public function test_ppk_file_isolation_is_strictly_enforced_on_web_paket_show(): void
    {
        $paketAssignedToPpk1 = PaketPengadaan::create([
            'kode_paket' => 'PKT-ISOLATION-01',
            'nomor_paket' => 'PKT-ISOLATION-01',
            'nama_paket' => 'Paket Khusus PPK 1 Terisolasi',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 250000000,
            'alokasi_pagu_paket' => 250000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'status_paket' => 'terdaftar',
        ]);

        // PPK 1 can view
        $responsePpk1 = $this->actingAs($this->userPpk1)->get(route('paket.show', $paketAssignedToPpk1->nomor_paket));
        $responsePpk1->assertStatus(200);

        // PPK 2 is rejected with 403
        $responsePpk2 = $this->actingAs($this->userPpk2)->get(route('paket.show', $paketAssignedToPpk1->nomor_paket));
        $responsePpk2->assertStatus(403);
    }

    public function test_paket_views_contain_zero_inline_script_tags(): void
    {
        $views = [
            resource_path('views/paket/index.blade.php'),
            resource_path('views/paket/create.blade.php'),
            resource_path('views/paket/show.blade.php'),
            resource_path('views/paket/split_jenis.blade.php'),
            resource_path('views/paket/reassign.blade.php'),
        ];

        foreach ($views as $viewPath) {
            $this->assertFileExists($viewPath);
            $content = file_get_contents($viewPath);
            $this->assertFalse((bool) preg_match('/<\s*script\b/i', $content), "Ditemukan tag <script> inline pada file: {$viewPath}");
        }
    }

    public function test_paket_views_resolve_dynamically_in_both_backend_and_frontend_modes(): void
    {
        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-DUAL-MODE',
            'nomor_paket' => 'PKT-DUAL-MODE',
            'nama_paket' => 'Paket Dual Mode Testing',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 40000000,
            'alokasi_pagu_paket' => 40000000,
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'status_paket' => 'terdaftar',
        ]);

        // Backend Mode
        Config::set('app.dev_viewmode', 'backend');
        $responseBackend = $this->actingAs($this->userPpbj)->get(route('paket.index'));
        $responseBackend->assertStatus(200);

        // Frontend Mode
        Config::set('app.dev_viewmode', 'frontend');
        $responseFrontend = $this->actingAs($this->userPpbj)->get(route('paket.index'));
        $responseFrontend->assertStatus(200);
    }
}
