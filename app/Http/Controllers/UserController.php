<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest('id_user')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'roles' => [
                User::ROLE_USER_PENGAJU,
                User::ROLE_STAFF_BIDANG_2,
                User::ROLE_WADIR_2,
                User::ROLE_PERENCANAAN,
                User::ROLE_PPBJ,
                User::ROLE_PP,
                User::ROLE_PPK,
                User::ROLE_PERLENGKAPAN,
                User::ROLE_KEUANGAN,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'nama' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6'],
            'jabatan' => ['required', 'string', 'max:100'],
            'unit_kerja' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', Rule::in([
                User::ROLE_USER_PENGAJU,
                User::ROLE_STAFF_BIDANG_2,
                User::ROLE_WADIR_2,
                User::ROLE_PERENCANAAN,
                User::ROLE_PPBJ,
                User::ROLE_PP,
                User::ROLE_PPK,
                User::ROLE_PERLENGKAPAN,
                User::ROLE_KEUANGAN,
            ])],
            'status_aktif' => ['nullable', 'boolean'],
            'no_hp' => ['required', 'string', 'max:20'],
            'no_telp_kantor' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['status_aktif'] = $request->boolean('status_aktif', true);

        $user = User::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dibuat',
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'nip' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user->id_user, 'id_user')],
            'email' => ['sometimes', 'required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id_user, 'id_user')],
            'nama' => ['sometimes', 'required', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'min:6'],
            'jabatan' => ['sometimes', 'required', 'string', 'max:100'],
            'unit_kerja' => ['sometimes', 'required', 'string', 'max:100'],
            'role' => ['sometimes', 'required', 'string', Rule::in([
                User::ROLE_USER_PENGAJU,
                User::ROLE_STAFF_BIDANG_2,
                User::ROLE_WADIR_2,
                User::ROLE_PERENCANAAN,
                User::ROLE_PPBJ,
                User::ROLE_PP,
                User::ROLE_PPK,
                User::ROLE_PERLENGKAPAN,
                User::ROLE_KEUANGAN,
            ])],
            'status_aktif' => ['nullable', 'boolean'],
            'no_hp' => ['sometimes', 'required', 'string', 'max:20'],
            'no_telp_kantor' => ['nullable', 'string', 'max:20'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        if ($request->has('status_aktif')) {
            $validated['status_aktif'] = $request->boolean('status_aktif');
        }

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diperbarui',
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dihapus',
        ]);
    }
}
