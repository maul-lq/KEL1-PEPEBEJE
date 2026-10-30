@extends('layouts.app')

@section('title', 'Dashboard Utama - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<div class="space-y-8">

    {{-- 1. HERO BANNER WELCOME (Unified & Preserved) --}}
    <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-white via-slate-50 to-blue-50/50 p-6 md:p-8 shadow-sm">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#0969da]/10 px-3 py-1 text-xs font-semibold text-[#0969da]">
                        <span class="size-2 rounded-full bg-[#0969da] animate-pulse"></span>
                        Dashboard {{ strtoupper(str_replace('_', ' ', $user->role ?? Auth::user()->role ?? 'user')) }}
                    </span>
                    <span class="text-xs text-slate-400 font-medium">• {{ date('d F Y') }}</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Selamat Datang, {{ $user->nama ?? Auth::user()->nama ?? 'Pengguna' }}!
                </h1>
                <p class="mt-1 text-sm text-slate-500 max-w-2xl">
                    {{ $user->jabatan ?? Auth::user()->jabatan ?? 'Pegawai Institusi' }} &bull; {{ $user->unit->nama_unit ?? $user->kode_unit ?? (Auth::user()?->unit?->nama_unit ?? 'Politeknik Negeri Jakarta') }}
                </p>
            </div>
            @if(($user->role ?? Auth::user()?->role) === 'user')
                <div class="shrink-0">
                    <a href="{{ route('permohonan.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition-all">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Ajukan Permohonan Baru
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- 2. DYNAMIC ROLE-SPECIFIC WORKSPACE SUBVIEW --}}
    @includeFirst(['dashboard.roles.' . ($user->role ?? 'user'), 'dashboard.roles.user'])

</div>
@endsection