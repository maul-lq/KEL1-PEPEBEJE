<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PaketPengadaan;
use App\Models\Permission;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Fitur RBAC: Koordinator PPBJ Memonitor Seluruh Aktivitas Pejabat Pengadaan (PP).
 *
 * Menguji:
 * 1. Default permissions 'pp.monitoring_aktivitas' dan 'lihat_pp' terpasang pada role 'ppbj' dan 'admin'.
 * 2. Helper method User::canMonitorPp() mengembalikan true untuk PPBJ/Admin dan false untuk user biasa.
 * 3. Halaman matriks role admin menampilkan permission monitoring PP.
 * 4. Dashboard PPBJ menampilkan widget/panel interaktif 'Monitoring Aktivitas Pejabat Pengadaan (PP)'.
 * 5. Dashboard Pengguna Biasa tidak menampilkan panel pemantauan PP.
 * 6. Riwayat aktivitas di modul /laporan mendukung klasifikasi & filter 'Pengadaan Langsung (PP)'.
 * 7. PaketPengadaanPolicy mengizinkan peran dengan hak akses 'lihat_pp' mengakses berkas paket PP.
 */
class PpbjMonitorPpActivityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $ppbjUser;

    protected User $ppUser;

    protected User $regularUser;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->unit = Unit::firstOrCreate(['kode_unit' => 'TIK'], ['nama_unit' => 'Teknik Informatika dan Komputer']);

        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->ppbjUser = User::where('role', User::ROLE_PPBJ)->firstOrFail();
        $this->regularUser = User::where('role', User::ROLE_USER)->firstOrFail();

        $this->ppUser = User::firstOrCreate(
            ['role' => User::ROLE_PP],
            [
                'username' => 'pejabat_pengadaan_test',
                'name' => 'Pejabat Pengadaan Institusi',
                'email' => 'pp_test@pnj.ac.id',
                'nip' => '198501012010121008',
                'password' => bcrypt('Password123!'),
                'status_aktif' => true,
                'kode_unit' => 'TIK',
            ]
        );
    }

    /**
     * 1. Memastikan permission 'pp.monitoring_aktivitas' dan 'lihat_pp' terpasang pada role PPBJ dan Admin.
     */
    public function test_ppbj_and_admin_have_pp_monitoring_permissions_by_default(): void
    {
        $this->assertTrue(Permission::where('nama_permission', 'pp.monitoring_aktivitas')->exists());
        $this->assertTrue(Permission::where('nama_permission', 'lihat_pp')->exists());

        $this->assertTrue($this->ppbjUser->canMonitorPp());
        $this->assertTrue($this->admin->canMonitorPp());
        $this->assertFalse($this->regularUser->canMonitorPp());

        $this->assertTrue($this->ppbjUser->hasPermission('pp.monitoring_aktivitas'));
        $this->assertTrue($this->ppbjUser->hasPermission('lihat_pp'));
    }

    /**
     * 2. Administrator dapat melihat permission monitoring PP pada matriks hak akses.
     */
    public function test_admin_can_see_lihat_pp_in_role_permissions_matrix(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'));
        $response->assertOk();
        $response->assertSee('Memonitor Seluruh Aktivitas &amp; Hasil Negosiasi PP', false);
        $response->assertSee('lihat_pp');
    }

    /**
     * 3. Dashboard PPBJ menampilkan widget pemantauan aktivitas PP lengkap dengan data paket dan negosiasi.
     */
    public function test_ppbj_dashboard_displays_monitoring_aktivitas_pp_section(): void
    {
        $vendor = Vendor::first() ?? Vendor::create([
            'nama_vendor' => 'PT Mitra Pengadaan Utama',
            'nama_perusahaan' => 'PT Mitra Pengadaan Utama',
            'npwp' => '99.888.777.6-555.000',
            'is_active' => true,
        ]);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '001/TEST-PPBJ/2026',
            'judul_pengadaan' => 'Pengadaan Komputer Lab Jaringan',
            'id_user' => $this->regularUser->id_user,
            'kode_unit' => $this->unit->kode_unit,
            'kategori_pengadaan' => 'barang',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'tanggal_pengajuan' => now(),
            'anggaran_diajukan' => 75000000,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-PP-75JT-001',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Pengadaan Komputer Lab Jaringan',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'pengadaan_langsung',
            'alokasi_pagu_paket' => 75000000,
            'nilai_hps' => 72000000,
            'nominal_harga_perkiraan' => 70000000,
            'nominal_harga_akhir' => 68500000,
            'nilai_hasil_negosiasi' => 68500000,
            'id_vendor' => $vendor->id,
            'npwp_vendor' => $vendor->npwp,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'status_paket' => PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
            'id_user_pp' => $this->ppUser->id_user,
            'id_user_ppbj' => $this->ppbjUser->id_user,
            'tanggal_registrasi_paket' => now(),
        ]);

        AuditLog::catat(
            $this->ppUser->id_user,
            'REVIEW_KELENGKAPAN_PP',
            'paket_pengadaan',
            $paket->nomor_paket,
            'terdaftar',
            $paket->status_paket,
            'PP memverifikasi dokumen lab jaringan dan berhasil negosiasi ke Rp 68.500.000.'
        );

        $response = $this->actingAs($this->ppbjUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Monitoring Aktivitas Pejabat Pengadaan (PP)');
        $response->assertSee('RBAC: Lihat PP Aktif');
        $response->assertSee($paket->nomor_paket);
        $response->assertSee($vendor->nama_vendor);
        $response->assertSee('68.500.000');
        $response->assertSee('Log Tindakan Pejabat Pengadaan');
    }

    /**
     * 4. Pengguna biasa (role 'user') tidak memiliki akses ke panel pemantauan PP di dashboard.
     */
    public function test_regular_user_dashboard_does_not_display_monitoring_pp_section(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Monitoring Aktivitas Pejabat Pengadaan (PP)');
        $response->assertDontSee('RBAC: Lihat PP Aktif');
    }

    /**
     * 5. Halaman laporan mendukung filter 'Pengadaan Langsung (PP)'.
     */
    public function test_laporan_activity_history_supports_pengadaan_langsung_pp_filter(): void
    {
        $response = $this->actingAs($this->ppbjUser)->get(route('laporan.index'));

        $response->assertOk();
        $response->assertSee('Pengadaan Langsung (PP)');
    }

    /**
     * 6. Kebijakan otorisasi (Policy) mengizinkan peran kustom dengan izin 'lihat_pp' melihat paket PP.
     */
    public function test_custom_role_with_lihat_pp_permission_can_access_pp_package_via_policy(): void
    {
        $customRole = Role::firstOrCreate(
            ['nama_role' => 'auditor_internal'],
            ['label' => 'Auditor Internal Kampus', 'deskripsi' => 'Pengawas Pengadaan', 'is_system' => false]
        );

        $lihatPpPermission = Permission::where('nama_permission', 'lihat_pp')->firstOrFail();
        $customRole->permissions()->sync([$lihatPpPermission->id]);

        $auditorUser = User::factory()->create([
            'role' => 'auditor_internal',
            'id_role' => $customRole->id,
            'status_aktif' => true,
        ]);

        $permohonan = PermohonanPengadaan::first() ?? PermohonanPengadaan::create([
            'nomor_surat' => '002/TEST-AUDIT/2026',
            'judul_pengadaan' => 'Pengadaan Alat Praktikum Elektronika',
            'id_user' => $this->regularUser->id_user,
            'kode_unit' => $this->unit->kode_unit,
            'kategori_pengadaan' => 'barang',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'tanggal_pengajuan' => now(),
            'anggaran_diajukan' => 60000000,
        ]);

        $paketPp = PaketPengadaan::create([
            'nomor_paket' => 'PKT-AUDIT-PP-002',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Pengadaan Alat Praktikum Elektronika',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'pengadaan_langsung',
            'alokasi_pagu_paket' => 60000000,
            'nilai_hps' => 59000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'status_paket' => 'reviu_dokumen',
            'id_user_pp' => $this->ppUser->id_user,
            'tanggal_registrasi_paket' => now(),
        ]);

        $this->assertTrue($auditorUser->canMonitorPp());
        $this->assertTrue($auditorUser->can('view', $paketPp));
    }
}
