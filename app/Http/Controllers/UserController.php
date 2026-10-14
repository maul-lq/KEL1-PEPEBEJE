<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('unit');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('kode_unit')) {
            $query->where('kode_unit', $request->kode_unit);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
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

        // FR 2.1: Batasan 1 user = 1 akun Pejabat Pengadaan (PP) aktif
        if ($validated['role'] === 'pp') {
            $existingPP = User::where('role', 'pp')->where('status_aktif', true)->exists();
            if ($existingPP) {
                throw ValidationException::withMessages([
                    'role' => 'Batasan sistem: Hanya diizinkan 1 akun Pejabat Pengadaan (PP) aktif di institusi (FR 2.1).',
                ]);
            }
        }

        $user = User::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil dibuat',
            'data' => $user->load('unit'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $user->load('unit'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
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

        // FR 2.1: Batasan 1 user = 1 akun PP aktif
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

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil diperbarui',
            'data' => $user->load('unit'),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }
}
