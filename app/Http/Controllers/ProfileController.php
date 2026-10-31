<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman profil
     */
    public function index()
    {
        $user = Auth::user();
        return view('profile.index', compact('user'));
    }

    /**
     * Update Informasi Profil & Foto
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'nama'        => 'required|string|max:255',
            'email'       => 'required|email|max:255|unique:users,email,' . $user->id_user . ',id_user',
            'nip'         => 'nullable|string|max:50',
            'no_hp'       => 'nullable|string|max:20',
            'jabatan'     => 'nullable|string|max:100',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Hapus foto profil jika diminta
        if ($request->has('hapus_foto') && $request->hapus_foto == '1') {
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $user->foto_profil = null;
        }

        // Upload foto profil baru
        if ($request->hasFile('foto_profil')) {
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $path = $request->file('foto_profil')->store('foto-profil', 'public');
            $user->foto_profil = $path;
        }

        $user->nama = $request->nama;
        $user->email = $request->email;
        if ($request->has('nip')) $user->nip = $request->nip;
        if ($request->has('no_hp')) $user->no_hp = $request->no_hp;
        if ($request->has('jabatan')) $user->jabatan = $request->jabatan;

        $user->save();

        return back()->with('success', 'Profil berhasil diperbarui!');
    }

    /**
     * Logika Ubah Password (Overlay Frame 68)
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password'     => 'required|string',
            'new_password'     => 'required|string|min:8|different:old_password',
            'confirm_password' => 'required|same:new_password',
        ], [
            'old_password.required'     => 'Password lama wajib diisi.',
            'new_password.required'     => 'Password baru wajib diisi.',
            'new_password.min'          => 'Password baru minimal harus 8 karakter.',
            'new_password.different'    => 'Password baru harus berbeda dari password lama.',
            'confirm_password.required' => 'Konfirmasi password baru wajib diisi.',
            'confirm_password.same'     => 'Konfirmasi password tidak cocok dengan password baru.',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Cek password lama
        if (!Hash::check($request->old_password, $user->password)) {
            return back()->with('error', 'Password lama yang Anda masukkan salah.')->with('password_modal_error', true);
        }

        // Update password baru
        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'Password berhasil diubah!');
    }

    /**
     * Logika Hapus Akun (Overlay Frame 69)
     */
    public function destroyAccount(Request $request)
    {
        $request->validate([
            'password_konfirmasi' => 'required|string',
        ], [
            'password_konfirmasi.required' => 'Password wajib diisi untuk konfirmasi penghapusan akun.',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Cek password konfirmasi
        if (!Hash::check($request->password_konfirmasi, $user->password)) {
            return back()->with('error', 'Password konfirmasi salah. Akun gagal dihapus.')->with('delete_modal_error', true);
        }

        // Hapus foto profil jika ada
        if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
            Storage::disk('public')->delete($user->foto_profil);
        }

        // Logout dan Hapus Akun
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Akun Anda telah berhasil dihapus.');
    }
}