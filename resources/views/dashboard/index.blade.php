@extends('layouts.app')

@section('title', 'Dashboard Utama - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
@php
    $authUser = Auth::user();
    $isUserOnly = ! $authUser->hasRole('admin') && (
        $authUser->hasRole(['user', 'pengaju']) || (
            $authUser->hasPermission('permohonan.create') &&
            ! $authUser->hasAnyPermission([
                'verifikasi.bidang_2_view',
                'verifikasi.wadir_2_view',
                'anggaran.mak_view',
                'paket.create',
                'paket.view_all',
                'reviu.pp',
                'reviu.ppk',
                'penerimaan.view',
                'pembayaran.pencairan_sp2d',
            ])
        )
    );

    $dashRole = $user->role ?? $authUser->role ?? 'user';
    if (! view()->exists('dashboard.roles.' . $dashRole)) {
        if ($authUser->hasPermission('admin.role_manage')) {
            $dashRole = 'admin';
        } elseif ($authUser->hasAnyPermission(['paket.create', 'paket.view_all', 'lihat_pp', 'pp.monitoring_aktivitas'])) {
            $dashRole = 'ppbj';
        } elseif ($authUser->hasPermission('reviu.ppk')) {
            $dashRole = 'ppk';
        } elseif ($authUser->hasPermission('reviu.pp')) {
            $dashRole = 'pp';
        } elseif ($authUser->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_alokasi'])) {
            $dashRole = 'perencanaan';
        } elseif ($authUser->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide'])) {
            $dashRole = 'wadir_2';
        } elseif ($authUser->hasPermission('verifikasi.bidang_2_view')) {
            $dashRole = 'staff_bidang_2';
        } elseif ($authUser->hasPermission('penerimaan.view')) {
            $dashRole = 'perlengkapan';
        } elseif ($authUser->hasPermission('pembayaran.pencairan_sp2d')) {
            $dashRole = 'keuangan';
        } else {
            $dashRole = 'user';
        }
    }
@endphp

<div class="space-y-8">

    {{-- 1. HERO BANNER WELCOME --}}
    @if($isUserOnly)
        {{-- Header & Welcome Banner for Role User matching Figma node 484:3385 --}}
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl md:text-[40px] font-semibold text-black tracking-tight leading-none">
                        Dashboard
                    </h1>
                    <span class="sr-only">Dashboard USER</span>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('permohonan.index') }}" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#3b82f6] text-white text-sm font-semibold hover:bg-blue-600 transition-colors shadow-sm">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Ajukan Permohonan Baru
                    </a>
                </div>
            </div>

            {{-- Welcome Banner matching Rectangle 486:4058 in Figma 484:3385 --}}
            <div class="rounded-[10px] border-b-3 border-[#d2d2d2] py-8 md:py-10 px-6 flex items-center justify-center shadow-xs"
                 style="background-image: linear-gradient(0deg, #ffffff 1%, #f5f7fc 99%);">
                <h2 class="text-2xl sm:text-3xl md:text-[38px] lg:text-[44px] font-semibold text-black text-center tracking-tight leading-tight">
                    Selamat Datang {{ $user->nama ?? Auth::user()->nama ?? 'Pengguna' }}!
                </h2>
            </div>
        </div>
    @else
        {{-- Preserved Unified Hero Banner for Other Roles --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-white via-slate-50 to-blue-50/50 p-6 md:p-8 shadow-sm">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#0969da]/10 px-3 py-1 text-xs font-semibold text-[#0969da]">
                            <span class="size-2 rounded-full bg-[#0969da] animate-pulse"></span>
                            @if(($user->roleEntity ?? Auth::user()?->roleEntity) && !($user->roleEntity ?? Auth::user()?->roleEntity)->is_system)
                                {{ ($user->roleEntity ?? Auth::user()?->roleEntity)->label }} ({{ strtoupper($user->role ?? Auth::user()->role) }})
                            @else
                                Dashboard {{ strtoupper(str_replace('_', ' ', $user->role ?? Auth::user()->role ?? 'user')) }}
                            @endif
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
            </div>
        </div>
    @endif

    {{-- 2. DYNAMIC ROLE-SPECIFIC WORKSPACE SUBVIEW --}}
    @includeFirst(['dashboard.roles.' . $dashRole, 'dashboard.roles.user'])

</div>
@endsection