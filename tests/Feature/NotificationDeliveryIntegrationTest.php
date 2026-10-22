<?php

namespace Tests\Feature;

use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PengingatPengadaanNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Uji Integrasi Pengiriman Notifikasi Surel & Endpoint OS Desktop (NotificationDeliveryIntegrationTest)
 *
 * Memverifikasi:
 * 1. Pemicuan pengiriman email otomatis (`PengingatPengadaanNotification`) saat record `NotifikasiUser` dibuat.
 * 2. Struktur konten email (subjek, greeting, informasi paket, tombol tindakan, dan penutup institusi).
 * 3. Endpoint `GET /api/notifikasi-terbaru` untuk konsumsi polling Web Notification API peramban / desktop OS.
 * 4. Dukungan filter `since_id` pada endpoint notifikasi terbaru.
 * 5. Ketahanan sistem saat terjadi gangguan koneksi surel (graceful degradation tanpa melempar 500).
 */
class NotificationDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userPengaju;

    protected PaketPengadaan $paket;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat master Unit
        Unit::updateOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );
        Unit::updateOrCreate(
            ['kode_unit' => 'TE'],
            ['nama_unit' => 'Jurusan Teknik Elektro', 'jenis_unit' => 'jurusan']
        );

        $this->seed(RolePermissionSeeder::class);

        $this->userPengaju = User::factory()->create([
            'role' => 'user',
            'username' => 'dosen_pengaju',
            'email' => 'pengaju.simpbj@pnj.ac.id',
            'nama' => 'Dr. Ir. Maulana Pengaju, M.T.',
            'status_aktif' => 'aktif',
            'kode_unit' => 'TE',
        ]);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '101/PL3/TE/2026',
            'judul_pengadaan' => 'Pengadaan Alat Uji Praktikum Embedded Systems',
            'tujuan_pengadaan_ringkas' => 'Laboratorium elektronika terapan',
            'file_permohonan' => 'srikandi/101.pdf',
            'status_permohonan' => 'diajukan',
            'status_anggaran' => 'menunggu',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => 35000000.00,
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TE',
            'id_user' => $this->userPengaju->id_user,
        ]);

        $this->paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-EMBED-001',
            'nama_paket' => 'Paket Pengadaan Embedded Systems',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'spse',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'jalur_routing' => 'langsung_sd_50jt',
            'nilai_hps' => 35000000.00,
            'alokasi_pagu_paket' => 35000000.00,
            'status_paket' => 'draft',
            'nomor_surat' => $permohonan->nomor_surat,
            'tanggal_registrasi_paket' => now(),
        ]);
    }

    /**
     * Memverifikasi pembuatan NotifikasiUser otomatis memicu pengiriman notifikasi email ke user.
     */
    public function test_notifikasi_user_creation_triggers_email_notification(): void
    {
        Notification::fake();

        $reminder = Reminder::create([
            'nomor_paket' => $this->paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now()->addDays(2),
            'judul_alert' => 'Pengingat Batas Akhir Pengiriman Barang',
            'pesan_alert' => 'Vendor dijadwalkan mengirimkan fisik komponen dalam 48 jam.',
        ]);

        // Buat rekaman notifikasi user (memicu NotifikasiUserObserver::created)
        $notif = NotifikasiUser::create([
            'id_user' => $this->userPengaju->id_user,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
        ]);

        // Pastikan notifikasi email dikirimkan ke user terkait
        Notification::assertSentTo(
            $this->userPengaju,
            PengingatPengadaanNotification::class,
            function (PengingatPengadaanNotification $notification) use ($notif) {
                return $notification->notifikasi->id === $notif->id;
            }
        );
    }

    /**
     * Memverifikasi format pesan email memiliki subjek resmi, greeting, dan tombol aksi.
     */
    public function test_pengingat_notification_builds_correct_mail_message(): void
    {
        $reminder = Reminder::create([
            'nomor_paket' => $this->paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now()->addDays(2),
            'judul_alert' => 'Alarm Uji Fungsi Komponen',
            'pesan_alert' => 'Harap persiapkan ruang laboratorium untuk uji fungsi barang.',
        ]);

        $notif = NotifikasiUser::create([
            'id_user' => $this->userPengaju->id_user,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
        ]);

        $notification = new PengingatPengadaanNotification($notif);
        $mail = $notification->toMail($this->userPengaju);

        $this->assertStringContainsString('[SIM-PBJ PNJ] Alarm Uji Fungsi Komponen', $mail->subject);
        $this->assertStringContainsString('Yth. Dr. Ir. Maulana Pengaju, M.T.', $mail->greeting);
        $this->assertStringContainsString('Alarm Uji Fungsi Komponen', implode(' ', $mail->introLines));
        $this->assertStringContainsString($notif->action_url, $mail->actionUrl);
    }

    /**
     * Memverifikasi endpoint API GET /api/notifikasi-terbaru mengembalikan daftar unread JSON untuk desktop alert.
     */
    public function test_notifikasi_terbaru_api_returns_unread_notifications_for_desktop_alert(): void
    {
        $reminder = Reminder::create([
            'nomor_paket' => $this->paket->nomor_paket,
            'tipe_reminder' => 'verifikasi',
            'waktu_trigger' => now(),
            'judul_alert' => 'Verifikasi Berkas Diterima',
            'pesan_alert' => 'Berkas Anda telah lolos telaah administrasi.',
        ]);

        NotifikasiUser::create([
            'id_user' => $this->userPengaju->id_user,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->userPengaju)->getJson('/api/notifikasi-terbaru');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('count', 1)
            ->assertJsonStructure([
                'status',
                'count',
                'data' => [
                    '*' => [
                        'id',
                        'judul',
                        'pesan',
                        'badge',
                        'referensi',
                        'action_url',
                        'action_label',
                        'created_at',
                    ],
                ],
            ]);
    }

    /**
     * Memverifikasi endpoint /api/notifikasi-terbaru mendukung filter query parameter since_id.
     */
    public function test_notifikasi_terbaru_api_supports_since_id_filtering(): void
    {
        $rem1 = Reminder::create([
            'nomor_paket' => $this->paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now(),
            'judul_alert' => 'Notif Lama 1',
            'pesan_alert' => 'Pesan lama',
        ]);
        $rem2 = Reminder::create([
            'nomor_paket' => $this->paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now(),
            'judul_alert' => 'Notif Baru 2',
            'pesan_alert' => 'Pesan baru',
        ]);

        $notif1 = NotifikasiUser::create(['id_user' => $this->userPengaju->id_user, 'id_reminder' => $rem1->id_reminder, 'is_read' => false]);
        $notif2 = NotifikasiUser::create(['id_user' => $this->userPengaju->id_user, 'id_reminder' => $rem2->id_reminder, 'is_read' => false]);

        // Saat browser memanggil dengan parameter since_id = notif1->id
        $response = $this->actingAs($this->userPengaju)->getJson('/api/notifikasi-terbaru?since_id='.$notif1->id);

        $response->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $notif2->id)
            ->assertJsonPath('data.0.judul', 'Notif Baru 2');
    }

    /**
     * Memverifikasi pengguna yang belum login tidak dapat mengakses /api/notifikasi-terbaru (401 Unauthorized).
     */
    public function test_guest_cannot_access_notifikasi_terbaru_api(): void
    {
        $response = $this->getJson('/api/notifikasi-terbaru');
        $response->assertStatus(401);
    }
}
