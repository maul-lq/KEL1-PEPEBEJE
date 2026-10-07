<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        return view('tes_registrasi');
    }

    /**
     * Proses pendaftaran user baru.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'nama' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'jabatan' => ['required', 'string', 'max:100'],
            'unit_kerja' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20'],
            'no_telp_kantor' => ['nullable', 'string', 'max:20'],
        ]);

        // Role default pemohon pengadaan
        $validated['role'] = User::ROLE_USER_PENGAJU;

        // Akun baru wajib diverifikasi/disetujui oleh Super Admin sebelum aktif
        $validated['status_aktif'] = false;

        User::create($validated);

        return redirect()->route('login')->with(
            'success',
            'Pendaftaran akun berhasil! Akun Anda sedang menunggu verifikasi & persetujuan Super Admin sebelum dapat digunakan untuk login.'
        );
    }

    /**
     * Proses login pengguna.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $fieldType = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'nip';

        // Cek autentikasi
        if (Auth::attempt([$fieldType => $credentials['login'], 'password' => $credentials['password']])) {
            $user = Auth::user();

            // Cek apakah akun sudah disetujui / aktif
            if (! $user->status_aktif) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'login' => 'Akun Anda belum disetujui atau sedang dinonaktifkan oleh Administrator. Silakan hubungi Super Admin.',
                ])->onlyInput('login');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

        return back()->withErrors([
            'login' => 'Kombinasi NIP/Email dan password tidak sesuai.',
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
