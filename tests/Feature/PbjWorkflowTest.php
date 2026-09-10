<?php

use App\Models\Pengadaan;
use App\Models\User;
use App\Services\PengadaanRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated users are redirected to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('quick role switch authenticates user and redirects to dashboard', function () {
    User::create([
        'name' => 'Staff Pengadaan',
        'email' => 'ppbj@kampus.ac.id',
        'password' => bcrypt('password'),
        'role' => User::ROLE_PPBJ,
        'nip' => '198403122008121002',
        'unit_kerja' => 'Subbag Pengadaan / PBJ',
    ]);

    $response = $this->get(route('quick-login', 'ppbj'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    expect(auth()->user()->role)->toBe(User::ROLE_PPBJ);
});

test('budget threshold logic correctly determines procurement jalur', function () {
    // Under 50M: Pengadaan Langsung
    expect(Pengadaan::determineJalur(35000000))->toBe(Pengadaan::JALUR_LANGSUNG);

    // 50M to 200M: Pejabat Pengadaan
    expect(Pengadaan::determineJalur(125000000))->toBe(Pengadaan::JALUR_PP);

    // Over 200M: PPK
    expect(Pengadaan::determineJalur(350000000))->toBe(Pengadaan::JALUR_PPK);
});

test('routing service dispatches correct jalur based on amount', function () {
    $user = User::create([
        'name' => 'Test User TI',
        'email' => 'user_ti@kampus.ac.id',
        'password' => bcrypt('password'),
        'role' => User::ROLE_USER,
        'nip' => '198501012010121001',
        'unit_kerja' => 'Teknik Informatika',
    ]);

    $pengadaan = Pengadaan::create([
        'nomor_pengadaan' => 'PBJ-TEST-001',
        'nama_pengadaan' => 'Pengadaan Laptop Test Unit',
        'jenis_pengadaan' => 'Barang',
        'jenis_belanja' => 'RM',
        'estimasi_anggaran' => 120000000,
        'user_id' => $user->id,
        'status' => Pengadaan::STATUS_DRAFT,
    ]);

    PengadaanRoutingService::assignJalur($pengadaan);

    expect($pengadaan->jalur_pengadaan)->toBe(Pengadaan::JALUR_PP);
});

test('user can access procurement creation page and view monitoring board', function () {
    $user = User::create([
        'name' => 'Pemohon Unit',
        'email' => 'pemohon@kampus.ac.id',
        'password' => bcrypt('password'),
        'role' => User::ROLE_USER,
        'nip' => '198901012015041001',
        'unit_kerja' => 'Jurusan Rekayasa Elektro',
    ]);

    $this->actingAs($user)->get(route('pengadaan.create'))
        ->assertStatus(200)
        ->assertSee('Formulir Pengajuan Permohonan Pengadaan');

    $this->actingAs($user)->get(route('monitoring'))
        ->assertStatus(200)
        ->assertSee('Dashboard Monitoring Status Pengadaan');
});

test('vendors and reminders pages are accessible by authorized staff', function () {
    $ppbj = User::create([
        'name' => 'PPBJ Staff',
        'email' => 'ppbj_staff@kampus.ac.id',
        'password' => bcrypt('password'),
        'role' => User::ROLE_PPBJ,
        'nip' => '198601012009121001',
        'unit_kerja' => 'Subbag PBJ',
    ]);

    $this->actingAs($ppbj)->get(route('vendor.index'))
        ->assertStatus(200)
        ->assertSee('Data Rekanan');

    $this->actingAs($ppbj)->get(route('reminders.index'))
        ->assertStatus(200)
        ->assertSee('Daftar Pengingat');
});

test('procurement print sheet is rendered with complete data', function () {
    $user = User::create([
        'name' => 'Pemohon Cetak',
        'email' => 'cetak@kampus.ac.id',
        'password' => bcrypt('password'),
        'role' => User::ROLE_USER,
        'nip' => '198701012012041001',
        'unit_kerja' => 'Jurusan Manajemen Informatika',
    ]);

    $pengadaan = Pengadaan::create([
        'nomor_pengadaan' => 'PBJ-PRINT-001',
        'nama_pengadaan' => 'Pengadaan Server Kampus',
        'jenis_pengadaan' => 'Barang',
        'jenis_belanja' => 'RM',
        'estimasi_anggaran' => 250000000,
        'user_id' => $user->id,
        'status' => Pengadaan::STATUS_DIAJUKAN,
    ]);

    $this->actingAs($user)->get(route('pengadaan.cetak', $pengadaan))
        ->assertStatus(200)
        ->assertSee('PBJ-PRINT-001')
        ->assertSee('Pengadaan Server Kampus');
});
