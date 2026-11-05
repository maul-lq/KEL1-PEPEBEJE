<?php

namespace Tests\Feature;

use App\Models\NotifikasiUser;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RoleBasedNavigationTest
 *
 * Verifikasi otorisasi tampilan antarmuka (navbar, sidebar, mini profile dropdown, dan tombol aksi per role):
 * 1. Admin layout tidak menampilkan tautan verifikasi teknis (verifikasi.bidang-2, verifikasi.wadir-2, verifikasi.perencanaan, verifikasi.ppk, dll).
 * 2. Admin tidak melihat tombol "Permohonan Baru" pada halaman permohonan.index.
 * 3. Admin melihat tautan administratif: Persetujuan Registrasi, Master Role & Hak Akses, dan Kelola / Hapus Pengajuan.
 * 4. User reguler melihat tautan Permohonan Saya dan Monitoring 8 Siklus, serta tombol "Permohonan Baru", namun tidak melihat Laporan Rekapitulasi atau verifikasi teknis.
 * 5. Dropdown profil pengguna bebas dari tautan AI slop (tidak ada "Dashboard Utama" atau "Monitoring 8 Siklus"), hanya menampilkan "Profil Saya" dan "Logout".
 * 6. Partisi navigasi sidebar peran lain (Staff Bidang 2, Wadir 2, Perencanaan, PPBJ, PP, PPK, Perlengkapan, Keuangan) selaras dengan alur resmi BPMN.
 * 7. Tombol Hapus Pengajuan pada permohonan.show tampil untuk Admin pada semua status, namun untuk User hanya tampil pada status draft/diajukan/ditolak milik sendiri.
 */
class RoleBasedNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected User $staffB2;

    protected User $wadir2;

    protected User $perencanaan;

    protected User $ppbj;

    protected User $pp;

    protected User $ppk;

    protected User $perlengkapan;

    protected User $keuangan;

    protected Unit $unitTIK;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.dev_viewmode' => 'frontend']);
        $this->seed(RolePermissionSeeder::class);

        $this->unitTIK = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $roles = Role::all()->keyBy('nama_role');

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'id_role' => $roles['admin']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Admin Utama SIM-PBJ',
            'username' => 'admin_nav',
            'email' => 'admin_nav@pnj.ac.id',
        ]);

        $this->user = User::factory()->create([
            'role' => 'user',
            'id_role' => $roles['user']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Dosen Pemohon Navigasi',
            'username' => 'user_nav',
            'email' => 'user_nav@pnj.ac.id',
        ]);

        $this->staffB2 = User::factory()->create([
            'role' => 'staff_bidang_2',
            'id_role' => $roles['staff_bidang_2']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Staf Bidang II Navigasi',
            'username' => 'staffb2_nav',
            'email' => 'staffb2_nav@pnj.ac.id',
        ]);

        $this->wadir2 = User::factory()->create([
            'role' => 'wadir_2',
            'id_role' => $roles['wadir_2']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Wakil Direktur II Navigasi',
            'username' => 'wadir2_nav',
            'email' => 'wadir2_nav@pnj.ac.id',
        ]);

        $this->perencanaan = User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $roles['perencanaan']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Perencanaan Pagu Navigasi',
            'username' => 'perencanaan_nav',
            'email' => 'perencanaan_nav@pnj.ac.id',
        ]);

        $this->ppbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $roles['ppbj']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Koordinator PPBJ Navigasi',
            'username' => 'ppbj_nav',
            'email' => 'ppbj_nav@pnj.ac.id',
        ]);

        $this->pp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $roles['pp']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Pejabat Pengadaan Navigasi',
            'username' => 'pp_nav',
            'email' => 'pp_nav@pnj.ac.id',
        ]);

        $this->ppk = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Pejabat Pembuat Komitmen Navigasi',
            'username' => 'ppk_nav',
            'email' => 'ppk_nav@pnj.ac.id',
        ]);

        $this->perlengkapan = User::factory()->create([
            'role' => 'perlengkapan',
            'id_role' => $roles['perlengkapan']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Unit Perlengkapan BMN Navigasi',
            'username' => 'perlengkapan_nav',
            'email' => 'perlengkapan_nav@pnj.ac.id',
        ]);

        $this->keuangan = User::factory()->create([
            'role' => 'keuangan',
            'id_role' => $roles['keuangan']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Bagian Keuangan SP2D Navigasi',
            'username' => 'keuangan_nav',
            'email' => 'keuangan_nav@pnj.ac.id',
        ]);
    }

    /**
     * 1. Verifikasi Admin: Layout TIDAK merender tautan verifikasi teknis dan HANYA merender tautan administratif.
     */
    public function test_admin_layout_does_not_render_technical_verification_links_and_renders_administrative_links(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Admin HARUS melihat menu administratif
        $response->assertSee(route('admin.persetujuan-registrasi.index'));
        $response->assertSee('Persetujuan Registrasi');
        $response->assertSee(route('admin.users.index'));
        $response->assertSee('Manajemen Pengguna');
        $response->assertSee(route('admin.roles.index'));
        $response->assertSee('Master Role');
        $response->assertSee(route('permohonan.index'));
        $response->assertSee('Kelola / Hapus Pengajuan');
        $response->assertSee(route('notifikasi.index'));
        $response->assertSee(route('dashboard'));
        $response->assertSee(route('profile.index'));

        // Admin DILARANG melihat menu teknis pengadaan pada sidebar
        $response->assertDontSee(route('verifikasi.bidang-2'));
        $response->assertDontSee('Telaah Berkas Bidang II');
        $response->assertDontSee(route('verifikasi.wadir-2'));
        $response->assertDontSee('Persetujuan Eksekutif');
        $response->assertDontSee(route('verifikasi.perencanaan'));
        $response->assertDontSee('Verifikasi MAK & Pagu');
        $response->assertDontSee('Verifikasi MAK &amp; Pagu');
        $response->assertDontSee(route('verifikasi.ppk'));
        $response->assertDontSee('Persetujuan Awal Usulan');
        $response->assertDontSee(route('paket.index'));
        $response->assertDontSee('Paket Pengadaan');
        $response->assertDontSee(route('kontrak.index'));
        $response->assertDontSee('Approval Paket Komitmen');
        $response->assertDontSee('Reviu & Negosiasi Harga');
        $response->assertDontSee('Reviu &amp; Negosiasi Harga');
        $response->assertDontSee(route('penerimaan.index'));
        $response->assertDontSee('Penerimaan Barang & BAST');
        $response->assertDontSee('Penerimaan Barang &amp; BAST');
        $response->assertDontSee(route('pembayaran.index'));
        $response->assertDontSee('Tagihan & Pembayaran SP2D');
        $response->assertDontSee('Tagihan &amp; Pembayaran SP2D');
        $response->assertDontSee(route('monitoring.index'));
        $response->assertDontSee(route('laporan.rekapitulasi'));
        $response->assertDontSee('Laporan Rekapitulasi');
    }

    /**
     * 2. Verifikasi Admin: TIDAK merender tombol "Permohonan Baru" pada permohonan.index.
     */
    public function test_admin_does_not_see_permohonan_baru_button_on_permohonan_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('permohonan.index'));
        $response->assertOk();

        // Admin tidak boleh melihat tombol Permohonan Baru
        $response->assertDontSee('Permohonan Baru');
        $response->assertDontSee('openCreateModal()');

        // Buat usulan dengan status disetujui (bukan draft/diajukan)
        $permohonanDisetujui = PermohonanPengadaan::create([
            'nomor_surat' => 'B/DIS-TEST/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Server Cloud Lab',
            'tujuan_pengadaan_ringkas' => 'Untuk server lab riset',
            'estimasi_total_pagu' => 45000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->user->id_user,
            'status_permohonan' => 'disetujui',
            'status_anggaran' => 'tersedia',
        ]);

        // Admin HARUS melihat tombol Hapus Pengajuan pada tracking pengajuan (permohonan.index)
        $responseWithData = $this->get(route('permohonan.index'));
        $responseWithData->assertOk();
        $responseWithData->assertSee('Hapus Pengajuan');
        $responseWithData->assertSee('modal-delete-permohonan');
    }

    /**
     * 3. Verifikasi User reguler: Merender "Permohonan Saya", "Monitoring 8 Siklus", dan tombol "Permohonan Baru",
     * tetapi TIDAK merender Laporan Rekapitulasi atau verifikasi teknis.
     */
    public function test_user_layout_renders_permohonan_saya_and_monitoring_without_technical_verification_or_rekapitulasi(): void
    {
        $this->actingAs($this->user);

        // A. Cek pada Dashboard
        $responseDashboard = $this->get(route('dashboard'));
        $responseDashboard->assertOk();

        // User melihat menu pemohon & Riwayat Laporan terpadu
        $responseDashboard->assertSee(route('permohonan.index'));
        $responseDashboard->assertSee('Permohonan Saya');
        $responseDashboard->assertSee(route('laporan.index'));
        $responseDashboard->assertSee('Riwayat & Laporan');
        $responseDashboard->assertSee(route('dashboard'));
        $responseDashboard->assertSee(route('profile.index'));

        // User TIDAK melihat menu administratif
        $responseDashboard->assertDontSee(route('admin.persetujuan-registrasi.index'));
        $responseDashboard->assertDontSee('Persetujuan Registrasi');
        $responseDashboard->assertDontSee(route('admin.roles.index'));
        $responseDashboard->assertDontSee('Master Role');
        $responseDashboard->assertDontSee('Kelola / Hapus Pengajuan');

        // User TIDAK melihat rute legacy monitoring atau rekapitulasi terpisah
        $responseDashboard->assertDontSee(route('monitoring.index'));
        $responseDashboard->assertDontSee(route('laporan.rekapitulasi'));
        $responseDashboard->assertDontSee('Laporan Rekapitulasi');

        // User TIDAK melihat verifikasi teknis
        $responseDashboard->assertDontSee(route('verifikasi.bidang-2'));
        $responseDashboard->assertDontSee(route('verifikasi.wadir-2'));
        $responseDashboard->assertDontSee(route('verifikasi.perencanaan'));
        $responseDashboard->assertDontSee(route('verifikasi.ppk'));
        $responseDashboard->assertDontSee(route('paket.index'));
        $responseDashboard->assertDontSee(route('penerimaan.index'));

        // B. Cek pada permohonan.index: User HARUS melihat tombol "Permohonan Baru"
        $responsePermohonan = $this->get(route('permohonan.index'));
        $responsePermohonan->assertOk();
        $responsePermohonan->assertSee('Permohonan Baru');
        $responsePermohonan->assertSee('openCreateModal()');
    }

    /**
     * 4. Verifikasi Dropdown Profil Pengguna pada layouts/app.blade.php:
     * Bebas dari tautan AI slop ("Dashboard Utama" dan "Monitoring 8 Siklus"), hanya ada "Profil Saya" dan "Logout".
     */
    public function test_profile_dropdown_does_not_contain_ai_slop_links_and_only_contains_profile_and_logout(): void
    {
        $rolesToTest = [$this->admin, $this->user, $this->wadir2];

        foreach ($rolesToTest as $currentUser) {
            $this->actingAs($currentUser);

            $response = $this->get(route('dashboard'));
            $response->assertOk();

            $content = $response->getContent();

            // Ekstrak bagian popover dropdown profil pengguna (x-show="openProfile")
            $start = strpos($content, 'x-show="openProfile"');
            $this->assertNotFalse($start, 'Dropdown profil pengguna (x-show="openProfile") harus tersedia pada layout.');

            $dropdownContent = substr($content, $start, 4000);

            // Tautan AI slop terlarang pada popover profil
            $this->assertStringNotContainsString('Dashboard Utama', $dropdownContent, 'AI slop "Dashboard Utama" tidak boleh ada di dropdown profil.');
            $this->assertStringNotContainsString('Monitoring 8 Siklus', $dropdownContent, 'AI slop "Monitoring 8 Siklus" tidak boleh ada di dropdown profil.');

            // Tautan yang diizinkan pada dropdown profil: Profil Saya & Logout
            $this->assertStringContainsString('Profil Saya', $dropdownContent);
            $this->assertStringContainsString(route('profile.index'), $dropdownContent);
            $this->assertStringContainsString(route('logout'), $dropdownContent);
        }
    }

    /**
     * 5. Verifikasi partisi menu sidebar untuk seluruh peran BPMN:
     * Menjamin setiap role hanya melihat menu yang relevan dengan kewenangannya.
     */
    public function test_sidebar_navigation_partitioning_across_all_bpmn_roles(): void
    {
        // A. Staff Bidang 2
        $this->actingAs($this->staffB2);
        $respB2 = $this->get(route('dashboard'));
        $respB2->assertSee(route('verifikasi.bidang-2'));
        $respB2->assertSee('Telaah Berkas Bidang II');
        $respB2->assertSee(route('laporan.index'));
        $respB2->assertDontSee(route('monitoring.index'));
        $respB2->assertDontSee(route('laporan.rekapitulasi'));
        $respB2->assertDontSee(route('admin.roles.index'));
        $respB2->assertDontSee(route('verifikasi.wadir-2'));

        // B. Wadir 2
        $this->actingAs($this->wadir2);
        $respW2 = $this->get(route('dashboard'));
        $respW2->assertSee(route('verifikasi.wadir-2'));
        $respW2->assertSee('Persetujuan Eksekutif');
        $respW2->assertSee(route('pembayaran.index'));
        $respW2->assertSee('Tagihan & Pembayaran SP2D');
        $respW2->assertSee(route('laporan.index'));
        $respW2->assertDontSee(route('monitoring.index'));
        $respW2->assertDontSee(route('laporan.rekapitulasi'));
        $respW2->assertDontSee(route('admin.roles.index'));
        $respW2->assertDontSee(route('verifikasi.bidang-2'));

        // C. Perencanaan
        $this->actingAs($this->perencanaan);
        $respPerenc = $this->get(route('dashboard'));
        $respPerenc->assertSee(route('verifikasi.perencanaan'));
        $respPerenc->assertSee('Verifikasi MAK & Pagu');
        $respPerenc->assertSee(route('laporan.index'));
        $respPerenc->assertDontSee(route('monitoring.index'));
        $respPerenc->assertDontSee(route('laporan.rekapitulasi'));
        $respPerenc->assertDontSee(route('admin.roles.index'));
        $respPerenc->assertDontSee(route('verifikasi.wadir-2'));

        // D. PPBJ
        $this->actingAs($this->ppbj);
        $respPpbj = $this->get(route('dashboard'));
        $respPpbj->assertSee(route('paket.index'));
        $respPpbj->assertSee('Paket Pengadaan');
        $respPpbj->assertSee(route('kontrak.index'));
        $respPpbj->assertSee('Kontrak SPK');
        $respPpbj->assertSee(route('pembayaran.index'));
        $respPpbj->assertSee('Perintah Bayar');
        $respPpbj->assertSee(route('laporan.index'));
        $respPpbj->assertDontSee(route('monitoring.index'));
        $respPpbj->assertDontSee(route('laporan.rekapitulasi'));
        $respPpbj->assertDontSee(route('admin.roles.index'));

        // E. PP (Pejabat Pengadaan)
        $this->actingAs($this->pp);
        $respPp = $this->get(route('dashboard'));
        $respPp->assertSee(route('kontrak.index'));
        $respPp->assertSee('Reviu & Negosiasi Harga');
        $respPp->assertSee(route('laporan.index'));
        $respPp->assertDontSee(route('monitoring.index'));
        $respPp->assertDontSee(route('laporan.rekapitulasi'));
        $respPp->assertDontSee(route('paket.index'));
        $respPp->assertDontSee(route('verifikasi.ppk'));

        // F. PPK (Pejabat Pembuat Komitmen)
        $this->actingAs($this->ppk);
        $respPpk = $this->get(route('dashboard'));
        $respPpk->assertSee(route('verifikasi.ppk'));
        $respPpk->assertSee('Persetujuan Awal Usulan');
        $respPpk->assertSee(route('kontrak.index'));
        $respPpk->assertSee('Approval Paket Komitmen');
        $respPpk->assertSee(route('pembayaran.index'));
        $respPpk->assertSee('Memo Bayar Level 1');
        $respPpk->assertSee(route('laporan.index'));
        $respPpk->assertDontSee(route('monitoring.index'));
        $respPpk->assertDontSee(route('laporan.rekapitulasi'));
        $respPpk->assertDontSee(route('admin.roles.index'));

        // G. Perlengkapan
        $this->actingAs($this->perlengkapan);
        $respPerl = $this->get(route('dashboard'));
        $respPerl->assertSee(route('penerimaan.index'));
        $respPerl->assertSee('Penerimaan Barang & BAST');
        $respPerl->assertSee(route('laporan.index'));
        $respPerl->assertDontSee(route('monitoring.index'));
        $respPerl->assertDontSee(route('laporan.rekapitulasi'));
        $respPerl->assertDontSee(route('admin.roles.index'));

        // H. Keuangan
        $this->actingAs($this->keuangan);
        $respKeu = $this->get(route('dashboard'));
        $respKeu->assertSee(route('pembayaran.index'));
        $respKeu->assertSee('Tagihan & Pembayaran SP2D');
        $respKeu->assertSee(route('laporan.index'));
        $respKeu->assertDontSee(route('monitoring.index'));
        $respKeu->assertDontSee(route('laporan.rekapitulasi'));
        $respKeu->assertDontSee(route('admin.roles.index'));
    }

    /**
     * 6. Verifikasi tombol Hapus Pengajuan pada permohonan.show:
     * - Admin melihat tombol "Hapus Pengajuan" pada usulan dalam status apa pun (termasuk status disetujui / menunggu_bidang_2).
     * - User biasa TIDAK melihat tombol hapus/batal pada usulan miliknya yang sudah berstatus lanjut (disetujui).
     */
    public function test_delete_action_visibility_on_permohonan_show_page(): void
    {
        // Buat usulan milik User yang sudah berstatus 'disetujui' (tahapan lanjut)
        $permohonanDisetujui = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SHOW-TEST-ADVANCED/PL3/TIK/2026',
            'perihal' => 'Pengadaan Laboratorium Jaringan Lanjut',
            'tujuan_pengadaan_ringkas' => 'Lab Jaringan',
            'anggaran_diajukan' => 80000000,
            'estimasi_total_pagu' => 80000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->user->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/dummy.pdf',
        ]);

        // A. Admin membuka permohonan.show: HARUS melihat tombol "Hapus Pengajuan"
        $this->actingAs($this->admin);
        $respAdminShow = $this->get(route('permohonan.show', $permohonanDisetujui->nomor_surat));
        $respAdminShow->assertOk();
        $respAdminShow->assertSee('Hapus Pengajuan');
        $respAdminShow->assertSee(route('permohonan.destroy', $permohonanDisetujui->nomor_surat));

        // B. User membuka permohonan.show miliknya: DILARANG melihat tombol hapus/batal karena status sudah disetujui
        $this->actingAs($this->user);
        $respUserShow = $this->get(route('permohonan.show', $permohonanDisetujui->nomor_surat));
        $respUserShow->assertOk();
        $respUserShow->assertDontSee('Hapus Pengajuan');
        $respUserShow->assertDontSee('Batalkan Usulan');
        $respUserShow->assertDontSee(route('permohonan.destroy', $permohonanDisetujui->nomor_surat));

        // C. Buat usulan berstatus 'draft' milik User: User HARUS melihat tombol "Batalkan Usulan"
        $permohonanDraft = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SHOW-TEST-DRAFT/PL3/TIK/2026',
            'perihal' => 'Pengadaan Draft Belum Diajukan',
            'tujuan_pengadaan_ringkas' => 'Pengadaan Draft',
            'anggaran_diajukan' => 10000000,
            'estimasi_total_pagu' => 10000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->user->id_user,
            'status_permohonan' => 'draft',
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/dummy.pdf',
        ]);

        $respUserDraft = $this->get(route('permohonan.show', $permohonanDraft->nomor_surat));
        $respUserDraft->assertOk();
        $respUserDraft->assertSee('Batalkan Usulan');
        $respUserDraft->assertSee(route('permohonan.destroy', $permohonanDraft->nomor_surat));
    }

    /**
     * 7. Verifikasi hierarki tata letak Vertical Navbar di kiri penuh (484:3808) + Top Banner di kanan (484:3391),
     * serta komponen Popup Mini Notifikasi (751:7854) dan Popup Profil Kecil (751:7823).
     */
    public function test_master_layout_vertical_navbar_left_hierarchy_and_figma_popovers(): void
    {
        $reminderUnread = Reminder::create([
            'tipe_reminder' => Reminder::TIPE_VERIFIKASI,
            'waktu_trigger' => now(),
            'is_recurring' => false,
            'judul_alert' => 'Bidang 2 menyetujui MAK',
            'pesan_alert' => 'Berkas pengajuan telah disetujui Bidang 2.',
        ]);

        $reminderRead = Reminder::create([
            'tipe_reminder' => Reminder::TIPE_VERIFIKASI,
            'waktu_trigger' => now()->subHours(2),
            'is_recurring' => false,
            'judul_alert' => 'Perencanaan mengembalikan surat karena anggaran belum ada',
            'pesan_alert' => 'Silakan revisi alokasi anggaran.',
        ]);

        NotifikasiUser::create([
            'id_user' => $this->user->id_user,
            'id_reminder' => $reminderUnread->id_reminder,
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        NotifikasiUser::create([
            'id_user' => $this->user->id_user,
            'id_reminder' => $reminderRead->id_reminder,
            'is_read' => true,
            'is_dismissed' => false,
            'waktu_dibaca' => now()->subHour(),
        ]);

        $this->actingAs($this->user);
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $html = $response->getContent();

        // Vertical Navbar (aside#vertical-navbar) wajib berada sebelum Top Banner (header#top-banner)
        $posSidebar = strpos($html, 'id="vertical-navbar"');
        $posBanner = strpos($html, 'id="top-banner"');
        $posMain = strpos($html, '<main');

        $this->assertNotFalse($posSidebar, 'Vertical navbar (id="vertical-navbar") wajib dirender.');
        $this->assertNotFalse($posBanner, 'Top banner header (id="top-banner") wajib dirender.');
        $this->assertNotFalse($posMain, 'Elemen <main> konten utama wajib dirender.');
        $this->assertLessThan($posBanner, $posSidebar, 'Vertical navbar wajib berada di kolom kiri sebelum kolom kanan Top Banner.');
        $this->assertLessThan($posMain, $posBanner, 'Top banner wajib berada di atas <main> dalam kolom kanan.');

        // Verifikasi Popup Mini Notifikasi (Figma 751:7854)
        $response->assertSee('data-node-id="751:7854"', false);
        $response->assertSee('Belum Dibaca');
        $response->assertSee('Lihat Semua');
        $response->assertSee('Bidang 2 menyetujui MAK');
        $response->assertSee('Perencanaan mengembalikan surat karena anggaran belum ada');

        // Verifikasi Popup Profil Kecil (Figma 751:7823)
        $response->assertSee('data-node-id="751:7823"', false);
        $response->assertSee($this->user->nama);
        $response->assertSee($this->unitTIK->nama_unit);
        $response->assertSee('Profil Saya');
        $response->assertSee('Logout');
    }
}
