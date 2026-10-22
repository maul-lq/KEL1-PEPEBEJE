@extends('layouts.app')

@section('title', 'Persetujuan Registrasi Pengguna Baru - SIM-PBJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-8">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#1E3156]">Persetujuan Registrasi Pengguna Baru</h1>
            <p class="text-sm text-slate-500 mt-1">
                Verifikasi pendaftaran akun sivitas kampus Politeknik Negeri Jakarta, tetapkan hak peran sistem, atau tolak permohonan yang tidak memenuhi syarat.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                Antrean Menunggu: {{ $pendingUsers->total() }} Pendaftar
            </span>
        </div>
    </div>

    {{-- ANTREAN PENDAFTAR AKUN MENUNGGU --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="size-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Antrean Menunggu Verifikasi Administrator
        </h2>

        @if($pendingUsers->isEmpty())
            <div class="py-8 text-center text-slate-400">
                <svg class="mx-auto size-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="mt-2 text-sm font-medium">Saat ini tidak ada pendaftaran akun baru yang menunggu persetujuan.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
                            <th class="py-3 px-3">Pegawai / NIP</th>
                            <th class="py-3 px-3">Kontak & Akun</th>
                            <th class="py-3 px-3">Unit / Jabatan</th>
                            <th class="py-3 px-3">Waktu Daftar</th>
                            <th class="py-3 px-3 text-center">Persetujuan & Tetapkan Role</th>
                            <th class="py-3 px-3 text-center">Penolakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @foreach($pendingUsers as $user)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-3 align-top">
                                    <div class="font-semibold text-slate-900">{{ $user->nama }}</div>
                                    <div class="font-mono text-xs text-slate-500">NIP: {{ $user->nip }}</div>
                                </td>
                                <td class="py-4 px-3 align-top text-xs">
                                    <div><span class="font-semibold text-slate-600">Email:</span> {{ $user->email }}</div>
                                    <div><span class="font-semibold text-slate-600">Username:</span> {{ $user->username }}</div>
                                    <div class="text-slate-500">HP: {{ $user->no_hp }}</div>
                                </td>
                                <td class="py-4 px-3 align-top text-xs">
                                    <div class="font-semibold text-slate-800">{{ $user->unit->nama_unit ?? $user->kode_unit }}</div>
                                    <div class="text-slate-500">{{ $user->jabatan }}</div>
                                </td>
                                <td class="py-4 px-3 align-top text-xs text-slate-500">
                                    {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="py-4 px-3 align-top">
                                    <form action="{{ route('admin.persetujuan-registrasi.acc', $user->id_user) }}" method="POST" class="space-y-2">
                                        @csrf
                                        <select name="id_role" required class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">-- Tetapkan Role --</option>
                                            @foreach($availableRoles as $role)
                                                <option value="{{ $role->id }}">
                                                    {{ $role->label }} ({{ $role->nama_role }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                            ACC & Aktifkan
                                        </button>
                                    </form>
                                </td>
                                <td class="py-4 px-3 align-top">
                                    <form action="{{ route('admin.persetujuan-registrasi.tolak', $user->id_user) }}" method="POST" class="space-y-2">
                                        @csrf
                                        <textarea name="alasan_penolakan" rows="2" required placeholder="Alasan penolakan..."
                                                  class="w-full rounded-lg border border-slate-300 p-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-rose-500"></textarea>
                                        <button type="submit" class="w-full rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-700 transition-colors">
                                            Tolak Akun
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $pendingUsers->links() }}
            </div>
        @endif
    </div>

    {{-- RIWAYAT KEPUTUSAN REGISTRASI --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="size-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Riwayat Keputusan Registrasi Terakhir
        </h2>

        @if($historyUsers->isEmpty())
            <p class="text-sm text-slate-400 italic">Belum ada riwayat persetujuan atau penolakan akun.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 uppercase text-slate-500 font-semibold">
                            <th class="py-2.5 px-3">Pegawai / NIP</th>
                            <th class="py-2.5 px-3">Unit Kerja</th>
                            <th class="py-2.5 px-3">Status Keputusan</th>
                            <th class="py-2.5 px-3">Peran Ditetapkan</th>
                            <th class="py-2.5 px-3">Alasan / Catatan</th>
                            <th class="py-2.5 px-3">Verifikator Admin</th>
                            <th class="py-2.5 px-3">Waktu Keputusan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($historyUsers as $hist)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-3">
                                    <div class="font-medium text-slate-900">{{ $hist->nama }}</div>
                                    <div class="font-mono text-slate-400">{{ $hist->nip }}</div>
                                </td>
                                <td class="py-3 px-3">{{ $hist->unit->nama_unit ?? $hist->kode_unit }}</td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold {{ $hist->status === 'aktif' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ ucfirst($hist->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-medium uppercase text-blue-700">
                                    {{ $hist->roleEntity ? $hist->roleEntity->label : str_replace('_', ' ', $hist->role) }}
                                </td>
                                <td class="py-3 px-3 max-w-[200px] truncate text-slate-500">
                                    {{ $hist->alasan_penolakan ?? '-' }}
                                </td>
                                <td class="py-3 px-3 text-slate-600">{{ $hist->approver->nama ?? '-' }}</td>
                                <td class="py-3 px-3 text-slate-400">
                                    {{ $hist->updated_at ? $hist->updated_at->format('d/m/Y H:i') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
