<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller Admin\UserController ini untuk mengelola CRUD akun pengguna sivitas PNJ oleh Administrator beserta penjagaan batas 1 akun PP aktif dan larangan hapus akun sendiri.
 */

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
 * Controller Manajemen Pengguna (Admin\UserController)
 *
 * memusatkan seluruh operasi administrasi akun (daftar terfilter, tambah user langsung aktif, edit profil/role,
 * dan hapus akun) di resource controller ini di bawah izin `admin.user_manage`.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance resource controller yang didaftarkan melalui `Route::resource('users', ...)`.
 *
 * Contoh pemanggilan:
 * `Route::resource('users', AdminUserController::class)->middleware('role:admin.user_manage');`
 */
class UserController extends Controller
{
    /**
     * menampilkan daftar akun pengguna terpaginasi beserta filter peran, unit, status akun, pencarian multi-kolom, dan kartu statistik.
     *
     * @param  Request  $request  membaca query filter (`role`, `id_role`, `kode_unit`, `status_aktif`, `status_registrasi`, `search`).
     * @return View|JsonResponse mengembalikan view `admin.users` atau payload JSON daftar pengguna dan statistik.
     *
     * Contoh pemanggilan:
     * `GET /admin/users?role=ppk&status_aktif=1`
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = User::with(['unit', 'roleEntity']);

        if ($request->filled('role')) {
            $query->withRole($request->role);
        } elseif ($request->filled('id_role')) {
            $query->where('id_role', $request->id_role);
        }

        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        if ($request->filled('status_aktif')) {
            $query->where('status_aktif', filter_var($request->status_aktif, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('status_registrasi')) {
            $query->where('status_registrasi', $request->status_registrasi);
        }

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

        $availableRoles = Role::orderBy('nama_role')->get();
        $availableUnits = Unit::orderBy('nama_unit')->get();

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
     * membuat akun pengguna baru secara langsung dari panel Admin dengan menetapkan `id_role` dan memvalidasi keunikan akun PP aktif.
     *
     * @param  Request  $request  memvalidasi NIP, username, email, kata sandi, peran, unit kerja, dan status aktif.
     * @return RedirectResponse|JsonResponse mengembalikan redirect ke indeks pengguna atau JSON status 201 Created.
     *
     * @throws ValidationException Jika mencoba mengaktifkan lebih dari satu akun Pejabat Pengadaan (PP).
     *
     * Contoh pemanggilan:
     * `POST /admin/users`
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // menurunkan username otomatis dari prefix email jika admin mengosongkan kolom username pada modal cepat.
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
            'id_role' => ['required_without:role', 'nullable', 'exists:roles,id'],
            'role' => ['required_without:id_role', 'nullable', 'string', 'exists:roles,nama_role'],
            'status_aktif' => ['nullable', 'boolean'],
            'status_registrasi' => ['nullable', 'string', 'in:menunggu_persetujuan,disetujui,ditolak'],
            'no_hp' => ['required', 'string', 'max:20'],
            'kode_unit' => ['nullable', 'string', 'exists:units,kode_unit'],
        ]);

        // mengaktifkan akun secara default saat dibuat langsung oleh Admin kecuali Admin menonaktifkannya secara eksplisit.
        $validated['status_aktif'] = $request->has('status_aktif') ? (bool) $request->status_aktif : true;

        if (empty($validated['status_registrasi'])) {
            $validated['status_registrasi'] = $validated['status_aktif']
                ? User::STATUS_REGISTRASI_DISETUJUI
                : User::STATUS_REGISTRASI_MENUNGGU;
        }

        $roleRecord = ! empty($validated['id_role'])
            ? Role::findOrFail($validated['id_role'])
            : Role::where('nama_role', $validated['role'])->firstOrFail();

        $validated['id_role'] = $roleRecord->id;
        unset($validated['role']);

        // menegakkan aturan FR 2.1 bahwa institusi hanya mengizinkan tepat 1 akun Pejabat Pengadaan (PP) berstatus aktif.
        if ($roleRecord->nama_role === User::ROLE_PP && $validated['status_aktif']) {
            $existingPP = User::withRole(User::ROLE_PP)->where('status_aktif', true)->exists();
            if ($existingPP) {
                throw ValidationException::withMessages([
                    'id_role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi.',
                ]);
            }
        }

        $validated['name'] = $validated['nama'];

        $user = User::create($validated);

        if (class_exists(AuditLog::class) && Auth::check()) {
            AuditLog::catat(
                Auth::id(),
                'CREATE',
                'users',
                (string) $user->id_user,
                null,
                json_encode(['nama' => $user->nama, 'username' => $user->username, 'id_role' => $user->id_role, 'role' => $roleRecord->nama_role]),
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
     * menampilkan rincian profil dan relasi unit/peran milik satu pengguna tertentu.
     *
     * @param  Request  $request  memeriksa apakah klien membutuhkan respons JSON atau view Blade.
     * @param  User  $user  menerima instance User target via route model binding.
     * @return View|JsonResponse mengembalikan view `admin.users_show` atau JSON detail user.
     *
     * Contoh pemanggilan:
     * `GET /admin/users/{user}`
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
     * memperbarui data akun, mutasi peran, atau status keaktifan pengguna dengan tetap menjaga keunikan 1 akun PP aktif.
     *
     * @param  Request  $request  memvalidasi atribut unik dengan mengecualikan ID user yang sedang disunting.
     * @param  User  $user  menerima model User sasaran pembaruan.
     * @return RedirectResponse|JsonResponse mengembalikan redirect sukses atau JSON data user terbarui.
     *
     * @throws ValidationException Jika pembaruan peran/status melanggar batas 1 akun PP aktif.
     *
     * Contoh pemanggilan:
     * `PUT /admin/users/{user}`
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
            'id_role' => ['required_without:role', 'nullable', 'exists:roles,id'],
            'role' => ['required_without:id_role', 'nullable', 'string', 'exists:roles,nama_role'],
            'status_aktif' => ['nullable', 'boolean'],
            'status_registrasi' => ['nullable', 'string', 'in:menunggu_persetujuan,disetujui,ditolak'],
            'no_hp' => ['required', 'string', 'max:20'],
            'kode_unit' => ['nullable', 'string', 'exists:units,kode_unit'],
        ]);

        $validated['status_aktif'] = $request->has('status_aktif') ? (bool) $request->status_aktif : (bool) $user->status_aktif;

        $roleRecord = ! empty($validated['id_role'])
            ? Role::findOrFail($validated['id_role'])
            : Role::where('nama_role', $validated['role'])->firstOrFail();

        $validated['id_role'] = $roleRecord->id;
        unset($validated['role']);

        if ($roleRecord->nama_role === User::ROLE_PP && $validated['status_aktif']) {
            $existingPP = User::withRole(User::ROLE_PP)
                ->where('status_aktif', true)
                ->where('id_user', '!=', $user->id_user)
                ->exists();
            if ($existingPP) {
                throw ValidationException::withMessages([
                    'id_role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi.',
                ]);
            }
        }

        // menghapus key password saat kolom dikosongkan agar admin bisa mengedit data profil tanpa mereset kata sandi pengguna.
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
                json_encode(['nama' => $user->nama, 'id_role' => $user->id_role, 'role' => $roleRecord->nama_role, 'status_aktif' => $user->status_aktif]),
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
     * menghapus akun pengguna dari sistem dengan mencegah Administrator menghapus akunnya sendiri yang sedang aktif login.
     *
     * @param  Request  $request  mengevaluasi format respons HTML redirect atau JSON 422 bila guard self-deletion terpicu.
     * @param  User  $user  menerima model User yang akan dihapus.
     * @return RedirectResponse|JsonResponse mengembalikan redirect ke indeks pengguna atau JSON hasil penghapusan.
     *
     * Contoh pemanggilan:
     * `DELETE /admin/users/{user}`
     */
    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        // memblokir penghapusan diri sendiri (422 / flash error) agar sistem tidak pernah kehilangan seluruh akun admin yang aktif login.
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
