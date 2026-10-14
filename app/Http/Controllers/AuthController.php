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

class AuthController extends Controller
{
    /**
     * Tampilkan halaman tes login.
     */
    public function showLoginForm(): View
    {
        return view('tes_login');
    }

    /**
     * Tampilkan halaman tes registrasi.
     */
    public function showRegistrationForm(): View
    {
        $units = Unit::all();

        return view('tes_registrasi', compact('units'));
    }

    /**
     * Proses pendaftaran user baru.
     * Menerapkan batasan 1 user = 1 akun PP (FR 2.1).
     */
    public function register(Request $request): RedirectResponse
    {
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

        // Default role: user (pemohon)
        $targetRole = $validated['role'] ?? 'user';

        // FR 2.1: Batasan 1 user = 1 akun Pejabat Pengadaan (PP) aktif
        if ($targetRole === 'pp') {
            $existingPP = User::where('role', 'pp')->where('status_aktif', true)->exists();
            if ($existingPP) {
                return back()->withErrors([
                    'role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ])->withInput();
            }
        }

        $validated['role'] = $targetRole;
        $validated['status_aktif'] = true; // Aktif untuk verifikasi prototipe

        User::create($validated);

        return redirect()->route('login')->with(
            'success',
            'Pendaftaran akun berhasil! Silakan login menggunakan Username, NIP, atau Email Anda.'
        );
    }

    /**
     * Proses login pengguna.
     * FR 1.1: Memasukkan username/nip/email dan password.
     * FR 1.2: Memvalidasi kredensial pengguna & proteksi rate limiting.
     * FR 1.3: Mengalihkan pengguna ke dashboard sesuai role.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = $credentials['login'];
        $throttleKey = Str::transliterate(Str::lower($loginInput).'|'.$request->ip());

        // FR 1.2: Proteksi brute-force login (maksimal 5 percobaan gagal per menit)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'login' => "Terlalu banyak percobaan login gagal. Silakan coba kembali dalam {$seconds} detik.",
            ])->onlyInput('login');
        }

        // Cari user berdasarkan username, email, atau nip
        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->orWhere('nip', $loginInput)
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            if (! $user->status_aktif) {
                return back()->withErrors([
                    'login' => 'Akun Anda sedang dinonaktifkan oleh Administrator.',
                ])->onlyInput('login');
            }

            // Bersihkan riwayat throttle saat berhasil
            RateLimiter::clear($throttleKey);

            Auth::login($user, $request->filled('remember'));
            $request->session()->regenerate();
            $request->session()->put('last_activity', time());

            // FR 1.3: Pengalihan pengguna ke dashboard sesuai role
            return redirect()->intended(route('home', ['role' => $user->role]))->with(
                'success',
                "Selamat datang kembali, {$user->nama}! Masuk sebagai ".strtoupper(str_replace('_', ' ', $user->role)).'.'
            );
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'login' => 'Kombinasi Username/NIP/Email dan password tidak sesuai.',
        ])->onlyInput('login');
    }

    /**
     * Proses logout pengguna.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar (logout).');
    }
}
