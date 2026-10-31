<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Menampilkan form pendaftaran
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Memproses pendaftaran pengguna baru
     */
    public function register(Request $request)
{
    // 1. Validasi input dari form
    $request->validate([
        'nip' => 'required|string|max:50',
        'username' => 'required|string|max:100|unique:users,username',
        'nama' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'unit_kerja' => 'required|string|max:255',
        'jabatan' => 'required|string|max:255',
        'no_hp' => 'required|string|max:20',
        'password' => 'required|string|min:8|confirmed',
        'kode_unit' => 'nullable|string|max:50', // Ubah menjadi nullable agar tidak wajib
    ]);

    // 2. Buat User baru (kode_unit otomatis diisi dari unit_kerja jika kosong)
    $user = User::create([
        'nip' => $request->nip,
        'username' => $request->username,
        'nama' => $request->nama,
        'email' => $request->email,
        'unit_kerja' => $request->unit_kerja,
        'kode_unit' => $request->kode_unit ?? strtoupper(substr($request->unit_kerja, 0, 10)), // Fallback otomatis
        'jabatan' => $request->jabatan,
        'no_hp' => $request->no_hp,
        'no_telp_kantor' => $request->no_telp_kantor,
        'password' => Hash::make($request->password),
        'role' => User::ROLE_USER,
        'status_aktif' => true,
        'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
    ]);

    Auth::login($user);

    return redirect()->route('dashboard')->with('success', 'Registrasi berhasil!');
}
}