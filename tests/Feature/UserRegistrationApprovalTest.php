<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengujian Alur Registrasi & Approval Akun Pengguna oleh Admin (UserRegistrationApprovalTest)
 *
 * Menguji kepatuhan seluruh skenario verifikasi pendaftaran akun pengguna baru:
 * 1. Pendaftar baru berstatus 'menunggu_persetujuan' dan 'status_aktif' = false (tanpa pemilihan role di form registrasi).
 * 2. Pengguna tidak dapat login sebelum disetujui (ACC) oleh Administrator.
 * 3. Administrator dapat melihat antrean verifikasi registrasi.
 * 4. Administrator dapat menyetujui (ACC) akun serta memilihkan dan menetapkan peran (role) sistem.
 * 5. Penegakan batasan FR 2.1 (maksimal 1 akun Pejabat Pengadaan aktif) saat ACC role 'pp'.
 * 6. Administrator dapat menolak pendaftaran akun dengan mencantumkan alasan penolakan wajib.
 * 7. Akses REST API verifikasi registrasi untuk integrasi frontend masa depan.
 */
class UserRegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->unit = Unit::firstOrFail();
    }

    /**
     * Pendaftar baru harus tersimpan dengan status 'menunggu_persetujuan' dan tidak aktif.
     */
    public function test_user_can_register_and_starts_with_pending_status_and_inactive(): void
    {
        $payload = [
            'nip' => '199505052026011005',
            'username' => 'dosen_baru',
            'nama' => 'Dosen Baru, M.Kom.',
            'email' => 'dosen_baru@pnj.ac.id',
            'password' => 'SandiRahasia123!',
            'password_confirmation' => 'SandiRahasia123!',
            'jabatan' => 'Dosen Laboratorium Komputer',
            'kode_unit' => $this->unit->kode_unit,
            'no_hp' => '081234567899',
            'no_telp_kantor' => '0217270099',
        ];

        $response = $this->post(route('register'), $payload);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'dosen_baru',
            'email' => 'dosen_baru@pnj.ac.id',
            'nip' => '199505052026011005',
            'status_aktif' => false,
            'status_registrasi' => User::STATUS_REGISTRASI_MENUNGGU,
        ]);
    }

    /**
     * Pengguna yang status pendaftarannya masih 'menunggu_persetujuan' dilarang login ke sistem.
     */
    public function test_user_cannot_login_while_registration_is_pending(): void
    {
        $user = User::factory()->menungguApproval()->create([
            'username' => 'pending_user',
            'email' => 'pending@pnj.ac.id',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->post(route('login'), [
            'login' => 'pending_user',
            'password' => 'Secret123!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $errorMessage = session('errors')->get('login')[0];
        $this->assertStringContainsString('menunggu verifikasi dan persetujuan', $errorMessage);
        $this->assertGuest();
    }

    /**
     * Pengguna yang pendaftarannya ditolak dilarang login dan menerima alasan penolakan.
     */
    public function test_user_cannot_login_if_registration_is_rejected(): void
    {
        $user = User::factory()->ditolak('NIP tidak terdaftar di kepegawaian PNJ')->create([
            'username' => 'rejected_user',
            'email' => 'rejected@pnj.ac.id',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->post(route('login'), [
            'login' => 'rejected_user',
            'password' => 'Secret123!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $errorMessage = session('errors')->get('login')[0];
        $this->assertStringContainsString('ditolak oleh Administrator', $errorMessage);
        $this->assertStringContainsString('NIP tidak terdaftar di kepegawaian PNJ', $errorMessage);
        $this->assertGuest();
    }

    /**
     * Administrator dapat mengakses antrean pendaftar akun, sedangkan non-admin diblokir (403).
     */
    public function test_only_admin_can_access_registration_approval_queue(): void
    {
        // 1. Non-admin (misal peran 'user') diblokir dengan 403 Forbidden
        $regularUser = User::where('role', User::ROLE_USER)->firstOrFail();
        $this->actingAs($regularUser);

        $forbiddenResponse = $this->get(route('admin.persetujuan-registrasi.index'));
        $forbiddenResponse->assertStatus(403);

        // 2. Administrator diizinkan mengakses halaman antrean verifikasi
        $this->actingAs($this->admin);
        $allowedResponse = $this->get(route('admin.persetujuan-registrasi.index'));
        $allowedResponse->assertOk();
        $allowedResponse->assertViewIs('admin.tes_approval_registrasi');
        $allowedResponse->assertSee('Antrean Pendaftar Akun Menunggu Persetujuan');
    }

    /**
     * Administrator dapat menyetujui (ACC) akun pengguna dan memilihkan peran (role) sistem.
     */
    public function test_admin_can_approve_registration_and_assign_role(): void
    {
        $pendingUser = User::factory()->menungguApproval()->create([
            'username' => 'calon_perencana',
            'password' => Hash::make('PasswordAman123!'),
        ]);

        $perencanaanRole = Role::where('nama_role', 'perencanaan')->firstOrFail();

        $this->actingAs($this->admin);

        // Administrator menyetujui (ACC) dan memilihkan role 'perencanaan'
        $response = $this->post(route('admin.persetujuan-registrasi.acc', $pendingUser->id_user), [
            'id_role' => $perencanaanRole->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan atribut pengguna terbarui di database
        $pendingUser->refresh();
        $this->assertTrue($pendingUser->status_aktif);
        $this->assertSame(User::STATUS_REGISTRASI_DISETUJUI, $pendingUser->status_registrasi);
        $this->assertSame('perencanaan', $pendingUser->role);
        $this->assertSame($perencanaanRole->id, $pendingUser->id_role);
        $this->assertSame($this->admin->id_user, $pendingUser->id_admin_approval);
        $this->assertNotNull($pendingUser->tanggal_approval);

        // Pastikan pengguna sekarang dapat login dan mengakses dashboard
        $this->post(route('logout'));
        $loginResponse = $this->post(route('login'), [
            'login' => 'calon_perencana',
            'password' => 'PasswordAman123!',
        ]);

        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($pendingUser);
    }

    /**
     * Administrator dilarang menetapkan role 'pp' jika sudah ada 1 akun Pejabat Pengadaan aktif (FR 2.1).
     */
    public function test_admin_approval_enforces_fr2_1_max_one_active_pp(): void
    {
        $existingPp = User::where('role', User::ROLE_PP)->where('status_aktif', true)->firstOrFail();

        $pendingUser = User::factory()->menungguApproval()->create([
            'username' => 'calon_pp_kedua',
        ]);

        $this->actingAs($this->admin);

        // Mencoba menetapkan role 'pp' via form approval
        $response = $this->post(route('admin.persetujuan-registrasi.acc', $pendingUser->id_user), [
            'role' => 'pp',
        ]);

        $response->assertSessionHasErrors(['role']);
        $errorMessage = session('errors')->get('role')[0];
        $this->assertStringContainsString('Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif', $errorMessage);

        // Pengguna tetap berstatus belum disetujui
        $pendingUser->refresh();
        $this->assertFalse($pendingUser->status_aktif);
        $this->assertSame(User::STATUS_REGISTRASI_MENUNGGU, $pendingUser->status_registrasi);
    }

    /**
     * Administrator dapat menolak pendaftaran akun dengan mencantumkan alasan penolakan wajib.
     */
    public function test_admin_can_reject_registration_with_mandatory_reason(): void
    {
        $pendingUser = User::factory()->menungguApproval()->create([
            'username' => 'pendaftar_meragukan',
        ]);

        $this->actingAs($this->admin);

        // 1. Menolak tanpa alasan harus gagal validasi
        $emptyReasonResponse = $this->post(route('admin.persetujuan-registrasi.tolak', $pendingUser->id_user), [
            'alasan_penolakan' => '',
        ]);
        $emptyReasonResponse->assertSessionHasErrors(['alasan_penolakan']);

        // 2. Menolak dengan alasan yang jelas berhasil
        $validRejectResponse = $this->post(route('admin.persetujuan-registrasi.tolak', $pendingUser->id_user), [
            'alasan_penolakan' => 'Identitas NIP tidak cocok dengan basis data kepegawaian PNJ.',
        ]);

        $validRejectResponse->assertRedirect();
        $validRejectResponse->assertSessionHas('success');

        $pendingUser->refresh();
        $this->assertFalse($pendingUser->status_aktif);
        $this->assertSame(User::STATUS_REGISTRASI_DITOLAK, $pendingUser->status_registrasi);
        $this->assertSame('Identitas NIP tidak cocok dengan basis data kepegawaian PNJ.', $pendingUser->alasan_penolakan);
        $this->assertSame($this->admin->id_user, $pendingUser->id_admin_approval);
        $this->assertNotNull($pendingUser->tanggal_approval);
    }

    /**
     * Menguji endpoint REST API JSON persetujuan dan penolakan registrasi oleh Administrator.
     */
    public function test_admin_approval_api_endpoints_work_properly(): void
    {
        $pendingUser = User::factory()->menungguApproval()->create();

        $this->actingAs($this->admin);

        // 1. API GET antrean registrasi
        $queueResponse = $this->getJson('/api/admin/persetujuan-registrasi');
        $queueResponse->assertOk();
        $queueResponse->assertJsonStructure([
            'status',
            'data' => [
                'pending' => ['data'],
                'history',
                'roles',
            ],
        ]);

        // 2. API POST ACC
        $userRole = Role::where('nama_role', 'user')->firstOrFail();
        $accResponse = $this->postJson("/api/admin/persetujuan-registrasi/{$pendingUser->id_user}/acc", [
            'id_role' => $userRole->id,
        ]);

        $accResponse->assertOk();
        $accResponse->assertJson([
            'status' => 'success',
        ]);

        $pendingUser->refresh();
        $this->assertTrue($pendingUser->status_aktif);
        $this->assertSame(User::STATUS_REGISTRASI_DISETUJUI, $pendingUser->status_registrasi);
    }
}
