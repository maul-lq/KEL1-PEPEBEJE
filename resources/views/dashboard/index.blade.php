{{--
  Informasi File

  Penulis   : DendyRafi, Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : View utama dasbor ini kami susun untuk menampilkan banner sambutan pengguna dan
              merender sub-tampilan workspace secara kumulatif berdasarkan `active_modules` RBAC.
--}}
@extends('layouts.app')

@section('title', 'Dashboard Utama - SIM-PBJ Politeknik Negeri Jakarta')

{{--
  Komentar Deskriptif: Seksi Konten Utama Dasbor Kumulatif RBAC (`@section('content')`).

  Tujuan: Kami memetakan daftar modul aktif dari controller ke sub-view peran di `dashboard.roles.*`
          serta menghindari duplikasi tampilan bila `ppbj` dan `monitoring_pp` sama-sama aktif.
  Parameter (Data dari HomeController):
    - `$user` (User|null): Entitas user login; `$roleData` (array): Data transaksi dan `active_modules`.
    - `$stats`, `$recentPermohonan`, `$recentPaket`, `$notifications`, `$units`.
  Nilai kembali: Halaman HTML dasbor terpadu sesuai kombinasi izin peran akun.
  Contoh pemanggilan: `return view('dashboard.index', compact(...));`
--}}
@section('content')
@php
    $authUser = $user ?? Auth::user();
    $activeModules = $roleData['active_modules'] ?? [];
    $hasPpbjModule = in_array('ppbj', $activeModules, true);

    // Peta module key RBAC ke nama file sub-view pada folder dashboard/roles/.
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

        <div class="dashboard-welcome-banner rounded-[10px] border-b-3 border-[#d2d2d2] py-8 md:py-10 px-6 flex items-center justify-center shadow-xs">
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
                Akun Anda belum memiliki hak akses fitur. Hubungi Administrator untuk mengatur hak akses pada menu <strong>Peran &amp; Hak Akses</strong>.
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
