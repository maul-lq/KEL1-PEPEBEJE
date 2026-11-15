@extends('layouts.app')

@section('title', 'Dashboard Utama - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
@php
    $authUser = $user ?? Auth::user();
    $activeModules = $roleData['active_modules'] ?? [];
    $hasPpbjModule = in_array('ppbj', $activeModules, true);

    $moduleViewMap = [
        'admin' => 'admin',
        'permohonan_user' => 'user',
        'verifikasi_bidang2' => 'staff_bidang_2',
        'perencanaan' => 'perencanaan',
        'verifikasi_wadir2' => 'wadir_2',
        'ppbj' => 'ppbj',
        'monitoring_pp' => 'monitoring_pp',
        'pp' => 'pp',
        'ppk' => 'ppk',
        'perlengkapan' => 'perlengkapan',
        'keuangan' => 'keuangan',
    ];
@endphp

<div class="space-y-8">
    @if(isset($errors) && $errors->any())
        <div class="rounded-[5px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1. UNIFIED FIGMA HEADER & WELCOME BANNER (SEMUA ROLE) --}}
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl md:text-[40px] font-semibold text-black tracking-tight leading-none">
                Dashboard
            </h1>
        </div>

        <div class="rounded-[10px] border-b-3 border-[#d2d2d2] py-8 md:py-10 px-6 flex items-center justify-center shadow-xs"
             style="background-image: linear-gradient(0deg, #ffffff 1%, #f5f7fc 99%);">
            <h2 class="text-2xl sm:text-3xl md:text-[38px] lg:text-[44px] font-semibold text-black text-center tracking-tight leading-tight">
                Selamat Datang {{ $authUser->nama ?? 'Pengguna' }}!
            </h2>
        </div>
    </div>

    {{-- 2. DYNAMIC RBAC MODULE WORKSPACE SUBVIEWS --}}
    @if(empty($activeModules))
        <div class="rounded-[5px] border border-[#d2d2d2] bg-white p-8 text-center shadow-xs space-y-2">
            <h3 class="text-base font-semibold text-[#4a4a4a]">Belum Ada Modul Dashboard Aktif</h3>
            <p class="text-xs text-[#657a7c] max-w-lg mx-auto">
                Peran Anda saat ini belum memiliki izin fitur operasional yang diaktifkan pada matriks RBAC.
                Hubungi Administrator untuk mengatur hak akses modul pada menu <strong>Master Role &amp; Hak Akses</strong>.
            </p>
        </div>
    @else
        <div class="space-y-8">
            @foreach($activeModules as $moduleKey)
                @continue($moduleKey === 'monitoring_pp' && $hasPpbjModule)
                @php
                    $viewSlug = $moduleViewMap[$moduleKey] ?? null;
                @endphp
                @if($viewSlug)
                    @includeIf('dashboard.roles.' . $viewSlug, [
                        'user' => $authUser,
                        'stats' => $stats,
                        'roleData' => $roleData,
                        'recentPermohonan' => $recentPermohonan,
                        'recentPaket' => $recentPaket,
                        'notifications' => $notifications,
                        'units' => $units,
                    ])
                @endif
            @endforeach
        </div>
    @endif
</div>
@endsection
