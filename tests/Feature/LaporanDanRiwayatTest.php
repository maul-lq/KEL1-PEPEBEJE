<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Suite: Modul Riwayat & Laporan Terpadu (/laporan)
 *
 * Menguji:
 * 1. Pengalihan rute /monitoring lama ke rute /laporan terpadu.
 * 2. Aksesibilitas halaman /laporan untuk seluruh peran (User, Admin, Wadir 2, Perencanaan, dsb.).
 * 3. Tab Riwayat Aktivitas (Figma 515:863): Jejak audit & link tracking permohonan.
 * 4. Tab Laporan Mode User (Figma 526:3083): Ringkasan spesifik unit kerja pemohon.
 * 5. Tab Laporan Mode Admin: Lingkup manajemen sistem & pengguna.
 * 6. Tab Laporan Mode Eksekutif: Distribusi anggaran DIPA seluruh jurusan.
 * 7. Endpoint unduh laporan ringkasan dan detail PDF (NFR 12: < 3 detik).
 */
class LaporanDanRiwayatTest extends TestCase
{
    use RefreshDatabase;

    protected User $userPemohon;

    protected User $userAdmin;

    protected User $userWadir2;

    protected Unit $unitTik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->unitTik = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->userPemohon = User::where('role', 'user')->first() ?? User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userAdmin = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userWadir2 = User::where('role', 'wadir_2')->first() ?? User::factory()->create([
            'role' => 'wadir_2',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);
    }

    /**
     * Memverifikasi bahwa rute monitoring lama dialihkan secara otomatis ke /laporan.
     */
    public function test_rute_monitoring_dialihkan_ke_laporan(): void
    {
        $response = $this->actingAs($this->userPemohon)->get(route('monitoring.index'));
        $response->assertRedirect(route('laporan.index'));
    }

    /**
     * Memverifikasi bahwa halaman /laporan dapat diakses dengan sukses (HTTP 200) oleh seluruh peran.
     */
    public function test_halaman_laporan_dapat_diakses_oleh_semua_role(): void
    {
        $respPemohon = $this->actingAs($this->userPemohon)->get(route('laporan.index'));
        $respPemohon->assertStatus(200);
        $respPemohon->assertSee('Riwayat dan Laporan');
        $respPemohon->assertSee('Riwayat Aktivitas');
        $respPemohon->assertSee('Laporan');

        $respAdmin = $this->actingAs($this->userAdmin)->get(route('laporan.index'));
        $respAdmin->assertStatus(200);
        $respAdmin->assertSee('Riwayat dan Laporan');

        $respWadir2 = $this->actingAs($this->userWadir2)->get(route('laporan.index'));
        $respWadir2->assertStatus(200);
        $respWadir2->assertSee('Riwayat dan Laporan');
    }

    /**
     * Memverifikasi bahwa Tab Riwayat Aktivitas memuat rekaman jejak audit dan tautan ke tracking permohonan.
     */
    public function test_tab_riwayat_aktivitas_menampilkan_data_dan_tautan_permohonan(): void
    {
        AuditLog::catat(
            $this->userPemohon->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            '001/PL3.TIK/PG/2026',
            null,
            'menunggu_bidang_2',
            'Pengajuan usulan server AI Lab Komputasi'
        );

        $response = $this->actingAs($this->userPemohon)->get(route('laporan.index', ['tab' => 'riwayat']));
        $response->assertStatus(200);
        $response->assertSee('001/PL3.TIK/PG/2026');
        $response->assertSee('Pencarian');
        $response->assertSee('Jenis Aktivitas');
        $response->assertSee('Filter Status');
    }

    /**
     * Memverifikasi bahwa role 'user' (Pengaju) menerima tampilan laporan khusus unit kerjanya.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_user(): void
    {
        $response = $this->actingAs($this->userPemohon)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pengadaan Unit Kerja');
        $response->assertSee('Daftar Rekapitulasi Usulan Pengadaan Unit');
        $response->assertSee('Total Estimasi Usulan Unit');
    }

    /**
     * Memverifikasi bahwa role 'admin' menerima tampilan laporan manajemen pengguna dan sistem.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_admin(): void
    {
        $response = $this->actingAs($this->userAdmin)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Manajemen Pengguna &amp; Sistem', false);
        $response->assertSee('Distribusi dan Status Pengguna Institusi PNJ');
        $response->assertSee('Total Pengguna Terdaftar');
    }

    /**
     * Memverifikasi bahwa role eksekutif (Wadir 2) menerima tabel Distribusi Anggaran DIPA per Jurusan.
     */
    public function test_tab_laporan_menampilkan_data_distribusi_dipa_institusi(): void
    {
        $response = $this->actingAs($this->userWadir2)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Keuangan &amp; Realisasi DIPA', false);
        $response->assertSee('Distribusi Anggaran per Jurusan dan Unit Kerja');
        $response->assertSee('Total Pagu Anggaran DIPA');
    }

    /**
     * Memverifikasi bahwa endpoint unduh laporan ringkasan dan detail PDF merespons dengan berkas PDF resmi.
     */
    public function test_unduh_laporan_ringkasan_dan_detail_pdf_berhasil(): void
    {
        $respRingkasan = $this->actingAs($this->userWadir2)->get(route('laporan.unduh.ringkasan', ['tahun' => 2026]));
        $respRingkasan->assertStatus(200);
        $respRingkasan->assertHeader('Content-Type', 'application/pdf');

        $respDetail = $this->actingAs($this->userWadir2)->get(route('laporan.unduh.detail', ['tahun' => 2026]));
        $respDetail->assertStatus(200);
        $respDetail->assertHeader('Content-Type', 'application/pdf');

        // Kompatibilitas rute legacy
        $respLegacy = $this->actingAs($this->userWadir2)->get(route('laporan.rekapitulasi.pdf', ['tahun' => 2026]));
        $respLegacy->assertStatus(200);
        $respLegacy->assertHeader('Content-Type', 'application/pdf');
    }
}
