<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller RoleController ini untuk mengelola CRUD peran RBAC dan matriks izin fitur serta menjaga proteksi peran sistem inti.
 */

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
 * Controller Manajemen Peran & Matriks Izin RBAC (RoleController)
 *
 * membangun controller ini agar Administrator dapat menambah peran kustom (termasuk role gabungan lintas modul),
 * mengatur centang permission per modul, dan mencegah penghapusan peran `admin` maupun peran yang masih dipakai pengguna.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance controller yang melayani grup rute `admin.roles.*`.
 *
 * Contoh pemanggilan:
 * `Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');`
 */
class RoleController extends Controller
{
    /**
     * menampilkan seluruh peran beserta jumlah pemegangnya dan matriks permission yang dikelompokkan per modul.
     *
     * @param  Request  $request  memeriksa apakah klien meminta view Blade atau respons JSON.
     * @return View|JsonResponse mengembalikan view `admin.roles` atau payload JSON daftar role dan permissions.
     *
     * Contoh pemanggilan:
     * `GET /admin/roles`
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
     * menyimpan peran kustom baru beserta sinkronisasi permission awal yang dicentang Administrator.
     *
     * @param  Request  $request  memvalidasi slug `nama_role` (snake_case), `label`, `deskripsi`, dan array ID `permissions`.
     * @return RedirectResponse|JsonResponse mengembalikan redirect sukses atau JSON status 201 dengan data role baru.
     *
     * Contoh pemanggilan:
     * `POST /admin/roles`
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'nama_role' => ['required', 'string', 'max:50', 'unique:roles,nama_role', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ], [
            'nama_role.regex' => 'Nama slug role hanya boleh menggunakan huruf kecil, angka, dan garis bawah (underscore).',
        ]);

        // selalu menandai peran buatan admin melalui UI sebagai kustom (is_system=false) agar dapat dikelola/dihapus bila suatu saat tidak terpakai.
        $role = Role::create([
            'nama_role' => Str::lower($validated['nama_role']),
            'label' => $validated['label'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

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
     * memperbarui metadata peran dan menyinkronkan ulang seluruh matriks permission RBAC milik peran tersebut.
     *
     * @param  Request  $request  menerima pembaruan label, deskripsi, opsi `is_system`, serta daftar ID permissions aktif.
     * @param  Role  $role  menerima model Role sasaran melalui route model binding.
     * @return RedirectResponse|JsonResponse mengembalikan redirect sukses atau JSON role terbaru beserta permissions.
     *
     * Contoh pemanggilan:
     * `PUT /admin/roles/{role}`
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

        // mengunci proteksi sistem untuk slug 'admin' secara permanen agar admin utama tidak pernah kehilangan proteksi.
        if ($role->nama_role !== 'admin') {
            $updateData['is_system'] = $request->boolean('is_system');
        }

        $role->update($updateData);
        $role->permissions()->sync($validated['permissions'] ?? []);

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
     * menghapus peran kustom setelah memastikan peran tersebut bukan admin utama, tidak terproteksi sistem, dan tidak lagi dipakai user aktif.
     *
     * @param  Request  $request  memeriksa tipe request untuk mengembalikan error 403/422 dalam JSON atau redirect flash.
     * @param  Role  $role  menerima model Role yang hendak dihapus.
     * @return RedirectResponse|JsonResponse mengembalikan redirect sukses/error atau respons JSON status penghapusan.
     *
     * Contoh pemanggilan:
     * `DELETE /admin/roles/{role}`
     */
    public function destroy(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

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

        // menolak penghapusan dengan 422 bila masih ada user yang menunjuk id_role ini agar tidak memicu kegagalan foreign key atau akun yatim tanpa peran.
        $usersCount = User::where('id_role', $role->id)->count();
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

        $role->permissions()->detach();
        $role->delete();

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
