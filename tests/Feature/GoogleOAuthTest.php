<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as TwoUser;
use Tests\TestCase;

/**
 * Pengujian Fitur Login & Penautan Akun Google OAuth (GoogleOAuthTest)
 *
 * Menguji kepatuhan seluruh aturan bisnis dan alur keamanan integrasi Google OAuth:
 * 1. Tamu diarahkan ke OAuth provider saat membuka /auth/google.
 * 2. Penolakan ketat login Google jika akun belum pernah ditautkan (Dilarang auto-create user baru).
 * 3. Keberhasilan login akun yang sudah ditautkan serta regenerasi sesi pengguna.
 * 4. Penolakan login Google jika status registrasi pengguna masih menunggu atau dinonaktifkan.
 * 5. Proteksi middleware auth pada alur penautan akun Google di halaman profil.
 * 6. Keberhasilan penautan akun Google dan penyimpanan atribut identitas OAuth.
 * 7. Pencegahan duplikasi penautan: 1 akun Google hanya dapat terhubung ke 1 akun lokal.
 * 8. Pemutusan tautan akun Google (unlink) melalui profil pengguna.
 * 9. Penanganan anggun saat otentikasi dibatalkan (graceful error handling).
 */
class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Memastikan rute /auth/google mengarahkan tamu ke URL otorisasi provider Google.
     */
    public function test_redirect_login_google_mengarah_ke_oauth_provider(): void
    {
        Socialite::fake('google');

        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
        $this->assertStringContainsString('socialite.fake/google/authorize', (string) $response->headers->get('Location'));
    }

    /**
     * Memastikan login Google ditolak jika akun Google belum pernah ditautkan di halaman profil.
     * Sistem dilarang otomatis membuat akun baru di database.
     */
    public function test_login_google_gagal_jika_akun_belum_pernah_ditautkan(): void
    {
        Socialite::fake('google', TwoUser::fake([
            'id' => 'google-unlinked-999',
            'name' => 'Pengguna Belum Tertaut',
            'email' => 'unlinked@example.com',
        ]));

        $initialUserCount = User::count();

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('belum ditautkan ke akun SIM-PBJ mana pun', (string) session('error'));

        // Pastikan tidak ada akun baru yang tercipta
        $this->assertEquals($initialUserCount, User::count());
        $this->assertGuest();
    }

    /**
     * Memastikan akun yang sudah pernah ditautkan berhasil login dan diarahkan ke dashboard.
     */
    public function test_login_google_berhasil_untuk_akun_yang_sudah_ditautkan(): void
    {
        /** @var User $user */
        $user = User::where('role', User::ROLE_USER)->firstOrFail();
        $user->update([
            'google_id' => 'google-linked-12345',
            'google_email' => 'user_pengaju@gmail.com',
            'google_linked_at' => now(),
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
        ]);

        Socialite::fake('google', TwoUser::fake([
            'id' => 'google-linked-12345',
            'name' => 'Dr. Ir. Maulana Pengaju',
            'email' => 'user_pengaju@gmail.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Memastikan login Google ditolak jika akun berstatus menunggu verifikasi atau nonaktif.
     */
    public function test_login_google_ditolak_jika_status_user_nonaktif_atau_menunggu(): void
    {
        /** @var User $user */
        $user = User::where('role', User::ROLE_USER)->firstOrFail();
        $user->update([
            'google_id' => 'google-pending-999',
            'google_email' => 'pending@example.com',
            'status_aktif' => false,
            'status_registrasi' => User::STATUS_REGISTRASI_MENUNGGU,
        ]);

        Socialite::fake('google', TwoUser::fake([
            'id' => 'google-pending-999',
            'email' => 'pending@example.com',
        ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();

        // Uji untuk status dinonaktifkan
        $user->update([
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'status_aktif' => false,
        ]);

        $responseNonAktif = $this->get(route('auth.google.callback'));
        $responseNonAktif->assertRedirect(route('login'));
        $responseNonAktif->assertSessionHas('error');
        $this->assertGuest();
    }

    /**
     * Memastikan akses penautan akun Google di profil memerlukan otentikasi login.
     */
    public function test_redirect_penautan_profil_google_memerlukan_login(): void
    {
        $response = $this->get(route('profile.google.link'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Memastikan pengguna terautentikasi dapat menautkan akun Google dan atribut tersimpan di database.
     */
    public function test_penautan_akun_google_berhasil_menyimpan_data_identitas(): void
    {
        /** @var User $user */
        $user = User::where('role', User::ROLE_USER)->firstOrFail();
        $user->update([
            'google_id' => null,
            'google_email' => null,
            'google_avatar' => null,
            'google_linked_at' => null,
        ]);

        Socialite::fake('google', TwoUser::fake([
            'id' => 'google-id-baru-555',
            'email' => 'maulana.google@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/avatar555',
        ]));

        $response = $this->actingAs($user)->get(route('profile.google.callback'));

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('google-id-baru-555', $user->google_id);
        $this->assertEquals('maulana.google@gmail.com', $user->google_email);
        $this->assertEquals('https://lh3.googleusercontent.com/avatar555', $user->google_avatar);
        $this->assertNotNull($user->google_linked_at);
        $this->assertTrue($user->isGoogleLinked());
    }

    /**
     * Memastikan penautan akun Google gagal jika ID Google tersebut sudah digunakan akun lain.
     */
    public function test_penautan_akun_google_gagal_jika_sudah_digunakan_user_lain(): void
    {
        /** @var User $userA */
        $userA = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $userA->update([
            'google_id' => 'google-id-terpakai-777',
            'google_email' => 'shared@gmail.com',
        ]);

        /** @var User $userB */
        $userB = User::where('role', User::ROLE_USER)->firstOrFail();
        $userB->update([
            'google_id' => null,
        ]);

        Socialite::fake('google', TwoUser::fake([
            'id' => 'google-id-terpakai-777',
            'email' => 'shared@gmail.com',
        ]));

        $response = $this->actingAs($userB)->get(route('profile.google.callback'));

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('sudah ditautkan ke akun pengguna SIM-PBJ lain', (string) session('error'));

        $userB->refresh();
        $this->assertNull($userB->google_id);
    }

    /**
     * Memastikan pengguna dapat memutuskan tautan akun Google (unlink) secara aman.
     */
    public function test_pemutusan_tautan_google_berhasil(): void
    {
        /** @var User $user */
        $user = User::where('role', User::ROLE_USER)->firstOrFail();
        $user->update([
            'google_id' => 'google-to-unlink-888',
            'google_email' => 'unlink@gmail.com',
            'google_linked_at' => now(),
        ]);

        $this->assertTrue($user->isGoogleLinked());

        $response = $this->actingAs($user)->post(route('profile.google.unlink'));

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertNull($user->google_email);
        $this->assertNull($user->google_avatar);
        $this->assertNull($user->google_linked_at);
        $this->assertFalse($user->isGoogleLinked());
    }

    /**
     * Memastikan pembatalan izin atau penolakan grant Google OAuth ditangani secara aman tanpa error 500.
     */
    public function test_pembatalan_oauth_google_ditangani_dengan_aman(): void
    {
        // 1. Pembatalan pada alur login
        $responseLogin = $this->get(route('auth.google.callback', ['error' => 'access_denied']));
        $responseLogin->assertRedirect(route('login'));
        $responseLogin->assertSessionHas('error');

        // 2. Pembatalan pada alur penautan akun profil
        /** @var User $user */
        $user = User::where('role', User::ROLE_USER)->firstOrFail();
        $responseLinking = $this->actingAs($user)->get(route('profile.google.callback', ['error' => 'access_denied']));
        $responseLinking->assertRedirect(route('profile.index'));
        $responseLinking->assertSessionHas('error');
    }
}
