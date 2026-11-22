<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Pengujian fitur ini tulis untuk memverifikasi seluruh siklus hidup autentikasi, notifikasi registrasi/login/penonaktifan, proteksi tombol Back browser, dan pemutusan sesi idle 15 menit (> 900 detik).
 */

use App\Http\Middleware\PreventBackHistory;
use App\Models\NotifikasiUser;
use App\Models\Reminder;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Notifications\PengingatPengadaanNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

it('notifies admin and registrant on successful registration and renders Indonesian validation errors', function (): void {
    Notification::fake();

    $admin = User::factory()->withRole(User::ROLE_ADMIN)->create([
        'email' => 'admin.notif@pnj.ac.id',
    ]);

    // Pengajuan kosong pada form daftar harus menampilkan pesan validasi berbahasa Indonesia.
    $this->from(route('register'))
        ->post(route('register'), [])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['nip', 'username', 'email', 'nama', 'password', 'jabatan', 'kode_unit', 'no_hp']);

    $registerResponse = $this->followingRedirects()
        ->post(route('register'), [
            'nip' => '199502022024011001',
            'username' => 'pendaftar_baru',
            'email' => 'pendaftar.baru@pnj.ac.id',
            'nama' => 'Siti Pendaftar',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'jabatan' => 'Staf Pengadaan Unit',
            'kode_unit' => 'TIK',
            'no_hp' => '081234567890',
        ]);

    $registerResponse->assertOk()
        ->assertSee('Pendaftaran akun berhasil diajukan! Akun Anda menunggu verifikasi dan persetujuan Administrator sebelum dapat digunakan.');

    $newUser = User::where('username', 'pendaftar_baru')->firstOrFail();

    expect($newUser->status_aktif)->toBeFalse()
        ->and($newUser->status_registrasi)->toBe(User::STATUS_REGISTRASI_MENUNGGU);

    Notification::assertSentTo($admin, PengingatPengadaanNotification::class, function (PengingatPengadaanNotification $notif): bool {
        return str_contains($notif->notifikasi->judul, 'Pendaftar Akun Baru');
    });

    Notification::assertSentTo($newUser, PengingatPengadaanNotification::class, function (PengingatPengadaanNotification $notif): bool {
        return $notif->notifikasi->judul === 'Pendaftaran Akun SIM-PBJ Diterima';
    });
});

it('displays clear alerts on login page for pending approval, rejected, deactivated, wrong credentials, and rate limit', function (): void {
    $pendingUser = User::factory()->withRole(User::ROLE_USER)->menungguApproval()->create([
        'username' => 'akun_menunggu',
        'password' => 'rahasia123',
    ]);

    $rejectedUser = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'akun_ditolak',
        'password' => 'rahasia123',
        'status_aktif' => false,
        'status_registrasi' => User::STATUS_REGISTRASI_DITOLAK,
        'alasan_penolakan' => 'NIP tidak ditemukan pada data kepegawaian.',
    ]);

    $disabledUser = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'akun_nonaktif',
        'password' => 'rahasia123',
        'status_aktif' => false,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ]);

    // 1. Akun menunggu ACC admin menampilkan kotak peringatan kuning pada halaman masuk.
    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login'), [
            'login' => $pendingUser->username,
            'password' => 'rahasia123',
        ])
        ->assertOk()
        ->assertSee('bg-amber-50', false)
        ->assertSee('Pendaftaran akun Anda sedang menunggu verifikasi dan persetujuan dari Administrator.');
    $this->assertGuest();

    // 2. Akun ditolak admin menampilkan kotak kesalahan merah beserta alasannya.
    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login'), [
            'login' => $rejectedUser->username,
            'password' => 'rahasia123',
        ])
        ->assertOk()
        ->assertSee('bg-red-50', false)
        ->assertSee('Pendaftaran akun Anda ditolak oleh Administrator. Alasan: NIP tidak ditemukan pada data kepegawaian.');
    $this->assertGuest();

    // 3. Akun dinonaktifkan admin menampilkan kotak kesalahan merah dengan pesan nonaktif.
    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login'), [
            'login' => $disabledUser->username,
            'password' => 'rahasia123',
        ])
        ->assertOk()
        ->assertSee('bg-red-50', false)
        ->assertSee('Akun Anda sedang dinonaktifkan oleh Administrator.');
    $this->assertGuest();

    // 4. Form kosong menampilkan pesan validasi Bahasa Indonesia.
    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login'), [])
        ->assertOk()
        ->assertSee('Email, username, atau NIP wajib diisi.')
        ->assertSee('Password wajib diisi.');

    // 5. Kredensial keliru menampilkan pesan gagal login dan pembatasan 5 kali percobaan gagal.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login'), [
                'login' => 'pengguna_salah',
                'password' => 'sandi_keliru',
            ])
            ->assertOk()
            ->assertSee('Kombinasi Username/NIP/Email dan password tidak sesuai.');
    }

    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login'), [
            'login' => 'pengguna_salah',
            'password' => 'sandi_keliru',
        ])
        ->assertOk()
        ->assertSee('Terlalu banyak percobaan login gagal.');
});

it('terminates active sessions immediately, notifies user via inbox and email, and enforces admin user activation guards', function (): void {
    Notification::fake();

    $admin = User::factory()->withRole(User::ROLE_ADMIN)->create();
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'user_aktif',
        'password' => 'rahasia123',
        'remember_token' => 'token-ingat-awal',
        'reminder_email_aktif' => false,
    ]);

    // 1. Admin menonaktifkan akun lewat hidden checkbox '0' -> status_aktif false, notifikasi & email status akun terkirim.
    $this->actingAs($admin)
        ->put(route('admin.users.update', $pengusul), [
            'nip' => $pengusul->nip,
            'username' => $pengusul->username,
            'email' => $pengusul->email,
            'nama' => $pengusul->nama,
            'jabatan' => $pengusul->jabatan,
            'id_role' => $pengusul->id_role,
            'status_aktif' => '0',
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'no_hp' => $pengusul->no_hp,
            'kode_unit' => $pengusul->kode_unit,
        ])
        ->assertRedirect(route('admin.users.index'));

    $pengusul->refresh();
    expect($pengusul->status_aktif)->toBeFalse()
        ->and($pengusul->remember_token)->not->toBe('token-ingat-awal');

    $deactivatedNotif = NotifikasiUser::with('reminder')
        ->where('id_user', $pengusul->id_user)
        ->latest('id')
        ->first();
    expect($deactivatedNotif)->not->toBeNull()
        ->and($deactivatedNotif->reminder?->tipe_reminder)->toBe(Reminder::TIPE_AKUN)
        ->and($deactivatedNotif->judul)->toContain('Dinonaktifkan')
        ->and($deactivatedNotif->jenis_badge)->toBe('Akun');

    Notification::assertSentTo(
        $pengusul,
        AccountStatusNotification::class,
        fn (AccountStatusNotification $notification): bool => $notification->isActive === false
    );
    Notification::assertNotSentTo($pengusul, PengingatPengadaanNotification::class);

    // 2. Percobaan login baru oleh akun yang baru dinonaktifkan ditolak dengan pesan error jelas.
    $this->post('/logout');
    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login'), [
            'login' => $pengusul->username,
            'password' => 'rahasia123',
        ])
        ->assertOk()
        ->assertSee('Akun Anda sedang dinonaktifkan oleh Administrator.');
    $this->assertGuest();

    // 3. Sesi pengusul yang masih aktif pada request berikutnya langsung diputus dan diberi tahu di halaman masuk.
    $kickedHtml = $this->actingAs($pengusul)
        ->withSession(['last_activity' => time()])
        ->get(route('dashboard'));

    $kickedHtml->assertRedirect(route('login'));
    $this->assertGuest();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('bg-red-50', false)
        ->assertSee('Akun Anda telah dinonaktifkan oleh Administrator.');

    // 4. Admin mengaktifkan kembali akun -> status_aktif true, email pengaktifan terkirim, dan pengguna bisa login kembali.
    $this->actingAs($admin)
        ->put(route('admin.users.update', $pengusul), [
            'nip' => $pengusul->nip,
            'username' => $pengusul->username,
            'email' => $pengusul->email,
            'nama' => $pengusul->nama,
            'jabatan' => $pengusul->jabatan,
            'id_role' => $pengusul->id_role,
            'status_aktif' => '1',
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'no_hp' => $pengusul->no_hp,
            'kode_unit' => $pengusul->kode_unit,
        ])
        ->assertRedirect(route('admin.users.index'));

    $pengusul->refresh();
    expect($pengusul->status_aktif)->toBeTrue();
    Notification::assertSentTo(
        $pengusul,
        AccountStatusNotification::class,
        fn (AccountStatusNotification $notification): bool => $notification->isActive === true
    );

    // 5. Guard Edit Pengguna: Admin tidak bisa menonaktifkan akunnya sendiri dan tidak bisa mengaktifkan akun menunggu persetujuan.
    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $admin), [
            'nip' => $admin->nip,
            'username' => $admin->username,
            'email' => $admin->email,
            'nama' => $admin->nama,
            'jabatan' => $admin->jabatan,
            'id_role' => $admin->id_role,
            'status_aktif' => '0',
            'no_hp' => $admin->no_hp,
            'kode_unit' => $admin->kode_unit,
        ])
        ->assertSessionHasErrors('status_aktif');
    expect($admin->fresh()->status_aktif)->toBeTrue();

    $unapproved = User::factory()->pendingApproval()->withRole(User::ROLE_USER)->create();
    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $unapproved), [
            'nip' => $unapproved->nip,
            'username' => $unapproved->username,
            'email' => $unapproved->email,
            'nama' => $unapproved->nama,
            'jabatan' => $unapproved->jabatan,
            'id_role' => $unapproved->id_role,
            'status_aktif' => '1',
            'no_hp' => $unapproved->no_hp,
            'kode_unit' => $unapproved->kode_unit,
        ])
        ->assertSessionHasErrors('status_aktif');
    expect($unapproved->fresh()->status_aktif)->toBeFalse();

    // Pengujian serupa untuk penolakan akun via ApprovalRegistrasiController serta polling JSON 401.
    Notification::fake();
    $pendingSessionUser = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'user_akan_ditolak',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.persetujuan-registrasi.tolak', $pendingSessionUser), [
            'alasan_penolakan' => 'Dokumen SK unit belum sesuai.',
        ])
        ->assertRedirect(route('admin.persetujuan-registrasi.index'));

    $pollingResponse = $this->actingAs($pendingSessionUser)
        ->withSession(['last_activity' => time()])
        ->getJson(route('notifikasi.terbaru'));

    $pollingResponse->assertStatus(401)
        ->assertJson([
            'status' => 'error',
            'reason' => 'account_inactive',
            'redirect' => route('login'),
        ]);
    $this->assertGuest();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Dokumen SK unit belum sesuai.');

    // Pengujian pemutusan sesi langsung jika status_aktif dimatikan sesudah login tanpa cache eksplisit.
    $directUser = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'user_langsung_nonaktif',
        'password' => 'rahasia123',
    ]);

    $this->post(route('login'), [
        'login' => $directUser->username,
        'password' => 'rahasia123',
    ])->assertRedirect(route('dashboard'));

    $directUser->update(['status_aktif' => false]);
    // Kosongkan cache guard in-memory agar request uji berikutnya memuat ulang status user dari basis data seperti permintaan HTTP nyata.
    auth()->forgetGuards();

    $this->followingRedirects()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Akun Anda sedang dinonaktifkan oleh Administrator.');
    $this->assertGuest();
});

it('sets anti-cache headers on private pages, invalidates session on logout, and blocks browser back access', function (): void {
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'pengusul_back',
        'password' => 'rahasia123',
    ]);

    $this->post(route('login'), [
        'login' => $pengusul->username,
        'password' => 'rahasia123',
    ])->assertRedirect(route('dashboard'));

    $dashboardResponse = $this->get(route('dashboard'));
    $dashboardResponse->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT')
        ->assertSee('data-auth-page="1"', false)
        ->assertSee('data-idle-timeout-ms="900000"', false);

    $cacheControl = (string) $dashboardResponse->headers->get('Cache-Control', '');
    expect($cacheControl)
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate');

    // Respons stream/unduhan file tetap dikecualikan dari Cache-Control: no-store.
    Route::middleware(['web', 'auth', 'prevent.back'])->get('/__test-stream-download', function (): StreamedResponse {
        return response()->streamDownload(function (): void {
            echo 'pdf-binary';
        }, 'dokumen.pdf');
    });

    $downloadResponse = $this->get('/__test-stream-download');
    $downloadResponse->assertOk();
    expect((string) $downloadResponse->headers->get('Cache-Control', ''))->not->toContain('no-store');

    $logoutResponse = $this->followingRedirects()->post(route('logout'));
    $logoutResponse->assertOk()
        ->assertSee('bg-emerald-50', false)
        ->assertSee('Anda telah berhasil keluar (logout).');

    $this->assertGuest();

    // Memastikan permintaan ulang (seperti tombol Back saat cache dimatikan) selalu ditolak ke halaman masuk.
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('logs out automatically with warning after > 900 seconds idle on both browser and background polling', function (): void {
    Config::set('session.idle_timeout', 15);
    Config::set('session.lifetime', 120);

    $pengusul = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'pengusul_idle',
        'password' => 'rahasia123',
    ]);

    $this->post(route('login'), [
        'login' => $pengusul->username,
        'password' => 'rahasia123',
    ])->assertRedirect(route('dashboard'));

    // Polling latar belakang sebelum 900 detik tidak boleh memperpanjang stempel waktu last_activity.
    $frozenActivity = time() - 300;
    $this->withSession(['last_activity' => $frozenActivity])
        ->getJson(route('notifikasi.terbaru'))
        ->assertOk();

    expect(session('last_activity'))->toBe($frozenActivity);

    // Melewati 900 detik pada navigasi browser langsung logout, invalidate sesi, dan tampilkan peringatan di halaman masuk.
    $expiredAt = time() - 901;
    $timeoutRedirect = $this->withSession(['last_activity' => $expiredAt])
        ->get(route('dashboard'));

    $timeoutRedirect->assertRedirect(route('login'));
    $this->assertGuest();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('bg-amber-50', false)
        ->assertSee('Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.');

    // Login ulang lalu simulasikan polling latar saat sesi telah diam > 900 detik -> membalas 401 dan menyiapkan flash untuk redirect JS.
    $this->post(route('login'), [
        'login' => $pengusul->username,
        'password' => 'rahasia123',
    ])->assertRedirect(route('dashboard'));

    $expiredPolling = $this->withSession(['last_activity' => time() - 905])
        ->getJson(route('notifikasi.terbaru'));

    $expiredPolling->assertStatus(401)
        ->assertJson([
            'status' => 'error',
            'reason' => 'session_timeout',
            'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.',
            'redirect' => route('login'),
        ]);
    $this->assertGuest();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.');

    expect(class_exists(PreventBackHistory::class))->toBeTrue()
        ->and(app(Request::class))->not->toBeNull();
});
