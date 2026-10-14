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
        return view('tes_login');
    }

    /**
     * Menampilkan formulir registrasi akun pegawai baru.
     * Mengambil daftar master unit/jurusan kampus PNJ untuk opsi pilihan unit kerja.
     */
    public function showRegistrationForm(): View
    {
        // Mengambil semua data unit kerja kampus untuk pilihan dropdown registrasi
        $units = Unit::all();

        return view('tes_registrasi', compact('units'));
    }

    /**
     * Memproses penyimpanan akun pengguna baru dari formulir pendaftaran.
     * Menerapkan validasi keunikan NIP, email, username, dan batasan 1 akun PP aktif.
     */
    public function register(Request $request): RedirectResponse
    {
        // 1. Validasi seluruh data input pendaftaran
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
            'role' => ['nullable', 'string', 'in:user,staff_bidang_2,wadir_2,perencanaan,ppbj,pp,ppk,perlengkapan,keuangan'],
        ]);

        // Default peran pengguna baru adalah 'user' (unit pemohon usulan pengadaan)
        $targetRole = $validated['role'] ?? 'user';

        // 2. FR 2.1: Batasan ketat 1 user = 1 akun Pejabat Pengadaan (PP) aktif di institusi
        if ($targetRole === 'pp') {
            $existingPP = User::where('role', 'pp')->where('status_aktif', true)->exists();
            if ($existingPP) {
                return back()->withErrors([
                    'role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ])->withInput();
            }
        }

        $validated['role'] = $targetRole;
        $validated['status_aktif'] = true; // Langsung diaktifkan untuk kemudahan simulasi

        // 3. Simpan data user baru ke database
        User::create($validated);

        // Berikan pesan sukses dan alihkan pengguna ke formulir login
        return redirect()->route('login')->with(
            'success',
            'Pendaftaran akun berhasil! Silakan login menggunakan Username, NIP, atau Email Anda.'
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
            // Periksa apakah akun masih aktif
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
            return redirect()->intended(route('home', ['role' => $user->role]))->with(
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
}
