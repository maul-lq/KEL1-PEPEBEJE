<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Controller Manajemen Pengguna (User Controller)
 *
 * Bertanggung jawab mengelola data seluruh pengguna sistem SIM-PBJ, mencakup 9 peran/aktor
 * (user/pengusul, staff bidang 2, wadir 2, perencanaan, ppbj, pp, ppk, perlengkapan, keuangan).
 * Mengakomodasi filtering berbasis peran/unit, pencarian kata kunci, serta penegakan aturan
 * bisnis FR 2.1 (batasan ketat 1 akun Pejabat Pengadaan / PP aktif di institusi).
 */
class UserController extends Controller
{
    /**
     * Menampilkan daftar pengguna dengan paginasi dan filter.
     *
     * Mendukung pemfilteran berdasarkan peran (role), unit kerja (kode_unit),
     * serta pencarian teks pada nama, username, NIP, atau alamat email.
     *
     * @param  Request  $request  Objek HTTP request berisi parameter filter dan kata kunci
     * @return JsonResponse Koleksi data pengguna terpaginasi beserta relasi unit kerjanya
     */
    public function index(Request $request): JsonResponse
    {
        // Memulai kueri eager loading relasi unit kerja untuk optimalisasi N+1 query
        $query = User::with('unit');

        // Filter spesifik berdasarkan peran pengguna (misal: 'ppk', 'pp', 'perencanaan')
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter berdasarkan unit kerja pengguna
        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        // Pencarian multi-kolom jika parameter pencarian diisi pengguna
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Membatasi hasil 15 record per halaman
        $users = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Membuat data pengguna baru dalam sistem.
     *
     * Memvalidasi keunikan NIP, username, dan email, kelengkapan nomor kontak,
     * serta menegakkan aturan FR 2.1: memastikan tidak ada akun Pejabat Pengadaan (PP)
     * aktif ganda dalam institusi.
     *
     * @param  Request  $request  Objek HTTP request berisi atribut pengguna baru
     * @return JsonResponse Data pengguna yang berhasil dibuat beserta kode HTTP 201
     *
     * @throws ValidationException Jika melanggar aturan batasan tunggal akun PP aktif
     */
    public function store(Request $request): JsonResponse
    {
        // Validasi format data dan keunikan kredensial pengguna
        $validated = $request->validate([
            'nip' => 'required|string|max:30|unique:users,nip',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:100|unique:users,email',
            'nama' => 'required|string|max:150',
            'password' => 'required|string|min:6',
            'jabatan' => 'required|string|max:100',
            'role' => 'required|string|in:user,staff_bidang_2,wadir_2,perencanaan,ppbj,pp,ppk,perlengkapan,keuangan',
            'status_aktif' => 'boolean',
            'no_hp' => 'required|string|max:20',
            'no_telp_kantor' => 'nullable|string|max:20',
            'kode_unit' => 'nullable|string|exists:units,kode_unit',
        ]);

        // Penegakan Aturan Bisnis FR 2.1: Batasan 1 user = 1 akun Pejabat Pengadaan (PP) aktif
        if ($validated['role'] === 'pp') {
            $existingPP = User::where('role', 'pp')->where('status_aktif', true)->exists();
            if ($existingPP) {
                throw ValidationException::withMessages([
                    'role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ]);
            }
        }

        // Menyimpan data pengguna ke tabel users (password otomatis di-hash via Model Mutator / Casts)
        $user = User::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil dibuat',
            'data' => $user->load('unit'),
        ], 201);
    }

    /**
     * Menampilkan detail profil dan akun pengguna tertentu.
     *
     * @param  User  $user  Model binding data pengguna yang dicari
     * @return JsonResponse Data lengkap pengguna beserta relasi unit kerja
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $user->load('unit'),
        ]);
    }

    /**
     * Memperbarui informasi profil atau kredensial pengguna yang ada.
     *
     * Mengabaikan ID pengguna saat memeriksa keunikan (NIP, username, email),
     * memastikan aturan batas 1 PP aktif tetap terjaga jika terjadi perubahan role/status aktif,
     * serta mengabaikan pembaruan kata sandi jika dikosongkan.
     *
     * @param  Request  $request  Objek HTTP request berisi perubahan data
     * @param  User  $user  Model binding pengguna yang akan diperbarui
     * @return JsonResponse Data pengguna yang telah diperbarui
     *
     * @throws ValidationException Jika pembaruan melanggar batas 1 PP aktif institusi
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Validasi data masukan dengan pengabaian keunikan terhadap record pengguna saat ini
        $validated = $request->validate([
            'nip' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user->id_user, 'id_user')],
            'username' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id_user, 'id_user')],
            'email' => ['sometimes', 'required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id_user, 'id_user')],
            'nama' => 'sometimes|required|string|max:150',
            'password' => 'sometimes|nullable|string|min:6',
            'jabatan' => 'sometimes|required|string|max:100',
            'role' => 'sometimes|required|string|in:user,staff_bidang_2,wadir_2,perencanaan,ppbj,pp,ppk,perlengkapan,keuangan',
            'status_aktif' => 'sometimes|boolean',
            'no_hp' => 'sometimes|required|string|max:20',
            'no_telp_kantor' => 'nullable|string|max:20',
            'kode_unit' => 'nullable|string|exists:units,kode_unit',
        ]);

        // Penegakan Aturan Bisnis FR 2.1: Batasan 1 user = 1 akun PP aktif
        if (isset($validated['role']) && $validated['role'] === 'pp' && ($validated['status_aktif'] ?? $user->status_aktif)) {
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

        // Hapus password dari array jika bernilai kosong agar tidak menimpa hash password lama
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        // Lakukan pembaruan atribut pengguna ke database
        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil diperbarui',
            'data' => $user->load('unit'),
        ]);
    }

    /**
     * Menghapus akun pengguna dari sistem.
     *
     * @param  User  $user  Model binding pengguna yang akan dihapus
     * @return JsonResponse Pesan konfirmasi penghapusan pengguna
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }
}
