<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis middleware CheckRole ini untuk menegakkan otorisasi berbasis RBAC permission dan memverifikasi status persetujuan serta keaktifan akun pada setiap request terproteksi.
 */

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Otorisasi Peran & Hak Akses (CheckRole)
 *
 * merancang middleware ini agar rute yang dilindungi dengan `role:...` tetap mendukung peran kustom dinamis
 * melalui pemetaan ke slug permission RBAC, sekaligus menolak akun yang belum disetujui admin atau nonaktif.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance middleware yang diproses oleh router Laravel.
 *
 * Contoh pemanggilan:
 * `Route::post('/kontrak-spk', ...)->middleware('role:spk.create');`
 */
class CheckRole
{
    /**
     * Pemetaan peran logis ke daftar permission RBAC agar peran kustom yang diberi hak akses serupa tetap lolos tanpa hardcode nama role.
     *
     * @var array<string, array<int, string>>
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
            'pp.monitoring_aktivitas',
            'spk.create',
            'pembayaran.pengajuan',
            'pembayaran.spp_terbitkan',
            'permohonan.split_jenis',
        ],
        'pp' => [
            'reviu.pp',
            'paket.view_assigned',
        ],
        'ppk' => [
            'verifikasi.ppk_approve',
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
     * Peta label cadangan untuk slug peran/izin apabila data label belum tersedia di basis data saat pengujian terisolasi.
     *
     * @var array<string, string>
     */
    protected const AUTHORIZATION_LABELS = [
        'admin' => 'Administrator Sistem',
        'user' => 'Pengusul / Jurusan / Unit Kerja',
        'staff_bidang_2' => 'Staff Bidang II (Umum & Keuangan)',
        'wadir_2' => 'Wakil Direktur II',
        'perencanaan' => 'Bagian Perencanaan',
        'ppbj' => 'Unit Pengadaan Barang & Jasa (PPBJ)',
        'pp' => 'Pejabat Pengadaan (PP)',
        'ppk' => 'Pejabat Pembuat Komitmen (PPK)',
        'perlengkapan' => 'Unit Perlengkapan & BMN',
        'keuangan' => 'Bagian Keuangan & Bendahara',
    ];

    /**
     * memvalidasi status autentikasi, status persetujuan admin, dan izin RBAC sebelum meneruskan request ke controller.
     *
     * @param  Request  $request  membaca objek request HTTP masuk untuk mendeteksi pengguna aktif dan apakah klien meminta JSON.
     * @param  Closure(Request): (Response)  $next  meneruskan eksekusi ke pipeline berikutnya hanya jika seluruh pemeriksaan akses lolos.
     * @param  string  ...$roles  menerima satu atau lebih nama permission maupun slug peran logis yang diizinkan mengakses rute.
     * @return Response mengembalikan respons controller, atau 401/403/redirect login jika otorisasi gagal.
     *
     * Contoh pemanggilan:
     * `$middleware->handle($request, $next, 'ppbj', 'perencanaan');`
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        // mengekstrak pengecekan user null terlebih dahulu karena klien JSON perlu menerima HTTP 401, bukan halaman login HTML.
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Halaman ini tidak dapat diakses karena Anda belum masuk ke sistem.',
                ], 401);
            }

            return redirect()->route('login');
        }

        // memblokir akun yang belum disetujui admin atau dinonaktifkan dengan 403 agar sesi lama/OAuth tidak bisa menembus modul operasional.
        if ($user->status_registrasi !== User::STATUS_REGISTRASI_DISETUJUI || ! $user->status_aktif) {
            $inactiveMessage = 'Halaman ini tidak dapat diakses karena akun Anda berstatus nonaktif atau belum disetujui oleh Administrator.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $inactiveMessage,
                ], 403);
            }

            abort(403, $inactiveMessage);
        }

        if (! empty($roles)) {
            $isAuthorized = false;
            $roleEntity = $user->roleEntity;

            foreach ($roles as $requiredRole) {
                // mengutamakan pengecekan langsung terhadap slug permission agar rute granular seperti 'spk.create' bekerja murni berbasis RBAC.
                if ($user->hasPermission($requiredRole)) {
                    $isAuthorized = true;
                    break;
                }

                // menerjemahkan parameter rute bertipe nama peran ke daftar permission ekuivalen supaya peran kustom dari admin tetap bisa mengakses modul.
                if (isset(self::ROLE_PERMISSIONS[$requiredRole]) && $user->hasAnyPermission(self::ROLE_PERMISSIONS[$requiredRole])) {
                    $isAuthorized = true;
                    break;
                }

                // menyediakan fallback ke slug role hanya jika tabel permission role tersebut masih kosong pada skenario pengujian terisolasi.
                if (($roleEntity?->permissions?->isEmpty() ?? true) && $user->hasRole($requiredRole)) {
                    $isAuthorized = true;
                    break;
                }
            }

            // menolak permintaan dengan 403 Forbidden serta menyebut otorisasi atau peran yang dibutuhkan dalam Bahasa Indonesia standar.
            if (! $isAuthorized) {
                $requiredLabels = $this->resolveRequiredAuthorizationLabels($roles);
                $deniedMessage = "Halaman ini tidak dapat diakses karena Anda tidak memiliki otorisasi {$requiredLabels}.";

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $deniedMessage,
                    ], 403);
                }

                abort(403, $deniedMessage);
            }
        }

        return $next($request);
    }

    /**
     * menerjemahkan daftar slug peran atau izin RBAC menjadi rangkaian label Bahasa Indonesia yang mudah dibaca pada pesan 403.
     *
     * @param  array<int, string>  $requiredEntries  daftar slug peran atau permission dari parameter middleware `role:...`.
     * @return string frasa gabungan label otorisasi yang dibutuhkan (dipisahkan dengan "atau").
     *
     * Contoh pemanggilan:
     * `$label = $this->resolveRequiredAuthorizationLabels(['ppbj', 'perencanaan']);`
     */
    protected function resolveRequiredAuthorizationLabels(array $requiredEntries): string
    {
        $cleanEntries = array_values(array_unique(array_filter(array_map('trim', $requiredEntries))));
        if ($cleanEntries === []) {
            return 'yang sesuai';
        }

        $permissionLabels = [];
        $roleLabels = [];

        try {
            $permissionLabels = Permission::whereIn('nama_permission', $cleanEntries)
                ->pluck('label', 'nama_permission')
                ->all();
            $roleLabels = Role::whereIn('nama_role', $cleanEntries)
                ->pluck('label', 'nama_role')
                ->all();
        } catch (\Throwable) {
            // mengabaikan gangguan query label RBAC agar mekanisme fallback peta konstanta tetap menghasilkan pesan 403 yang rapi.
        }

        $resolved = [];
        foreach ($cleanEntries as $entry) {
            $label = $permissionLabels[$entry]
                ?? $roleLabels[$entry]
                ?? self::AUTHORIZATION_LABELS[$entry]
                ?? str_replace(['.', '_'], ' ', $entry);
            $resolved[] = trim((string) $label);
        }

        $uniqueLabels = array_values(array_unique(array_filter($resolved)));
        $count = count($uniqueLabels);

        if ($count === 0) {
            return 'yang sesuai';
        }

        if ($count === 1) {
            return $uniqueLabels[0];
        }

        if ($count === 2) {
            return $uniqueLabels[0].' atau '.$uniqueLabels[1];
        }

        $last = array_pop($uniqueLabels);

        return implode(', ', $uniqueLabels).', atau '.$last;
    }
}
