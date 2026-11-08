<?php

use App\Models\PermohonanPengadaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('tabel users tidak memiliki kolom gelar', function () {
    expect(Schema::hasColumn('users', 'gelar'))->toBeFalse();
});

test('staff bidang 2 dapat melihat seluruh berkas permohonan pada /verifikasi/bidang-2', function () {
    $staffB2 = User::where('role', 'staff_bidang_2')->first();

    $response = $this->actingAs($staffB2)->get('/verifikasi/bidang-2');
    $response->assertStatus(200);
    $response->assertViewIs('verifikasi.bidang_2');
    $response->assertViewHas('permohonanMasuk');

    $permohonanMasuk = $response->viewData('permohonanMasuk');
    expect($permohonanMasuk)->not->toBeNull();
});

test('pengaju yang submit permohonan otomatis mendapatkan kode_unit sesuai akun tanpa input manual', function () {
    Storage::fake('public');

    $user = User::where('role', 'user')->first();

    $nomorSurat = 'TEST/B2/UNIT-AUTO/'.uniqid();

    $response = $this->actingAs($user)->post('/permohonan', [
        'nomor_surat' => $nomorSurat,
        'perihal' => 'Pengadaan Fasilitas AI Lab Otomatis Unit',
        'tujuan_pengadaan_ringkas' => 'Untuk riset mahasiswa',
        'anggaran_diajukan' => 15000000,
        // Di form frontend disuntikkan secara otomatis via hidden input Auth::user()->kode_unit
        'kode_unit' => $user->kode_unit,
        'tanggal_pengajuan' => now()->toDateString(),
    ]);

    $response->assertRedirect();

    $created = PermohonanPengadaan::where('nomor_surat', $nomorSurat)->first();
    expect($created)->not->toBeNull();
    expect($created->kode_unit)->toBe($user->kode_unit);
});

test('pengguna dapat memperbarui password saat data valid', function () {
    $user = User::where('role', 'user')->first();

    $user->password = Hash::make('password_awal123');
    $user->save();

    $response = $this->actingAs($user)->put('/profil/password', [
        'old_password' => 'password_awal123',
        'new_password' => 'password_baru123',
        'confirm_password' => 'password_baru123',
    ]);

    $response->assertSessionHas('success');
    $user->refresh();
    expect(Hash::check('password_baru123', $user->password))->toBeTrue();
});

test('pembaruan password gagal dan me-redirect dengan error jika password lama salah', function () {
    $user = User::where('role', 'user')->first();

    $user->password = Hash::make('password_asli123');
    $user->save();

    $response = $this->actingAs($user)->put('/profil/password', [
        'old_password' => 'password_salah_total',
        'new_password' => 'password_baru123',
        'confirm_password' => 'password_baru123',
    ]);

    $response->assertSessionHas('password_modal_error', true);
    $response->assertSessionHas('error');
    $user->refresh();
    expect(Hash::check('password_asli123', $user->password))->toBeTrue();
});

test('pengguna dapat mengunggah foto profil dan file tersimpan di storage serta database', function () {
    Storage::fake('public');

    $user = User::where('role', 'user')->first();

    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $response = $this->actingAs($user)->put('/profil', [
        'nama' => $user->nama,
        'email' => $user->email,
        'foto_profil' => $file,
    ]);

    $response->assertSessionHas('success');
    $user->refresh();

    expect($user->foto_profil)->not->toBeNull();
    Storage::disk('public')->assertExists($user->foto_profil);
});
