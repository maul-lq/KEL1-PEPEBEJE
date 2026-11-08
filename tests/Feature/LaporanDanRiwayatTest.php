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
     * Memverifikasi bahwa data riwayat dan laporan terisolasi dengan ketat antar user pemohon.
     */
    public function test_riwayat_dan_laporan_terisolasi_antar_user_pemohon(): void
    {
        // User A punya permohonan
        AuditLog::catat(
            $this->userPemohon->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            '001/PL3.TIK/PG/2026',
            null,
            'menunggu_bidang_2',
            'Pengajuan usulan server AI Lab Komputasi'
        );

        // User B baru yang belum punya permohonan
        $userB = User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        // User A melihat usulannya
        $respA = $this->actingAs($this->userPemohon)->get(route('laporan.index', ['tab' => 'riwayat']));
        $respA->assertStatus(200);
        $respA->assertSee('001/PL3.TIK/PG/2026');

        // User B TIDAK melihat usulan User A
        $respB = $this->actingAs($userB)->get(route('laporan.index', ['tab' => 'riwayat']));
        $respB->assertStatus(200);
        $respB->assertDontSee('001/PL3.TIK/PG/2026');
    }

    /**
     * Memverifikasi bahwa role 'pp' menerima laporan khusus penugasan Pejabat Pengadaan.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_pp(): void
    {
        $userPp = User::where('role', 'pp')->first() ?? User::factory()->create([
            'role' => 'pp',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userPp)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Kinerja Pejabat Pengadaan');
        $response->assertSee('Rekapitulasi Paket Pengadaan Langsung (Penugasan PP)');
        $response->assertSee('Total Paket Penugasan PP');
    }

    /**
     * Memverifikasi bahwa role 'ppk' menerima laporan khusus paket komitmen PPK.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_ppk(): void
    {
        $userPpk = User::where('role', 'ppk')->first() ?? User::factory()->create([
            'role' => 'ppk',
            'kode_unit' => $this->unitTik->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userPpk)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Komitmen &amp; Paket Pengadaan', false);
        $response->assertSee('Total Paket Komitmen PPK');
    }

    /**
     * Memverifikasi bahwa role 'perlengkapan' menerima laporan BAST & penerimaan barang.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_perlengkapan(): void
    {
        $userPerlengkapan = User::where('role', 'perlengkapan')->first() ?? User::factory()->create([
            'role' => 'perlengkapan',
            'kode_unit' => 'LKP',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userPerlengkapan)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Penerimaan Barang &amp; Aset', false);
        $response->assertSee('Rekapitulasi Penerimaan Barang &amp; Berita Acara Serah Terima (BAST)', false);
        $response->assertSee('Total BAST Diterbitkan');
    }

    /**
     * Memverifikasi bahwa role 'keuangan' menerima laporan pembayaran & pencairan SP2D.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_keuangan(): void
    {
        $userKeuangan = User::where('role', 'keuangan')->first() ?? User::factory()->create([
            'role' => 'keuangan',
            'kode_unit' => 'KEU',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userKeuangan)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pembayaran Tagihan &amp; Realisasi SP2D', false);
        $response->assertSee('SP2D Siap Dicairkan');
    }

    /**
     * Memverifikasi bahwa role 'staff_bidang_2' menerima laporan telaah berkas permohonan.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_staff_bidang_2(): void
    {
        $userStaffB2 = User::where('role', 'staff_bidang_2')->first() ?? User::factory()->create([
            'role' => 'staff_bidang_2',
            'kode_unit' => 'B2',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userStaffB2)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Penerimaan &amp; Telaah Berkas', false);
        $response->assertSee('Total Berkas Usulan Masuk');
    }

    /**
     * Memverifikasi bahwa role 'perencanaan' menerima laporan alokasi MAK DIPA.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_perencanaan(): void
    {
        $userPerencanaan = User::where('role', 'perencanaan')->first() ?? User::factory()->create([
            'role' => 'perencanaan',
            'kode_unit' => 'B2',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userPerencanaan)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Alokasi Anggaran MAK DIPA');
        $response->assertSee('Total Pagu Anggaran DIPA');
    }

    /**
     * Memverifikasi bahwa role 'ppbj' menerima laporan pengelolaan paket & jalur eksekusi.
     */
    public function test_tab_laporan_menampilkan_data_spesifik_role_ppbj(): void
    {
        $userPpbj = User::where('role', 'ppbj')->first() ?? User::factory()->create([
            'role' => 'ppbj',
            'kode_unit' => 'B2',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $response = $this->actingAs($userPpbj)->get(route('laporan.index', ['tab' => 'laporan']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pengelolaan Paket Pengadaan');
        $response->assertSee('Total Paket Terdaftar PPBJ');
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
