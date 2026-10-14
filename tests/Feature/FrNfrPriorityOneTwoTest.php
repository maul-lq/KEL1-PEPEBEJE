<?php

use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    RateLimiter::clear('tes-login|127.0.0.1');
    RateLimiter::clear('random_user_failed|127.0.0.1');
});

test('FR 1: login supports multi-identity (email, username, nip) and checks active status', function () {
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
});

test('FR 1: login is rate limited after 5 failed attempts', function () {
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
    expect($errors[0])->toContain('Terlalu banyak percobaan login');
});

test('FR 2 & NFR RBAC: only 1 active PP (Pejabat Pengadaan) account is permitted', function () {
    $existingPp = User::where('role', 'pp')->where('status_aktif', true)->first();
    expect($existingPp)->not->toBeNull();

    $unit = Unit::first();

    // Mencoba registrasi PP kedua melalui endpoint registrasi web
    $response = $this->post(route('register'), [
        'username' => 'second_pp_attempt',
        'nama' => 'Pejabat Pengadaan Kedua',
        'nip' => '198801012026012222',
        'email' => 'pp2@pnj.ac.id',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'role' => 'pp',
        'kode_unit' => $unit->kode_unit,
        'jabatan' => 'PP Cadangan',
        'no_hp' => '081299998888',
    ]);

    $response->assertSessionHasErrors(['role']);
    $errors = session('errors')->get('role');
    expect($errors[0])->toContain('Hanya diizinkan 1 akun Pejabat Pengadaan');

    // Mencoba membuat PP kedua melalui REST API /api/users
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
});

test('NFR RBAC: PPK file isolation prevents PPK 1 from viewing or updating PPK 2 packages', function () {
    $ppk1 = User::where('role', 'ppk')->first();
    $ppk2 = User::where('role', 'ppk')->where('id_user', '!=', $ppk1->id_user)->first();

    expect($ppk1)->not->toBeNull();
    expect($ppk2)->not->toBeNull();

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
});

test('NFR Immutability: MAK cannot be updated or deleted when is_locked is true', function () {
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
    expect(function () use ($mak) {
        $mak->update(['pagu_anggaran' => 50000000.00]);
    })->toThrow(Exception::class, 'telah dikunci permanen');

    // Hapus MAK harus throw Exception
    expect(function () use ($mak) {
        $mak->delete();
    })->toThrow(Exception::class, 'tidak dapat dihapus');
});

test('NFR Digital Signature: BAST generates and verifies HMAC SHA-256 digital signature token', function () {
    $timestamp = now()->toIso8601String();
    $token = PenerimaanBarang::generateSignatureToken('BAST-TEST-001', 1, $timestamp);

    expect($token)->not->toBeEmpty();
    expect(strlen($token))->toBe(64); // SHA-256 hex length is 64 characters

    $bast = new PenerimaanBarang([
        'ttd_digital_bast' => $token,
    ]);

    expect($bast->verifyTtdDigital($token))->toBeTrue();
    expect($bast->verifyTtdDigital('tampered_token_invalid_hash'))->toBeFalse();
});

test('NFR Document Upload: restricts files to maximum 10MB and valid formats', function () {
    // 1. File Valid: PDF 1MB
    $validPdf = UploadedFile::fake()->create('dokumen.pdf', 1024, 'application/pdf');
    $v1 = Validator::make(['file' => $validPdf], ['file' => [DokumenUploadRule::pdfOrImage()]]);
    expect($v1->passes())->toBeTrue();

    // 2. File Valid: Image JPG 2MB
    $validJpg = UploadedFile::fake()->image('foto_barang.jpg', 800, 600)->size(2048);
    $v2 = Validator::make(['file' => $validJpg], ['file' => [DokumenUploadRule::pdfOrImage()]]);
    expect($v2->passes())->toBeTrue();

    // 3. File Gagal: Ukuran melebihi 10MB (11MB = 11264 KB)
    $oversizedFile = UploadedFile::fake()->create('dokumen_besar.pdf', 11264, 'application/pdf');
    $v3 = Validator::make(['file' => $oversizedFile], ['file' => [DokumenUploadRule::pdfOrImage()]]);
    expect($v3->fails())->toBeTrue();
    expect($v3->errors()->first('file'))->toContain('10 MB');

    // 4. File Gagal: Format terlarang (.exe atau .zip)
    $forbiddenFile = UploadedFile::fake()->create('skrip_berbahaya.exe', 500, 'application/x-msdownload');
    $v4 = Validator::make(['file' => $forbiddenFile], ['file' => [DokumenUploadRule::pdfOrImage()]]);
    expect($v4->fails())->toBeTrue();
    expect($v4->errors()->first('file'))->toContain('Format berkas');
});
