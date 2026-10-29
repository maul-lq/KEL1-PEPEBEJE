<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Controller Otentikasi & Penautan Akun Google (GoogleAuthController)
 *
 * Mengelola alur integrasi Google OAuth 2.0 menggunakan Laravel Socialite:
 * 1. Alur Masuk (Login):
 *    - Mengarahkan tamu ke server Google OAuth (/auth/google).
 *    - Menangani callback autentikasi (/auth/google/callback).
 *    - Menegakkan larangan auto-registrasi akun baru: akun Google yang belum pernah ditautkan
 *      ke profil pengguna lokal akan ditolak secara eksplisit dengan pesan edukatif.
 *    - Memeriksa kelayakan status akun (status_registrasi = 'disetujui' dan status_aktif = true).
 *    - Menjalankan session regeneration demi mencegah serangan Session Fixation.
 *
 * 2. Alur Penautan Akun di Profil (Account Linking):
 *    - Memungkinkan pengguna terautentikasi menghubungkan akun Google miliknya (/profile/google/link).
 *    - Menangani callback penautan (/profile/google/callback).
 *    - Menegakkan integritas data 1:1: satu akun Google hanya dapat ditautkan ke satu akun lokal SIM-PBJ.
 *    - Menyimpan atribut google_id, google_email, google_avatar, dan tanggal penautan google_linked_at.
 *    - Memungkinkan pengguna memutuskan tautan Google kapan saja (/profile/google/unlink).
 */
class GoogleAuthController extends Controller
{
    /**
     * Mengarahkan pengguna (tamu) ke halaman otentikasi Google OAuth untuk alur login.
     *
     * URL redirect default mengacu pada rute 'auth.google.callback'.
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
     * Menangani callback otentikasi dari Google untuk alur login pengguna.
     *
     * Validasi ketat diterapkan di sini:
     * - Menolak pengguna jika membatalkan perizinan pada Google consent screen.
     * - Menolak jika akun Google belum pernah ditautkan (TIDAK MEMBUAT AKUN BARU OTOMATIS).
     * - Memvalidasi status approval registrasi dan status aktif akun pengguna lokal.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        // 1. Tangani pembatalan izin atau parameter error dari provider Google
        if ($request->has('error')) {
            Log::warning('Pengguna membatalkan atau menolak izin Google OAuth (Login)', [
                'error' => $request->query('error'),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Otentikasi dengan Google dibatalkan atau izin akses tidak diberikan.'
            );
        }

        // 2. Ambil data profil dari Google melalui Socialite
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

        // 3. Cari pengguna lokal berdasarkan ID unik Google (google_id)
        $googleId = (string) $googleUser->getId();
        $googleEmail = (string) $googleUser->getEmail();

        $user = User::where('google_id', $googleId)->first();

        // 4. PENEGAKAN KEAMANAN: Jangan buat akun baru otomatis jika belum ditautkan
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

        // 5. Validasi status verifikasi dan status aktif akun pengguna lokal
        if ($user->status_registrasi === User::STATUS_REGISTRASI_MENUNGGU) {
            return redirect()->route('login')->with(
                'status',
                'Akun Anda sedang menunggu verifikasi dan persetujuan (ACC) dari Administrator sebelum dapat digunakan.'
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

        // 6. Sinkronkan profil terbaru (email dan avatar) jika tersedia
        $user->update([
            'google_email' => $googleEmail,
            'google_avatar' => $googleUser->getAvatar(),
        ]);

        // 7. Otentikasi dan regenerasi sesi untuk mencegah Session Fixation
        Auth::login($user);
        $request->session()->regenerate();

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
     * Mengarahkan pengguna terotentikasi ke Google OAuth untuk menautkan akun Google miliknya.
     *
     * Menggunakan URI callback penautan khusus 'profile.google.callback'.
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
     * Menangani callback dari Google OAuth untuk proses penautan akun ke profil pengguna lokal.
     *
     * Validasi:
     * - Memastikan akun Google belum ditautkan ke akun lokal SIM-PBJ milik pengguna lain.
     * - Menyimpan identitas Google ke akun pengguna yang sedang aktif login.
     */
    public function handleGoogleLinkingCallback(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        // 1. Periksa jika pengguna membatalkan izin penautan di layar Google
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

        // 2. Ambil data profil akun Google dari Socialite
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

        // 3. Validasi keunikan penautan: pastikan google_id belum dipakai oleh akun lain
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

        // 4. Simpan identitas Google pada akun pengguna yang sedang login
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
     * Memutuskan tautan akun Google dari profil pengguna yang sedang login.
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
