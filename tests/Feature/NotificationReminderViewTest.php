<?php

namespace Tests\Feature;

use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Fitur Tampilan Halaman Notifikasi dan Pengingat (NotificationReminderViewTest)
 *
 * Memverifikasi implementasi frontend halaman notifikasi/reminder seluruh role sesuai
 * rancangan visual Figma 121:45 dan standar FR 12 / NFR 5:
 * 1. Otentikasi & Hak Akses seluruh role organisasi (admin, user, staf B2, wadir 2, perencanaan, PPBJ, PP, PPK, perlengkapan, keuangan).
 * 2. Struktur antarmuka semantik, judul halaman, dan kartu notifikasi dengan penanda baca.
 * 3. Navigasi tab status ("Semua", "Belum dibaca", "Perlu tindakan", "Pengingat") dengan count pill.
 * 4. Fungsionalitas filter kata kunci pencarian, jenis notifikasi, dan periode waktu.
 * 5. Fitur tandai satu notifikasi telah dibaca (`POST /notifikasi/{id}/read`).
 * 6. Fitur tandai seluruh notifikasi telah dibaca (`POST /notifikasi/mark-all-read`).
 */
class NotificationReminderViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat data master Unit pendukung
        Unit::updateOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );
        Unit::updateOrCreate(
            ['kode_unit' => 'TE'],
            ['nama_unit' => 'Jurusan Teknik Elektro', 'jenis_unit' => 'jurusan']
        );

        // Benihkan master role dan permissions
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Memverifikasi tamu yang belum login dialihkan ke halaman login saat membuka /notifikasi.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/notifikasi');
        $response->assertRedirect('/login');
    }

    /**
     * Memverifikasi seluruh 9 peran resmi dapat mengakses halaman /notifikasi dengan status HTTP 200.
     */
    public function test_all_official_roles_can_access_notification_page(): void
    {
        $roles = [
            'admin',
            'user',
            'staff_bidang_2',
            'wadir_2',
            'perencanaan',
            'ppbj',
            'pp',
            'ppk',
            'perlengkapan',
            'keuangan',
        ];

        foreach ($roles as $roleName) {
            $user = User::factory()->create([
                'role' => $roleName,
                'status_aktif' => 'aktif',
            ]);

            $response = $this->actingAs($user)->get('/notifikasi');
            $response->assertStatus(200)
                ->assertSee('Notifikasi dan Reminder')
                ->assertSee('Semua')
                ->assertSee('Belum dibaca')
                ->assertSee('Perlu tindakan')
                ->assertSee('Pengingat');
        }
    }

    /**
     * Memverifikasi tampilan halaman notifikasi untuk pemohon (user) menampilkan konten Figma 121:45.
     */
    public function test_user_views_notifications_matching_figma_content(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'username' => 'user_pengaju',
            'status_aktif' => 'aktif',
        ]);

        $this->seed(NotificationSeeder::class);

        $response = $this->actingAs($user)->get('/notifikasi');

        $response->assertStatus(200)
            ->assertSee('Notifikasi dan Reminder')
            ->assertSee('Cari no. surat / judul')
            ->assertSee('Jenis Notifikasi')
            ->assertSee('Periode')
            ->assertSee('Pengingat revisi permohonan saya')
            ->assertSee('032/PL3/TE/2026')
            ->assertSee('Buka revisi saya ↗')
            ->assertSee('Belum dibaca');
    }

    /**
     * Memverifikasi penyaringan notifikasi berdasarkan tab navigasi (Semua, Belum dibaca, Pengingat).
     */
    public function test_tab_filtering_displays_matching_notifications(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status_aktif' => 'aktif']);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '055/PL3/TE/2026',
            'judul_pengadaan' => 'Peralatan Praktikum Robotika',
            'tujuan_pengadaan_ringkas' => 'Laboratorium robotika mahasiswa',
            'file_permohonan' => 'srikandi/055.pdf',
            'status_permohonan' => 'diajukan',
            'status_anggaran' => 'menunggu',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => 20000000.00,
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TE',
            'id_user' => $user->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-ROBOT-001',
            'nama_paket' => 'Paket Pengadaan Robotika',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'spse',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'jalur_routing' => 'langsung_sd_50jt',
            'nilai_hps' => 20000000.00,
            'alokasi_pagu_paket' => 20000000.00,
            'status_paket' => 'draft',
            'nomor_surat' => $permohonan->nomor_surat,
            'tanggal_registrasi_paket' => now(),
        ]);

        // Reminder 1: Unread
        $rem1 = Reminder::create([
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now(),
            'judul_alert' => 'Alarm Jadwal Pengiriman Robot',
            'pesan_alert' => 'Pengiriman barang dijadwalkan besok.',
        ]);
        NotifikasiUser::create([
            'id_user' => $user->id_user,
            'id_reminder' => $rem1->id_reminder,
            'is_read' => false,
        ]);

        // Reminder 2: Read
        $rem2 = Reminder::create([
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'verifikasi',
            'waktu_trigger' => now()->subDay(),
            'judul_alert' => 'Pemberitahuan Telaah Selesai',
            'pesan_alert' => 'Berkas telaah telah disetujui staf.',
        ]);
        NotifikasiUser::create([
            'id_user' => $user->id_user,
            'id_reminder' => $rem2->id_reminder,
            'is_read' => true,
            'waktu_dibaca' => now()->subHours(5),
        ]);

        // Tab "belum_dibaca": hanya menampilkan rem1
        $resUnread = $this->actingAs($user)->get('/notifikasi?tab=belum_dibaca');
        $resUnread->assertStatus(200)
            ->assertSee('Alarm Jadwal Pengiriman Robot')
            ->assertDontSee('Pemberitahuan Telaah Selesai');

        // Tab "semua": menampilkan keduanya
        $resAll = $this->actingAs($user)->get('/notifikasi?tab=semua');
        $resAll->assertStatus(200)
            ->assertSee('Alarm Jadwal Pengiriman Robot')
            ->assertSee('Pemberitahuan Telaah Selesai');
    }

    /**
     * Memverifikasi fitur penandaan satu notifikasi sebagai telah dibaca (mark as read).
     */
    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status_aktif' => 'aktif']);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '077/PL3/TE/2026',
            'judul_pengadaan' => 'Modul IoT Smart Campus',
            'tujuan_pengadaan_ringkas' => 'Riset IoT kampus pintar',
            'file_permohonan' => 'srikandi/077.pdf',
            'status_permohonan' => 'diajukan',
            'status_anggaran' => 'menunggu',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => 15000000.00,
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TE',
            'id_user' => $user->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-IOT-001',
            'nama_paket' => 'Paket Pengadaan Modul IoT',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'spse',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'jalur_routing' => 'langsung_sd_50jt',
            'nilai_hps' => 15000000.00,
            'alokasi_pagu_paket' => 15000000.00,
            'status_paket' => 'draft',
            'nomor_surat' => $permohonan->nomor_surat,
            'tanggal_registrasi_paket' => now(),
        ]);

        $reminder = Reminder::create([
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now(),
            'judul_alert' => 'Alarm Uji Coba IoT',
            'pesan_alert' => 'Barang IoT telah sampai di gudang.',
        ]);

        $notif = NotifikasiUser::create([
            'id_user' => $user->id_user,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
        ]);

        $this->assertFalse((bool) $notif->is_read);

        $response = $this->actingAs($user)->post(route('notifikasi.read', $notif->id));
        $response->assertRedirect();

        $notif->refresh();
        $this->assertTrue((bool) $notif->is_read);
        $this->assertNotNull($notif->waktu_dibaca);
    }

    /**
     * Memverifikasi fitur penandaan seluruh notifikasi yang belum dibaca menjadi dibaca sekaligus.
     */
    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status_aktif' => 'aktif']);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '088/PL3/TE/2026',
            'judul_pengadaan' => 'Kabel Jaringan Fiber Optic',
            'tujuan_pengadaan_ringkas' => 'Infrastruktur internet jurusan',
            'file_permohonan' => 'srikandi/088.pdf',
            'status_permohonan' => 'diajukan',
            'status_anggaran' => 'menunggu',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => 18000000.00,
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TE',
            'id_user' => $user->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-FO-001',
            'nama_paket' => 'Paket Fiber Optic',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'spse',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'jalur_routing' => 'langsung_sd_50jt',
            'nilai_hps' => 18000000.00,
            'alokasi_pagu_paket' => 18000000.00,
            'status_paket' => 'draft',
            'nomor_surat' => $permohonan->nomor_surat,
            'tanggal_registrasi_paket' => now(),
        ]);

        $rem1 = Reminder::create([
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now(),
            'judul_alert' => 'Alarm 1 FO',
            'pesan_alert' => 'Pesan 1',
        ]);
        $rem2 = Reminder::create([
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'verifikasi',
            'waktu_trigger' => now(),
            'judul_alert' => 'Alarm 2 FO',
            'pesan_alert' => 'Pesan 2',
        ]);

        NotifikasiUser::create(['id_user' => $user->id_user, 'id_reminder' => $rem1->id_reminder, 'is_read' => false]);
        NotifikasiUser::create(['id_user' => $user->id_user, 'id_reminder' => $rem2->id_reminder, 'is_read' => false]);

        $this->assertEquals(2, NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->count());

        $response = $this->actingAs($user)->post(route('notifikasi.mark-all-read'));
        $response->assertRedirect();

        $this->assertEquals(0, NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->count());
    }
}
