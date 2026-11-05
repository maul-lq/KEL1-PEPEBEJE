<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controller Manajemen Pengguna Administrator (Admin UserController)
 *
 * Bertanggung jawab melayani fungsionalitas CRUD Pengguna (User Management) untuk role Admin:
 * - Menampilkan daftar seluruh pengguna sivitas PNJ dengan filter peran, unit kerja, status akun, dan pencarian multi-kolom.
 * - Membuat akun pengguna baru secara langsung dengan penetapan role definitif dan unit kerja.
 * - Memperbarui profil, kredensial, role, atau unit kerja pengguna.
 * - Menghapus akun pengguna dari sistem dengan proteksi pencegahan hapus akun sendiri (self-deletion guard).
 * - Menegakkan aturan institusi FR 2.1 (maksimal 1 akun Pejabat Pengadaan / PP aktif).
 */
class UserController extends Controller
{
    /**
     * Menampilkan daftar pengguna dengan paginasi, statistik akun, dan pemfilteran terintegrasi.
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = User::with(['unit', 'roleEntity']);

        // Filter berdasarkan peran pengguna
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter berdasarkan unit kerja
        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        // Filter status aktif (1 = aktif, 0 = nonaktif)
        if ($request->filled('status_aktif')) {
            $query->where('status_aktif', filter_var($request->status_aktif, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter status verifikasi registrasi
        if ($request->filled('status_registrasi')) {
            $query->where('status_registrasi', $request->status_registrasi);
        }

        // Pencarian multi-kolom
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('jabatan', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('nama')->paginate(15)->withQueryString();

        // Data pendukung untuk dropdown filter & modal CRUD
        $availableRoles = Role::orderBy('nama_role')->get();
        $availableUnits = Unit::orderBy('nama_unit')->get();

        // Statistik ringkas untuk kartu indikator
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status_aktif', true)->count(),
            'pending_approval' => User::where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU)->count(),
            'inactive_users' => User::where('status_aktif', false)->count(),
        ];

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $users,
                    'roles' => $availableRoles,
                    'units' => $availableUnits,
                    'stats' => $stats,
                ],
            ]);
        }

        return view('admin.users', compact('users', 'availableRoles', 'availableUnits', 'stats'));
    }

    /**
     * Membuat akun pengguna baru dalam sistem secara langsung oleh Administrator.
     *
     * @throws ValidationException Jika melanggar aturan batasan tunggal akun PP aktif (FR 2.1)
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Auto-fill username dari prefix email jika tidak dikirimkan eksplisit
        if (! $request->filled('username') && $request->filled('email')) {
            $request->merge([
                'username' => explode('@', $request->email)[0],
            ]);
        }

        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'nama' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6'],
            'jabatan' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string'],
            'id_role' => ['nullable', 'exists:roles,id'],
            'status_aktif' => ['nullable', 'boolean'],
            'status_registrasi' => ['nullable', 'string', 'in:menunggu_persetujuan,disetujui,ditolak'],
            'no_hp' => ['required', 'string', 'max:20'],
            'no_telp_kantor' => ['nullable', 'string', 'max:20'],
            'kode_unit' => ['nullable', 'string', 'exists:units,kode_unit'],
        ]);

        // Default status aktif bernilai true jika dibuat langsung oleh Admin
        $validated['status_aktif'] = $request->has('status_aktif') ? (bool) $request->status_aktif : true;

        if (empty($validated['status_registrasi'])) {
            $validated['status_registrasi'] = $validated['status_aktif']
                ? User::STATUS_REGISTRASI_DISETUJUI
                : User::STATUS_REGISTRASI_MENUNGGU;
        }

        // Sinkronisasi id_role jika belum terisi
        if (empty($validated['id_role'])) {
            $roleRecord = Role::where('nama_role', $validated['role'])->first();
            if ($roleRecord) {
                $validated['id_role'] = $roleRecord->id;
            }
        }

        // Penegakan Aturan Bisnis FR 2.1: Batasan 1 user = 1 akun Pejabat Pengadaan (PP) aktif
        if ($validated['role'] === 'pp' && $validated['status_aktif']) {
            $existingPP = User::where('role', 'pp')->where('status_aktif', true)->exists();
            if ($existingPP) {
                throw ValidationException::withMessages([
                    'role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ]);
            }
        }

        // Pengisian nama alias jika kosong
        $validated['name'] = $validated['nama'];

        $user = User::create($validated);

        // Catat jejak audit jika class AuditLog tersedia
        if (class_exists(AuditLog::class) && Auth::check()) {
            AuditLog::catat(
                Auth::id(),
                'CREATE',
                'users',
                (string) $user->id_user,
                null,
                json_encode(['nama' => $user->nama, 'username' => $user->username, 'role' => $user->role]),
                "Admin membuat akun pengguna baru: {$user->nama} ({$user->username})"
            );
        }

        $message = "Pengguna {$user->nama} berhasil ditambahkan.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $user->load('unit', 'roleEntity'),
            ], 201);
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    /**
     * Menampilkan detail profil dan akun pengguna tertentu.
     */
    public function show(Request $request, User $user): View|JsonResponse
    {
        $user->load('unit', 'roleEntity');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'data' => $user,
            ]);
        }

        return view('admin.users_show', compact('user'));
    }

    /**
     * Memperbarui informasi profil, peran, atau status akun pengguna.
     *
     * @throws ValidationException Jika pembaruan melanggar batas 1 PP aktif institusi (FR 2.1)
     */
    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user->id_user, 'id_user')],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id_user, 'id_user')],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id_user, 'id_user')],
            'nama' => ['required', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'min:6'],
            'jabatan' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string'],
            'id_role' => ['nullable', 'exists:roles,id'],
            'status_aktif' => ['nullable', 'boolean'],
            'status_registrasi' => ['nullable', 'string', 'in:menunggu_persetujuan,disetujui,ditolak'],
            'no_hp' => ['required', 'string', 'max:20'],
            'no_telp_kantor' => ['nullable', 'string', 'max:20'],
            'kode_unit' => ['nullable', 'string', 'exists:units,kode_unit'],
        ]);

        $validated['status_aktif'] = $request->has('status_aktif') ? (bool) $request->status_aktif : (bool) $user->status_aktif;

        // Sinkronisasi id_role jika berubah
        if (empty($validated['id_role'])) {
            $roleRecord = Role::where('nama_role', $validated['role'])->first();
            if ($roleRecord) {
                $validated['id_role'] = $roleRecord->id;
            }
        }

        // Penegakan Aturan Bisnis FR 2.1: Batasan 1 user = 1 akun PP aktif
        if ($validated['role'] === 'pp' && $validated['status_aktif']) {
            $existingPP = User::where('role', 'pp')
                ->where('status_aktif', true)
                ->where('id_user', '!=', $user->id_user)
                ->exists();
            if ($existingPP) {
                throw ValidationException::withMessages([
                    'role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ]);
            }
        }

        // Hapus password dari array jika dikosongkan agar hash password lama tidak tertimpa
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['name'] = $validated['nama'];

        $user->update($validated);

        if (class_exists(AuditLog::class) && Auth::check()) {
            AuditLog::catat(
                Auth::id(),
                'UPDATE',
                'users',
                (string) $user->id_user,
                null,
                json_encode(['nama' => $user->nama, 'role' => $user->role, 'status_aktif' => $user->status_aktif]),
                "Admin memperbarui data pengguna: {$user->nama} ({$user->username})"
            );
        }

        $message = "Data pengguna {$user->nama} berhasil diperbarui.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $user->load('unit', 'roleEntity'),
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    /**
     * Menghapus akun pengguna dari sistem.
     *
     * Dilengkapi proteksi pencegahan hapus akun sendiri (self-deletion guard).
     */
    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        // Proteksi: Admin tidak boleh menghapus akun yang sedang login
        if ($user->id_user === Auth::id()) {
            $errorMsg = 'Aksi ditolak: Anda tidak dapat menghapus akun Anda sendiri.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg,
                ], 422);
            }

            return redirect()->route('admin.users.index')->with('error', $errorMsg);
        }

        $userName = $user->nama;
        $userUsername = $user->username;

        $user->delete();

        if (class_exists(AuditLog::class) && Auth::check()) {
            AuditLog::catat(
                Auth::id(),
                'DELETE',
                'users',
                (string) $user->id_user,
                json_encode(['nama' => $userName, 'username' => $userUsername]),
                null,
                "Admin menghapus akun pengguna: {$userName} ({$userUsername})"
            );
        }

        $message = "Pengguna {$userName} berhasil dihapus dari sistem.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }
}
