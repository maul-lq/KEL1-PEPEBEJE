<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Manajemen Role & Matriks Hak Akses (RoleAndPermissionManagementTest)
 *
 * Menguji fungsionalitas manajemen peran dinamis oleh Administrator:
 * 1. Otorisasi rute: hanya peran Administrator yang berhak mengelola role dan permissions.
 * 2. Pembuatan peran baru (custom role) beserta penugasan granular hak akses (permissions).
 * 3. Pembaruan data dan sinkronisasi matriks hak akses pada role.
 * 4. Proteksi peran bawaan institusi (is_system = true): dilarang dihapus via antarmuka sistem.
 * 5. Penghapusan peran kustom yang tidak memiliki pengguna aktif.
 * 6. Pencegahan penghapusan peran kustom jika masih terikat pada pengguna aktif.
 * 7. Pemeriksaan hak akses dinamis melalui helper hasPermission() dan hasAnyPermission() pada Model User.
 * 8. Pengujian endpoint REST API /api/roles.
 */
class RoleAndPermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->regularUser = User::where('role', User::ROLE_USER)->firstOrFail();
    }

    /**
     * Memastikan hanya Administrator yang diizinkan mengakses halaman manajemen role.
     */
    public function test_only_admin_can_access_role_management(): void
    {
        // 1. Pengguna biasa ditolak dengan 403 Forbidden
        $this->actingAs($this->regularUser);
        $forbiddenResponse = $this->get(route('admin.roles.index'));
        $forbiddenResponse->assertStatus(403);

        // 2. Administrator diizinkan mengakses halaman manajemen role
        $this->actingAs($this->admin);
        $allowedResponse = $this->get(route('admin.roles.index'));
        $allowedResponse->assertOk();
        $allowedResponse->assertViewIs('admin.tes_roles');
        $allowedResponse->assertSee('Manajemen Role');
    }

    /**
     * Administrator dapat membuat peran kustom baru beserta penugasan hak akses awal.
     */
    public function test_admin_can_create_custom_role_with_permissions(): void
    {
        $this->actingAs($this->admin);

        // Ambil beberapa permissions untuk ditugaskan ke peran kustom baru
        $permissions = Permission::whereIn('nama_permission', ['permohonan.create', 'paket.create', 'monitoring.view'])->get();
        $this->assertCount(3, $permissions);

        $payload = [
            'nama_role' => 'auditor_internal',
            'label' => 'Auditor Pengawas Internal',
            'deskripsi' => 'Peran auditor untuk memantau permohonan, paket, dan jejak audit pengadaan.',
            'permissions' => $permissions->pluck('id')->toArray(),
        ];

        $response = $this->post(route('admin.roles.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verifikasi role tersimpan di database dengan is_system = false
        $this->assertDatabaseHas('roles', [
            'nama_role' => 'auditor_internal',
            'label' => 'Auditor Pengawas Internal',
            'is_system' => false,
        ]);

        $newRole = Role::where('nama_role', 'auditor_internal')->firstOrFail();
        $this->assertCount(3, $newRole->permissions);
    }

    /**
     * Pembuatan role dengan nama_role duplikat harus ditolak oleh validasi.
     */
    public function test_admin_cannot_create_duplicate_role_name(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'nama_role' => 'admin', // Duplikat dengan role sistem yang sudah ada
            'label' => 'Administrator Kloning',
        ];

        $response = $this->post(route('admin.roles.store'), $payload);
        $response->assertSessionHasErrors(['nama_role']);
    }

    /**
     * Administrator dapat memperbarui label, deskripsi, dan matriks hak akses suatu peran.
     */
    public function test_admin_can_update_role_details_and_permissions(): void
    {
        $this->actingAs($this->admin);

        // Buat role kustom
        $role = Role::create([
            'nama_role' => 'pengawas_lapangan',
            'label' => 'Pengawas Lapangan Awal',
            'deskripsi' => 'Deskripsi lama',
            'is_system' => false,
        ]);

        $newPermissions = Permission::whereIn('nama_permission', ['penerimaan.view', 'penerimaan.bast_create'])->get();

        $updatePayload = [
            'label' => 'Pengawas Lapangan & Gudang (Diperbarui)',
            'deskripsi' => 'Deskripsi baru yang lebih akurat',
            'permissions' => $newPermissions->pluck('id')->toArray(),
        ];

        $response = $this->put(route('admin.roles.update', $role->id), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertSame('Pengawas Lapangan & Gudang (Diperbarui)', $role->label);
        $this->assertSame('Deskripsi baru yang lebih akurat', $role->deskripsi);
        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->hasPermission('penerimaan.bast_create'));
    }

    /**
     * Sembilan peran bawaan sistem institusi (is_system = true) tidak boleh dihapus via web.
     */
    public function test_system_roles_are_protected_from_deletion_via_web(): void
    {
        $this->actingAs($this->admin);

        // Ambil role bawaan sistem, misalnya 'ppbj' atau 'wadir_2'
        $ppbjRole = Role::where('nama_role', 'ppbj')->firstOrFail();
        $this->assertTrue((bool) $ppbjRole->is_system);

        $response = $this->delete(route('admin.roles.destroy', $ppbjRole->id));

        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('role sistem institusi yang diproteksi', $errorMessage);

        // Pastikan role masih utuh di database
        $this->assertDatabaseHas('roles', ['id' => $ppbjRole->id]);
    }

    /**
     * Peran kustom yang belum memiliki pengguna dapat dihapus oleh Administrator.
     */
    public function test_admin_can_delete_custom_role_without_assigned_users(): void
    {
        $this->actingAs($this->admin);

        $customRole = Role::create([
            'nama_role' => 'konsultan_sementara',
            'label' => 'Konsultan PBJ Sementara',
            'is_system' => false,
        ]);

        $response = $this->delete(route('admin.roles.destroy', $customRole->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);
    }

    /**
     * Peran kustom yang masih terhubung ke pengguna dilarang dihapus.
     */
    public function test_admin_cannot_delete_custom_role_if_still_assigned_to_users(): void
    {
        $this->actingAs($this->admin);

        $customRole = Role::create([
            'nama_role' => 'staff_gudang_khusus',
            'label' => 'Staff Gudang Khusus',
            'is_system' => false,
        ]);

        // Hubungkan pengguna ke role kustom ini
        User::factory()->create([
            'role' => 'staff_gudang_khusus',
            'id_role' => $customRole->id,
            'status_aktif' => true,
        ]);

        $response = $this->delete(route('admin.roles.destroy', $customRole->id));

        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('masih digunakan oleh', $errorMessage);

        // Pastikan role tetap ada di database
        $this->assertDatabaseHas('roles', ['id' => $customRole->id]);
    }

    /**
     * Memastikan helper hasPermission() pada model User menghormati hak akses dinamis.
     */
    public function test_user_has_permission_helper_respects_custom_role_permissions(): void
    {
        // 1. Role Administrator otomatis memiliki seluruh permission
        $this->assertTrue($this->admin->hasPermission('permohonan.create'));
        $this->assertTrue($this->admin->hasPermission('pembayaran.sp2d'));
        $this->assertTrue($this->admin->hasPermission('apapun_fiturnya'));

        // 2. Pengguna dengan role kustom
        $auditorRole = Role::create([
            'nama_role' => 'auditor_sample',
            'label' => 'Sample Auditor',
            'is_system' => false,
        ]);

        $permAudit = Permission::where('nama_permission', 'audit_log.view')->firstOrFail();
        $auditorRole->permissions()->attach($permAudit->id);

        $auditorUser = User::factory()->create([
            'role' => 'auditor_sample',
            'id_role' => $auditorRole->id,
            'status_aktif' => true,
        ]);

        // Verifikasi hak akses yang diberikan vs yang tidak diberikan
        $this->assertTrue($auditorUser->hasPermission('audit_log.view'));
        $this->assertFalse($auditorUser->hasPermission('pembayaran.sp2d'));
        $this->assertTrue($auditorUser->hasAnyPermission(['audit_log.view', 'pembayaran.sp2d']));
        $this->assertFalse($auditorUser->hasAnyPermission(['kontrak.approve', 'pembayaran.sp2d']));
    }

    /**
     * Menguji REST API Resource untuk manajemen roles (/api/roles).
     */
    public function test_role_management_api_endpoints_work_properly(): void
    {
        $this->actingAs($this->admin);

        // 1. GET /api/roles
        $listResponse = $this->getJson('/api/roles');
        $listResponse->assertOk();
        $listResponse->assertJsonStructure([
            'status',
            'data' => [
                'roles',
                'permissions_by_module',
            ],
        ]);

        // 2. POST /api/roles
        $createResponse = $this->postJson('/api/roles', [
            'nama_role' => 'api_tester_role',
            'label' => 'Role Penguji API',
            'deskripsi' => 'Role yang dibuat via REST API',
        ]);
        $createResponse->assertStatus(201);
        $createResponse->assertJson([
            'status' => 'success',
        ]);

        $createdRoleId = $createResponse->json('data.id');

        // 3. PUT /api/roles/{role}
        $updateResponse = $this->putJson("/api/roles/{$createdRoleId}", [
            'label' => 'Role Penguji API (Update)',
        ]);
        $updateResponse->assertOk();

        // 4. DELETE /api/roles/{role}
        $deleteResponse = $this->deleteJson("/api/roles/{$createdRoleId}");
        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $createdRoleId]);
    }

    /**
     * Memastikan pengguna dengan peran kustom (seperti 'sirajin') dapat melihat navigasi
     * sesuai permissions yang diberikan dan dapat mengakses rute-rute terkait tanpa 403 Forbidden.
     */
    public function test_custom_role_user_can_see_navigation_and_access_permitted_modules(): void
    {
        // 1. Buat role kustom baru 'sirajin'
        $customRole = Role::create([
            'nama_role' => 'sirajin',
            'label' => 'Si Paling Rajin',
            'deskripsi' => 'Role kustom dengan kombinasi izin dinamis',
            'is_system' => false,
        ]);

        // Berikan izin: paket.view_all, anggaran.mak_view, admin.role_manage
        $permPaket = Permission::where('nama_permission', 'paket.view_all')->firstOrFail();
        $permAnggaran = Permission::where('nama_permission', 'anggaran.mak_view')->firstOrFail();
        $permRoleManage = Permission::where('nama_permission', 'admin.role_manage')->firstOrFail();
        $customRole->permissions()->attach([$permPaket->id, $permAnggaran->id, $permRoleManage->id]);

        // 2. Buat user dengan role kustom 'sirajin'
        $userRajin = User::factory()->create([
            'username' => 'rajin_sekali',
            'email' => 'rajin@rajin.rajin',
            'role' => 'sirajin',
            'id_role' => $customRole->id,
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
        ]);

        $this->actingAs($userRajin);

        // 3. Akses Dashboard - navigasi harus menampilkan modul yang diizinkan
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Si Paling Rajin (SIRAJIN)');
        $dashboardResponse->assertSee('Daftar Paket Pengadaan');
        $dashboardResponse->assertSee('Verifikasi Anggaran MAK');
        $dashboardResponse->assertSee('Manajemen Role');

        // 4. Akses rute yang diizinkan via CheckRole middleware (memetakan permission ke rute)
        // Rute /paket-pengadaan dilindungi role:ppbj,pp,ppk -> role sirajin memiliki paket.view_all
        $paketResponse = $this->get(route('paket.index'));
        $paketResponse->assertOk();

        // Rute /verifikasi/perencanaan dilindungi role:perencanaan -> role sirajin memiliki anggaran.mak_view
        $anggaranResponse = $this->get(route('verifikasi.perencanaan'));
        $anggaranResponse->assertOk();

        // Rute /admin/roles dilindungi role:admin -> role sirajin memiliki admin.role_manage
        $roleResponse = $this->get(route('admin.roles.index'));
        $roleResponse->assertOk();

        // 5. Akses rute yang TIDAK diizinkan (misal penerimaan-barang -> role:perlengkapan) harus 403 Forbidden
        // karena sirajin TIDAK memiliki satupun izin perlengkapan
        $penerimaanResponse = $this->get(route('penerimaan.index'));
        $penerimaanResponse->assertStatus(403);
    }
}
