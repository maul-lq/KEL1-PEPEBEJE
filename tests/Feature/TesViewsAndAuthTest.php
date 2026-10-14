<?php

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Unit::create([
        'kode_unit' => 'TIK',
        'nama_unit' => 'Jurusan Teknik Informatika dan Komputer',
        'jenis_unit' => 'jurusan',
    ]);
});

test('login view renders successfully with plain HTML', function () {
    $response = $this->get('/tes-login');

    $response->assertStatus(200)
        ->assertSee('Login - Sistem Informasi Pengadaan Barang dan Jasa')
        ->assertSee('user_pengaju')
        ->assertSee('password123');
});

test('registrasi view renders successfully with plain HTML', function () {
    $response = $this->get('/tes-registrasi');

    $response->assertStatus(200)
        ->assertSee('Daftar Akun Baru - Sistem Informasi PBJ')
        ->assertSee('Unit Kerja / Jurusan');
});

test('user registration saves user as active user', function () {
    $registrationData = [
        'nip' => '199812122022011001',
        'username' => 'dosen_baru_tik',
        'nama' => 'Dosen Baru TIK',
        'email' => 'dosenbaru@pnj.ac.id',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'jabatan' => 'Dosen Pengajar',
        'kode_unit' => 'TIK',
        'role' => 'user',
        'no_hp' => '081299887766',
        'no_telp_kantor' => '0217279999',
    ];

    $response = $this->post('/tes-registrasi', $registrationData);

    $response->assertRedirect('/tes-login');
    $response->assertSessionHas('success');

    $newUser = User::where('email', 'dosenbaru@pnj.ac.id')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->nip)->toBe('199812122022011001')
        ->and($newUser->role)->toBe('user')
        ->and($newUser->status_aktif)->toBeTrue();
});

test('inactive user cannot login and gets waiting for approval error', function () {
    User::create([
        'nip' => '199901012023011002',
        'username' => 'tes_menunggu',
        'email' => 'menunggu@pnj.ac.id',
        'nama' => 'Pengguna Belum Aktif',
        'password' => Hash::make('password123'),
        'jabatan' => 'Staf Laboratorium',
        'kode_unit' => 'TIK',
        'role' => 'user',
        'status_aktif' => false,
        'no_hp' => '081300001111',
    ]);

    $response = $this->from('/tes-login')->post('/tes-login', [
        'login' => 'menunggu@pnj.ac.id',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/tes-login');
    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('active user can login successfully and redirect to tes-home', function () {
    User::create([
        'nip' => '198501012010011003',
        'username' => 'tes_aktif',
        'email' => 'aktif@pnj.ac.id',
        'nama' => 'User Aktif Terverifikasi',
        'password' => Hash::make('password123'),
        'jabatan' => 'Ketua Jurusan',
        'kode_unit' => 'TIK',
        'role' => 'user',
        'status_aktif' => true,
        'no_hp' => '081300002222',
    ]);

    $response = $this->post('/tes-login', [
        'login' => 'aktif@pnj.ac.id',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('home', ['role' => 'user']));
    $this->assertAuthenticated();
});

test('unauthenticated user cannot access tes-home or tes-api', function () {
    $this->get('/tes-home')->assertRedirect('/tes-login');
    $this->get('/tes-api')->assertRedirect('/tes-login');
});

test('authenticated user can view tes-home and tes-api', function () {
    $user = User::create([
        'nip' => '198801012015011004',
        'username' => 'staff_ppbj_user',
        'email' => 'staff_ppbj@pnj.ac.id',
        'nama' => 'Staff Terdaftar PPBJ',
        'password' => Hash::make('password123'),
        'jabatan' => 'Staff Pengadaan',
        'kode_unit' => 'TIK',
        'role' => 'ppbj',
        'status_aktif' => true,
        'no_hp' => '081300003333',
    ]);

    $homeResponse = $this->actingAs($user)->get('/tes-home');
    $homeResponse->assertStatus(200)
        ->assertSee('Beranda Dashboard - SIM-PBJ Politeknik Negeri Jakarta')
        ->assertSee('Staff Terdaftar PPBJ')
        ->assertSee('PPBJ');

    $apiResponse = $this->actingAs($user)->get('/tes-api');
    $apiResponse->assertStatus(200)
        ->assertSee('Halaman Tes API Backend (17 Entitas)')
        ->assertSee('/api/users')
        ->assertSee('/api/vendors');
});

test('authenticated user can logout', function () {
    $user = User::create([
        'nip' => '198901012016011005',
        'username' => 'user_logout',
        'email' => 'logout@pnj.ac.id',
        'nama' => 'User Logout',
        'password' => Hash::make('password123'),
        'jabatan' => 'Staff Biasa',
        'kode_unit' => 'TIK',
        'role' => 'user',
        'status_aktif' => true,
        'no_hp' => '081300004444',
    ]);

    $response = $this->actingAs($user)->post('/tes-logout');

    $response->assertRedirect('/tes-login');
    $this->assertGuest();
});
