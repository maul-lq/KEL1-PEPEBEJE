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
use App\Models\NotifikasiUser;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        // membaca boolean eksplisit dari input (termasuk hidden '0' saat checkbox dilepas) dan hanya memakai default aktif jika field sama sekali tidak dikirim.
        $validated['status_aktif'] = $request->exists('status_aktif')
            ? $request->boolean('status_aktif')
            : true;

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

        $wasActive = (bool) $user->status_aktif;

        // mengecek keberadaan kunci input agar pelepasan checkbox via hidden '0' terbaca false tanpa mengganggu pembaruan parsial API.
        $validated['status_aktif'] = $request->exists('status_aktif')
            ? $request->boolean('status_aktif')
            : $wasActive;

        // mencegah Administrator menonaktifkan akunnya sendiri yang sedang aktif login agar akses pengelolaan tidak terkunci.
        if ($user->id_user === Auth::id() && ! $validated['status_aktif']) {
            throw ValidationException::withMessages([
                'status_aktif' => 'Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang digunakan untuk masuk.',
            ]);
        }

        $effectiveRegistrationStatus = $validated['status_registrasi'] ?? $user->status_registrasi;

        // mewajibkan pendaftaran disetujui lebih dulu supaya aktivasi akun baru tetap melalui menu Persetujuan Registrasi dan penetapan peran.
        if ($validated['status_aktif'] && $effectiveRegistrationStatus !== User::STATUS_REGISTRASI_DISETUJUI) {
            throw ValidationException::withMessages([
                'status_aktif' => 'Akun yang belum disetujui atau ditolak hanya dapat diaktifkan melalui menu Persetujuan Registrasi.',
            ]);
        }

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

        $isNowActive = (bool) $user->status_aktif;

        // merotasi remember_token dan menandai cache sesi agar pengguna aktif yang dinonaktifkan/ditolak Admin langsung diputus ke halaman Login beserta pesannya.
        if (! $isNowActive || $user->status_registrasi !== User::STATUS_REGISTRASI_DISETUJUI) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();

            $reason = match ($user->status_registrasi) {
                User::STATUS_REGISTRASI_DITOLAK => 'Pendaftaran akun Anda ditolak oleh Administrator.'
                    .($user->alasan_penolakan ? " Alasan: {$user->alasan_penolakan}" : ''),
                User::STATUS_REGISTRASI_MENUNGGU => 'Pendaftaran akun Anda sedang menunggu verifikasi dan persetujuan dari Administrator.',
                default => 'Akun Anda telah dinonaktifkan oleh Administrator.',
            };

            Cache::put("force_logout_user_{$user->id_user}", $reason, now()->addHours(24));
        } else {
            Cache::forget("force_logout_user_{$user->id_user}");
        }

        // mengirim pemberitahuan ke kotak masuk aplikasi dan email resmi setiap kali status_aktif berubah (nonaktif atau aktif kembali).
        if ($wasActive !== $isNowActive) {
            $this->notifyAccountStatusChange($user, $isNowActive);
        }

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

    /**
     * mencatat notifikasi di aplikasi dan mengirim email pemberitahuan saat status aktif pengguna diubah Admin.
     *
     * @param  User  $user  menerima model pengguna sasaran perubahan status.
     * @param  bool  $isActive  menentukan isi pemberitahuan penonaktifan (`false`) atau pengaktifan kembali (`true`).
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notifyAccountStatusChange($user, false);`
     */
    protected function notifyAccountStatusChange(User $user, bool $isActive): void
    {
        $judul = $isActive
            ? 'Akun Anda Diaktifkan Kembali oleh Administrator'
            : 'Akun Anda Dinonaktifkan oleh Administrator';

        $pesan = $isActive
            ? 'Akun SIM-PBJ Anda telah diaktifkan kembali oleh Administrator. Anda sekarang dapat masuk kembali ke sistem.'
            : 'Akun SIM-PBJ Anda telah dinonaktifkan oleh Administrator. Selama dinonaktifkan, Anda tidak dapat masuk ke sistem.';

        try {
            $reminder = Reminder::create([
                'nomor_paket' => null,
                'tipe_reminder' => Reminder::TIPE_AKUN,
                'waktu_trigger' => now(),
                'is_recurring' => false,
                'is_active' => false,
                'last_triggered_at' => now(),
                'judul_alert' => $judul,
                'pesan_alert' => $pesan,
            ]);

            // menyimpan riwayat notifikasi tanpa memicu observer agar tidak mengirim email format pengingat paket ganda.
            NotifikasiUser::createQuietly([
                'id_user' => $user->id_user,
                'id_reminder' => $reminder->id_reminder,
                'is_read' => false,
                'is_dismissed' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat notifikasi perubahan status akun {$user->username}: {$e->getMessage()}");
        }

        if (empty($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            // mengirim email status akun secara langsung tanpa memedulikan opt-out pengingat pengadaan agar informasi akses akun selalu diterima pemiliknya.
            $user->notify(new AccountStatusNotification($isActive));
        } catch (\Throwable $e) {
            // menahan kegagalan SMTP agar pembaruan status keaktifan akun di database tidak ikut batal.
            Log::warning("Gagal mengirim email perubahan status akun ke {$user->email}: {$e->getMessage()}", [
                'user_id' => $user->id_user,
                'status_aktif' => $isActive,
            ]);
        }
    }
}
