<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller GoogleAuthController ini untuk menangani alur login via Google OAuth 2.0 (Socialite) serta penautan/pemutusan akun Google 1:1 pada halaman Profil pengguna.
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Controller Integrasi Google OAuth 2.0 (GoogleAuthController)
 *
 * memisahkan alur login tamu dan alur penautan akun profil ke dalam endpoint callback yang berbeda
 * agar akun Google yang belum pernah ditautkan tidak pernah membuat user baru otomatis (anti auto-provisioning).
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance controller yang melayani rute `/auth/google/*` dan `/profile/google/*`.
 *
 * Contoh pemanggilan:
 * `Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);`
 */
class GoogleAuthController extends Controller
{
    /**
     * mengarahkan pengguna tamu ke halaman persetujuan Google OAuth dengan callback khusus alur login.
     *
     * @return RedirectResponse mengembalikan redirect menuju layar Google OAuth atau kembali ke `/login` jika konfigurasi gagal.
     *
     * Contoh pemanggilan:
     * `GET /auth/google`
     */
    public function redirectToGoogle(): RedirectResponse
    {
        try {
            return Socialite::driver('google')
                ->redirectUrl(route('auth.google.callback'))
                ->redirect();
        } catch (Throwable $e) {
            Log::error('Gagal mengarahkan pengguna ke Google OAuth (Login): '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Terjadi kesalahan saat menghubungi layanan Google OAuth. Silakan coba kembali beberapa saat lagi.'
            );
        }
    }

    /**
     * memproses callback login Google, memblokir akun Google yang belum ditautkan, memverifikasi persetujuan admin, dan meregenerasi sesi.
     *
     * @param  Request  $request  memeriksa parameter `error` dari Google consent screen serta mengambil sesi setelah otentikasi.
     * @return RedirectResponse mengarahkan ke dashboard saat sah, atau kembali ke login dengan pesan penolakan yang jelas.
     *
     * Contoh pemanggilan:
     * `GET /auth/google/callback`
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            Log::warning('Pengguna membatalkan atau menolak izin Google OAuth (Login)', [
                'error' => $request->query('error'),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Otentikasi dengan Google dibatalkan atau izin akses tidak diberikan.'
            );
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('auth.google.callback'))
                ->user();
        } catch (Throwable $e) {
            Log::error('Gagal mengambil data pengguna dari Google OAuth (Login): '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Gagal menyelesaikan otentikasi dengan Google. Pastikan koneksi internet stabil dan coba kembali.'
            );
        }

        $googleId = (string) $googleUser->getId();
        $googleEmail = (string) $googleUser->getEmail();

        $user = User::where('google_id', $googleId)->first();

        // melarang auto-registrasi dari Google OAuth karena setiap akun SIM-PBJ harus melalui persetujuan NIP & penetapan role oleh Admin terlebih dahulu.
        if (! $user) {
            Log::notice('Percobaan login Google untuk akun yang belum ditautkan', [
                'google_id' => $googleId,
                'google_email' => $googleEmail,
            ]);

            return redirect()->route('login')->with(
                'error',
                'Akun Google ini ('.$googleEmail.') belum ditautkan ke akun SIM-PBJ mana pun. Silakan masuk menggunakan akun lokal Anda (Username / NIP / Email & Password) terlebih dahulu, lalu hubungkan akun Google melalui halaman Profil.'
            );
        }

        if ($user->status_registrasi === User::STATUS_REGISTRASI_MENUNGGU) {
            return redirect()->route('login')->with(
                'warning',
                'Pendaftaran akun Anda sedang menunggu verifikasi dan persetujuan dari Administrator.'
            );
        }

        if ($user->status_registrasi === User::STATUS_REGISTRASI_DITOLAK) {
            $alasan = $user->alasan_penolakan ? ': '.$user->alasan_penolakan : '.';

            return redirect()->route('login')->with(
                'error',
                'Pendaftaran akun Anda ditolak oleh Administrator'.$alasan
            );
        }

        if (! $user->status_aktif) {
            return redirect()->route('login')->with(
                'error',
                'Akun Anda saat ini sedang dinonaktifkan oleh Administrator. Silakan hubungi unit terkait.'
            );
        }

        $user->update([
            'google_email' => $googleEmail,
            'google_avatar' => $googleUser->getAvatar(),
        ]);

        Auth::login($user);
        // meregenerasi sesi setelah login OAuth untuk mencegah serangan Session Fixation dan mencatat timestamp awal idle timeout.
        $request->session()->regenerate();
        $request->session()->put('last_activity', time());
        $request->session()->put('verified_active_user_id', $user->id_user);

        Log::info('Pengguna berhasil masuk melalui Google OAuth', [
            'id_user' => $user->id_user,
            'username' => $user->username,
            'google_id' => $googleId,
        ]);

        return redirect()->intended(route('dashboard'))->with(
            'success',
            'Selamat datang kembali, '.$user->nama.'! Anda berhasil masuk menggunakan akun Google.'
        );
    }

    /**
     * mengarahkan pengguna yang sudah login ke Google OAuth dengan callback penautan profil (`profile.google.callback`).
     *
     * @return RedirectResponse mengembalikan redirect ke Google OAuth atau kembali ke halaman profil bila terjadi kesalahan koneksi.
     *
     * Contoh pemanggilan:
     * `GET /profile/google/link`
     */
    public function redirectToGoogleForLinking(): RedirectResponse
    {
        try {
            return Socialite::driver('google')
                ->redirectUrl(route('profile.google.callback'))
                ->redirect();
        } catch (Throwable $e) {
            Log::error('Gagal mengarahkan pengguna ke Google OAuth (Linking): '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('profile.index')->with(
                'error',
                'Gagal memulai proses penautan akun Google. Silakan coba kembali beberapa saat lagi.'
            );
        }
    }

    /**
     * menyelesaikan callback penautan akun Google di profil sambil menjamin satu Google ID hanya terikat pada satu akun lokal SIM-PBJ.
     *
     * @param  Request  $request  memeriksa penolakan izin dan mengambil data profil Google untuk ditautkan ke pengguna aktif.
     * @return RedirectResponse mengarahkan kembali ke halaman profil dengan pesan sukses atau alasan penolakan duplikasi tautan.
     *
     * Contoh pemanggilan:
     * `GET /profile/google/callback`
     */
    public function handleGoogleLinkingCallback(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if ($request->has('error')) {
            Log::warning('Pengguna membatalkan penautan akun Google di halaman profil', [
                'id_user' => $currentUser->id_user,
                'error' => $request->query('error'),
            ]);

            return redirect()->route('profile.index')->with(
                'error',
                'Proses penautan akun Google dibatalkan atau izin akses tidak diberikan.'
            );
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('profile.google.callback'))
                ->user();
        } catch (Throwable $e) {
            Log::error('Gagal mengambil data akun Google saat penautan profil: '.$e->getMessage(), [
                'id_user' => $currentUser->id_user,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('profile.index')->with(
                'error',
                'Gagal menghubungkan akun Google. Pastikan Anda memberikan izin akses dan koneksi internet stabil.'
            );
        }

        $googleId = (string) $googleUser->getId();
        $googleEmail = (string) $googleUser->getEmail();
        $googleAvatar = $googleUser->getAvatar();

        // menegakkan relasi 1:1 antara akun Google dan akun lokal supaya satu email Google tidak bisa mengambil alih dua akun peran berbeda.
        $isUsedByOther = User::where('google_id', $googleId)
            ->where('id_user', '!=', $currentUser->id_user)
            ->exists();

        if ($isUsedByOther) {
            Log::warning('Percobaan penautan akun Google yang sudah terikat ke pengguna lain', [
                'current_user_id' => $currentUser->id_user,
                'attempted_google_id' => $googleId,
                'attempted_google_email' => $googleEmail,
            ]);

            return redirect()->route('profile.index')->with(
                'error',
                'Akun Google ini ('.$googleEmail.') sudah ditautkan ke akun pengguna SIM-PBJ lain. Satu akun Google hanya dapat dihubungkan ke satu akun lokal.'
            );
        }

        $currentUser->update([
            'google_id' => $googleId,
            'google_email' => $googleEmail,
            'google_avatar' => $googleAvatar,
            'google_linked_at' => now(),
        ]);

        Log::info('Akun Google berhasil ditautkan ke pengguna SIM-PBJ', [
            'id_user' => $currentUser->id_user,
            'google_id' => $googleId,
            'google_email' => $googleEmail,
        ]);

        return redirect()->route('profile.index')->with(
            'success',
            'Akun Google ('.$googleEmail.') berhasil ditautkan ke akun SIM-PBJ Anda! Anda sekarang dapat masuk dalam satu klik menggunakan Google.'
        );
    }

    /**
     * memutus tautan akun Google dari profil pengguna aktif sehingga login hanya bisa memakai kredensial lokal.
     *
     * @param  Request  $request  menerima permintaan POST dari tombol putus tautan di halaman profil.
     * @return RedirectResponse mengarahkan kembali ke halaman profil dengan pesan sukses atau error bila belum tertaut.
     *
     * Contoh pemanggilan:
     * `POST /profile/google/unlink`
     */
    public function unlinkGoogle(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->isGoogleLinked()) {
            return redirect()->route('profile.index')->with(
                'error',
                'Akun Anda belum terhubung dengan akun Google mana pun.'
            );
        }

        $formerEmail = $currentUser->google_email;

        $currentUser->update([
            'google_id' => null,
            'google_email' => null,
            'google_avatar' => null,
            'google_linked_at' => null,
        ]);

        Log::info('Tautan akun Google berhasil diputuskan oleh pengguna', [
            'id_user' => $currentUser->id_user,
            'former_google_email' => $formerEmail,
        ]);

        return redirect()->route('profile.index')->with(
            'success',
            'Tautan akun Google berhasil diputuskan. Anda kini hanya dapat masuk menggunakan username/NIP/email dan password lokal.'
        );
    }
}
