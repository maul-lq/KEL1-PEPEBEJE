<?php

/**
 * Informasi File
 *
 * Penulis   : DendyRafi, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller ProfileController ini untuk menangani pembaruan profil & foto pengguna, penggantian kata sandi aman, serta penghapusan akun dengan verifikasi password.
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Controller Pengaturan Profil & Keamanan Akun (ProfileController)
 *
 * memusatkan fitur sensitif akun (ubah password dan hapus akun permanen) di controller ini
 * agar validasi konfirmasi sandi serta pembersihan foto profil di storage tertangani secara konsisten.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance controller yang melayani rute `profile.password.update` dan `profile.destroy`.
 *
 * Contoh pemanggilan:
 * `Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');`
 */
class ProfileController extends Controller
{
    /**
     * menampilkan halaman profil pengguna beserta status foto dan tautan Google OAuth miliknya.
     *
     * @return View mengembalikan tampilan Blade `profile.index` dengan objek `$user`.
     *
     * Contoh pemanggilan:
     * `$controller->index();`
     */
    public function index()
    {
        $user = Auth::user();

        return view('profile.index', compact('user'));
    }

    /**
     * memperbarui data diri pengguna (nama, email, NIP, nomor HP, jabatan) serta mengelola unggah atau hapus foto profil.
     *
     * @param  Request  $request  memvalidasi keunikan email dengan mengecualikan ID user aktif serta membatasi gambar profil maksimal 2 MB.
     * @return RedirectResponse mengembalikan redirect kembali ke halaman profil dengan pesan sukses.
     *
     * Contoh pemanggilan:
     * `$controller->update($request);`
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id_user.',id_user',
            'nip' => 'nullable|string|max:50',
            'no_hp' => 'nullable|string|max:20',
            'jabatan' => 'nullable|string|max:100',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // menghapus berkas foto lama di disk public sebelum mengosongkan kolom atau menimpanya agar storage server tidak penuh file yatim.
        if ($request->has('hapus_foto') && $request->hapus_foto == '1') {
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $user->foto_profil = null;
        }

        if ($request->hasFile('foto_profil')) {
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $path = $request->file('foto_profil')->store('foto-profil', 'public');
            $user->foto_profil = $path;
        }

        $user->nama = $request->nama;
        $user->email = $request->email;
        if ($request->has('nip')) {
            $user->nip = $request->nip;
        }
        if ($request->has('no_hp')) {
            $user->no_hp = $request->no_hp;
        }
        if ($request->has('jabatan')) {
            $user->jabatan = $request->jabatan;
        }

        $user->save();

        return back()->with('success', 'Profil berhasil diperbarui!');
    }

    /**
     * memverifikasi kata sandi lama dan menggantinya dengan kata sandi baru minimal 8 karakter yang berbeda dari sebelumnya.
     *
     * @param  Request  $request  memvalidasi `old_password`, `new_password`, dan `confirm_password`.
     * @return RedirectResponse mengembalikan redirect kembali dengan flag `password_modal_error` jika sandi lama keliru.
     *
     * Contoh pemanggilan:
     * `PUT /profil/password`
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|different:old_password',
            'confirm_password' => 'required|same:new_password',
        ], [
            'old_password.required' => 'Password lama wajib diisi.',
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal harus 8 karakter.',
            'new_password.different' => 'Password baru harus berbeda dari password lama.',
            'confirm_password.required' => 'Konfirmasi password baru wajib diisi.',
            'confirm_password.same' => 'Konfirmasi password tidak cocok dengan password baru.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // menyertakan flag sesi password_modal_error supaya Alpine.js langsung membuka kembali modal Ubah Password saat kembali ke halaman profil.
        if (! Hash::check($request->old_password, $user->password)) {
            return back()->with('error', 'Password lama yang Anda masukkan salah.')->with('password_modal_error', true);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'Password berhasil diubah!');
    }

    /**
     * menghapus akun pengguna secara permanen setelah memvalidasi ulang kata sandi konfirmasi dan membersihkan sesi aktif.
     *
     * @param  Request  $request  mewajibkan `password_konfirmasi` untuk mencegah penghapusan akun akibat sesi yang ditinggal terbuka.
     * @return RedirectResponse mengarahkan ke halaman login setelah logout dan invalidate sesi, atau kembali dengan `delete_modal_error`.
     *
     * Contoh pemanggilan:
     * `DELETE /profil/hapus-akun`
     */
    public function destroyAccount(Request $request)
    {
        $request->validate([
            'password_konfirmasi' => 'required|string',
        ], [
            'password_konfirmasi.required' => 'Password wajib diisi untuk konfirmasi penghapusan akun.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // menyertakan flag delete_modal_error agar modal konfirmasi hapus akun tetap terbuka dan menampilkan pesan kesalahan.
        if (! Hash::check($request->password_konfirmasi, $user->password)) {
            return back()->with('error', 'Password konfirmasi salah. Akun gagal dihapus.')->with('delete_modal_error', true);
        }

        if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
            Storage::disk('public')->delete($user->foto_profil);
        }

        // mengeluarkan sesi terlebih dahulu sebelum menghapus record user supaya token/cookie sesi tidak lagi terikat ke ID yang sudah lenyap.
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Akun Anda telah berhasil dihapus.');
    }
}
