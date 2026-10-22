<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckRole
 *
 * Berfungsi untuk memeriksa apakah pengguna yang sedang login memiliki salah satu
 * dari peran (role) yang diizinkan untuk mengakses endpoint atau rute tertentu.
 * Mendukung otorisasi peran PBJ serta verifikasi status persetujuan registrasi
 * dan status keaktifan akun pengguna.
 */
class CheckRole
{
    /**
     * Pemetaan peran institusi ke daftar hak akses (permissions) yang setara.
     * Memungkinkan peran kustom baru (seperti 'sirajin' atau 'auditor') yang diberikan
     * hak akses oleh Administrator untuk mengakses modul yang dilindungi oleh middleware peran terkait.
     */
    protected const ROLE_PERMISSIONS = [
        'admin' => [
            'admin.approval_registrasi',
            'admin.role_manage',
            'admin.user_manage',
        ],
        'user' => [
            'permohonan.create',
            'permohonan.view_own',
            'permohonan.view_all',
            'permohonan.revisi',
        ],
        'staff_bidang_2' => [
            'verifikasi.bidang_2_view',
            'verifikasi.bidang_2_teruskan',
        ],
        'wadir_2' => [
            'verifikasi.wadir_2_view',
            'verifikasi.wadir_2_decide',
            'pembayaran.memo_wadir2',
        ],
        'perencanaan' => [
            'anggaran.mak_view',
            'anggaran.mak_manage',
            'anggaran.mak_alokasi',
            'anggaran.mak_lock',
            'permohonan.split_mak',
        ],
        'ppbj' => [
            'paket.create',
            'paket.view_all',
            'paket.reassign_ppk',
            'paket.split_jenis',
            'spk.create',
            'reminder.manage',
            'pembayaran.pengajuan',
            'pembayaran.spp_terbitkan',
            'permohonan.split_jenis',
        ],
        'pp' => [
            'reviu.pp',
            'paket.view_assigned',
        ],
        'ppk' => [
            'reviu.ppk',
            'paket.view_assigned',
            'pembayaran.memo_ppk',
        ],
        'perlengkapan' => [
            'penerimaan.view',
            'penerimaan.bast_create',
            'penerimaan.bast_sign',
        ],
        'keuangan' => [
            'pembayaran.pencairan_sp2d',
        ],
    ];

    /**
     * Menangani pemeriksaan otorisasi peran dan status akun pada request yang masuk.
     *
     * @param  Closure(Request): (Response)  $next  Callback pipeline request berikutnya
     * @param  string  ...$roles  Daftar peran yang diperbolehkan mengakses rute ini
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 1. Periksa apakah pengguna sudah login ke dalam sistem
        $user = $request->user();
        if (! $user) {
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

        // 2. Periksa apakah akun pengguna sudah disetujui oleh Administrator dan berstatus aktif
        if ($user->status_registrasi !== User::STATUS_REGISTRASI_DISETUJUI || ! $user->status_aktif) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Akun Anda belum disetujui oleh Administrator atau berstatus nonaktif.',
                ], 403);
            }

            abort(403, 'Akses Ditolak: Akun Anda belum disetujui oleh Administrator atau berstatus nonaktif.');
        }

        // 3. Periksa otorisasi peran atau hak akses fitur (permissions)
        if (! empty($roles)) {
            $isAuthorized = false;

            // A. Super Administrator ('admin') selalu memiliki hak akses penuh
            if ($user->role === User::ROLE_ADMIN || ($user->roleEntity && $user->roleEntity->nama_role === User::ROLE_ADMIN)) {
                $isAuthorized = true;
            }

            // B. Periksa apakah nama peran pengguna cocok langsung dengan salah satu peran yang diizinkan
            if (! $isAuthorized && $user->hasRole($roles)) {
                $isAuthorized = true;
            }

            // C. Jika pengguna mengemban peran kustom (atau peran lain), periksa apakah pengguna
            // memiliki hak akses (permission) yang setara dengan peran/modul yang dibutuhkan rute ini
            if (! $isAuthorized) {
                foreach ($roles as $requiredRole) {
                    // 1. Jika argumen rute langsung berupa nama permission yang dimiliki pengguna
                    if ($user->hasPermission($requiredRole)) {
                        $isAuthorized = true;
                        break;
                    }

                    // 2. Jika argumen rute berupa nama peran standar, periksa apakah pengguna
                    // memiliki salah satu hak akses fitur yang terkait dengan peran tersebut
                    if (isset(self::ROLE_PERMISSIONS[$requiredRole]) && $user->hasAnyPermission(self::ROLE_PERMISSIONS[$requiredRole])) {
                        $isAuthorized = true;
                        break;
                    }
                }
            }

            // D. Jika tidak memiliki peran maupun hak akses yang sesuai, tolak dengan 403 Forbidden
            if (! $isAuthorized) {
                $userRole = strtoupper(str_replace('_', ' ', $user->roleEntity ? $user->roleEntity->label : ($user->role ?? 'PENGGUNA')));

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
        }

        // 4. Jika pengguna terotentikasi dan perannya sesuai, izinkan request lanjut ke controller
        return $next($request);
    }
}
