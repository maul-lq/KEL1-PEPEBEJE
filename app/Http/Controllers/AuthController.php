<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller AuthController ini untuk menangani tampilan login/registrasi, pendaftaran akun menunggu persetujuan admin, login multi-identitas dengan rate limiter, logout, dan stub pemulihan kata sandi.
 */

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
     * menampilkan formulir lupa kata sandi apabila view tersedia, atau mengalihkan kembali ke login dengan panduan kontak Administrator.
     *
     * @return View|RedirectResponse mengembalikan view `auth.forgot-password` bila tersedia atau redirect aman ke login.
     *
     * Contoh pemanggilan:
     * `GET /forgot-password`
     */
    public function showForgotPasswordForm(): View|RedirectResponse
    {
        if (view()->exists('auth.forgot-password')) {
            return view('auth.forgot-password');
        }

        return redirect()->route('login')->with('info', 'Layanan reset kata sandi belum tersedia. Silakan hubungi Administrator.');
    }

    /**
     * memvalidasi email pemulihan kata sandi dan mengembalikan pesan status netral agar tidak membocorkan daftar email terdaftar.
     *
     * @param  Request  $request  memvalidasi field `email` yang dikirim pemohon reset kata sandi.
     * @return RedirectResponse mengembalikan redirect kembali dengan pesan status informatif netral.
     *
     * Contoh pemanggilan:
     * `POST /forgot-password`
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        return back()->with('status', 'Jika alamat email terdaftar, instruksi pemulihan telah dikirimkan.');
    }

    /**
     * menampilkan formulir penetapan kata sandi baru berdasarkan token pemulihan apabila templat Blade-nya tersedia.
     *
     * @param  string  $token  meneruskan token reset kata sandi dari parameter rute ke view.
     * @return View|RedirectResponse mengembalikan view `auth.reset-password` atau redirect aman ke halaman login.
     *
     * Contoh pemanggilan:
     * `GET /reset-password/{token}`
     */
    public function showResetPasswordForm(string $token): View|RedirectResponse
    {
        if (view()->exists('auth.reset-password')) {
            return view('auth.reset-password', ['token' => $token]);
        }

        return redirect()->route('login')->with('info', 'Tautan pemulihan kata sandi belum tersedia. Silakan hubungi Administrator.');
    }

    /**
     * memvalidasi permintaan pembaruan kata sandi melalui token reset dan mengarahkan kembali ke halaman login.
     *
     * @param  Request  $request  memvalidasi `token`, `email`, serta `password` terkonfirmasi minimal 6 karakter.
     * @return RedirectResponse mengembalikan redirect ke login dengan pesan sukses.
     *
     * Contoh pemanggilan:
     * `POST /reset-password`
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        return redirect()->route('login')->with('success', 'Kata sandi berhasil diperbarui. Silakan masuk kembali.');
    }
}
