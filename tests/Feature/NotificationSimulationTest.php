<?php

namespace Tests\Feature;

use App\Models\NotifikasiUser;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PengingatPengadaanNotification;
use App\Services\PengadaanNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Uji Endpoint Simulasi Notifikasi Mandiri (NotificationSimulationTest)
 *
 * Memverifikasi fitur uji coba manual ("Kirim Notifikasi Uji Coba ke Akun Saya"):
 * 1. Pengguna login dapat memicu pembuatan notifikasi dan surel simulasi ke akunnya sendiri.
 * 2. Tamu (guest / unauthenticated) dilarang mengakses endpoint simulasi.
 * 3. Validasi skenario simulasi yang tidak terdaftar ditolak.
 */
class NotificationSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $unit = Unit::updateOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $roleUser = Role::where('nama_role', 'user')->first();

        $this->user = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'overseer.tester@pnj.ac.id',
            'nama' => 'Bapak Overseer Tester',
        ]);
    }

    /**
     * Memastikan pengguna dapat mengirimkan notifikasi simulasi dan surel ke dirinya sendiri.
     */
    public function test_user_dapat_mengirim_notifikasi_simulasi_ke_akun_sendiri(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->user)
            ->from(route('notifikasi.index'))
            ->post(route('notifikasi.tes-kirim'), [
                'jenis' => 'persetujuan_wadir',
            ]);

        $response->assertRedirect(route('notifikasi.index'));
        $response->assertSessionHas('success');

        // Pastikan record notifikasi_user tercatat di database
        $this->assertDatabaseHas('notifikasi_user', [
            'id_user' => $this->user->id_user,
            'is_read' => false,
        ]);

        $notif = NotifikasiUser::where('id_user', $this->user->id_user)->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Disetujui', $notif->judul);

        // Pastikan email notifikasi dikirimkan ke email user
        Notification::assertSentTo($this->user, PengingatPengadaanNotification::class);
    }

    /**
     * Memastikan tamu (unauthenticated) diarahkan ke halaman login.
     */
    public function test_tamu_tidak_dapat_mengakses_endpoint_simulasi(): void
    {
        $response = $this->post(route('notifikasi.tes-kirim'), [
            'jenis' => 'pengajuan_baru',
        ]);

        $response->assertRedirect(route('login'));
    }

    /**
     * Memastikan input skenario di luar daftar resmi ditolak dengan error validasi.
     */
    public function test_validasi_menolak_skenario_simulasi_tidak_terdaftar(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('notifikasi.tes-kirim'), [
                'jenis' => 'skenario_palsu_ngawur',
            ]);

        $response->assertSessionHasErrors(['jenis']);
    }

    /**
     * Memastikan reminder dapat dibuat tanpa nomor paket (nomor_paket nullable)
     * saat pengadaan belum memiliki paket fisik atau dalam mode uji coba simulasi.
     */
    public function test_reminder_dapat_dibuat_tanpa_nomor_paket_dan_tersimpan_di_database(): void
    {
        /** @var PengadaanNotificationService $service */
        $service = app(PengadaanNotificationService::class);
        $notif = $service->kirimUjiCoba($this->user, 'pengajuan_baru');

        $this->assertNotNull($notif);
        $this->assertDatabaseHas('reminder', [
            'id_reminder' => $notif->id_reminder,
            'nomor_paket' => null,
        ]);
        $this->assertNull($notif->reminder->nomor_paket);
    }
}
