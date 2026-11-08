<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Manajemen Role & Hak Akses Fitur (RoleController)
 *
 * Mengelola fungsionalitas CRUD Role dan pengaturan matriks hak akses fitur (permissions):
 * - Menampilkan daftar seluruh role sistem dan kustom beserta permissions yang aktif.
 * - Membuat role kustom baru beserta penugasan permissions awal.
 * - Mengedit metadata dan memperbarui permissions role.
 * - Menghapus role kustom (Role sistem institusi diproteksi dengan is_system = true).
 */
class RoleController extends Controller
{
    /**
     * Menampilkan daftar role, jumlah pengguna tiap role, dan matriks hak akses permissions.
     */
    public function index(Request $request): View|JsonResponse
    {
        $roles = Role::with(['permissions', 'users'])->withCount('users')->orderBy('nama_role')->get();
        $permissionsByModule = Permission::all()->groupBy('kategori_modul');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'roles' => $roles,
                    'permissions_by_module' => $permissionsByModule,
                ],
            ]);
        }

        return view('admin.roles', compact('roles', 'permissionsByModule'));
    }

    /**
     * Menyimpan role kustom baru beserta penugasan hak akses fitur yang dipilih.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        // 1. Validasi input pembuatan role baru
        $validated = $request->validate([
            'nama_role' => ['required', 'string', 'max:50', 'unique:roles,nama_role', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ], [
            'nama_role.regex' => 'Nama slug role hanya boleh menggunakan huruf kecil, angka, dan garis bawah (underscore).',
        ]);

        // 2. Buat record role baru (Role baru selalu berstatus kustom: is_system = false)
        $role = Role::create([
            'nama_role' => Str::lower($validated['nama_role']),
            'label' => $validated['label'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'is_system' => false,
        ]);

        // 3. Sinkronkan permissions fitur yang dicentang Admin
        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        // 4. Catat jejak audit log pembuatan role baru
        AuditLog::catat(
            $admin?->id_user ?? 1,
            'BUAT_ROLE_BARU',
            'roles',
            $role->nama_role,
            null,
            $role->nama_role,
            "Admin membuat role baru: {$role->label} ({$role->nama_role}) dengan ".count($validated['permissions'] ?? []).' hak akses fitur.'
        );

        $successMsg = "Role baru [{$role->label}] berhasil dibuat!";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
                'data' => $role->load('permissions'),
            ], 201);
        }

        return redirect()->route('admin.roles.index')->with('success', $successMsg);
    }

    /**
     * Memperbarui label, deskripsi, status proteksi sistem, dan matriks hak akses permissions dari suatu role.
     */
    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_system' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $updateData = [
            'label' => $validated['label'],
            'deskripsi' => $validated['deskripsi'] ?? null,
        ];

        // Role admin utama selalu diproteksi sebagai sistem, role lain dapat diatur status proteksinya oleh Admin
        if ($role->nama_role !== 'admin') {
            $updateData['is_system'] = $request->boolean('is_system');
        }

        // Update informasi label, deskripsi, dan status proteksi
        $role->update($updateData);

        // Sinkronisasi permissions fitur
        $role->permissions()->sync($validated['permissions'] ?? []);

        // Catat audit log pembaruan hak akses role
        AuditLog::catat(
            $admin?->id_user ?? 1,
            'UBAH_HAK_AKSES_ROLE',
            'roles',
            $role->nama_role,
            null,
            $role->nama_role,
            "Admin memperbarui hak akses fitur untuk role: {$role->label} ({$role->nama_role})."
        );

        $successMsg = "Hak akses fitur untuk role [{$role->label}] berhasil diperbarui!";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
                'data' => $role->load('permissions'),
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', $successMsg);
    }

    /**
     * Menghapus role kustom dari sistem.
     * Role administrator sistem utama diproteksi mutlak dari penghapusan.
     */
    public function destroy(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        // 1. Proteksi Role Administrator Utama: Tidak dapat dihapus
        if ($role->nama_role === 'admin') {
            $errorMsg = "Akses Ditolak: Role [{$role->label}] adalah Administrator utama sistem yang tidak boleh dihapus.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg,
                ], 403);
            }

            return back()->with('error', $errorMsg);
        }

        // 2. Proteksi Role dengan status is_system aktif
        if ($role->is_system) {
            $errorMsg = "Akses Ditolak: Role [{$role->label}] saat ini memiliki proteksi peran sistem. Buka modal 'Atur Hak Akses' lalu hilangkan centang proteksi sistem jika ingin menghapusnya.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg,
                ], 403);
            }

            return back()->with('error', $errorMsg);
        }

        // 3. Proteksi Pengguna Aktif: Role tidak dapat dihapus jika masih ada pengguna yang menggunakannya
        $usersCount = User::where('role', $role->nama_role)->orWhere('id_role', $role->id)->count();
        if ($usersCount > 0) {
            $errorMsg = "Tidak dapat menghapus role [{$role->label}] karena masih digunakan oleh {$usersCount} pengguna. Alihkan peran pengguna tersebut terlebih dahulu.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg,
                ], 422);
            }

            return back()->with('error', $errorMsg);
        }

        $namaRole = $role->label;
        $slugRole = $role->nama_role;

        // 4. Hapus relasi pivot permissions dan hapus role
        $role->permissions()->detach();
        $role->delete();

        // 5. Catat jejak audit log penghapusan role
        AuditLog::catat(
            $admin?->id_user ?? 1,
            'HAPUS_ROLE_KUSTOM',
            'roles',
            $slugRole,
            $slugRole,
            null,
            "Admin menghapus role: {$namaRole} ({$slugRole})."
        );

        $successMsg = "Role [{$namaRole}] berhasil dihapus.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', $successMsg);
    }
}
