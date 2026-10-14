<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $validated['role'] = $validated['role'] ?? 'user';
        $validated['status_aktif'] = true; // Langsung aktif untuk kemudahan pengujian prototype

        User::create($validated);

        return redirect()->route('login')->with(
            'success',
            'Pendaftaran akun berhasil! Silakan login menggunakan Username, NIP, atau Email Anda.'
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

        $loginInput = $credentials['login'];

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

            Auth::login($user, $request->filled('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

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
