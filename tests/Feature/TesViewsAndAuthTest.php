<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('login view renders successfully with simple HTML', function () {
    $response = $this->get('/tes-login');

    $response->assertStatus(200)
        ->assertSee('Halaman Tes Login Sistem PBJ')
        ->assertSee('tes_pengaju@kampus.ac.id')
        ->assertSee('tes_password123');
});

test('registrasi view renders successfully with super admin approval notice', function () {
    $response = $this->get('/tes-registrasi');

    $response->assertStatus(200)
        ->assertSee('Halaman Tes Registrasi Pengguna Baru')
        ->assertSee('persetujuan Super Admin');
});

test('user registration saves user as inactive pending super admin approval', function () {
    $registrationData = [
        'nip' => 'tes_199812122022011001',
        'nama' => 'tes_Dosen Baru TIK',
        'email' => 'tes_dosenbaru@kampus.ac.id',
        'password' => 'tes_password123',
        'password_confirmation' => 'tes_password123',
        'jabatan' => 'Dosen Pengajar',
        'unit_kerja' => 'Jurusan Teknik Informatika',
        'no_hp' => '081299887766',
        'no_telp_kantor' => '0217279999',
    ];

    $response = $this->post('/tes-registrasi', $registrationData);

    $response->assertRedirect('/tes-login');
    $response->assertSessionHas('success');

    $newUser = User::where('email', 'tes_dosenbaru@kampus.ac.id')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->nip)->toBe('tes_199812122022011001')
        ->and($newUser->role)->toBe(User::ROLE_USER_PENGAJU)
        ->and($newUser->status_aktif)->toBeFalse();
});

test('inactive user cannot login and gets waiting for approval error', function () {
    User::create([
        'nip' => 'tes_199901012023011002',
        'email' => 'tes_menunggu@kampus.ac.id',
        'nama' => 'tes_Pengguna Belum Aktif',
        'password' => Hash::make('tes_password123'),
        'jabatan' => 'Staf Laboratorium',
        'unit_kerja' => 'Lab Komputer',
        'role' => User::ROLE_USER_PENGAJU,
        'status_aktif' => false,
        'no_hp' => '081300001111',
    ]);

    $response = $this->from('/tes-login')->post('/tes-login', [
        'login' => 'tes_menunggu@kampus.ac.id',
        'password' => 'tes_password123',
    ]);

    $response->assertRedirect('/tes-login');
    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('active user can login successfully and redirect to tes-home', function () {
    User::create([
        'nip' => 'tes_198501012010011003',
        'email' => 'tes_aktif@kampus.ac.id',
        'nama' => 'tes_User Aktif Terverifikasi',
        'password' => Hash::make('tes_password123'),
        'jabatan' => 'Ketua Jurusan',
        'unit_kerja' => 'Jurusan Elektro',
        'role' => User::ROLE_USER_PENGAJU,
        'status_aktif' => true,
        'no_hp' => '081300002222',
    ]);

    $response = $this->post('/tes-login', [
        'login' => 'tes_aktif@kampus.ac.id',
        'password' => 'tes_password123',
    ]);

    $response->assertRedirect('/tes-home');
    $this->assertAuthenticated();
});

test('unauthenticated user cannot access tes-home or tes-api', function () {
    $this->get('/tes-home')->assertRedirect('/tes-login');
    $this->get('/tes-api')->assertRedirect('/tes-login');
});

test('authenticated user can view tes-home and tes-api', function () {
    $user = User::create([
        'nip' => 'tes_198801012015011004',
        'email' => 'tes_staff@kampus.ac.id',
        'nama' => 'tes_Staff Terdaftar',
        'password' => Hash::make('tes_password123'),
        'jabatan' => 'Staff Pengadaan',
        'unit_kerja' => 'ULP',
        'role' => User::ROLE_PPBJ,
        'status_aktif' => true,
        'no_hp' => '081300003333',
    ]);

    $homeResponse = $this->actingAs($user)->get('/tes-home');
    $homeResponse->assertStatus(200)
        ->assertSee('Halaman Tes Beranda (Home) Sistem PBJ')
        ->assertSee('tes_Staff Terdaftar')
        ->assertSee('tes_staff@kampus.ac.id')
        ->assertSee('ppbj');

    $apiResponse = $this->actingAs($user)->get('/tes-api');
    $apiResponse->assertStatus(200)
        ->assertSee('Halaman Tes API Backend (17 Entitas)')
        ->assertSee('/api/users')
        ->assertSee('/api/vendors');
});

test('authenticated user can logout', function () {
    $user = User::create([
        'nip' => 'tes_198901012016011005',
        'email' => 'tes_logout@kampus.ac.id',
        'nama' => 'tes_User Logout',
        'password' => Hash::make('tes_password123'),
        'jabatan' => 'Staff Biasa',
        'unit_kerja' => 'Unit Umum',
        'role' => User::ROLE_USER_PENGAJU,
        'status_aktif' => true,
        'no_hp' => '081300004444',
    ]);

    $response = $this->actingAs($user)->post('/tes-logout');

    $response->assertRedirect('/tes-login');
    $this->assertGuest();
});
