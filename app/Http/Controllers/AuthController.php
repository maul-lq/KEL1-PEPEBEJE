<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller AuthController ini untuk menangani tampilan login/registrasi, pendaftaran akun menunggu persetujuan admin, login multi-identitas dengan rate limiter, logout, serta pemulihan dan reset kata sandi melalui email.
 */

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\PengadaanNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Autentikasi & Registrasi Pengguna (AuthController)
 *
 * memisahkan logika autentikasi lokal dari Google OAuth agar alur multi-identitas (username/NIP/email),
 * proteksi brute-force RateLimiter, serta verifikasi status registrasi tertata dalam satu controller.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance controller yang dipetakan ke rute tamu (`/login`, `/register`, dll.) dan `/logout`.
 *
 * Contoh pemanggilan:
 * `Route::post('/login', [AuthController::class, 'login']);`
 */
class AuthController extends Controller
{
    /**
     * merender halaman formulir login resmi SIM-PBJ untuk pengunjung yang belum terautentikasi.
     *
     * @return View mengembalikan tampilan Blade `auth.login`.
     *
     * Contoh pemanggilan:
     * `GET /login`
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * menampilkan formulir pendaftaran pegawai baru beserta daftar master unit kerja PNJ untuk dropdown pilihan unit.
     *
     * @return View mengembalikan tampilan Blade `auth.register` dengan variabel `$units`.
     *
     * Contoh pemanggilan:
     * `GET /register`
     */
    public function showRegistrationForm(): View
    {
        $units = Unit::all();

        return view('auth.register', compact('units'));
    }

    /**
     * memvalidasi dan menyimpan pendaftaran akun baru dalam status menunggu persetujuan Admin tanpa mengizinkan pendaftar memilih perannya sendiri.
     *
     * @param  Request  $request  menerima input formulir registrasi (termasuk dukungan alias field `name`, `phone`, `unit_kerja`).
     * @return RedirectResponse mengarahkan pengguna ke halaman login dengan pesan bahwa akun menunggu verifikasi Admin.
     *
     * Contoh pemanggilan:
     * `POST /register`
     */
    public function register(Request $request): RedirectResponse
    {
        // menormalkan alias field form/test terdahulu ke nama kolom resmi sebelum validasi dijalankan.
        if ($request->filled('name') && ! $request->filled('nama')) {
            $request->merge(['nama' => $request->name]);
        }
        if ($request->filled('phone') && ! $request->filled('no_hp')) {
            $request->merge(['no_hp' => $request->phone]);
        }
        if ($request->filled('unit_kerja') && ! $request->filled('kode_unit')) {
            $unit = Unit::where('kode_unit', $request->unit_kerja)
                ->orWhere('nama_unit', 'like', "%{$request->unit_kerja}%")
                ->first();
            if ($unit) {
                $request->merge(['kode_unit' => $unit->kode_unit]);
            }
        }

        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'nama' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'jabatan' => ['required', 'string', 'max:100'],
            'kode_unit' => ['required', 'string', 'exists:units,kode_unit'],
            'no_hp' => ['required', 'string', 'max:20'],
        ]);

        // mengunci akun baru agar tidak aktif sampai Administrator memverifikasi dan menetapkan role definitif saat ACC.
        $validated['id_role'] = Role::where('nama_role', User::ROLE_USER)->value('id');
        $validated['status_aktif'] = false;
        $validated['status_registrasi'] = User::STATUS_REGISTRASI_MENUNGGU;

        $newUser = User::create($validated);

        try {
            app(PengadaanNotificationService::class)->kirimKePeran(
                'admin',
                "Pendaftar Akun Baru: {$newUser->nama}",
                "Pengguna baru {$newUser->nama} (NIP: {$newUser->nip}) telah mendaftar dan menunggu verifikasi serta penetapan peran (role) oleh Administrator.",
                'verifikasi'
            );
        } catch (\Throwable $e) {
            // menelan kegagalan notifikasi agar proses registrasi pendaftar tidak ikut gagal bila mailer sedang bermasalah.
            Log::warning("Gagal mengirim notifikasi pendaftar baru ke admin: {$e->getMessage()}");
        }

        return redirect()->route('login')->with(
            'success',
            'Pendaftaran akun berhasil diajukan! Akun Anda menunggu verifikasi dan persetujuan Administrator sebelum dapat digunakan.'
        );
    }

    /**
     * memverifikasi kredensial multi-identitas (username, email, atau NIP), memeriksa status persetujuan akun, dan mencegah brute-force dengan RateLimiter.
     *
     * @param  Request  $request  membaca input `login`, `password`, dan flag `remember`.
     * @return RedirectResponse mengarahkan pengguna ke halaman tujuan/dashboard bila lolos, atau kembali dengan pesan error spesifik.
     *
     * Contoh pemanggilan:
     * `POST /login`
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = $credentials['login'];
        // menggabungkan loginInput + IP sebagai kunci throttle agar satu IP tidak bisa menebak sandi satu akun maupun banyak akun berulang kali.
        $throttleKey = Str::transliterate(Str::lower($loginInput).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'login' => "Terlalu banyak percobaan login gagal. Silakan coba kembali dalam {$seconds} detik.",
            ])->onlyInput('login');
        }

        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->orWhere('nip', $loginInput)
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            // memvalidasi status registrasi dan keaktifan sebelum membuat sesi agar akun tertunda/tertolak tidak bisa masuk.
            if ($user->status_registrasi === User::STATUS_REGISTRASI_MENUNGGU) {
                return back()->withErrors([
                    'login' => 'Pendaftaran akun Anda sedang menunggu verifikasi dan persetujuan dari Administrator.',
                ])->onlyInput('login');
            }

            if ($user->status_registrasi === User::STATUS_REGISTRASI_DITOLAK) {
                $alasan = $user->alasan_penolakan ? " Alasan: {$user->alasan_penolakan}" : '';

                return back()->withErrors([
                    'login' => "Pendaftaran akun Anda ditolak oleh Administrator.{$alasan}",
                ])->onlyInput('login');
            }

            if (! $user->status_aktif) {
                return back()->withErrors([
                    'login' => 'Akun Anda sedang dinonaktifkan oleh Administrator.',
                ])->onlyInput('login');
            }

            RateLimiter::clear($throttleKey);

            Auth::login($user, $request->filled('remember'));
            // meregenerasi ID sesi untuk menangkal Session Fixation serta mencatat timestamp awal timer idle timeout 15 menit.
            $request->session()->regenerate();
            $request->session()->put('last_activity', time());

            $user->loadMissing('roleEntity');
            $roleLabel = $user->roleEntity?->label ?? 'Pengguna';

            return redirect()->intended(route('dashboard'))->with(
                'success',
                "Selamat datang kembali, {$user->nama}! Masuk sebagai {$roleLabel}."
            );
        }

        // menambahkan hit kegagalan berdurasi 60 detik hanya jika password/identitas tidak cocok.
        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'login' => 'Kombinasi Username/NIP/Email dan password tidak sesuai.',
        ])->onlyInput('login');
    }

    /**
     * mengakhiri sesi pengguna yang sedang login, membatalkan seluruh data sesi, dan meregenerasi CSRF token.
     *
     * @param  Request  $request  menerima request logout dari link GET maupun form POST.
     * @return RedirectResponse mengarahkan kembali ke halaman login dengan pesan konfirmasi keluar.
     *
     * Contoh pemanggilan:
     * `POST /logout`
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar (logout).');
    }

    /**
     * menampilkan formulir permintaan tautan pengaturan ulang kata sandi untuk pengunjung tamu.
     *
     * @return View mengembalikan tampilan Blade `auth.forgot-password`.
     *
     * Contoh pemanggilan:
     * `GET /forgot-password`
     */
    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * memproses permintaan tautan reset kata sandi hanya untuk akun aktif yang sudah disetujui, dengan pesan respons netral anti-enumerasi.
     *
     * @param  Request  $request  memvalidasi alamat `email` dan membatasi frekuensi permintaan per IP agar terhindar dari penyalahgunaan spam.
     * @return RedirectResponse|JsonResponse mengembalikan redirect ke formulir atau JSON status netral saat diminta oleh klien JSON.
     *
     * Contoh pemanggilan:
     * `POST /forgot-password`
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.max' => 'Alamat email maksimal 100 karakter.',
        ]);

        // membatasi permintaan hanya per IP (bukan per email) agar banjir email acak tertahan tanpa membocorkan apakah suatu alamat terdaftar.
        $ipThrottleKey = 'forgot-password-ip|'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipThrottleKey, 10)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);
            $throttleMessage = "Terlalu banyak permintaan pengaturan ulang kata sandi. Silakan coba kembali dalam {$seconds} detik.";

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $throttleMessage], 429);
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $throttleMessage]);
        }
        RateLimiter::hit($ipThrottleKey, 60);

        $normalizedEmail = Str::lower(trim((string) $validated['email']));
        $neutralStatus = 'Jika alamat email terdaftar dan aktif, tautan pengaturan ulang kata sandi telah dikirimkan.';

        try {
            // menyaring LOWER(email), status_aktif, dan status_registrasi disetujui di broker agar akun tertunda/tertolak/nonaktif tidak pernah menerima token maupun email.
            Password::broker()->sendResetLink([
                fn (Builder $query) => $query
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->where('status_aktif', true)
                    ->where('status_registrasi', User::STATUS_REGISTRASI_DISETUJUI),
            ]);
        } catch (\Throwable $e) {
            // menghapus token baru jika pengiriman SMTP gagal agar pengguna tidak langsung terkena jeda throttle 60 detik saat mencoba ulang.
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->delete();
            }

            Log::warning('Gagal mengirim email pengaturan ulang kata sandi.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $serviceError = 'Layanan pengiriman email sedang mengalami kendala. Silakan coba kembali beberapa saat lagi atau hubungi Administrator.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $serviceError], 503);
            }

            return back()
                ->withInput($request->only('email'))
                ->with('error', $serviceError);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $neutralStatus]);
        }

        // menyamakan pesan baik saat email terkirim, tidak ditemukan, belum aktif, maupun sedang masa throttle 60 detik demi mencegah enumerasi akun.
        return back()->with('status', $neutralStatus);
    }

    /**
     * menampilkan formulir penetapan kata sandi baru berdasarkan token pemulihan dan email dari tautan email.
     *
     * @param  Request  $request  membaca parameter query `email` agar kolom email langsung terisi di formulir.
     * @param  string  $token  meneruskan token reset kata sandi dari parameter rute ke view.
     * @return View mengembalikan tampilan Blade `auth.reset-password`.
     *
     * Contoh pemanggilan:
     * `GET /reset-password/{token}?email=nama@pnj.ac.id`
     */
    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * memvalidasi token serta memperbarui kata sandi akun aktif yang disetujui, merotasi token sesi, membersihkan throttle login, dan mencatat jejak audit.
     *
     * @param  Request  $request  memvalidasi `token`, `email`, serta `password` terkonfirmasi minimal 6 karakter.
     * @return RedirectResponse|JsonResponse mengarahkan ke login saat berhasil atau mengembalikan JSON/redirect dengan pesan error seragam.
     *
     * Contoh pemanggilan:
     * `POST /reset-password`
     */
    public function resetPassword(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'token.required' => 'Token pengaturan ulang kata sandi tidak ditemukan.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $normalizedEmail = Str::lower(trim((string) $validated['email']));
        $resetThrottleKey = 'reset-password|'.$normalizedEmail.'|'.$request->ip();

        // membatasi percobaan validasi token per kombinasi email dan IP untuk menangkal tebakan berulang.
        if (RateLimiter::tooManyAttempts($resetThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($resetThrottleKey);
            $throttleMessage = "Terlalu banyak percobaan pengaturan ulang kata sandi. Silakan coba kembali dalam {$seconds} detik.";

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $throttleMessage], 429);
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $throttleMessage]);
        }

        $status = Password::broker()->reset(
            [
                'token' => $validated['token'],
                'password' => $validated['password'],
                fn (Builder $query) => $query
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->where('status_aktif', true)
                    ->where('status_registrasi', User::STATUS_REGISTRASI_DISETUJUI),
            ],
            function (User $user, string $password) use ($request): void {
                // memperbarui sandi (otomatis ter-hash oleh cast model User) sekaligus merotasi remember_token agar sesi ingat lama tidak berlaku lagi.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // menghapus seluruh sesi database lama milik pengguna setelah reset kata sandi berhasil.
                if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
                    DB::table(config('session.table', 'sessions'))
                        ->where('user_id', $user->id_user)
                        ->delete();
                }

                // membersihkan batas gagal login pada seluruh identitas akun untuk IP pemohon agar pengguna langsung bisa masuk dengan sandi baru.
                foreach (array_filter([$user->username, $user->email, $user->nip]) as $identity) {
                    $loginThrottleKey = Str::transliterate(Str::lower((string) $identity).'|'.$request->ip());
                    RateLimiter::clear($loginThrottleKey);
                }

                AuditLog::catat(
                    $user->id_user,
                    'RESET_PASSWORD',
                    'users',
                    (string) $user->id_user,
                    null,
                    null,
                    "Pengguna mengatur ulang kata sandi melalui tautan email pemulihan ({$user->username})"
                );

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            RateLimiter::clear($resetThrottleKey);
            $successMessage = 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $successMessage]);
            }

            return redirect()->route('login')->with('success', $successMessage);
        }

        RateLimiter::hit($resetThrottleKey, 60);
        $invalidMessage = 'Tautan pengaturan ulang kata sandi tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.';

        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $invalidMessage], 422);
        }

        // memakai satu pesan error yang sama untuk token tidak valid, kedaluwarsa, maupun akun tidak ditemukan agar status email tidak bocor.
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $invalidMessage]);
    }
}
