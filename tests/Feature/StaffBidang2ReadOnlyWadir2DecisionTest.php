<?php

use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->unit = Unit::firstOrCreate(
        ['kode_unit' => 'TIK'],
        ['nama_unit' => 'Teknologi Informasi & Komputer', 'jenis_unit' => 'jurusan']
    );

    $this->userPemohon = User::factory()->create([
        'role' => 'user',
        'kode_unit' => $this->unit->kode_unit,
        'status_aktif' => true,
    ]);

    $this->staffB2 = User::factory()->create([
        'role' => 'staff_bidang_2',
        'kode_unit' => $this->unit->kode_unit,
        'status_aktif' => true,
    ]);

    $this->wadir2 = User::factory()->create([
        'role' => 'wadir_2',
        'kode_unit' => $this->unit->kode_unit,
        'status_aktif' => true,
    ]);
});

test('staff_bidang_2 role has read-only permissions in rbac and excludes teruskan permission', function () {
    $role = Role::where('nama_role', 'staff_bidang_2')->with('permissions')->firstOrFail();
    $permNames = $role->permissions->pluck('nama_permission')->toArray();

    expect($permNames)->toContain('verifikasi.bidang_2_view')
        ->toContain('permohonan.view_all')
        ->toContain('monitoring.view')
        ->toContain('dokumen.unduh_pdf')
        ->not->toContain('verifikasi.bidang_2_teruskan');
});

test('staff_bidang_2 can access verifikasi bidang-2 in read-only mode without forward form', function () {
    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'B/READONLY-B2/TIK/2026',
        'perihal' => 'Pengadaan Server Riset Komputasi',
        'anggaran_diajukan' => 120000000,
        'estimasi_total_pagu' => 120000000,
        'kode_unit' => $this->unit->kode_unit,
        'id_user' => $this->userPemohon->id_user,
        'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        'status' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        'tanggal_pengajuan' => now(),
    ]);

    $response = $this->actingAs($this->staffB2)->get(route('verifikasi.bidang-2'));

    $response->assertOk();
    $response->assertSee('B/READONLY-B2/TIK/2026');
    $response->assertSee('Read-Only');
    $response->assertDontSee('Kirim Memo Cek MAK ke Bagian Perencanaan');
});

test('wadir_2 can directly inspect documents and decide acc on newly submitted proposals without staff_bidang_2 forwarding', function () {
    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'B/WADIR2-DIRECT-ACC/TIK/2026',
        'perihal' => 'Pengadaan Laboratorium AI dan Robotika',
        'anggaran_diajukan' => 180000000,
        'estimasi_total_pagu' => 180000000,
        'kode_unit' => $this->unit->kode_unit,
        'id_user' => $this->userPemohon->id_user,
        'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        'status' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        'tanggal_pengajuan' => now(),
    ]);

    VerifikasiParalel::create([
        'id_user' => $this->wadir2->id_user,
        'nomor_surat' => $permohonan->nomor_surat,
        'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
        'status_keputusan' => 'menunggu',
    ]);

    // Wadir 2 langsung dapat melihat proposal di antrean
    $queueResponse = $this->actingAs($this->wadir2)->get(route('verifikasi.wadir-2'));
    $queueResponse->assertOk();
    $queueResponse->assertSee('B/WADIR2-DIRECT-ACC/TIK/2026');

    // Wadir 2 memberikan keputusan ACC (disetujui)
    $decisionResponse = $this->actingAs($this->wadir2)->post(route('verifikasi.wadir-2.keputusan'), [
        'nomor_surat' => $permohonan->nomor_surat,
        'keputusan' => 'disetujui',
    ]);

    $decisionResponse->assertRedirect(route('verifikasi.wadir-2'));

    $verif = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
        ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
        ->first();

    expect($verif)->not->toBeNull();
    expect($verif->status_keputusan)->toBe('disetujui');
});
