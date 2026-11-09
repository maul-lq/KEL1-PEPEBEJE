@extends('layouts.app')

@section('title', 'Manajemen Pengguna (User Management) - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6" x-data="{
    addUserModal: false,
    editUserModal: false,
    deleteUserModal: false,
    editUser: {},
    deleteUser: {},
    openEdit(user) {
        this.editUser = Object.assign({}, user);
        this.editUserModal = true;
    },
    openDelete(user) {
        this.deleteUser = Object.assign({}, user);
        this.deleteUserModal = true;
    }
}">

    {{-- 1. HEADER --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400 font-mono">CRUD Pengguna Sivitas SIM-PBJ</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Manajemen Pengguna</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola seluruh akun sivitas Politeknik Negeri Jakarta, atur peran dan unit kerja, serta perbarui status aktif pengguna.
                </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <a href="{{ route('admin.persetujuan-registrasi.index') }}" 
                   class="px-3.5 py-2 rounded-xl border border-amber-300 bg-amber-50 text-xs font-bold text-amber-800 hover:bg-amber-100 transition inline-flex items-center gap-1.5 shadow-sm">
                    <svg class="size-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    <span>Antrean Approval ({{ $stats['pending_approval'] }})</span>
                </a>

                <button
                    type="button"
                    @click="addUserModal = true"
                    class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Tambah Pengguna Baru</span>
                </button>
            </div>
        </div>
    </div>

    {{-- NOTIFIKASI SUKSES / ERROR FLASH --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="size-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="size-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1 shadow-sm">
            <div class="font-bold flex items-center gap-1.5">
                <svg class="size-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Terjadi kesalahan validasi data:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 2. STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Pengguna"
            :value="$stats['total_users']"
            icon="users"
            color="navy"
            subtitle="Sivitas terdaftar di sistem"
        />

        <x-stat-card
            title="Pengguna Aktif"
            :value="$stats['active_users']"
            icon="check"
            color="emerald"
            badge="Aktif"
            badgeColor="bg-emerald-50 text-emerald-700 border-emerald-200"
            subtitle="Dapat login & beroperasi"
        />

        <x-stat-card
            title="Menunggu Persetujuan"
            :value="$stats['pending_approval']"
            icon="bell"
            color="amber"
            badge="Perlu ACC"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Pendaftaran akun baru"
            :href="route('admin.persetujuan-registrasi.index')"
        />

        <x-stat-card
            title="Akun Nonaktif / Ditolak"
            :value="$stats['inactive_users']"
            icon="clock"
            color="rose"
            subtitle="Akses login dinonaktifkan"
        />
    </div>

    {{-- 3. FILTER & SEARCH BAR --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            {{-- Search input --}}
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari Pengguna</label>
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari nama, NIP, username, atau email..."
                        class="w-full h-9.5 rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da] transition"
                    >
                    <svg class="size-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Role Filter --}}
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Peran / Role</label>
                <select name="role" class="w-full h-9.5 rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da] transition">
                    <option value="">Semua Role</option>
                    @foreach($availableRoles as $r)
                        <option value="{{ $r->nama_role }}" {{ request('role') == $r->nama_role ? 'selected' : '' }}>
                            {{ $r->label ?? ucfirst(str_replace('_', ' ', $r->nama_role)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Unit Filter --}}
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Unit Kerja / Jurusan</label>
                <select name="kode_unit" class="w-full h-9.5 rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da] transition">
                    <option value="">Semua Unit</option>
                    @foreach($availableUnits as $u)
                        <option value="{{ $u->kode_unit }}" {{ request('kode_unit') == $u->kode_unit ? 'selected' : '' }}>
                            {{ $u->nama_unit }} ({{ $u->kode_unit }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status & Buttons --}}
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Status Akun</label>
                    <select name="status_aktif" class="w-full h-9.5 rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da] transition">
                        <option value="">Semua</option>
                        <option value="1" {{ request('status_aktif') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ request('status_aktif') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <button type="submit" class="h-9.5 px-3.5 rounded-xl bg-[#213153] hover:bg-slate-800 text-white text-xs font-bold transition shadow-sm shrink-0">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'role', 'kode_unit', 'status_aktif', 'status_registrasi']))
                    <a href="{{ route('admin.users.index') }}" class="h-9.5 px-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold transition flex items-center justify-center shrink-0" title="Reset Filter">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- 4. DAFTAR PENGGUNA TABEL --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-[#ddeeff] text-[#213153] font-bold border-b border-blue-100">
                        <th class="px-4 py-3.5">Nama &amp; NIP</th>
                        <th class="px-4 py-3.5">Username &amp; Email</th>
                        <th class="px-4 py-3.5">Peran / Role</th>
                        <th class="px-4 py-3.5">Unit Kerja &amp; Jabatan</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            {{-- Nama & NIP --}}
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-[#213153] text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($user->nama, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-[#213153]">{{ $user->nama }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono">NIP: {{ $user->nip ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Username & Email --}}
                            <td class="px-4 py-3.5">
                                <div class="font-mono text-slate-700 font-semibold">{{ $user->username }}</div>
                                <div class="text-[11px] text-slate-500">{{ $user->email }}</div>
                                <div class="text-[10px] text-slate-400">Telp: {{ $user->no_hp ?? '-' }}</div>
                            </td>

                            {{-- Peran / Role --}}
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold 
                                    @if($user->role === 'admin') bg-rose-50 text-rose-700 border border-rose-200
                                    @elseif($user->role === 'ppk') bg-indigo-50 text-indigo-700 border border-indigo-200
                                    @elseif($user->role === 'pp') bg-purple-50 text-purple-700 border border-purple-200
                                    @elseif($user->role === 'ppbj') bg-blue-50 text-[#0969da] border border-blue-200
                                    @elseif(str_contains($user->role, 'wadir')) bg-teal-50 text-teal-700 border border-teal-200
                                    @else bg-slate-100 text-slate-700 border border-slate-200
                                    @endif">
                                    {{ $user->roleEntity->label ?? ucfirst(str_replace('_', ' ', $user->role)) }}
                                </span>
                            </td>

                            {{-- Unit Kerja & Jabatan --}}
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-slate-800">{{ $user->unit->nama_unit ?? $user->kode_unit ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $user->jabatan ?? '-' }}</div>
                            </td>

                            {{-- Status Akun --}}
                            <td class="px-4 py-3.5 text-center">
                                <div class="inline-flex flex-col items-center gap-1">
                                    @if($user->status_aktif)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            ● Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                            ○ Nonaktif
                                        </span>
                                    @endif

                                    @if($user->status_registrasi === 'menunggu_persetujuan')
                                        <span class="text-[9px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200">
                                            Menunggu ACC
                                        </span>
                                    @elseif($user->status_registrasi === 'ditolak')
                                        <span class="text-[9px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.2 rounded border border-rose-200">
                                            Registrasi Ditolak
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3.5 text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    {{-- Edit Button --}}
                                    <button
                                        type="button"
                                        @click="openEdit({{ json_encode($user) }})"
                                        class="px-2.5 py-1 rounded-lg bg-blue-50 text-[#0969da] hover:bg-blue-100 text-xs font-bold transition flex items-center gap-1"
                                        title="Edit Data Pengguna"
                                    >
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        <span>Edit</span>
                                    </button>

                                    {{-- Delete Button (Guarded for Self) --}}
                                    @if($user->id_user !== Auth::id())
                                        <button
                                            type="button"
                                            @click="openDelete({{ json_encode($user) }})"
                                            class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs font-bold transition flex items-center gap-1"
                                            title="Hapus Pengguna"
                                        >
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            <span>Hapus</span>
                                        </button>
                                    @else
                                        <span class="text-[10px] text-slate-400 italic px-2 py-1">Akun Anda</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <svg class="size-9 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <p class="text-xs font-medium">Tidak ada pengguna yang sesuai dengan kriteria pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi --}}
        @if($users->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL TAMBAH PENGGUNA --}}
    {{-- ========================================================================= --}}
    <div x-show="addUserModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
        <div @click.away="addUserModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-[#213153]">Tambah Pengguna Baru</h3>
                    <p class="text-xs text-slate-500">Daftarkan akun sivitas dan tetapkan peran serta unit kerjanya secara definitif.</p>
                </div>
                <button type="button" @click="addUserModal = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('admin.users.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NIP (Nomor Induk Pegawai) <span class="text-rose-500">*</span></label>
                        <input type="text" name="nip" value="{{ old('nip') }}" required placeholder="Contoh: 198501012015041001"
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama" value="{{ old('nama') }}" required placeholder="Contoh: Dr. Ir. Budi Santoso, M.T."
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                        <input type="text" name="username" value="{{ old('username') }}" required placeholder="Contoh: budi_santoso"
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Email Dinas <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="Contoh: budi.santoso@pnj.ac.id"
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Awal <span class="text-rose-500">*</span></label>
                        <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter..."
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jabatan Fungsional/Struktural <span class="text-rose-500">*</span></label>
                        <input type="text" name="jabatan" value="{{ old('jabatan') }}" required placeholder="Contoh: Dosen / Ketua Laboratorium"
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Peran (Role) Sistem <span class="text-rose-500">*</span></label>
                        <select name="role" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                            <option value="">-- Pilih Role Pengguna --</option>
                            @foreach($availableRoles as $r)
                                <option value="{{ $r->nama_role }}" {{ old('role') == $r->nama_role ? 'selected' : '' }}>
                                    {{ $r->label ?? ucfirst(str_replace('_', ' ', $r->nama_role)) }}
                                    @if($r->nama_role === 'pp') (Maksimal 1 akun aktif) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Unit Kerja / Jurusan</label>
                        <select name="kode_unit" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                            <option value="">-- Tidak Terikat Unit / Kantor Pusat --</option>
                            @foreach($availableUnits as $u)
                                <option value="{{ $u->kode_unit }}" {{ old('kode_unit') == $u->kode_unit ? 'selected' : '' }}>
                                    {{ $u->nama_unit }} ({{ $u->kode_unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor WhatsApp / HP Aktif <span class="text-rose-500">*</span></label>
                    <input type="text" name="no_hp" value="{{ old('no_hp') }}" required placeholder="Contoh: 081234567890"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                </div>

                <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="status_aktif" value="1" checked class="rounded border-slate-300 text-[#0969da] focus:ring-[#0969da]">
                        <span class="text-xs font-bold text-slate-700">Akun Langsung Aktif (Dapat Login)</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="addUserModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                            Simpan Pengguna
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL EDIT PENGGUNA --}}
    {{-- ========================================================================= --}}
    <div x-show="editUserModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
        <div @click.away="editUserModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-[#213153]">Edit Data Pengguna</h3>
                    <p class="text-xs text-slate-500">Perbarui profil, unit kerja, peran sistem, atau ubah kata sandi.</p>
                </div>
                <button type="button" @click="editUserModal = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form :action="'{{ url('admin/users') }}/' + editUser.id_user" method="POST" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NIP (Nomor Induk Pegawai) <span class="text-rose-500">*</span></label>
                        <input type="text" name="nip" x-model="editUser.nip" required
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama" x-model="editUser.nama" required
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                        <input type="text" name="username" x-model="editUser.username" required
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Email Dinas <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" x-model="editUser.email" required
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Reset Kata Sandi <span class="text-[11px] font-normal text-slate-400">(Kosongkan jika tidak diubah)</span>
                        </label>
                        <input type="password" name="password" minlength="6" placeholder="Minimal 6 karakter baru..."
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jabatan <span class="text-rose-500">*</span></label>
                        <input type="text" name="jabatan" x-model="editUser.jabatan" required
                               class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Peran (Role) Sistem <span class="text-rose-500">*</span></label>
                        <select name="role" x-model="editUser.role" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                            @foreach($availableRoles as $r)
                                <option value="{{ $r->nama_role }}">
                                    {{ $r->label ?? ucfirst(str_replace('_', ' ', $r->nama_role)) }}
                                    @if($r->nama_role === 'pp') (Maksimal 1 akun aktif) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Unit Kerja / Jurusan</label>
                        <select name="kode_unit" x-model="editUser.kode_unit" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                            <option value="">-- Tidak Terikat Unit / Kantor Pusat --</option>
                            @foreach($availableUnits as $u)
                                <option value="{{ $u->kode_unit }}">
                                    {{ $u->nama_unit }} ({{ $u->kode_unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor WhatsApp / HP Aktif <span class="text-rose-500">*</span></label>
                    <input type="text" name="no_hp" x-model="editUser.no_hp" required
                           class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                </div>

                <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="status_aktif" value="1" :checked="editUser.status_aktif" class="rounded border-slate-300 text-[#0969da] focus:ring-[#0969da]">
                        <span class="text-xs font-bold text-slate-700">Status Akun Aktif (Dapat Login)</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="editUserModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                            Perbarui Data Pengguna
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL KONFIRMASI HAPUS PENGGUNA --}}
    {{-- ========================================================================= --}}
    <div x-show="deleteUserModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
        <div @click.away="deleteUserModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-center gap-3 text-rose-600 mb-3">
                <div class="size-10 rounded-full bg-rose-50 flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800">Hapus Akun Pengguna</h3>
                    <p class="text-xs text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Apakah Anda yakin ingin menghapus akun pengguna <strong class="text-slate-900" x-text="deleteUser.nama"></strong> (<span class="font-mono text-slate-500" x-text="deleteUser.username"></span>)?
            </p>

            <form :action="'{{ url('admin/users') }}/' + deleteUser.id_user" method="POST" class="flex items-center justify-end gap-2">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteUserModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 shadow-sm transition">
                    Ya, Hapus Pengguna
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
