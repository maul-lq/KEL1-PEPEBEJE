<?php

namespace Tests\Feature;

use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Peralihan Mode Tampilan DEV_VIEWMODE (DevViewModeSwitchTest)
 *
 * Menguji fungsionalitas DevModeViewFinder dan konfigurasi DEV_VIEWMODE
 * untuk memastikan sistem dapat beralih secara dinamis dan mulus antara
 * tampilan mode backend (berkas tes_*) dan mode frontend (berkas non-tes_*).
 */
class DevViewModeSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $userPemohon;

    private User $userAdmin;

    private PermohonanPengadaan $permohonan;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $roleUser = Role::firstOrCreate(
            ['nama_role' => 'user'],
            ['label' => 'Pemohon / Jurusan', 'is_system' => true]
        );

        $roleAdmin = Role::firstOrCreate(
            ['nama_role' => 'admin'],
            ['label' => 'Administrator', 'is_system' => true]
        );

        $this->userPemohon = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userAdmin = User::factory()->create([
            'role' => 'admin',
            'id_role' => $roleAdmin->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/DEV-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Laboratorium Jaringan Komputer',
            'tujuan_pengadaan_ringkas' => 'Pengadaan perangkat switch dan router penunjang praktikum mahasiswa',
            'kode_unit' => $unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'estimasi_total_pagu' => 150000000,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/dummy.pdf',
        ]);
    }

    /**
     * Memastikan DevModeViewFinder memprioritaskan berkas tes_* pada mode backend.
     */
    public function test_dev_viewmode_backend_resolves_tes_views(): void
    {
        config(['app.dev_viewmode' => 'backend']);

        $finder = app('view.finder');

        $resolvedDashboard = $finder->find('dashboard.index');
        $this->assertStringContainsString('tes_index.blade.php', $resolvedDashboard);

        $resolvedPermohonanShow = $finder->find('permohonan.show');
        $this->assertStringContainsString('tes_show.blade.php', $resolvedPermohonanShow);

        $resolvedPermohonanIndex = $finder->find('permohonan.index');
        $this->assertStringContainsString('tes_index.blade.php', $resolvedPermohonanIndex);

        $resolvedAdminRoles = $finder->find('admin.roles');
        $this->assertStringContainsString('tes_roles.blade.php', $resolvedAdminRoles);

        $resolvedNotifikasi = $finder->find('notifikasi.index');
        $this->assertStringContainsString('tes_index.blade.php', $resolvedNotifikasi);
    }

    /**
     * Memastikan DevModeViewFinder memprioritaskan berkas non-tes_* pada mode frontend.
     */
    public function test_dev_viewmode_frontend_resolves_standard_views(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $finder = app('view.finder');

        $resolvedDashboard = $finder->find('dashboard.index');
        $this->assertStringEndsWith('dashboard/index.blade.php', str_replace('\\', '/', $resolvedDashboard));

        $resolvedPermohonanShow = $finder->find('permohonan.show');
        $this->assertStringEndsWith('permohonan/show.blade.php', str_replace('\\', '/', $resolvedPermohonanShow));

        $resolvedPermohonanIndex = $finder->find('permohonan.index');
        $this->assertStringEndsWith('permohonan/index.blade.php', str_replace('\\', '/', $resolvedPermohonanIndex));

        $resolvedAdminRoles = $finder->find('admin.roles');
        $this->assertStringEndsWith('admin/roles.blade.php', str_replace('\\', '/', $resolvedAdminRoles));

        $resolvedNotifikasi = $finder->find('notifikasi.index');
        $this->assertStringEndsWith('notifikasi/index.blade.php', str_replace('\\', '/', $resolvedNotifikasi));
    }

    /**
     * Memastikan halaman detail permohonan dapat diakses dan di-render pada mode backend (tes_show).
     */
    public function test_permohonan_show_renders_successfully_in_backend_mode(): void
    {
        config(['app.dev_viewmode' => 'backend']);

        $response = $this->actingAs($this->userPemohon)
            ->get(route('permohonan.show', $this->permohonan->nomor_surat));

        $response->assertOk();
        $response->assertSee('Pelacakan Siklus Pengadaan');
        $response->assertSee($this->permohonan->nomor_surat);
        $response->assertSee('Mode Tampilan Aktif:');
    }

    /**
     * Memastikan halaman detail permohonan dapat diakses dan di-render pada mode frontend (show).
     */
    public function test_permohonan_show_renders_successfully_in_frontend_mode(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $response = $this->actingAs($this->userPemohon)
            ->get(route('permohonan.show', $this->permohonan->nomor_surat));

        $response->assertOk();
        $response->assertSee('Pelacakan 8 Tahapan Siklus Pengadaan');
        $response->assertSee($this->permohonan->nomor_surat);
    }

    /**
     * Memastikan halaman admin approval registrasi dapat di-render pada mode backend dan frontend.
     */
    public function test_admin_approval_registrasi_renders_in_both_modes(): void
    {
        // 1. Mode Backend
        config(['app.dev_viewmode' => 'backend']);
        $responseBackend = $this->actingAs($this->userAdmin)
            ->get(route('admin.persetujuan-registrasi.index'));
        $responseBackend->assertOk();
        $responseBackend->assertSee('Persetujuan Registrasi Akun Pengguna Baru');

        // 2. Mode Frontend
        config(['app.dev_viewmode' => 'frontend']);
        $responseFrontend = $this->actingAs($this->userAdmin)
            ->get(route('admin.persetujuan-registrasi.index'));
        $responseFrontend->assertOk();
        $responseFrontend->assertSee('Persetujuan Registrasi Pengguna Baru');
    }

    /**
     * Memastikan halaman admin manajemen role dapat di-render pada mode backend dan frontend.
     */
    public function test_admin_roles_renders_in_both_modes(): void
    {
        // 1. Mode Backend
        config(['app.dev_viewmode' => 'backend']);
        $responseBackend = $this->actingAs($this->userAdmin)
            ->get(route('admin.roles.index'));
        $responseBackend->assertOk();
        $responseBackend->assertSee('Manajemen Role & Hak Akses');

        // 2. Mode Frontend
        config(['app.dev_viewmode' => 'frontend']);
        $responseFrontend = $this->actingAs($this->userAdmin)
            ->get(route('admin.roles.index'));
        $responseFrontend->assertOk();
        $responseFrontend->assertSee('Manajemen Role');
    }

    /**
     * Memastikan staf Perencanaan dapat mendaftarkan rekening MAK baru melalui form web (FR 6.1).
     */
    public function test_perencanaan_can_create_new_mak_via_web_form(): void
    {
        $rolePerencanaan = Role::firstOrCreate(
            ['nama_role' => 'perencanaan'],
            ['label' => 'Subbagian Perencanaan', 'is_system' => true]
        );

        $userPerencanaan = User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $rolePerencanaan->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $makData = [
            'kode_mak' => 'MAK-TEST-2026-999',
            'uraian_mak' => 'Pengadaan Fasilitas Server Cloud Baru',
            'sumber_dana' => 'BOPTN',
            'tahun_anggaran' => 2026,
            'pagu_anggaran' => 300000000,
            'catatan_mak' => 'Pagu alokasi DIPA tahun anggaran 2026',
        ];

        $response = $this->actingAs($userPerencanaan)
            ->post(route('verifikasi.perencanaan.mak.store'), $makData);

        $response->assertRedirect(route('verifikasi.perencanaan'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('anggaran_mak', [
            'kode_mak' => 'MAK-TEST-2026-999',
            'uraian_mak' => 'Pengadaan Fasilitas Server Cloud Baru',
            'pagu_anggaran' => 300000000,
            'sisa_pagu_mak' => 300000000,
            'is_locked' => false,
        ]);
    }

    /**
     * Memastikan halaman notifikasi dapat diakses dan di-render pada mode backend (tes_index).
     */
    public function test_notifikasi_index_renders_successfully_in_backend_mode(): void
    {
        config(['app.dev_viewmode' => 'backend']);

        $response = $this->actingAs($this->userPemohon)
            ->get(route('notifikasi.index'));

        $response->assertOk();
        $response->assertSee('Pusat Notifikasi, Pengingat Jadwal');
        $response->assertSee('Kategori Tab Notifikasi:');
        $response->assertSee('Daftar Pemberitahuan & Pengingat Masuk');
    }
}
