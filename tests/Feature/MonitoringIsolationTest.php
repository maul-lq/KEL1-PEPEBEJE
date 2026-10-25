<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Isolasi Data Halaman Monitoring (MonitoringIsolationTest)
 *
 * Memverifikasi:
 * 1. Pengusul biasa (role 'user') hanya dapat memantau usulan pengadaan miliknya sendiri.
 * 2. Usulan pengadaan milik pengguna atau unit lain tidak bocor ke tampilan monitoring pengusul.
 * 3. Jejak audit (Audit Trail) pada halaman monitoring pengusul hanya menampilkan riwayat aktivitas usulan miliknya.
 * 4. Peran administratif dan pengawas (admin, wadir_2, perencanaan, ppbj, keuangan) tetap memiliki akses monitoring institusi.
 */
class MonitoringIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $pengajuTik;

    private User $pengajuElektro;

    private User $wadir2;

    private User $admin;

    private PermohonanPengadaan $permohonanTik;

    private PermohonanPengadaan $permohonanElektro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $unitTik = Unit::updateOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $unitElektro = Unit::updateOrCreate(
            ['kode_unit' => 'TE'],
            ['nama_unit' => 'Teknik Elektro', 'jenis_unit' => 'jurusan']
        );

        $roleUser = Role::where('nama_role', 'user')->first();
        $roleWadir = Role::where('nama_role', 'wadir_2')->first();
        $roleAdmin = Role::where('nama_role', 'admin')->first();

        // 1. Pengaju dari Unit TIK
        $this->pengajuTik = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'dosen.tik@pnj.ac.id',
            'nama' => 'Dosen TIK PNJ',
        ]);

        // 2. Pengaju dari Unit TE
        $this->pengajuElektro = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $unitElektro->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'dosen.elektro@pnj.ac.id',
            'nama' => 'Dosen Elektro PNJ',
        ]);

        // 3. Pejabat Wadir 2
        $this->wadir2 = User::factory()->create([
            'role' => 'wadir_2',
            'id_role' => $roleWadir->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'wadir2@pnj.ac.id',
            'nama' => 'Wakil Direktur II PNJ',
        ]);

        // 4. Admin
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'id_role' => $roleAdmin->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'admin@pnj.ac.id',
            'nama' => 'Administrator SIM-PBJ',
        ]);

        // Berkas Usulan TIK
        $this->permohonanTik = PermohonanPengadaan::create([
            'nomor_surat' => '001/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Komputer Server AI Laboratorium TIK',
            'perihal' => 'Pengadaan Komputer Server AI Laboratorium TIK',
            'tujuan_pengadaan_ringkas' => 'Server riset deep learning',
            'estimasi_total_pagu' => 250000000,
            'anggaran_diajukan' => 250000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pengajuTik->id_user,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/tik.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'status' => 'diajukan',
        ]);

        // Berkas Usulan Elektro
        $this->permohonanElektro = PermohonanPengadaan::create([
            'nomor_surat' => '002/PL3/TE/2026',
            'judul_pengadaan' => 'Pengadaan Osciloscope Digital Elektro',
            'perihal' => 'Pengadaan Osciloscope Digital Elektro',
            'tujuan_pengadaan_ringkas' => 'Alat ukur praktikum gelombang',
            'estimasi_total_pagu' => 80000000,
            'anggaran_diajukan' => 80000000,
            'kode_unit' => 'TE',
            'id_user' => $this->pengajuElektro->id_user,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/te.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'status' => 'diajukan',
        ]);

        // Catat Audit Log
        AuditLog::catat(
            $this->pengajuTik->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            $this->permohonanTik->nomor_surat,
            null,
            'diajukan',
            'Pengajuan usulan TIK server'
        );

        AuditLog::catat(
            $this->pengajuElektro->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            $this->permohonanElektro->nomor_surat,
            null,
            'diajukan',
            'Pengajuan usulan Elektro osiloskop'
        );
    }

    /**
     * Memastikan pengusul biasa (role: user) hanya melihat permohonan miliknya sendiri pada halaman monitoring.
     */
    public function test_pengaju_hanya_melihat_dokumen_miliknya_sendiri(): void
    {
        config(['app.dev_viewmode' => 'backend']);

        $response = $this->actingAs($this->pengajuTik)
            ->get(route('monitoring.index'));

        $response->assertOk();

        // Harus melihat usulan miliknya sendiri
        $response->assertSee('001/PL3/TIK/2026');
        $response->assertSee('Pengadaan Komputer Server AI Laboratorium TIK');

        // DILARANG melihat usulan pengaju lain
        $response->assertDontSee('002/PL3/TE/2026');
        $response->assertDontSee('Pengadaan Osciloscope Digital Elektro');
    }

    /**
     * Memastikan jejak audit pada halaman monitoring pengusul hanya memuat log miliknya sendiri.
     */
    public function test_pengaju_hanya_melihat_audit_log_usulan_miliknya_sendiri(): void
    {
        config(['app.dev_viewmode' => 'backend']);

        $response = $this->actingAs($this->pengajuTik)
            ->get(route('monitoring.index'));

        $response->assertOk();
        $response->assertSee('Pengajuan usulan TIK server');
        $response->assertDontSee('Pengajuan usulan Elektro osiloskop');
    }

    /**
     * Memastikan peran manajemen (Wadir 2 dan Admin) dapat memantau seluruh usulan pengadaan lintas jurusan.
     */
    public function test_peran_manajemen_dapat_melihat_seluruh_usulan_pengadaan(): void
    {
        config(['app.dev_viewmode' => 'backend']);

        $response = $this->actingAs($this->wadir2)
            ->get(route('monitoring.index'));

        $response->assertOk();

        // Wadir 2 berhak melihat seluruh usulan dari semua unit
        $response->assertSee('001/PL3/TIK/2026');
        $response->assertSee('002/PL3/TE/2026');
        $response->assertSee('Pengajuan usulan TIK server');
        $response->assertSee('Pengajuan usulan Elektro osiloskop');
    }

    /**
     * Memastikan halaman monitoring meresolusi view sesuai dev_viewmode.
     */
    public function test_monitoring_view_resolves_correctly_per_dev_viewmode(): void
    {
        // 1. Mode Backend
        config(['app.dev_viewmode' => 'backend']);
        $responseBackend = $this->actingAs($this->pengajuTik)->get(route('monitoring.index'));
        $responseBackend->assertOk();
        $responseBackend->assertSee('Mode Tampilan Aktif:');

        // 2. Mode Frontend
        config(['app.dev_viewmode' => 'frontend']);
        $responseFrontend = $this->actingAs($this->pengajuTik)->get(route('monitoring.index'));
        $responseFrontend->assertOk();
        $responseFrontend->assertSee('Detail & Tracking Pengadaan Saya');
    }
}
