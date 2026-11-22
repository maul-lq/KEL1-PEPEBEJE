<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller ApprovalRegistrasiController ini untuk menangani antrean persetujuan pendaftar akun baru oleh Administrator, penetapan peran tunggal, serta penolakan disertai alasan.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controller Persetujuan Registrasi Akun (ApprovalRegistrasiController)
 *
 * memisahkan pengelolaan antrean pendaftaran akun baru di controller ini agar Administrator
 * dapat menetapkan peran secara definitif saat ACC dan menegakkan batas maksimal 1 akun PP aktif.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance controller yang dipanggil oleh router grup `admin.persetujuan-registrasi.*`.
 *
 * Contoh pemanggilan:
 * `Route::post('/persetujuan-registrasi/{user}/acc', [ApprovalRegistrasiController::class, 'acc']);`
 */
class ApprovalRegistrasiController extends Controller
{
    /**
     * menampilkan daftar pendaftar yang masih menunggu persetujuan admin, riwayat keputusan sebelumnya, serta daftar role tersedia.
     *
     * @param  Request  $request  membaca header request untuk menentukan format keluaran View Blade atau JSON API.
     * @return View|JsonResponse mengembalikan view `admin.approval_registrasi` atau JSON daftar antrean, riwayat, dan role.
     *
     * Contoh pemanggilan:
     * `GET /admin/persetujuan-registrasi`
     */
    public function index(Request $request): View|JsonResponse
    {
        $pendingUsers = User::with('unit')
            ->where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU)
            ->latest()
            ->paginate(15);

        $historyUsers = User::with(['unit', 'adminApproval', 'roleEntity'])
            ->whereIn('status_registrasi', [User::STATUS_REGISTRASI_DISETUJUI, User::STATUS_REGISTRASI_DITOLAK])
            ->latest('tanggal_approval')
            ->take(20)
            ->get();

        $availableRoles = Role::orderBy('nama_role')->get();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'pending' => $pendingUsers,
                    'history' => $historyUsers,
                    'roles' => $availableRoles,
                ],
            ]);
        }

        return view('admin.approval_registrasi', compact('pendingUsers', 'historyUsers', 'availableRoles'));
    }

    /**
     * menyetujui pendaftaran akun baru, menetapkan `id_role` pilihan admin, mencatat audit log, dan mengirim notifikasi ke pemilik akun.
     *
     * @param  Request  $request  memvalidasi `id_role` atau slug `role` yang dipilih Administrator.
     * @param  User  $user  menerima model pendaftar yang akan diaktifkan melalui route model binding.
     * @return RedirectResponse|JsonResponse mengembalikan redirect dengan flash sukses atau JSON data akun yang telah disetujui.
     *
     * @throws ValidationException Jika admin belum memilih peran atau mencoba mengaktifkan akun Pejabat Pengadaan (PP) kedua.
     *
     * Contoh pemanggilan:
     * `POST /admin/persetujuan-registrasi/{user}/acc`
     */
    public function acc(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'id_role' => ['nullable', 'exists:roles,id'],
            'role' => ['nullable', 'string', 'exists:roles,nama_role'],
        ]);

        // mewajibkan minimal salah satu parameter role terisi supaya akun yang di-ACC tidak dibiarkan memakai role default tanpa keputusan admin.
        if (empty($validated['id_role']) && empty($validated['role'])) {
            throw ValidationException::withMessages([
                'role' => 'Administrator wajib memilihkan peran (role) untuk pengguna yang disetujui.',
            ]);
        }

        $role = ! empty($validated['id_role'])
            ? Role::findOrFail($validated['id_role'])
            : Role::where('nama_role', $validated['role'])->firstOrFail();

        // menegakkan aturan institusi FR 2.1 bahwa hanya boleh ada 1 akun Pejabat Pengadaan (PP) aktif di seluruh kampus.
        if ($role->nama_role === User::ROLE_PP) {
            $existingActivePP = User::withRole(User::ROLE_PP)
                ->where('status_aktif', true)
                ->where('id_user', '!=', $user->id_user)
                ->exists();

            if ($existingActivePP) {
                throw ValidationException::withMessages([
                    'id_role' => 'Batasan institusi: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi.',
                ]);
            }
        }

        $user->status_aktif = true;
        $user->status_registrasi = User::STATUS_REGISTRASI_DISETUJUI;
        $user->id_role = $role->id;
        $user->id_admin_approval = $admin?->id_user;
        $user->tanggal_approval = now();
        // menghapus alasan penolakan lama jika pendaftar sebelumnya pernah ditolak lalu disetujui ulang.
        $user->alasan_penolakan = null;
        $user->save();

        Cache::forget("force_logout_user_{$user->id_user}");

        AuditLog::catat(
            $admin?->id_user ?? $user->id_user,
            'REGISTRASI_USER_DISETUJUI',
            'users',
            $user->nip,
            'menunggu_persetujuan',
            'disetujui',
            "Admin menyetujui pendaftaran {$user->nama} ({$user->nip}) dan menetapkan peran {$role->label} ({$role->nama_role})."
        );

        app(PengadaanNotificationService::class)->kirimKeUser(
            $user,
            'Pendaftaran Akun SIM-PBJ Disetujui',
            "Selamat! Akun SIM-PBJ Anda telah diverifikasi dan disetujui oleh Administrator dengan peran {$role->label}. Anda kini dapat masuk ke sistem.",
            'verifikasi'
        );

        $successMsg = "Pendaftaran akun {$user->nama} berhasil disetujui dengan peran: {$role->label}.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
                'data' => $user->load(['unit', 'roleEntity', 'adminApproval']),
            ]);
        }

        return redirect()->route('admin.persetujuan-registrasi.index')->with('success', $successMsg);
    }

    /**
     * menolak pendaftaran akun baru dengan menyimpan alasan wajib, mencatat audit log, dan mengabarkan penolakan ke pendaftar.
     *
     * @param  Request  $request  mewajibkan field `alasan_penolakan` maksimal 500 karakter sebagai transparansi penolakan.
     * @param  User  $user  menerima model User pendaftar yang ditolak.
     * @return RedirectResponse|JsonResponse mengembalikan redirect dengan flash konfirmasi atau JSON status sukses.
     *
     * Contoh pemanggilan:
     * `POST /admin/persetujuan-registrasi/{user}/tolak`
     */
    public function tolak(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'alasan_penolakan' => ['required', 'string', 'max:500'],
        ], [
            'alasan_penolakan.required' => 'Catatan alasan penolakan pendaftaran wajib diisi oleh Administrator.',
        ]);

        // memastikan status_aktif bernilai false agar akun tertolak tidak bisa menembus login maupun middleware CheckRole.
        $user->status_aktif = false;
        $user->status_registrasi = User::STATUS_REGISTRASI_DITOLAK;
        $user->alasan_penolakan = $validated['alasan_penolakan'];
        $user->id_admin_approval = $admin?->id_user;
        $user->tanggal_approval = now();
        $user->forceFill(['remember_token' => Str::random(60)]);
        $user->save();

        Cache::put(
            "force_logout_user_{$user->id_user}",
            "Pendaftaran akun Anda ditolak oleh Administrator. Alasan: {$validated['alasan_penolakan']}",
            now()->addHours(24)
        );

        AuditLog::catat(
            $admin?->id_user ?? $user->id_user,
            'REGISTRASI_USER_DITOLAK',
            'users',
            $user->nip,
            'menunggu_persetujuan',
            'ditolak',
            "Admin menolak pendaftaran {$user->nama} ({$user->nip}). Alasan: {$validated['alasan_penolakan']}"
        );

        app(PengadaanNotificationService::class)->kirimKeUser(
            $user,
            'Pendaftaran Akun SIM-PBJ Ditolak',
            "Mohon maaf, pendaftaran akun SIM-PBJ Anda belum dapat disetujui oleh Administrator. Alasan penolakan: {$validated['alasan_penolakan']}.",
            'verifikasi'
        );

        $successMsg = "Pendaftaran akun {$user->nama} telah ditolak. Alasan: {$validated['alasan_penolakan']}.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
                'data' => $user->load(['unit', 'adminApproval']),
            ]);
        }

        return redirect()->route('admin.persetujuan-registrasi.index')->with('success', $successMsg);
    }
}
