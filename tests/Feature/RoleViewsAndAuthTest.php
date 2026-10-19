<?php

namespace Tests\Feature;

use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengujian Fitur: Autentikasi & Antarmuka Program Riil Berbasis Peran (PHPUnit)
 *
 * Menguji seluruh alur autentikasi, registrasi, dashboard dinamis berbasis peran (RBAC),
 * penegakan isolasi data PPK, dan penyajian antarmuka semantik murni tanpa CSS.
 */
class RoleViewsAndAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan master data unit institusi untuk pengujian
        Unit::create([
            'kode_unit' => 'TIK',
            'nama_unit' => 'Jurusan Teknik Informatika dan Komputer',
            'jenis_unit' => 'jurusan',
        ]);

        Unit::create([
            'kode_unit' => 'B2',
            'nama_unit' => 'Bidang II (Umum dan Keuangan)',
            'jenis_unit' => 'bidang',
        ]);
    }

    /**
     * Memverifikasi halaman login resmi menyajikan form semantik dan panel akun demo.
     */
    public function test_login_page_renders_with_semantic_html_and_quick_login_panel(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee('Sistem Informasi Manajemen Pengadaan Barang dan Jasa (SIM-PBJ)')
            ->assertSee('Direktori Akun Dinas Pengguna Resmi Politeknik Negeri Jakarta')
            ->assertSee('user_pengaju')
            ->assertSee('wadir2')
            ->assertSee('ppbj')
            ->assertSee('ppk_1')
            ->assertDontSee('<style>', false);
    }

    /**
     * Memverifikasi halaman registrasi resmi menyajikan form pendaftaran dosen/pegawai.
     */
    public function test_register_page_renders_with_semantic_html(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200)
            ->assertSee('Pendaftaran Akun Pegawai / Pengusul Baru')
            ->assertSee('Unit Kerja / Jurusan Asal')
            ->assertSee('Jurusan Teknik Informatika dan Komputer');
    }

    /**
     * Memverifikasi pendaftaran akun baru berhasil menyimpan ke database dan mengarahkan ke login.
     */
    public function test_user_registration_saves_user_successfully(): void
    {
        $registrationData = [
            'nip' => '199501012020011001',
            'nama' => 'Dosen Baru Penguji, M.Kom.',
            'username' => 'dosen_baru_test',
            'email' => 'dosenbaru@pnj.ac.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'jabatan' => 'Dosen Pengajar TIK',
            'kode_unit' => 'TIK',
            'role' => 'user',
            'no_hp' => '081299887711',
            'no_telp_kantor' => '0217270001',
        ];

        $response = $this->post('/register', $registrationData);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'dosen_baru_test',
            'email' => 'dosenbaru@pnj.ac.id',
            'role' => 'user',
            'status_aktif' => true,
        ]);
    }

    /**
     * Memverifikasi pengguna aktif dapat login dan otomatis dialihkan ke /dashboard.
     */
    public function test_active_user_can_login_and_redirect_to_dashboard(): void
    {
        $user = User::create([
            'nip' => '198501012010011001',
            'username' => 'user_test_aktif',
            'email' => 'user_aktif@pnj.ac.id',
            'nama' => 'Pengusul Uji Aktif',
            'password' => Hash::make('password123'),
            'jabatan' => 'Ketua Jurusan TIK',
            'kode_unit' => 'TIK',
            'role' => 'user',
            'status_aktif' => true,
            'no_hp' => '081234567800',
        ]);

        $response = $this->post('/login', [
            'login' => 'user_test_aktif',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Memverifikasi akun yang dinonaktifkan ditolak saat login.
     */
    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'nip' => '198501012010011099',
            'username' => 'user_nonaktif',
            'email' => 'nonaktif@pnj.ac.id',
            'nama' => 'Akun Dinonaktifkan',
            'password' => Hash::make('password123'),
            'jabatan' => 'Staf',
            'kode_unit' => 'TIK',
            'role' => 'user',
            'status_aktif' => false,
            'no_hp' => '081234567899',
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => 'user_nonaktif',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    /**
     * Memverifikasi pengguna yang belum login dicegah mengakses rute terproteksi.
     */
    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/permohonan')->assertRedirect('/login');
        $this->get('/paket-pengadaan')->assertRedirect('/login');
        $this->get('/monitoring')->assertRedirect('/login');
    }

    /**
     * Memverifikasi peran User (Pemohon) melihat ruang kerja permohonan pengadaan pada dashboard.
     */
    public function test_user_role_sees_permohonan_workspace(): void
    {
        $user = User::create([
            'nip' => '198501012010011010',
            'username' => 'pemohon_jurusan',
            'email' => 'pemohon@pnj.ac.id',
            'nama' => 'Dr. Maulana Pengusul, M.T.',
            'password' => Hash::make('password123'),
            'jabatan' => 'Kajur TIK',
            'kode_unit' => 'TIK',
            'role' => 'user',
            'status_aktif' => true,
            'no_hp' => '081234567810',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200)
            ->assertSee('Ruang Kerja Pemohon (Unit / Jurusan)')
            ->assertSee('Ajukan Permohonan Pengadaan Baru')
            ->assertSee('Daftar & Riwayat Permohonan Saya', false);
    }

    /**
     * Memverifikasi peran Wadir 2 melihat ruang kerja verifikasi eksekutif institusi (ACC / Tolak).
     */
    public function test_wadir2_role_sees_verifikasi_eksekutif_workspace(): void
    {
        $wadir = User::create([
            'nip' => '197001011995011001',
            'username' => 'wadir2_user',
            'email' => 'wadir2@pnj.ac.id',
            'nama' => 'Prof. Dr. Ir. Wadir Dua, M.Sc.',
            'password' => Hash::make('password123'),
            'jabatan' => 'Wakil Direktur Bidang Umum dan Keuangan',
            'kode_unit' => 'B2',
            'role' => 'wadir_2',
            'status_aktif' => true,
            'no_hp' => '081298765432',
        ]);

        $response = $this->actingAs($wadir)->get('/dashboard');

        $response->assertStatus(200)
            ->assertSee('Ruang Kerja Wakil Direktur II (Otoritas Mutlak Persetujuan / ACC)')
            ->assertSee('Buka Antrean Verifikasi Eksekutif Wadir 2');
    }

    /**
     * Memverifikasi penegakan isolasi data PPK: PPK 1 hanya melihat paket yang ditugaskan kepadanya.
     */
    public function test_ppk_role_enforces_isolated_assigned_packages(): void
    {
        $ppbjUser = User::create([
            'nip' => '198001012005011002',
            'username' => 'ppbj_test',
            'email' => 'ppbj_test@pnj.ac.id',
            'nama' => 'Citra Koordinator PPBJ, M.M.',
            'password' => Hash::make('password123'),
            'jabatan' => 'Koordinator Pengadaan',
            'kode_unit' => 'B2',
            'role' => 'ppbj',
            'status_aktif' => true,
            'no_hp' => '081512345678',
        ]);

        $ppk1 = User::create([
            'nip' => '198203032007031001',
            'username' => 'ppk_sarana',
            'email' => 'ppk1@pnj.ac.id',
            'nama' => 'Eko PPK Bidang Sarana, M.T.',
            'password' => Hash::make('password123'),
            'jabatan' => 'Pejabat Pembuat Komitmen I (Sarana)',
            'kode_unit' => 'B2',
            'role' => 'ppk',
            'status_aktif' => true,
            'no_hp' => '081734567891',
        ]);

        $ppk2 = User::create([
            'nip' => '198203032007031002',
            'username' => 'ppk_lab',
            'email' => 'ppk2@pnj.ac.id',
            'nama' => 'Fajar PPK Bidang Laboratorium, M.Kom.',
            'password' => Hash::make('password123'),
            'jabatan' => 'Pejabat Pembuat Komitmen II (Laboratorium)',
            'kode_unit' => 'B2',
            'role' => 'ppk',
            'status_aktif' => true,
            'no_hp' => '081734567892',
        ]);

        // Buat usulan permohonan induk dengan kolom lengkap
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/001/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Fasilitas Server Cloud',
            'tujuan_pengadaan_ringkas' => 'Mendukung riset AI dan cloud computing mahasiswa TIK',
            'perihal' => 'Pengadaan Fasilitas Server Cloud',
            'anggaran_diajukan' => 350000000.00,
            'kode_unit' => 'TIK',
            'id_user' => $ppk1->id_user,
            'file_permohonan' => 'permohonan/dummy.pdf',
            'status' => 'disetujui',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
        ]);

        // Paket 1 ditugaskan ke PPK 1
        $paket1 = PaketPengadaan::create([
            'kode_paket' => 'PKT-SARANA-001',
            'nomor_paket' => 'PKT-SARANA-001',
            'nama_paket' => 'Paket Pengadaan Server Sarana Kampus',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Tender',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 300000000.00,
            'alokasi_pagu_paket' => 300000000.00,
            'jalur_routing' => 'ppk_diatas_200jt',
            'id_user_ppbj' => $ppbjUser->id_user,
            'id_user_ppk' => $ppk1->id_user,
            'status_paket' => 'terdaftar',
        ]);

        // Paket 2 ditugaskan ke PPK 2
        $paket2 = PaketPengadaan::create([
            'kode_paket' => 'PKT-LAB-002',
            'nomor_paket' => 'PKT-LAB-002',
            'nama_paket' => 'Paket Pengadaan Alat Lab Elektronika',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Tender',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 400000000.00,
            'alokasi_pagu_paket' => 400000000.00,
            'jalur_routing' => 'ppk_diatas_200jt',
            'id_user_ppbj' => $ppbjUser->id_user,
            'id_user_ppk' => $ppk2->id_user,
            'status_paket' => 'terdaftar',
        ]);

        // Saat PPK 1 login ke dashboard:
        $response1 = $this->actingAs($ppk1)->get('/dashboard');
        $response1->assertStatus(200)
            ->assertSee('PKT-SARANA-001')
            ->assertDontSee('PKT-LAB-002'); // Paket PPK 2 tidak boleh terlihat

        // Saat PPK 2 login ke dashboard:
        $response2 = $this->actingAs($ppk2)->get('/dashboard');
        $response2->assertStatus(200)
            ->assertSee('PKT-LAB-002')
            ->assertDontSee('PKT-SARANA-001'); // Paket PPK 1 tidak boleh terlihat
    }

    /**
     * Memverifikasi pengguna yang terotentikasi dapat melakukan logout.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'nip' => '198501012010011077',
            'username' => 'user_logout_test',
            'email' => 'logout_test@pnj.ac.id',
            'nama' => 'User Logout Test',
            'password' => Hash::make('password123'),
            'jabatan' => 'Staff',
            'kode_unit' => 'TIK',
            'role' => 'user',
            'status_aktif' => true,
            'no_hp' => '081234567877',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
