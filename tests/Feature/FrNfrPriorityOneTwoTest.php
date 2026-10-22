<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Pengujian Prioritas 1 & 2 FR/NFR (FrNfrPriorityOneTwoTest)
 * Mencakup autentikasi multi-identitas, rate limiting, RBAC 9 peran (termasuk Admin & 1 PP aktif),
 * isolasi berkas 4 PPK, immutabilitas MAK, tanda tangan digital BAST, dan validasi upload NFR 11.
 */
class FrNfrPriorityOneTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        RateLimiter::clear('tes-login|127.0.0.1');
        RateLimiter::clear('random_user_failed|127.0.0.1');
    }

    public function test_fr1_login_supports_multi_identity_and_checks_active_status(): void
    {
        $unit = Unit::first();

        $activeUser = User::create([
            'username' => 'test_multi_identity',
            'nama' => 'User Multi Identity',
            'nip' => '199901012026011999',
            'email' => 'multi_identity@pnj.ac.id',
            'password' => Hash::make('Secret123!'),
            'role' => 'user',
            'kode_unit' => $unit->kode_unit,
            'jabatan' => 'Staff Penguji',
            'no_hp' => '081299990001',
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
        ]);

        $inactiveUser = User::create([
            'username' => 'test_inactive_user',
            'nama' => 'User Inactive',
            'nip' => '199901012026011888',
            'email' => 'inactive_user@pnj.ac.id',
            'password' => Hash::make('Secret123!'),
            'role' => 'user',
            'kode_unit' => $unit->kode_unit,
            'jabatan' => 'Staff Penguji',
            'no_hp' => '081299990002',
            'status_aktif' => false,
        ]);

        // Test 1: Login dengan Username
        $response = $this->post(route('login'), [
            'login' => 'test_multi_identity',
            'password' => 'Secret123!',
        ]);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($activeUser);
        $this->post(route('logout'));

        // Test 2: Login dengan NIP
        $response = $this->post(route('login'), [
            'login' => '199901012026011999',
            'password' => 'Secret123!',
        ]);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($activeUser);
        $this->post(route('logout'));

        // Test 3: Login dengan Email
        $response = $this->post(route('login'), [
            'login' => 'multi_identity@pnj.ac.id',
            'password' => 'Secret123!',
        ]);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($activeUser);
        $this->post(route('logout'));

        // Test 4: Inactive user ditolak
        $response = $this->post(route('login'), [
            'login' => 'test_inactive_user',
            'password' => 'Secret123!',
        ]);
        $response->assertSessionHasErrors(['login']);
        $this->assertGuest();
    }

    public function test_fr1_login_is_rate_limited_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'login' => 'random_user_failed',
                'password' => 'WrongPassword!',
            ]);
        }

        // Percobaan ke-6 harus ditolak oleh RateLimiter
        $response = $this->post(route('login'), [
            'login' => 'random_user_failed',
            'password' => 'WrongPassword!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $errors = session('errors')->get('login');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
    }

    public function test_fr2_and_nfr_rbac_supports_admin_and_permits_only_one_active_pp_account(): void
    {
        // Pastikan akun Admin tersedia dari seeder (Peran ke-1 dari 9 peran organisasi)
        $adminUser = User::where('role', User::ROLE_ADMIN)->where('status_aktif', true)->first();
        $this->assertNotNull($adminUser);

        // Pastikan tepat 4 akun PPK tersedia dari seeder
        $ppkCount = User::where('role', User::ROLE_PPK)->where('status_aktif', true)->count();
        $this->assertSame(4, $ppkCount);

        $existingPp = User::where('role', 'pp')->where('status_aktif', true)->first();
        $this->assertNotNull($existingPp);

        $unit = Unit::first();

        // 1. Registrasi user baru via web (masuk antrean 'menunggu_persetujuan' tanpa pemilihan role oleh pendaftar)
        $response = $this->post(route('register'), [
            'username' => 'second_pp_attempt',
            'nama' => 'Pejabat Pengadaan Kedua',
            'nip' => '198801012026012222',
            'email' => 'pp2@pnj.ac.id',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'kode_unit' => $unit->kode_unit,
            'jabatan' => 'PP Cadangan',
            'no_hp' => '081299998888',
        ]);

        $response->assertRedirect(route('login'));
        $pendingUser = User::where('username', 'second_pp_attempt')->first();
        $this->assertNotNull($pendingUser);
        $this->assertSame(User::STATUS_REGISTRASI_MENUNGGU, $pendingUser->status_registrasi);
        $this->assertFalse($pendingUser->status_aktif);

        // 2. Admin mencoba menyetujui (ACC) user ini dengan menetapkan role 'pp' ketika sudah ada 1 akun PP aktif
        $this->actingAs($adminUser);
        $accResponse = $this->post(route('admin.persetujuan-registrasi.acc', $pendingUser->id_user), [
            'role' => 'pp',
        ]);
        $accResponse->assertSessionHasErrors(['role']);
        $errors = session('errors')->get('role');
        $this->assertStringContainsString('Hanya diizinkan 1 akun Pejabat Pengadaan', $errors[0]);

        // 3. Mencoba membuat PP kedua melalui REST API /api/users
        $this->actingAs($existingPp);
        $apiResponse = $this->postJson('/api/users', [
            'username' => 'second_pp_api',
            'nama' => 'PP API Test',
            'nip' => '198801012026012333',
            'email' => 'pp_api@pnj.ac.id',
            'password' => 'Secret123!',
            'role' => 'pp',
            'kode_unit' => $unit->kode_unit,
            'jabatan' => 'PP Cadangan API',
            'no_hp' => '081299998889',
            'status_aktif' => true,
        ]);

        $apiResponse->assertStatus(422);
        $apiResponse->assertJsonValidationErrors(['role']);
    }

    public function test_nfr_rbac_ppk_file_isolation_prevents_ppk1_from_viewing_or_updating_ppk2_packages(): void
    {
        $ppk1 = User::where('role', 'ppk')->first();
        $ppk2 = User::where('role', 'ppk')->where('id_user', '!=', $ppk1->id_user)->first();

        $this->assertNotNull($ppk1);
        $this->assertNotNull($ppk2);

        $permohonan = PermohonanPengadaan::first();
        $ppbjUser = User::where('role', 'ppbj')->first();

        // Buat paket pengadaan yang ditugaskan khusus untuk PPK 2
        $paketPpk2 = PaketPengadaan::create([
            'nomor_paket' => 'TEST-PKT-PPK2-'.time(),
            'nama_paket' => 'Pengadaan Server Riset Khusus PPK 2',
            'jenis_pengadaan' => 'barang',
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'nilai_hps' => 250000000.00,
            'alokasi_pagu_paket' => 250000000.00,
            'status_paket' => 'reviu_spk',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbjUser->id_user,
            'id_user_ppk' => $ppk2->id_user,
        ]);

        // PPK 1 mencoba mengakses paket milik PPK 2 via API show -> Harus 403 Forbidden
        $this->actingAs($ppk1);
        $showResponse = $this->getJson("/api/paket-pengadaan/{$paketPpk2->nomor_paket}");
        $showResponse->assertStatus(403);

        // PPK 1 mencoba update paket milik PPK 2 -> Harus 403 Forbidden
        $updateResponse = $this->putJson("/api/paket-pengadaan/{$paketPpk2->nomor_paket}", [
            'nama_paket' => 'Perubahan Ilegal oleh PPK 1',
        ]);
        $updateResponse->assertStatus(403);

        // PPK 2 sah mengakses paket miliknya sendiri -> Harus 200 OK
        $this->actingAs($ppk2);
        $legitResponse = $this->getJson("/api/paket-pengadaan/{$paketPpk2->nomor_paket}");
        $legitResponse->assertStatus(200);
    }

    public function test_nfr_immutability_mak_cannot_be_updated_or_deleted_when_is_locked_is_true(): void
    {
        $user = User::first();

        $mak = AnggaranMAK::create([
            'kode_mak' => 'TEST-MAK-IMMUTABLE-'.time(),
            'uraian_mak' => 'Anggaran Uji Immutabilitas',
            'pagu_anggaran' => 100000000.00,
            'sumber_dana' => 'BOPTN',
            'tahun_anggaran' => 2026,
            'id_user' => $user->id_user,
            'is_locked' => true,
            'tanggal_kunci' => now(),
        ]);

        // Update core pagu_anggaran harus throw Exception
        try {
            $mak->update(['pagu_anggaran' => 50000000.00]);
            $this->fail('Seharusnya melempar Exception saat mengubah MAK yang sudah terkunci.');
        } catch (Exception $e) {
            $this->assertStringContainsString('telah dikunci permanen', $e->getMessage());
        }

        // Hapus MAK harus throw Exception
        try {
            $mak->delete();
            $this->fail('Seharusnya melempar Exception saat menghapus MAK yang sudah terkunci.');
        } catch (Exception $e) {
            $this->assertStringContainsString('tidak dapat dihapus', $e->getMessage());
        }
    }

    public function test_nfr_digital_signature_bast_generates_and_verifies_hmac_sha256_token(): void
    {
        $timestamp = now()->toIso8601String();
        $token = PenerimaanBarang::generateSignatureToken('BAST-TEST-001', 1, $timestamp);

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token));

        $bast = new PenerimaanBarang([
            'ttd_digital_bast' => $token,
        ]);

        $this->assertTrue($bast->verifyTtdDigital($token));
        $this->assertFalse($bast->verifyTtdDigital('tampered_token_invalid_hash'));
    }

    public function test_nfr_document_upload_restricts_files_to_maximum_10mb_and_valid_formats(): void
    {
        // 1. File Valid: PDF 1MB
        $validPdf = UploadedFile::fake()->create('dokumen.pdf', 1024, 'application/pdf');
        $v1 = Validator::make(['file' => $validPdf], ['file' => [DokumenUploadRule::pdfOrImage()]]);
        $this->assertTrue($v1->passes());

        // 2. File Valid: Image JPG 2MB
        $validJpg = UploadedFile::fake()->image('foto_barang.jpg', 800, 600)->size(2048);
        $v2 = Validator::make(['file' => $validJpg], ['file' => [DokumenUploadRule::pdfOrImage()]]);
        $this->assertTrue($v2->passes());

        // 3. File Gagal: Ukuran melebihi 10MB (11MB = 11264 KB)
        $oversizedFile = UploadedFile::fake()->create('dokumen_besar.pdf', 11264, 'application/pdf');
        $v3 = Validator::make(['file' => $oversizedFile], ['file' => [DokumenUploadRule::pdfOrImage()]]);
        $this->assertTrue($v3->fails());
        $this->assertStringContainsString('10 MB', $v3->errors()->first('file'));

        // 4. File Gagal: Format terlarang (.exe atau .zip)
        $forbiddenFile = UploadedFile::fake()->create('skrip_berbahaya.exe', 500, 'application/x-msdownload');
        $v4 = Validator::make(['file' => $forbiddenFile], ['file' => [DokumenUploadRule::pdfOrImage()]]);
        $this->assertTrue($v4->fails());
        $this->assertStringContainsString('Format berkas', $v4->errors()->first('file'));
    }
}
