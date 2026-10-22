@extends('layouts.app')

@section('title', 'Manajemen Role & Hak Akses - SIM-PBJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-8" x-data="{ addRoleModal: false }">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#1E3156]">Manajemen Role &amp; Hak Akses</h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola master peran pengguna sivitas, tambah peran kustom, serta sesuaikan matriks hak akses fitur (permissions) secara aman.
            </p>
        </div>
        <button type="button" @click="addRoleModal = true"
                class="inline-flex items-center gap-2 rounded-lg bg-[#0088E8] px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-600 transition-colors">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            + Tambah Peran Baru
        </button>
    </div>

    {{-- MODAL TAMBAH ROLE --}}
    <div x-show="addRoleModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
        <div @click.away="addRoleModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-bold text-slate-900">Tambah Peran (Role) Baru</h3>
                <button type="button" @click="addRoleModal = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('admin.roles.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kode Role (Slug huruf kecil &amp; underscore)</label>
                    <input type="text" name="nama_role" value="{{ old('nama_role') }}" required placeholder="Contoh: auditor_internal"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Label Tampilan Role</label>
                    <input type="text" name="label" value="{{ old('label') }}" required placeholder="Contoh: Auditor Internal PBJ"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi Singkat Wewenang</label>
                    <textarea name="deskripsi" rows="2" placeholder="Deskripsi tugas dan tanggung jawab peran..."
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('deskripsi') }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-2">Pilih Hak Akses Fitur (Permissions)</label>
                    <div class="space-y-3 max-h-60 overflow-y-auto border border-slate-200 p-3 rounded-lg bg-slate-50">
                        @foreach($permissionsByModule as $module => $modulePermissions)
                            <div>
                                <div class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-1">{{ $module }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pl-2">
                                    @foreach($modulePermissions as $perm)
                                        <label class="flex items-start gap-2 text-xs text-slate-600">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="mt-0.5 rounded text-blue-600">
                                            <span><strong>{{ $perm->label }}</strong> ({{ $perm->slug }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="addRoleModal = false" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-lg bg-[#0088E8] px-4 py-2 text-xs font-semibold text-white shadow hover:bg-blue-600">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>

    {{-- DAFTAR ROLE & MATRIKS HAK AKSES --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="size-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            Daftar Master Peran Sistem SIM-PBJ
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3 px-3">Kode &amp; Nama Role</th>
                        <th class="py-3 px-3">Tipe</th>
                        <th class="py-3 px-3">Pengguna</th>
                        <th class="py-3 px-3">Hak Akses Aktif</th>
                        <th class="py-3 px-3 text-center">Kelola Hak Akses</th>
                        <th class="py-3 px-3 text-center">Aksi Hapus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach($roles as $role)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-3 align-top">
                                <div class="font-semibold text-slate-900">{{ $role->label }}</div>
                                <div class="font-mono text-xs text-blue-600">{{ $role->nama_role }}</div>
                                <div class="text-xs text-slate-400 mt-1 max-w-[240px]">{{ $role->deskripsi }}</div>
                            </td>
                            <td class="py-4 px-3 align-top">
                                @if($role->is_system)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-800">
                                        Sistem (Terkunci)
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                        Kustom
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-3 align-top text-xs font-medium">
                                {{ $role->users_count }} Pengguna
                            </td>
                            <td class="py-4 px-3 align-top text-xs">
                                @if($role->nama_role === 'admin')
                                    <span class="font-bold text-indigo-700">Super Administrator (Seluruh Hak Akses)</span>
                                @elseif($role->permissions->isEmpty())
                                    <span class="text-slate-400 italic">Belum diatur</span>
                                @else
                                    <div class="flex flex-wrap gap-1 max-w-[320px]">
                                        @foreach($role->permissions->take(6) as $p)
                                            <span class="inline-flex rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-700 font-medium">
                                                {{ $p->label }}
                                            </span>
                                        @endforeach
                                        @if($role->permissions->count() > 6)
                                            <span class="text-[10px] text-slate-400 self-center">+{{ $role->permissions->count() - 6 }} lainnya</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-3 align-top text-center" x-data="{ editModal: false }">
                                @if($role->nama_role === 'admin')
                                    <span class="text-xs text-slate-400 italic">Penuh</span>
                                @else
                                    <button type="button" @click="editModal = true" class="rounded-lg bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors">
                                        Atur Matriks
                                    </button>

                                    {{-- MODAL EDIT PERMISSIONS --}}
                                    <div x-show="editModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 text-left" style="display: none;">
                                        <div @click.away="editModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                                <h3 class="text-lg font-bold text-slate-900">Atur Hak Akses: {{ $role->label }}</h3>
                                                <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600">
                                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>

                                            <form action="{{ route('admin.roles.update', $role->id) }}" method="POST" class="mt-4 space-y-4">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Tampilan Role (Label)</label>
                                                    <input type="text" name="label" value="{{ $role->label }}" required
                                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi Peran</label>
                                                    <textarea name="deskripsi" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">{{ $role->deskripsi }}</textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-2">Matriks Hak Akses Fitur</label>
                                                    <div class="space-y-3 max-h-64 overflow-y-auto border border-slate-200 p-3 rounded-lg bg-slate-50">
                                                        @php
                                                            $currentPermIds = $role->permissions->pluck('id')->toArray();
                                                        @endphp
                                                        @foreach($permissionsByModule as $module => $modulePermissions)
                                                            <div>
                                                                <div class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-1">{{ $module }}</div>
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pl-2">
                                                                    @foreach($modulePermissions as $perm)
                                                                        <label class="flex items-start gap-2 text-xs text-slate-600">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                                                                   {{ in_array($perm->id, $currentPermIds) ? 'checked' : '' }}
                                                                                   class="mt-0.5 rounded text-blue-600">
                                                                            <span><strong>{{ $perm->label }}</strong></span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                                                    <button type="button" @click="editModal = false" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Batal</button>
                                                    <button type="submit" class="rounded-lg bg-[#0088E8] px-4 py-2 text-xs font-semibold text-white shadow hover:bg-blue-600">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-3 align-top text-center">
                                @if($role->is_system)
                                    <span class="text-xs text-slate-400 italic">Terkunci</span>
                                @else
                                    <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus peran kustom ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition-colors">
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
