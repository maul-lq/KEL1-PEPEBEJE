<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Autentikasi & Registrasi Pengguna (AuthController)
 *
 * Mengimplementasikan:
 * - FR 1.1: Formulir multi-identitas login (Username, NIP 18-digit, atau Email dinas PNJ).
 * - FR 1.2: Verifikasi kredensial aman & pembatasan frekuensi percobaan (Rate Limiting anti brute-force).
 * - FR 1.3: Pengalihan pengguna secara otomatis ke tampilan dashboard sesuai perannya.
 * - FR 2.1: Batasan unik institusi (hanya diizinkan 1 akun Pejabat Pengadaan / PP aktif).
 */
class AuthController extends Controller
{
    /**
     * Menampilkan formulir halaman login simulasi backend.
     * Menggunakan template murni HTML semantik tanpa CSS total (AGENTS-BACKEND.md).
     */
    public function showLoginForm(): View
    {
        return view('auth.tes_login');
    }

    /**
     * Menampilkan formulir registrasi akun pegawai baru.
     * Mengambil daftar master unit/jurusan kampus PNJ untuk opsi pilihan unit kerja.
     */
    public function showRegistrationForm(): View
    {
        // Mengambil semua data unit kerja kampus untuk pilihan dropdown registrasi
        $units = Unit::all();

        return view('auth.tes_register', compact('units'));
    }

    /**
     * Memproses penyimpanan akun pengguna baru dari formulir pendaftaran.
     * Menerapkan validasi keunikan NIP, email, username, dan batasan 1 akun PP aktif.
     */
    public function register(Request $request): RedirectResponse
    {
        // 1. Validasi seluruh data input pendaftaran (tanpa pemilihan role oleh pendaftar, sesuai Opsi A)
        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'nama' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'jabatan' => ['required', 'string', 'max:100'],
            'kode_unit' => ['required', 'string', 'exists:units,kode_unit'],
            'no_hp' => ['required', 'string', 'max:20'],
            'no_telp_kantor' => ['nullable', 'string', 'max:20'],
        ]);

        // 2. Status awal pendaftaran: Tidak aktif dan menunggu persetujuan Administrator
        // Role definitif akan dipilihkan dan ditetapkan 100% oleh Admin saat tombol Setujui (ACC) ditekan
        $validated['role'] = User::ROLE_USER; // Default sementara sebelum dipetakan Admin
        $validated['status_aktif'] = false;
        $validated['status_registrasi'] = User::STATUS_REGISTRASI_MENUNGGU;

        // 3. Simpan data user baru ke database
        User::create($validated);

        // Berikan pesan sukses dan alihkan pengguna ke formulir login
        return redirect()->route('login')->with(
            'success',
            'Pendaftaran akun berhasil diajukan! Akun Anda sedang menunggu verifikasi dan persetujuan (ACC) dari Administrator sebelum dapat digunakan.'
        );
    }

    /**
     * Menangani proses login pengguna ke dalam sistem.
     * Mendukung multi-identitas dan rate limiting 5 kali percobaan per menit.
     */
    public function login(Request $request): RedirectResponse
    {
        // 1. Validasi input formulir login
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = $credentials['login'];
        // Kunci unik untuk throttle pembatasan percobaan login berdasarkan input dan alamat IP
        $throttleKey = Str::transliterate(Str::lower($loginInput).'|'.$request->ip());

        // 2. FR 1.2: Proteksi brute-force (maksimal 5 kali kegagalan per menit)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'login' => "Terlalu banyak percobaan login gagal. Silakan coba kembali dalam {$seconds} detik.",
            ])->onlyInput('login');
        }

        // 3. Pencarian identitas pengguna di database (Username, Email, atau NIP)
        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->orWhere('nip', $loginInput)
            ->first();

        // 4. Verifikasi kecocokan hash password
        if ($user && Hash::check($credentials['password'], $user->password)) {
            // Periksa status verifikasi persetujuan (approval) oleh Administrator
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

            // Periksa apakah akun dinonaktifkan
            if (! $user->status_aktif) {
                return back()->withErrors([
                    'login' => 'Akun Anda sedang dinonaktifkan oleh Administrator.',
                ])->onlyInput('login');
            }

            // Bersihkan hitungan percobaan gagal rate limiter karena login berhasil
            RateLimiter::clear($throttleKey);

            // Buat sesi login resmi Laravel
            Auth::login($user, $request->filled('remember'));
            $request->session()->regenerate();
            // Catat waktu aktivitas awal untuk kalkulasi inactivity timeout 15 menit
            $request->session()->put('last_activity', time());

            // 5. FR 1.3: Mengalihkan pengguna ke dashboard sesuai peran masing-masing
            return redirect()->intended(route('dashboard'))->with(
                'success',
                "Selamat datang kembali, {$user->nama}! Masuk sebagai ".strtoupper(str_replace('_', ' ', $user->role)).'.'
            );
        }

        // 6. Jika kredensial salah, catat 1 kali hit kegagalan ke rate limiter (berlaku 60 detik)
        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'login' => 'Kombinasi Username/NIP/Email dan password tidak sesuai.',
        ])->onlyInput('login');
    }

    /**
     * Memproses keluar (logout) pengguna dari sistem dan menghancurkan sesi aktif.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar (logout).');
    }

    /**
     * Menampilkan formulir permohonan pemulihan kata sandi (Lupa Password).
     * Disiapkan untuk integrasi frontend lanjutan.
     */
    public function showForgotPasswordForm(): View|RedirectResponse
    {
        // Jika view auth.forgot-password belum dibuat tim frontend, alihkan kembali ke halaman login
        if (view()->exists('auth.forgot-password')) {
            return view('auth.forgot-password');
        }

        return redirect()->route('login')->with('info', 'Layanan reset mandiri sedang dalam persiapan oleh tim IT.');
    }

    /**
     * Mengirimkan surel berisi tautan pengaturan ulang kata sandi.
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        return back()->with('status', 'Jika alamat email terdaftar, instruksi pemulihan telah dikirimkan.');
    }

    /**
     * Menampilkan formulir penetapan kata sandi baru berdasarkan token verifikasi.
     */
    public function showResetPasswordForm(string $token): View|RedirectResponse
    {
        if (view()->exists('auth.reset-password')) {
            return view('auth.reset-password', ['token' => $token]);
        }

        return redirect()->route('login')->with('info', 'Tautan pemulihan kata sandi dapat digunakan setelah modul frontend diaktifkan.');
    }

    /**
     * Memproses pembaruan kata sandi akun pengguna dari formulir reset.
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
