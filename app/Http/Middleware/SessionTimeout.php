<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis middleware SessionTimeout ini untuk memutus sesi akun yang dinonaktifkan/ditolak Admin dan menegakkan batas ketidakaktifan sesi pengguna (default 15 menit / 900 detik).
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Batas Waktu Ketidakaktifan & Integritas Sesi (SessionTimeout)
 *
 * memasang middleware ini di grup 'web' agar setiap sesi aktif yang akunnya dinonaktifkan Admin
 * maupun yang diam melebihi `session.idle_timeout` otomatis di-logout dan dialihkan dengan pesan jelas ke halaman Login.
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
     * memeriksa keaktifan akun serta selisih waktu sejak interaksi terakhir pengguna dan menghancurkan sesi bila melanggar batas idle.
     *
     * @param  Request  $request  memeriksa sesi aktif dan URL path request agar polling latar tidak mereset timer idle.
     * @param  Closure(Request): (Response)  $next  melanjutkan eksekusi request apabila akun aktif dan sesi belum kedaluwarsa.
     * @return Response mengembalikan respons lanjutan, atau JSON 401 / redirect login jika akun dinonaktifkan atau sesi berakhir.
     *
     * Contoh pemanggilan:
     * `$middleware->handle($request, $next);`
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            /** @var User|null $user */
            $user = Auth::user();
            $cacheKey = $user ? "force_logout_user_{$user->id_user}" : null;
            $cachedReason = $cacheKey ? Cache::get($cacheKey) : null;
            $wasVerifiedSession = $user && $request->session()->get('verified_active_user_id') === $user->id_user;
            $isAccountEligible = $user
                && $user->status_aktif
                && $user->status_registrasi === User::STATUS_REGISTRASI_DISETUJUI;

            // memutus sesi aktif pengguna seketika bila akun sebelumnya aktif atau ditandai dicabut Admin lalu menjadi nonaktif/ditolak.
            if ($user && ($cachedReason !== null || ($wasVerifiedSession && ! $isAccountEligible))) {
                if ($cacheKey) {
                    Cache::forget($cacheKey);
                }

                $flashType = $user->status_registrasi === User::STATUS_REGISTRASI_MENUNGGU ? 'warning' : 'error';
                $inactiveMessage = is_string($cachedReason) && $cachedReason !== ''
                    ? $cachedReason
                    : $this->resolveInactiveAccountMessage($user);

                return $this->terminateSessionWithNotice($request, $flashType, $inactiveMessage, 'account_inactive');
            }

            if ($isAccountEligible) {
                $request->session()->put('verified_active_user_id', $user->id_user);
            } elseif ($user && $request->session()->has('verified_active_user_id')) {
                // menghapus penanda pengguna sebelumnya bila sesi test atau guard berpindah ke pengguna lain yang belum disetujui.
                $request->session()->forget('verified_active_user_id');
            }

            $lastActivity = $request->session()->get('last_activity');
            // membaca batas idle tersendiri dari session.idle_timeout (900 detik) agar tidak berbenturan dengan umur penyimpanan session.lifetime.
            $idleMinutes = max(1, (int) config('session.idle_timeout', 15));
            $timeoutSeconds = $idleMinutes * 60;

            if ($lastActivity && (time() - (int) $lastActivity) > $timeoutSeconds) {
                $timeoutMessage = "Sesi Anda telah berakhir karena tidak ada aktivitas selama {$idleMinutes} menit. Silakan login kembali.";

                return $this->terminateSessionWithNotice($request, 'warning', $timeoutMessage, 'session_timeout');
            }

            // mengecualikan endpoint polling notifikasi agar skrip latar belakang tidak memperpanjang masa aktif sesi pengguna yang sedang idle.
            if (! $request->is('api/notifikasi-terbaru')) {
                $request->session()->put('last_activity', time());
            }
        }

        return $next($request);
    }

    /**
     * menyusun pesan penolakan yang sesuai dengan alasan penonaktifan atau status persetujuan akun.
     *
     * @param  User  $user  membaca `status_registrasi`, `alasan_penolakan`, dan `status_aktif` milik akun.
     * @return string kalimat penjelasan untuk ditampilkan pada halaman Login setelah sesi diputus.
     *
     * Contoh pemanggilan:
     * `$pesan = $this->resolveInactiveAccountMessage($user);`
     */
    protected function resolveInactiveAccountMessage(User $user): string
    {
        if ($user->status_registrasi === User::STATUS_REGISTRASI_MENUNGGU) {
            return 'Pendaftaran akun Anda sedang menunggu verifikasi dan persetujuan dari Administrator.';
        }

        if ($user->status_registrasi === User::STATUS_REGISTRASI_DITOLAK) {
            $alasan = $user->alasan_penolakan ? " Alasan: {$user->alasan_penolakan}" : '';

            return "Pendaftaran akun Anda ditolak oleh Administrator.{$alasan}";
        }

        return 'Akun Anda sedang dinonaktifkan oleh Administrator.';
    }

    /**
     * mengeluarkan sesi pengguna, meregenerasi CSRF, menyimpan pesan flash di sesi baru, lalu mengalihkan atau membalas JSON 401.
     *
     * @param  Request  $request  mengenali permintaan AJAX/Fetch (`expectsJson()`) vs navigasi browser standar.
     * @param  string  $flashKey  menentukan jenis kotak pesan (`warning` atau `error`) yang akan dirender pada halaman Login.
     * @param  string  $message  menyampaikan alasan pemutusan sesi ke pengguna.
     * @param  string  $reason  menandai penyebab pemutusan sesi secara terstruktur pada respons JSON.
     * @return Response mengembalikan JSON 401 (dengan flash sesi tersimpan untuk redirect tab) atau redirect langsung ke `route('login')`.
     *
     * Contoh pemanggilan:
     * `return $this->terminateSessionWithNotice($request, 'warning', $timeoutMessage, 'session_timeout');`
     */
    protected function terminateSessionWithNotice(Request $request, string $flashKey, string $message, string $reason): Response
    {
        Auth::logout();
        // meng-invalidate sesi dan meregenerasi CSRF token sekaligus supaya cookie sesi lama tidak bisa dipakai ulang.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            // menyimpan flash ke sesi baru hasil regenerasi agar pengalihan JS tab terbuka ke /login langsung menampilkan notifikasi penyebabnya.
            $request->session()->flash($flashKey, $message);

            return response()->json([
                'status' => 'error',
                'reason' => $reason,
                'message' => $message,
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()->route('login')->with($flashKey, $message);
    }
}
