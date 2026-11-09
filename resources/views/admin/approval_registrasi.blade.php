@extends('layouts.app')

@section('title', 'Persetujuan Registrasi Pengguna Baru - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400 font-mono">Modul Administrator</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Persetujuan Registrasi Pengguna Baru</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Verifikasi identitas sivitas kampus PNJ, tetapkan hak peran sistem, atau tolak pendaftaran yang tidak memenuhi syarat tata kelola.
                </p>
            </div>

            <div class="shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    Antrean Menunggu: {{ $pendingUsers->total() }} Pendaftar
                </span>
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
                Terjadi kesalahan:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- STAT CARDS (4 Cards) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Antrean Menunggu Verifikasi"
            :value="$pendingUsers->total()"
            icon="bell"
            color="amber"
            badge="Tindak Lanjut"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Pendaftar belum diverifikasi"
        />

        <x-stat-card
            title="Total Riwayat Keputusan"
            :value="$historyUsers->count()"
            icon="document"
            color="navy"
            subtitle="Pendaftaran telah diproses"
        />

        <x-stat-card
            title="Akun Disetujui (ACC)"
            :value="$historyUsers->where('status_aktif', true)->count()"
            icon="check"
            color="emerald"
            badge="Aktif"
            badgeColor="bg-emerald-50 text-emerald-700 border-emerald-200"
            subtitle="Sivitas kampus terdaftar sah"
        />

        <x-stat-card
            title="Pendaftaran Ditolak"
            :value="$historyUsers->where('status_registrasi', 'ditolak')->count()"
            icon="box"
            color="rose"
            badge="Ditolak"
            badgeColor="bg-rose-50 text-rose-700 border-rose-200"
            subtitle="Tidak memenuhi persyaratan"
        />
    </div>

    {{-- ANTREAN PENDAFTAR AKUN MENUNGGU --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Antrean Pendaftar Menunggu Verifikasi Admin</h3>
                <p class="text-xs text-slate-400 mt-0.5">Penetapan peran definitif sistem PBJ PNJ</p>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $pendingUsers->total() }} Antrean</span>
        </div>

        @if($pendingUsers->isEmpty())
            <div class="py-12 text-center text-slate-400">
                <svg class="mx-auto size-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="mt-2 text-xs font-medium text-slate-500">Saat ini tidak ada pendaftaran akun baru yang menunggu persetujuan.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold uppercase text-slate-500 tracking-wider">
                            <th class="px-5 py-3">Pegawai / NIP</th>
                            <th class="px-5 py-3">Kontak &amp; Akun</th>
                            <th class="px-5 py-3">Unit / Jabatan</th>
                            <th class="px-5 py-3">Waktu Daftar</th>
                            <th class="px-5 py-3 text-center">Persetujuan &amp; Tetapkan Role</th>
                            <th class="px-5 py-3 text-center">Penolakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($pendingUsers as $user)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-4 align-top">
                                    <div class="font-bold text-slate-900">{{ $user->nama }}</div>
                                    <div class="font-mono text-xs text-slate-400 mt-0.5">NIP: {{ $user->nip }}</div>
                                </td>
                                <td class="px-5 py-4 align-top text-xs space-y-0.5">
                                    <div><span class="font-semibold text-slate-500">Email:</span> {{ $user->email }}</div>
                                    <div><span class="font-semibold text-slate-500">Username:</span> {{ $user->username }}</div>
                                    <div class="text-slate-600 font-mono">HP: {{ $user->no_hp }}</div>
                                    @if($user->no_telp_kantor)
                                        <div class="text-slate-400 font-mono text-[11px]">Telp: {{ $user->no_telp_kantor }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top text-xs">
                                    <div class="font-bold text-[#213153]">{{ $user->unit->nama_unit ?? $user->kode_unit }}</div>
                                    <div class="text-slate-500 mt-0.5">{{ $user->jabatan }}</div>
                                </td>
                                <td class="px-5 py-4 align-top text-xs text-slate-400 font-mono">
                                    {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-5 py-4 align-top max-w-xs">
                                    <form action="{{ route('admin.persetujuan-registrasi.acc', $user->id_user) }}" method="POST" class="space-y-2">
                                        @csrf
                                        <select name="id_role" required class="w-full h-9 rounded-lg border border-slate-200 px-2.5 text-xs bg-white text-slate-800 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]">
                                            <option value="">-- Tetapkan Role --</option>
                                            @foreach($availableRoles as $role)
                                                <option value="{{ $role->id }}">
                                                    {{ $role->label }} ({{ $role->nama_role }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="w-full h-8 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition">
                                            ACC &amp; Aktifkan Akun
                                        </button>
                                    </form>
                                </td>
                                <td class="px-5 py-4 align-top max-w-xs">
                                    <form action="{{ route('admin.persetujuan-registrasi.tolak', $user->id_user) }}" method="POST" class="space-y-2">
                                        @csrf
                                        <textarea name="alasan_penolakan" rows="2" required placeholder="Tuliskan alasan penolakan..." class="w-full rounded-lg border border-slate-200 p-2 text-xs bg-white text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"></textarea>
                                        <button type="submit" class="w-full h-8 rounded-lg bg-rose-600 px-3 text-xs font-bold text-white shadow-xs hover:bg-rose-700 transition">
                                            Tolak Pendaftaran
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100">
                {{ $pendingUsers->links() }}
            </div>
        @endif
    </div>

    {{-- RIWAYAT KEPUTUSAN REGISTRASI TERAKHIR --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Riwayat Keputusan Registrasi Terakhir</h3>
                <p class="text-xs text-slate-400 mt-0.5">Catatan audit riwayat persetujuan dan penolakan akun pegawai</p>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $historyUsers->count() }} Riwayat</span>
        </div>

        @if($historyUsers->isEmpty())
            <div class="py-8 text-center text-xs text-slate-400 italic">Belum ada riwayat persetujuan atau penolakan akun.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold uppercase text-slate-500 tracking-wider">
                            <th class="px-5 py-3">Pegawai / NIP</th>
                            <th class="px-5 py-3">Unit Kerja</th>
                            <th class="px-5 py-3">Status Keputusan</th>
                            <th class="px-5 py-3">Peran Ditetapkan</th>
                            <th class="px-5 py-3">Alasan / Catatan</th>
                            <th class="px-5 py-3">Verifikator Admin</th>
                            <th class="px-5 py-3">Waktu Keputusan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($historyUsers as $hist)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-900">{{ $hist->nama }}</div>
                                    <div class="font-mono text-xs text-slate-400">{{ $hist->nip }}</div>
                                </td>
                                <td class="px-5 py-3.5">{{ $hist->unit->nama_unit ?? $hist->kode_unit }}</td>
                                <td class="px-5 py-3.5">
                                    @if($hist->status_registrasi === \App\Models\User::STATUS_REGISTRASI_DISETUJUI || $hist->status_aktif)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                                            Disetujui
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700 border border-rose-200">
                                            Ditolak
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-bold uppercase text-[#0969da]">
                                    {{ $hist->roleEntity ? $hist->roleEntity->label : str_replace('_', ' ', $hist->role) }}
                                </td>
                                <td class="px-5 py-3.5 max-w-[200px] truncate text-slate-500 text-[11px]">
                                    {{ $hist->alasan_penolakan ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-700 font-medium">
                                    {{ $hist->adminApproval->nama ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-400 font-mono text-[11px]">
                                    {{ $hist->tanggal_approval ? $hist->tanggal_approval->format('d/m/Y H:i') : ($hist->updated_at ? $hist->updated_at->format('d/m/Y H:i') : '-') }}
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
