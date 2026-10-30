@extends('layouts.app')

@section('title', 'Manajemen Role & Hak Akses - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6" x-data="{ addRoleModal: false }">

    {{-- HEADER --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                        Feature 31: Role-Based Access Control
                    </span>
                    <span class="text-xs text-slate-400 font-mono">RBAC Matriks SIM-PBJ</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Manajemen Role &amp; Hak Akses</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola master peran pengguna sivitas, tambah peran kustom, serta sesuaikan matriks hak akses fitur (permissions) secara aman.
                </p>
            </div>

            <button
                type="button"
                @click="addRoleModal = true"
                class="px-4 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-2 shrink-0"
            >
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Peran Baru</span>
            </button>
        </div>
    </div>

    {{-- STAT CARDS (4 Cards) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Peran Terdaftar"
            :value="$roles->count()"
            icon="document"
            color="navy"
            subtitle="Master peran dalam sistem"
        />

        <x-stat-card
            title="Peran Sistem (Terkunci)"
            :value="$roles->where('is_system', true)->count()"
            icon="check"
            color="blue"
            badge="Terkunci"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Peran standar institusi"
        />

        <x-stat-card
            title="Peran Kustom"
            :value="$roles->where('is_system', false)->count()"
            icon="users"
            color="amber"
            badge="Dinamis"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Peran kustom dapat disesuaikan"
        />

        <x-stat-card
            title="Total Modul Izin Fitur"
            :value="count($permissionsByModule)"
            icon="box"
            color="teal"
            subtitle="Grup wewenang operasional"
        />
    </div>

    {{-- MODAL TAMBAH ROLE --}}
    <div x-show="addRoleModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
        <div @click.away="addRoleModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-[#213153]">Tambah Peran (Role) Baru</h3>
                <button type="button" @click="addRoleModal = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('admin.roles.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Kode Role (Slug huruf kecil &amp; underscore)</label>
                    <input type="text" name="nama_role" value="{{ old('nama_role') }}" required placeholder="Contoh: auditor_internal"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Label Tampilan Role</label>
                    <input type="text" name="label" value="{{ old('label') }}" required placeholder="Contoh: Auditor Internal PBJ"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi Singkat Wewenang</label>
                    <textarea name="deskripsi" rows="2" placeholder="Deskripsi tugas dan tanggung jawab peran..."
                              class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">{{ old('deskripsi') }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Hak Akses Fitur (Permissions)</label>
                    <div class="space-y-3 max-h-60 overflow-y-auto border border-slate-200 p-3 rounded-xl bg-slate-50">
                        @foreach($permissionsByModule as $module => $modulePermissions)
                            <div>
                                <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wide mb-1">{{ $module }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pl-2">
                                    @foreach($modulePermissions as $perm)
                                        <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="mt-0.5 rounded text-[#0969da]">
                                            <span><strong>{{ $perm->label }}</strong> <span class="text-[10px] text-slate-400 font-mono">({{ $perm->slug }})</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" @click="addRoleModal = false" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                        Simpan Peran Baru
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL MASTER PERAN SISTEM & KUSTOM --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Daftar Peran Pengguna (Roles Directory)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Konfigurasi izin modul untuk setiap peran pengguna</p>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $roles->count() }} Peran</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold uppercase text-slate-500 tracking-wider">
                        <th class="px-5 py-3">Peran &amp; Slug</th>
                        <th class="px-5 py-3">Deskripsi Wewenang</th>
                        <th class="px-5 py-3">Tipe Peran</th>
                        <th class="px-5 py-3">Hak Akses Fitur</th>
                        <th class="px-5 py-3 text-center">Kelola Izin</th>
                        <th class="px-5 py-3 text-center">Hapus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach($roles as $role)
                        <tr class="hover:bg-slate-50/50 transition" x-data="{ editModal: false }">
                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-slate-900 text-sm">{{ $role->label }}</div>
                                <div class="font-mono text-xs text-slate-400 mt-0.5">{{ $role->nama_role }}</div>
                            </td>

                            <td class="px-5 py-4 align-top max-w-xs text-slate-600 text-xs">
                                {{ $role->deskripsi ?? 'Tidak ada deskripsi wewenang khusus.' }}
                            </td>

                            <td class="px-5 py-4 align-top">
                                @if($role->is_system)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 font-bold text-slate-700 border border-slate-200 text-[11px]">
                                        Peran Bawaan Sistem
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 font-bold text-[#0969da] border border-blue-200 text-[11px]">
                                        Peran Kustom
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="font-bold text-slate-800">{{ $role->permissions->count() }} Izin</span>
                                <div class="mt-1 flex flex-wrap gap-1 max-w-sm">
                                    @foreach($role->permissions->take(4) as $p)
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 font-mono">
                                            {{ $p->slug }}
                                        </span>
                                    @endforeach
                                    @if($role->permissions->count() > 4)
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-400">
                                            +{{ $role->permissions->count() - 4 }} lagi
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                @if($role->is_system && !in_array($role->nama_role, ['admin']))
                                    <span class="text-xs text-slate-400 italic">Sistem Terkunci</span>
                                @else
                                    <button
                                        type="button"
                                        @click="editModal = true"
                                        class="px-3 py-1 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                    >
                                        Ubah Matriks
                                    </button>

                                    {{-- MODAL UBAH ROLE --}}
                                    <div x-show="editModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 text-left" style="display: none;">
                                        <div @click.away="editModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                                <h3 class="text-base font-bold text-[#213153]">Ubah Matriks Izin: {{ $role->label }}</h3>
                                                <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600">
                                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>

                                            <form action="{{ route('admin.roles.update', $role->id) }}" method="POST" class="mt-4 space-y-4">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 mb-1">Label Tampilan Role</label>
                                                    <input type="text" name="label" value="{{ old('label', $role->label) }}" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi Wewenang</label>
                                                    <textarea name="deskripsi" rows="2" class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">{{ old('deskripsi', $role->deskripsi) }}</textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Hak Akses Fitur (Permissions)</label>
                                                    <div class="space-y-3 max-h-64 overflow-y-auto border border-slate-200 p-3 rounded-xl bg-slate-50">
                                                        @php
                                                            $currentPermIds = $role->permissions->pluck('id')->toArray();
                                                        @endphp
                                                        @foreach($permissionsByModule as $module => $modulePermissions)
                                                            <div>
                                                                <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wide mb-1">{{ $module }}</div>
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pl-2">
                                                                    @foreach($modulePermissions as $perm)
                                                                        <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                                                                   {{ in_array($perm->id, $currentPermIds) ? 'checked' : '' }}
                                                                                   class="mt-0.5 rounded text-[#0969da]">
                                                                            <span><strong>{{ $perm->label }}</strong></span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                                                    <button type="button" @click="editModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">Batal</button>
                                                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top text-center">
                                @if($role->is_system)
                                    <span class="text-xs text-slate-400 italic">Terkunci</span>
                                @else
                                    <form
                                        action="{{ route('admin.roles.destroy', $role->id) }}"
                                        method="POST"
                                        @submit.prevent="if(confirm('Yakin ingin menghapus peran kustom ini?')) $el.submit()"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
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
