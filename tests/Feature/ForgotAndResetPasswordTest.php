<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Pengujian fitur ini tulis untuk memverifikasi seluruh alur Lupa Kata Sandi dan Reset Kata Sandi SIM-PBJ, termasuk aturan anti-enumerasi akun, keamanan token, rotasi sesi, pencatatan audit, serta penanganan error HTML/JSON.
 */

use App\Models\AuditLog;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Unit::firstOrCreate(
        ['kode_unit' => 'TIK'],
        [
            'nama_unit' => 'Jurusan Teknik Informatika dan Komputer',
            'jenis_unit' => 'jurusan',
        ]
    );

    $this->seed(RolePermissionSeeder::class);
});

it('exposes forgot password routes for guests only and hides forgot-password recovery from profile', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('password.request'), false)
        ->assertSee('Lupa password?');

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Lupa Kata Sandi')
        ->assertSee(route('password.email'), false)
        ->assertDontSee('<script>', false);

    $this->get(route('password.reset', ['token' => 'contoh-token', 'email' => 'user@pnj.ac.id']))
        ->assertOk()
        ->assertSee('Buat Kata Sandi Baru')
        ->assertSee('user@pnj.ac.id');

    $user = User::factory()->withRole(User::ROLE_USER)->create();

    // Pengguna yang sudah masuk diarahkan ke dashboard oleh middleware guest dan halaman profil tetap memakai Ubah Password saja.
    $this->actingAs($user)
        ->get(route('password.request'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('profile.index'))
        ->assertOk()
        ->assertSee('Ubah Password')
        ->assertDontSee('Lupa password lama');
});

it('sends synchronous Indonesian password reset link for active approved users regardless of email letter case', function (): void {
    Notification::fake();

    $user = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'pegawai.tik@pnj.ac.id',
        'nama' => 'Budi Pegawai',
        'status_aktif' => true,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ]);

    $response = $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'PEGAWAI.TIK@PNJ.AC.ID',
        ]);

    $response->assertRedirect(route('password.request'))
        ->assertSessionHas('status', 'Jika alamat email terdaftar dan aktif, tautan pengaturan ulang kata sandi telah dikirimkan.');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        expect($notification)->not->toBeInstanceOf(ShouldQueue::class)
            ->and($mail->subject)->toBe('[SIM-PBJ PNJ] Atur Ulang Kata Sandi Akun')
            ->and($mail->actionUrl)->toContain('/reset-password/'.$notification->token)
            ->and($mail->actionUrl)->toContain(urlencode($user->email));

        return true;
    });

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => 'pegawai.tik@pnj.ac.id',
    ]);
});

it('returns uniform neutral status and does not issue tokens for unknown, pending, rejected, inactive, or throttled accounts', function (): void {
    Notification::fake();

    $pendingUser = User::factory()->withRole(User::ROLE_USER)->menungguApproval()->create([
        'email' => 'menunggu@pnj.ac.id',
    ]);
    $rejectedUser = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'ditolak@pnj.ac.id',
        'status_aktif' => false,
        'status_registrasi' => User::STATUS_REGISTRASI_DITOLAK,
    ]);
    $inactiveUser = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'nonaktif@pnj.ac.id',
        'status_aktif' => false,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ]);
    $activeUser = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'aktif@pnj.ac.id',
        'status_aktif' => true,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ]);

    $expectedStatus = 'Jika alamat email terdaftar dan aktif, tautan pengaturan ulang kata sandi telah dikirimkan.';

    foreach (['tidak-ada@pnj.ac.id', 'menunggu@pnj.ac.id', 'ditolak@pnj.ac.id', 'nonaktif@pnj.ac.id'] as $email) {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', $expectedStatus);
    }

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);

    // Pengajuan beruntun ke akun aktif dalam 60 detik terkena throttle broker, tetapi respons layar tetap netral dan email kedua tidak dikirim.
    $this->post(route('password.email'), ['email' => $activeUser->email])
        ->assertSessionHas('status', $expectedStatus);
    $this->post(route('password.email'), ['email' => $activeUser->email])
        ->assertSessionHas('status', $expectedStatus);

    Notification::assertSentToTimes($activeUser, ResetPasswordNotification::class, 1);
});

it('rate limits excessive forgot-password attempts by IP and cleans token cleanly when mail transport fails', function (): void {
    // Simulasikan kegagalan pengiriman SMTP saat notifikasi hendak dikirim.
    Event::listen(NotificationSending::class, function (): void {
        throw new RuntimeException('SMTP server unreachable for internal credential xyz');
    });

    $user = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'smtp-gagal@pnj.ac.id',
    ]);

    $mailErrorResponse = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email]);

    $mailErrorResponse->assertRedirect(route('password.request'))
        ->assertSessionHas('error', 'Layanan pengiriman email sedang mengalami kendala. Silakan coba kembali beberapa saat lagi atau hubungi Administrator.');

    // Pesan error internal tidak boleh bocor dan sisa token harus dibersihkan agar pengguna bisa mencoba ulang.
    expect(session('error'))->not->toContain('SMTP server unreachable')
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();

    // Uji pembatasan frekuensi permintaan berlebih per IP.
    for ($i = 0; $i < 10; $i++) {
        $this->post(route('password.email'), ['email' => "bebas{$i}@pnj.ac.id"]);
    }

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'kelebihan@pnj.ac.id'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');
});

it('resets password with valid token, rotates remember token, invalidates old db sessions, clears login throttle, and writes audit log', function (): void {
    Notification::fake();
    Event::fake([PasswordReset::class]);

    $user = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'reset.sukses@pnj.ac.id',
        'username' => 'reset_sukses',
        'nip' => '199001012020011001',
        'password' => 'sandiLama123',
        'remember_token' => 'token-lama-60-karakter',
    ]);

    Config::set('session.driver', 'database');
    DB::table('sessions')->insert([
        'id' => 'sesi-lama-pengguna',
        'user_id' => $user->id_user,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    // Kunci akun lewat 5 kali percobaan login gagal untuk memastikan reset membersihkan rate limiter login.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), [
            'login' => $user->username,
            'password' => 'salah',
        ]);
    }
    $loginThrottleKey = Str::transliterate(Str::lower($user->username).'|127.0.0.1');
    expect(RateLimiter::tooManyAttempts($loginThrottleKey, 5))->toBeTrue();

    $token = Password::broker()->createToken($user);

    $resetResponse = $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'RESET.SUKSES@PNJ.AC.ID',
        'password' => 'baru12',
        'password_confirmation' => 'baru12',
    ]);

    $resetResponse->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.');

    $user->refresh();

    expect(Hash::check('baru12', $user->password))->toBeTrue()
        ->and(Hash::check('sandiLama123', $user->password))->toBeFalse()
        ->and($user->remember_token)->not->toBe('token-lama-60-karakter')
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id_user)->exists())->toBeFalse()
        ->and(RateLimiter::tooManyAttempts($loginThrottleKey, 5))->toBeFalse();

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event) => $event->user->is($user));

    $audit = AuditLog::where('jenis_aksi', 'RESET_PASSWORD')
        ->where('nama_entitas', 'users')
        ->where('id_entitas', (string) $user->id_user)
        ->first();
    expect($audit)->not->toBeNull();

    // Token bersifat sekali pakai dan sandi baru langsung dapat dipakai masuk.
    $this->from(route('password.reset', ['token' => $token]))
        ->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'sandiLain123',
            'password_confirmation' => 'sandiLain123',
        ])
        ->assertSessionHasErrors('email');

    $this->post(route('login'), [
        'login' => $user->username,
        'password' => 'baru12',
    ])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid token, expired token, inactive account, weak password, and renders custom HTTP errors cleanly', function (): void {
    $activeUser = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'token.cek@pnj.ac.id',
        'password' => 'sandiAsli123',
    ]);
    $inactiveUser = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'beku@pnj.ac.id',
        'status_aktif' => false,
    ]);

    $validToken = Password::broker()->createToken($activeUser);
    $staleTokenForInactive = Password::broker()->createToken($inactiveUser);

    // Token keliru dan akun tidak ditemukan harus memunculkan pesan error generik yang identik.
    $wrongToken = $this->from(route('password.reset', ['token' => 'salah']))
        ->post(route('password.update'), [
            'token' => 'salah',
            'email' => $activeUser->email,
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ]);
    $unknownEmail = $this->from(route('password.reset', ['token' => $validToken]))
        ->post(route('password.update'), [
            'token' => $validToken,
            'email' => 'gaib@pnj.ac.id',
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ]);

    $wrongToken->assertSessionHasErrors([
        'email' => 'Tautan pengaturan ulang kata sandi tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.',
    ]);
    $unknownEmail->assertSessionHasErrors([
        'email' => 'Tautan pengaturan ulang kata sandi tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.',
    ]);

    // Akun nonaktif tetap ditolak walau memiliki token di basis data.
    $this->post(route('password.update'), [
        'token' => $staleTokenForInactive,
        'email' => $inactiveUser->email,
        'password' => 'sandiBaru123',
        'password_confirmation' => 'sandiBaru123',
    ])->assertSessionHasErrors('email');

    // Token kedaluwarsa (> 60 menit) harus ditolak.
    DB::table('password_reset_tokens')
        ->where('email', $activeUser->email)
        ->update(['created_at' => now()->subMinutes(65)]);

    $this->post(route('password.update'), [
        'token' => $validToken,
        'email' => $activeUser->email,
        'password' => 'sandiBaru123',
        'password_confirmation' => 'sandiBaru123',
    ])->assertSessionHasErrors('email');

    // Kata sandi kurang dari 6 karakter atau tidak cocok harus ditolak dengan pesan Bahasa Indonesia.
    $this->post(route('password.update'), [
        'token' => $validToken,
        'email' => $activeUser->email,
        'password' => '12345',
        'password_confirmation' => 'beda',
    ])->assertSessionHasErrors(['password']);

    expect(Hash::check('sandiAsli123', $activeUser->fresh()->password))->toBeTrue();

    // Penanganan error HTML & JSON sesuai standar error SIM-PBJ (.ai/rules/views.md).
    $this->get('/reset-password')
        ->assertStatus(405)
        ->assertSee('Metode Permintaan Tidak Diizinkan')
        ->assertSee('Kembali ke Halaman Sebelumnya');

    $this->getJson('/reset-password')
        ->assertStatus(405);

    $this->get('/reset-password/token/bagian-tidak-ada')
        ->assertStatus(404)
        ->assertSee('Halaman Tidak Ditemukan');

    $this->postJson(route('password.email'), ['email' => 'format-salah'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->postJson(route('password.email'), ['email' => 'siapa@pnj.ac.id'])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Jika alamat email terdaftar dan aktif, tautan pengaturan ulang kata sandi telah dikirimkan.',
        ]);

    // Pengajuan JSON token salah berulang kali mengembalikan 422 lalu 429 saat batas percobaan terlampaui.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('password.update'), [
            'token' => 'token-json-salah',
            'email' => 'throttle.reset@pnj.ac.id',
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ])->assertStatus(422);
    }

    $this->postJson(route('password.update'), [
        'token' => 'token-json-salah',
        'email' => 'throttle.reset@pnj.ac.id',
        'password' => 'sandiBaru123',
        'password_confirmation' => 'sandiBaru123',
    ])->assertStatus(429);

    $freshToken = Password::broker()->createToken($activeUser);
    $jsonResetUser = User::factory()->withRole(User::ROLE_USER)->create([
        'email' => 'json.reset@pnj.ac.id',
        'password' => 'sandiAwal123',
    ]);
    $jsonToken = Password::broker()->createToken($jsonResetUser);

    expect($freshToken)->not->toBeEmpty();

    $this->postJson(route('password.update'), [
        'token' => $jsonToken,
        'email' => $jsonResetUser->email,
        'password' => 'sandiJsonBaru123',
        'password_confirmation' => 'sandiJsonBaru123',
    ])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.',
        ]);

    expect(Hash::check('sandiJsonBaru123', $jsonResetUser->fresh()->password))->toBeTrue();
});
