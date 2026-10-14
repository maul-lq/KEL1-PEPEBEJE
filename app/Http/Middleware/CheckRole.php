<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckRole
 *
 * Berfungsi untuk memeriksa apakah pengguna yang sedang login memiliki salah satu
 * dari peran (role) yang diizinkan untuk mengakses endpoint atau rute tertentu.
 * Mendukung otorisasi 9 peran PBJ (user, staff_bidang_2, wadir_2, perencanaan,
 * ppbj, pp, ppk, perlengkapan, keuangan) sesuai NFR Role-Based Access Control (RBAC).
 */
class CheckRole
{
    /**
     * Menangani pemeriksaan otorisasi peran pada request yang masuk.
     *
     * @param  Closure(Request): (Response)  $next  Callback pipeline request berikutnya
     * @param  string  ...$roles  Daftar peran yang diperbolehkan mengakses rute ini
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 1. Periksa apakah pengguna sudah login ke dalam sistem
        if (! $request->user()) {
            // Jika permintaan berupa request API / JSON, kembalikan respons 401 Unauthenticated
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated: Silakan login terlebih dahulu.',
                ], 401);
            }

            // Jika permintaan berasal dari browser web, arahkan ke halaman form login simulasi
            return redirect()->route('login');
        }

        // 2. Periksa apakah peran pengguna saat ini ada di dalam daftar peran yang diizinkan
        if (! in_array($request->user()->role, $roles, true)) {
            // Format nama peran agar mudah dibaca di pesan kesalahan (misal: "STAFF BIDANG 2")
            $userRole = strtoupper(str_replace('_', ' ', $request->user()->role));

            // Jika request berupa API / JSON, kirimkan format respons error HTTP 403 Forbidden
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Akses Ditolak: Peran [{$userRole}] tidak memiliki wewenang untuk mengakses fungsi ini (FR 2 & NFR RBAC).",
                ], 403);
            }

            // Jika request berasal dari browser biasa, lempar abort 403 dengan pesan bahasa Indonesia
            abort(403, "Akses Ditolak: Peran [{$userRole}] tidak memiliki wewenang untuk mengakses halaman ini (FR 2 & NFR RBAC).");
        }

        // 3. Jika pengguna terotentikasi dan perannya sesuai, izinkan request lanjut ke controller
        return $next($request);
    }
}
