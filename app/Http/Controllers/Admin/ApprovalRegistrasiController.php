<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controller Verifikasi & Persetujuan Registrasi Pengguna Baru (ApprovalRegistrasiController)
 *
 * Bertanggung jawab melayani wewenang Administrator:
 * - Meninjau daftar pendaftar akun baru yang berstatus 'menunggu_persetujuan'.
 * - Menyetujui (ACC) akun pengguna sekaligus memilihkan & menetapkan Role yang sesuai bagi pengguna tersebut.
 * - Menolak pendaftaran akun pengguna dengan catatan alasan penolakan wajib.
 * - Menegakkan aturan institusi FR 2.1 (maksimal 1 akun Pejabat Pengadaan / PP aktif).
 */
class ApprovalRegistrasiController extends Controller
{
    /**
     * Menampilkan antrean pendaftar akun yang menunggu persetujuan Admin beserta riwayat keputusan.
     */
    public function index(Request $request): View|JsonResponse
    {
        // 1. Ambil pendaftar yang sedang menunggu verifikasi/persetujuan
        $pendingUsers = User::with('unit')
            ->where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU)
            ->latest()
            ->paginate(15);

        // 2. Ambil riwayat pendaftaran yang telah diproses (Disetujui / Ditolak)
        $historyUsers = User::with(['unit', 'adminApproval', 'roleEntity'])
            ->whereIn('status_registrasi', [User::STATUS_REGISTRASI_DISETUJUI, User::STATUS_REGISTRASI_DITOLAK])
            ->latest('tanggal_approval')
            ->take(20)
            ->get();

        // 3. Ambil daftar seluruh role aktif untuk dropdown penugasan role oleh Admin
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

        return view('admin.tes_approval_registrasi', compact('pendingUsers', 'historyUsers', 'availableRoles'));
    }

    /**
     * Menyetujui (ACC) pendaftaran akun pengguna baru dan menetapkan peran (role) yang dipilihkan Admin.
     */
    public function acc(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        // 1. Validasi masukan role yang dipilihkan Admin
        $validated = $request->validate([
            'id_role' => ['nullable', 'exists:roles,id'],
            'role' => ['nullable', 'string', 'exists:roles,nama_role'],
        ]);

        if (empty($validated['id_role']) && empty($validated['role'])) {
            throw ValidationException::withMessages([
                'role' => 'Administrator wajib memilihkan peran (role) untuk pengguna yang disetujui.',
            ]);
        }

        $role = ! empty($validated['id_role'])
            ? Role::findOrFail($validated['id_role'])
            : Role::where('nama_role', $validated['role'])->firstOrFail();

        // 2. Penegakan Aturan FR 2.1: Batasan ketat maksimal 1 akun Pejabat Pengadaan (PP) aktif
        if ($role->nama_role === 'pp') {
            $existingActivePP = User::where('role', 'pp')
                ->where('status_aktif', true)
                ->where('id_user', '!=', $user->id_user)
                ->exists();

            if ($existingActivePP) {
                throw ValidationException::withMessages([
                    'role' => 'Batasan institusi: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ]);
            }
        }

        // 3. Mutakhirkan status pengguna menjadi aktif dan disetujui dengan peran yang ditetapkan
        $user->status_aktif = true;
        $user->status_registrasi = User::STATUS_REGISTRASI_DISETUJUI;
        $user->role = $role->nama_role;
        $user->id_role = $role->id;
        $user->id_admin_approval = $admin?->id_user;
        $user->tanggal_approval = now();
        $user->alasan_penolakan = null;
        $user->save();

        // 4. Catat jejak audit log persetujuan registrasi
        AuditLog::catat(
            $admin?->id_user ?? $user->id_user,
            'REGISTRASI_USER_DISETUJUI',
            'users',
            $user->nip,
            'menunggu_persetujuan',
            'disetujui',
            "Admin menyetujui pendaftaran {$user->nama} ({$user->nip}) dan menetapkan peran {$role->label} ({$role->nama_role})."
        );

        $successMsg = "Pendaftaran akun {$user->nama} berhasil disetujui (ACC) dengan peran: {$role->label}.";

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
     * Menolak pendaftaran akun pengguna baru dengan menyertakan catatan alasan penolakan wajib.
     */
    public function tolak(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        // 1. Validasi catatan alasan penolakan
        $validated = $request->validate([
            'alasan_penolakan' => ['required', 'string', 'max:500'],
        ], [
            'alasan_penolakan.required' => 'Catatan alasan penolakan pendaftaran wajib diisi oleh Administrator.',
        ]);

        // 2. Mutakhirkan status pendaftaran menjadi ditolak
        $user->status_aktif = false;
        $user->status_registrasi = User::STATUS_REGISTRASI_DITOLAK;
        $user->alasan_penolakan = $validated['alasan_penolakan'];
        $user->id_admin_approval = $admin?->id_user;
        $user->tanggal_approval = now();
        $user->save();

        // 3. Catat jejak audit log penolakan registrasi
        AuditLog::catat(
            $admin?->id_user ?? $user->id_user,
            'REGISTRASI_USER_DITOLAK',
            'users',
            $user->nip,
            'menunggu_persetujuan',
            'ditolak',
            "Admin menolak pendaftaran {$user->nama} ({$user->nip}). Alasan: {$validated['alasan_penolakan']}"
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
