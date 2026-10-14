<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware SessionTimeout
 *
 * Mengimplementasikan NFR Keamanan Sesi (Inactivity Timeout 15 Menit).
 * Berfungsi untuk melacak waktu interaksi terakhir pengguna. Jika pengguna tidak melakukan
 * aktivitas apa pun selama lebih dari batas waktu yang ditentukan (default 15 menit),
 * sesi login akan dihancurkan secara otomatis demi mencegah pembajakan sesi di stasiun kerja.
 */
class SessionTimeout
{
    /**
     * Memeriksa durasi ketidakaktifan (idle) pengguna pada setiap request.
     *
     * @param  Closure(Request): (Response)  $next  Callback pipeline request berikutnya
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Pemeriksaan hanya dilakukan jika pengguna sedang berada dalam kondisi login
        if (Auth::check()) {
            // Mengambil timestamp waktu aktivitas terakhir yang tersimpan di session
            $lastActivity = $request->session()->get('last_activity');

            // Menghitung batas waktu timeout dalam detik berdasarkan konfigurasi session.lifetime (default 15 menit = 900 detik)
            $timeoutSeconds = (int) config('session.lifetime', 15) * 60;

            // 2. Jika sudah pernah ada aktivitas dan selisih waktu sekarang dengan aktivitas terakhir melampaui batas timeout
            if ($lastActivity && (time() - $lastActivity) > $timeoutSeconds) {
                // Logout pengguna dari sistem Laravel
                Auth::logout();

                // Batalkan seluruh data session lama
                $request->session()->invalidate();

                // Perbarui CSRF token untuk mencegah serangan CSRF
                $request->session()->regenerateToken();

                $timeoutMessage = 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.';

                // Jika permintaan datang dari client API / fetch JSON
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $timeoutMessage,
                    ], 401);
                }

                // Jika permintaan dari peramban web biasa, arahkan ke login dengan pesan error flash
                return redirect()->route('login')->withErrors([
                    'login' => $timeoutMessage,
                ]);
            }

            // 3. Perbarui timestamp 'last_activity' dengan waktu server saat ini
            $request->session()->put('last_activity', time());
        }

        // 4. Lanjutkan request ke tahapan atau controller berikutnya
        return $next($request);
    }
}
