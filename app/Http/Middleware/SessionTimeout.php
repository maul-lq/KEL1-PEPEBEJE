<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis middleware SessionTimeout ini untuk menegakkan batas ketidakaktifan sesi pengguna (default 15 menit) demi mencegah penyalahgunaan akun yang ditinggal login.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Batas Waktu Ketidakaktifan Sesi (SessionTimeout)
 *
 * memasang middleware ini di grup 'web' agar setiap sesi terautentikasi yang diam melebihi `session.lifetime`
 * otomatis di-logout dan di-invalidate, sambil mengecualikan endpoint polling latar notifikasi.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance middleware yang diproses oleh pipeline HTTP web.
 *
 * Contoh pemanggilan:
 * `$middleware->appendToGroup('web', SessionTimeout::class);`
 */
class SessionTimeout
{
    /**
     * memeriksa selisih waktu sejak interaksi terakhir pengguna dan menghancurkan sesi bila melampaui batas idle.
     *
     * @param  Request  $request  memeriksa sesi aktif dan URL path request agar polling latar tidak mereset timer idle.
     * @param  Closure(Request): (Response)  $next  melanjutkan eksekusi request apabila sesi belum melewati batas timeout.
     * @return Response mengembalikan respons lanjutan, atau JSON 401 / redirect login jika sesi telah kedaluwarsa.
     *
     * Contoh pemanggilan:
     * `$middleware->handle($request, $next);`
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = $request->session()->get('last_activity');
            // mengonversi session.lifetime (menit) ke detik dengan default aman 15 menit jika konfigurasi belum diisi.
            $timeoutSeconds = (int) config('session.lifetime', 15) * 60;

            if ($lastActivity && (time() - $lastActivity) > $timeoutSeconds) {
                Auth::logout();
                // meng-invalidate sesi dan meregenerasi CSRF token sekaligus supaya cookie sesi lama tidak bisa dipakai ulang.
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $timeoutMessage = 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $timeoutMessage,
                    ], 401);
                }

                return redirect()->route('login')->withErrors([
                    'login' => $timeoutMessage,
                ]);
            }

            // mengecualikan endpoint polling notifikasi agar skrip latar belakang tidak memperpanjang masa aktif sesi pengguna yang sedang idle.
            if (! $request->is('api/notifikasi-terbaru')) {
                $request->session()->put('last_activity', time());
            }
        }

        return $next($request);
    }
}
