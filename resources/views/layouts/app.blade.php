<!DOCTYPE html>
<html lang="id" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIM-PBJ Politeknik Negeri Jakarta')</title>

    <!-- Dynamic Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js Core (Defer) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Google Fonts: Iceland & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Iceland&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body x-data="{ sidebarExpanded: false, mobileSidebarOpen: false }" class="min-h-screen bg-[#F4F8FA] text-gray-800 flex antialiased">

    @auth
    @php
        $allHeaderNotifs = \App\Models\NotifikasiUser::with(['reminder.paket.permohonan'])
            ->where('id_user', Auth::id())
            ->where('is_dismissed', false)
            ->latest()
            ->take(8)
            ->get();
        $unreadHeaderNotifs = \App\Models\NotifikasiUser::with(['reminder.paket.permohonan'])
            ->where('id_user', Auth::id())
            ->where('is_dismissed', false)
            ->where('is_read', false)
            ->latest()
            ->take(8)
            ->get();
        $unreadHeaderCount = \App\Models\NotifikasiUser::where('id_user', Auth::id())
            ->where('is_dismissed', false)
            ->where('is_read', false)
            ->count();
        $menuOn  = 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 font-semibold';
        $menuOff = 'text-slate-300 hover:bg-white/10 hover:text-white';
    @endphp

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div x-show="mobileSidebarOpen"
         @click="mobileSidebarOpen = false"
         x-cloak
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-xs md:hidden"></div>

    <!-- 1. KOLOM KIRI: VERTICAL NAVBAR PENUH DARI ATAS KE BAWAH (FIGMA 484:3808) -->
    <aside id="vertical-navbar"
           data-node-id="484:3808"
           class="fixed inset-y-0 left-0 z-40 flex h-screen shrink-0 flex-col overflow-hidden bg-[#213153] pb-5 shadow-xl transition-all duration-300 md:sticky md:top-0 md:h-screen"
           :class="{
               'w-80 px-1': sidebarExpanded,
               'w-16 items-center px-1': !sidebarExpanded,
               'translate-x-0': mobileSidebarOpen,
               '-translate-x-full md:translate-x-0': !mobileSidebarOpen
           }">

    <!-- Header Sidebar (Tinggi presisi 73px sejajar Top Banner Figma 348:1913 & 484:3391) -->
    <div class="relative z-10 flex h-[73px] w-full shrink-0 items-center justify-between px-3.5">
        <a href="{{ route('dashboard') }}" x-show="sidebarExpanded" x-cloak class="flex shrink-0 items-center">
            <img src="{{ asset('favicon.ico') }}" alt="Logo SIM-PBJ" class="size-[45px] rounded-full object-contain">
        </a>
        <button @click="window.innerWidth < 768 ? (mobileSidebarOpen = false) : (sidebarExpanded = !sidebarExpanded)"
                type="button"
                title="Buka/Tutup Sidebar"
                :class="{ 'ml-auto': sidebarExpanded, 'mx-auto': !sidebarExpanded }"
                class="flex size-9 items-center justify-center rounded-xl text-slate-100 hover:bg-white/10 transition-colors cursor-pointer">
            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Garis Pembatas Atas (Line 3 tepat di y=73px sejajar garis bawah Top Banner) -->
    <div class="relative z-10 mx-3 mb-4 h-px w-[calc(100%-1.5rem)] shrink-0 bg-white/15"></div>

    <!-- Menu Navigation Stack (Scrollable jika menu banyak) -->
    <nav class="relative z-10 flex w-full flex-1 flex-col gap-3 overflow-y-auto overflow-x-hidden px-1">

        <!-- 1. DASHBOARD (All Roles) -->
        @php $aDash = request()->routeIs('dashboard'); @endphp
        <a href="{{ route('dashboard') }}" 
           title="Dashboard" 
           class="flex h-[49px] w-full items-center gap-4 rounded-[10px] px-4 transition-all {{ $aDash ? $menuOn : $menuOff }}">
            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 10.2 12 3.8l8 6.4V19a1.5 1.5 0 0 1-1.5 1.5H15v-4.5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v4.5H5.5A1.5 1.5 0 0 1 4 19z" fill="{{ $aDash ? 'currentColor' : 'none' }}"/>
            </svg>
            <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Dashboard</span>
        </a>

        <!-- 2. ADMIN ONLY: RBAC, User CRUD & Master Management -->
        @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['admin.approval_registrasi', 'admin.user_manage', 'admin.role_manage']))
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.approval_registrasi'))
                @php
                    $pendingRegistrasiCount = \App\Models\User::where('status_registrasi', \App\Models\User::STATUS_REGISTRASI_MENUNGGU)->count();
                    $aReg = request()->routeIs('admin.persetujuan-registrasi.*');
                @endphp
                <a href="{{ route('admin.persetujuan-registrasi.index') }}" 
                   title="Persetujuan Registrasi" 
                   class="relative flex h-[49px] w-full items-center justify-between rounded-[10px] px-4 transition-all {{ $aReg ? $menuOn : $menuOff }}">
                    <div class="flex min-w-0 items-center gap-4">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Persetujuan Registrasi</span>
                    </div>
                    @if($pendingRegistrasiCount > 0)
                        <span x-show="!sidebarExpanded" class="absolute top-2 right-2 flex size-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2 bg-amber-500"></span>
                        </span>
                        <span x-show="sidebarExpanded" x-cloak class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                            {{ $pendingRegistrasiCount }}
                        </span>
                    @endif
                </a>
            @endif

            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.user_manage'))
                @php $aUsr = request()->routeIs('admin.users.*'); @endphp
                <a href="{{ route('admin.users.index') }}" 
                   title="Manajemen Pengguna" 
                   class="flex h-[49px] w-full items-center gap-4 rounded-[10px] px-4 transition-all {{ $aUsr ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Manajemen Pengguna</span>
                </a>
            @endif

            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.role_manage'))
                @php $aRole = request()->routeIs('admin.roles.*'); @endphp
                <a href="{{ route('admin.roles.index') }}" 
                   title="Master Role & Hak Akses" 
                   class="flex h-[49px] w-full items-center gap-4 rounded-[10px] px-4 transition-all {{ $aRole ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Master Role &amp; Hak Akses</span>
                </a>
            @endif

            @if(Auth::user()->hasRole('admin'))
                @php $aAdminPerm = request()->routeIs('permohonan.*'); @endphp
                <a href="{{ route('permohonan.index') }}" 
                   title="Kelola / Hapus Pengajuan" 
                   class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aAdminPerm ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Kelola / Hapus Pengajuan</span>
                </a>
            @endif
        @endif

        @php
            $u = Auth::user();
            $isAdmin = $u->hasRole('admin');
        @endphp

        <!-- 3. PERMOHONAN PENGAJUAN (Pengusul / Jurusan / Unit / Admin) -->
        @if($isAdmin || $u->hasRole(['user', 'pengaju']) || $u->hasAnyPermission(['permohonan.create', 'permohonan.view_own', 'permohonan.view_all']))
            @php $aUserPerm = request()->routeIs('permohonan.*'); @endphp
            <a href="{{ route('permohonan.index') }}" 
               title="Permohonan Pengadaan" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aUserPerm ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Permohonan Pengadaan</span>
            </a>
        @endif

        <!-- 4. STAFF BIDANG 2: Telaah Berkas Masuk -->
        @if($isAdmin || $u->hasRole('staff_bidang_2') || $u->hasPermission('verifikasi.bidang_2_view'))
            @php $aBidang2 = request()->routeIs('verifikasi.bidang-2*'); @endphp
            <a href="{{ route('verifikasi.bidang-2') }}" 
               title="Telaah Berkas Bidang II" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aBidang2 ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Telaah Berkas Bidang II</span>
            </a>
        @endif

        <!-- 5. WADIR 2: Persetujuan Eksekutif & Disposisi -->
        @if($isAdmin || $u->hasRole('wadir_2') || $u->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide']))
            @php $aWadir2 = request()->routeIs('verifikasi.wadir-2*'); @endphp
            <a href="{{ route('verifikasi.wadir-2') }}" 
               title="Persetujuan Eksekutif Wadir II" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aWadir2 ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Persetujuan Eksekutif</span>
            </a>
        @endif

        <!-- 6. PERENCANAAN: Alokasi & Penguncian MAK -->
        @if($isAdmin || $u->hasRole('perencanaan') || $u->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_manage', 'anggaran.mak_alokasi', 'anggaran.mak_lock', 'permohonan.split_mak']))
            @php $aPerencanaan = request()->routeIs('verifikasi.perencanaan*'); @endphp
            <a href="{{ route('verifikasi.perencanaan') }}" 
               title="Verifikasi MAK & Pagu" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPerencanaan ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Verifikasi MAK &amp; Pagu</span>
            </a>
        @endif

        <!-- 7. PPK: Persetujuan Awal Usulan PPK (Isolasi 4 Akun) -->
        @if($isAdmin || $u->hasRole('ppk') || $u->hasPermission('reviu.ppk'))
            @php
                $ppkAntreanCount = isset($stats['antrean_persetujuan_awal']) 
                    ? $stats['antrean_persetujuan_awal'] 
                    : \App\Models\PermohonanPengadaan::where('id_user_ppk_awal', Auth::id())
                        ->whereIn('status_permohonan', [
                            \App\Models\PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                            \App\Models\PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        ])
                        ->count();
                $aPPKVerif = request()->routeIs('verifikasi.ppk*');
            @endphp
            <a href="{{ route('verifikasi.ppk') }}" 
               title="Persetujuan Awal Usulan PPK" 
               class="relative flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPPKVerif ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Persetujuan Awal Usulan</span>
                @if($ppkAntreanCount > 0)
                    <span x-show="!sidebarExpanded" class="absolute top-2 right-2 flex size-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2.5 bg-cyan-400"></span>
                    </span>
                    <span x-show="sidebarExpanded" x-cloak class="ml-auto rounded-full bg-cyan-400/20 px-2 py-0.5 text-[10px] font-bold text-cyan-300">
                        {{ $ppkAntreanCount }}
                    </span>
                @endif
            </a>
        @endif

        <!-- 8. PPBJ: Manajemen Paket Pengadaan -->
        @if($isAdmin || $u->hasRole('ppbj') || $u->hasAnyPermission(['paket.create', 'paket.view_all', 'paket.reassign_ppk', 'paket.split_jenis']))
            @php $aPaket = request()->routeIs('paket.*'); @endphp
            <a href="{{ route('paket.index') }}" 
               title="Paket Pengadaan" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPaket ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Paket Pengadaan</span>
            </a>
        @endif

        <!-- 9. MONITOR AKTIVITAS PP (FR RBAC lihat_pp / pp.monitoring_aktivitas - PPBJ, Pengawas & Admin) -->
        @if($isAdmin || $u->canMonitorPp() || $u->hasAnyPermission(['lihat_pp', 'pp.monitoring_aktivitas']))
            @php $aMonitorPP = request()->routeIs('monitor-pp.*'); @endphp
            <a href="{{ route('monitor-pp.index') }}" 
               title="Monitor Aktivitas PP" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aMonitorPP ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Monitor Aktivitas PP</span>
            </a>
        @endif

        <!-- 10. PP: Reviu & Negosiasi Harga -->
        @if($isAdmin || $u->hasRole('pp') || $u->hasPermission('reviu.pp'))
            @php $aPPKontrak = request()->routeIs('kontrak.*') && request('tab') === 'reviu'; @endphp
            <a href="{{ route('kontrak.index', ['tab' => 'reviu']) }}" 
               title="Reviu & Negosiasi Harga (PP)" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPPKontrak ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Reviu &amp; Negosiasi Harga</span>
            </a>
        @endif

        <!-- 10.B. PPK: Persetujuan Dokumen Pengadaan (BPMN N30-N36) -->
        @if($isAdmin || $u->hasPermission('reviu.ppk'))
            @php
                $ppkPaketCount = \App\Models\PaketPengadaan::where('id_user_ppk', $u->id_user)
                    ->where('status_paket', \App\Models\PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
                    ->count();
                $aPPKDokumen = request()->routeIs('ppk.persetujuan-dokumen*');
            @endphp
            <a href="{{ route('ppk.persetujuan-dokumen.index') }}" 
               title="Persetujuan Dokumen Pengadaan (BPMN N30-N36)" 
               class="relative flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPPKDokumen ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Persetujuan Dokumen</span>
                @if($ppkPaketCount > 0)
                    <span x-show="!sidebarExpanded" class="absolute top-2 right-2 flex size-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2.5 bg-teal-400"></span>
                    </span>
                    <span x-show="sidebarExpanded" x-cloak class="ml-auto rounded-full bg-teal-400/20 px-2 py-0.5 text-[10px] font-bold text-teal-300">
                        {{ $ppkPaketCount }}
                    </span>
                @endif
            </a>
        @endif

        <!-- 11. KONTRAK SPK / DOKUMEN PERIKATAN (PPBJ / PPK / PP / Admin) -->
        @if($isAdmin || $u->hasRole(['ppbj', 'ppk', 'pp']) || $u->hasAnyPermission(['spk.create', 'reviu.ppk', 'reviu.pp']))
            @php $aKontrakSPK = request()->routeIs('kontrak.*') && request('tab') !== 'reviu'; @endphp
            <a href="{{ route('kontrak.index') }}" 
               title="Kontrak SPK & Dokumen Perikatan" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aKontrakSPK ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Kontrak SPK</span>
            </a>
        @endif

        <!-- 12. PERLENGKAPAN: Penerimaan Barang & BAST -->
        @if($isAdmin || $u->hasRole('perlengkapan') || $u->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create', 'penerimaan.bast_sign']))
            @php $aPerlengkapan = request()->routeIs('penerimaan.*'); @endphp
            <a href="{{ route('penerimaan.index') }}" 
               title="Penerimaan Barang & BAST" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPerlengkapan ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Penerimaan Barang &amp; BAST</span>
            </a>
        @endif

        <!-- 13. KEUANGAN / WADIR 2 / PPK / PPBJ / Admin: Tagihan & Pembayaran SP2D -->
        @if($isAdmin || $u->hasRole(['keuangan', 'wadir_2', 'ppk', 'ppbj']) || $u->hasAnyPermission(['pembayaran.pencairan_sp2d', 'pembayaran.memo_wadir2', 'pembayaran.memo_ppk', 'pembayaran.spp_terbitkan', 'pembayaran.pengajuan']))
            @php $aKeuangan = request()->routeIs('pembayaran.*'); @endphp
            <a href="{{ route('pembayaran.index') }}" 
               title="Tagihan & Pembayaran SP2D" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aKeuangan ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Tagihan &amp; Pembayaran</span>
            </a>
        @endif

        <!-- 12. RIWAYAT & LAPORAN (All Roles - Menggantikan Monitoring & Rekapitulasi Lama) -->
        @php $aLaporan = request()->routeIs('laporan.*'); @endphp
        <a href="{{ route('laporan.index') }}" 
           title="Riwayat & Laporan" 
           class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aLaporan ? $menuOn : $menuOff }}">
            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3v18h18"/>
                <path d="M18 17V9"/>
                <path d="M13 17V5"/>
                <path d="M8 17v-3"/>
            </svg>
            <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Riwayat &amp; Laporan</span>
        </a>

        <!-- 14. NOTIFIKASI & REMINDER (All Roles) -->
        @php 
            $aNotif = request()->routeIs('notifikasi.*'); 
            $unreadCount = $unreadHeaderCount ?? 0;
        @endphp
        <a href="{{ route('notifikasi.index') }}" 
           title="Notifikasi & Reminder" 
           class="relative flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aNotif ? $menuOn : $menuOff }}">
            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 10a6 6 0 1 1 12 0c0 3.2 1 4.7 2 5.8a.6.6 0 0 1-.45 1H4.45a.6.6 0 0 1-.45-1C5 14.7 6 13.2 6 10z" fill="{{ $aNotif ? 'currentColor' : 'none' }}"/>
                <path d="M10 20a2 2 0 0 0 4 0"/>
            </svg>
            <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Notifikasi &amp; Reminder</span>
            @if($unreadCount > 0)
                <span x-show="!sidebarExpanded" class="absolute top-2 right-2 flex size-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full size-2.5 bg-cyan-400"></span>
                </span>
                <span x-show="sidebarExpanded" x-cloak class="ml-auto rounded-full bg-cyan-400/20 px-2 py-0.5 text-[10px] font-bold text-cyan-300">
                    {{ $unreadCount }}
                </span>
            @endif
        </a>
        
        <!-- Garis Pembatas Bawah -->
        <div class="mx-1 my-1 h-px bg-white/15 w-full"></div>
    
        <!-- 15. PROFIL SAYA (All Roles) -->
        @php $aProf = request()->routeIs('profile.*'); @endphp
        <div class="w-full">
            <a href="{{ route('profile.index') }}" 
               title="Profil" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aProf ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="3.5" fill="{{ $aProf ? 'currentColor' : 'none' }}"/>
                    <path d="M5 20v-.5c0-2.6 3.1-4.5 7-4.5s7 1.9 7 4.5v.5z" fill="{{ $aProf ? 'currentColor' : 'none' }}"/>
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Profil</span>
            </a>
        </div>
    </nav>

    <!-- DEKORASI UNIK GELOMBANG NAVBAR BIRU (FIGMA ELLIPSE 4 & ELLIPSE 5 / FRAME 29) -->
    <!-- Mode Normal: node-id 359-2268 (Frame 29) | Mode Extended: node-id 348-24 (Ellipse 4 & 5) -->
    <div class="pointer-events-none absolute bottom-[-224px] h-[572.5px] w-[1078.4px] select-none transition-all duration-300 ease-in-out z-0"
         :class="sidebarExpanded ? 'left-[-391px]' : 'left-[-396px]'"
         id="sidebar-frame-29"
         aria-hidden="true">
        <!-- Ellipse 5 (Figma node-id: 348:1895 / 359:2271) -->
        <div class="absolute h-[344px] left-[142px] top-[103px] w-[637px]" data-node-id="348:1895" data-name="Ellipse 5">
            <div class="absolute" style="top: -43.6%; right: -15.7%; bottom: -14.53%; left: -15.7%;">
                <img src="{{ asset('images/sidebar/ellipse-5.svg') }}" alt="" class="block max-w-none size-full pointer-events-none select-none">
            </div>
        </div>
        <!-- Ellipse 4 (Figma node-id: 348:1896 / 359:2272) -->
        <div class="absolute h-[450.4px] left-0 top-0 w-[1078.4px]" data-node-id="348:1896" data-name="Ellipse 4">
            <div class="absolute" style="top: -33.31%; right: -9.27%; bottom: -11.1%; left: -9.27%;">
                <img src="{{ asset('images/sidebar/ellipse-4.svg') }}" alt="" class="block max-w-none size-full pointer-events-none select-none">
            </div>
        </div>
    </div>
    </aside>
    @endauth

    <!-- 2. KOLOM KANAN: TOP BANNER (FIGMA 484:3391) + KONTEN UTAMA + FOOTER -->
    <div class="flex min-h-screen min-w-0 flex-1 flex-col">

        <!-- TOP BANNER HEADER (FIGMA NODE 484:3391 - FRAME 133) -->
        <header id="top-banner"
                data-node-id="484:3391"
                class="sticky top-0 z-30 flex h-[73px] w-full shrink-0 items-center justify-between border-b border-slate-200/80 bg-white pl-[15px] pr-0 shadow-[0px_2px_2px_rgba(0,0,0,0.08)]">

            <!-- Kiri: Hamburger Mobile + Logo & Teks SIM-PBJ (Figma 484:3392) -->
            <div class="flex items-center gap-3">
                @auth
                <button @click="mobileSidebarOpen = !mobileSidebarOpen"
                        type="button"
                        title="Buka Menu Navigasi"
                        class="flex size-9 items-center justify-center rounded-lg text-[#213153] hover:bg-slate-100 transition-colors md:hidden cursor-pointer">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                @endauth

                <a href="{{ route('dashboard') }}" class="flex items-center gap-3.5 group" data-node-id="484:3392">
                    <img src="{{ asset('favicon.ico') }}" alt="Logo SIM-PBJ" class="size-[44px] sm:size-[53px] rounded-full object-contain transition-transform group-hover:scale-105">
                    <span class="font-iceland text-[32px] sm:text-[40px] font-normal tracking-normal text-black group-hover:text-[#213153] transition-colors leading-none">
                        SIM-PBJ
                    </span>
                </a>
            </div>

            <!-- Kanan: Unit - Profil dan Notif (Figma 751:7821 & Popups 751:7822) -->
            @auth
            <div class="relative flex h-full items-center gap-[18px] sm:gap-[26px]"
                 data-node-id="751:7821"
                 x-data="{
                     openNotif: false,
                     openProfile: false,
                     notifTab: 'semua',
                     unreadCount: {{ $unreadHeaderCount }},
                     toggleNotif() {
                         this.openNotif = !this.openNotif;
                         if (this.openNotif) {
                             this.openProfile = false;
                         }
                     },
                     toggleProfile() {
                         this.openProfile = !this.openProfile;
                         if (this.openProfile) {
                             this.openNotif = false;
                         }
                     },
                     async fetchTerbaru() {
                         try {
                             const res = await fetch('/api/notifikasi-terbaru');
                             const data = await res.json();
                             if (data.status === 'success') {
                                 this.unreadCount = data.unread_count || (this.unreadCount + 1);
                             } else {
                                 this.unreadCount++;
                             }
                         } catch (e) {
                             this.unreadCount++;
                         }
                     }
                 }"
                 @notification-updated.window="fetchTerbaru()"
                 @click.outside="openNotif = false; openProfile = false"
                 @keydown.escape.window="openNotif = false; openProfile = false">

                <!-- Tombol Lonceng Notifikasi (Figma 751:7798) -->
                <button @click="toggleNotif()"
                        type="button"
                        title="Notifikasi"
                        data-node-id="751:7798"
                        class="relative flex size-9 items-center justify-center rounded-full transition-colors hover:bg-slate-100 focus:outline-none cursor-pointer">
                    <!-- State Aktif (Solid Biru #3B82F6 seperti Variant3/Variant4 Figma) -->
                    <svg x-show="openNotif" x-cloak class="size-[22px] text-[#3b82f6]" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2a2 2 0 0 0-2 2v.34A7.002 7.002 0 0 0 5 11v3.586l-1.707 1.707A1 1 0 0 0 4 18h16a1 1 0 0 0 .707-1.707L19 14.586V11a7.002 7.002 0 0 0-5-6.66V4a2 2 0 0 0-2-2zm-2.5 17a2.5 2.5 0 0 0 5 0h-5z"/>
                    </svg>
                    <!-- State Default (Outline Hitam seperti Default/Variant2 Figma) -->
                    <svg x-show="!openNotif" class="size-[22px] text-black" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>

                    <!-- Titik Unread Badge Dinamis -->
                    <template x-if="unreadCount > 0 && !openNotif">
                        <span class="absolute top-1 right-1 flex size-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#3b82f6] opacity-75"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-[#3b82f6] ring-2 ring-white"></span>
                        </span>
                    </template>
                </button>

                <!-- Tombol Mini Profil + Garis Pemisah Vertikal (Figma 751:7814) -->
                <button @click="toggleProfile()"
                        type="button"
                        data-node-id="751:7814"
                        class="relative flex h-[73px] sm:w-[265px] items-center gap-3.5 bg-white px-4 hover:bg-slate-50/80 transition-colors focus:outline-none cursor-pointer">
                    <!-- Garis Pemisah Vertikal Kiri (Figma Line 5 - 751:7820) -->
                    <span class="absolute left-0 top-[14px] bottom-[14px] w-px bg-[#C4C4C4]" aria-hidden="true"></span>

                    <!-- Avatar Mini 45x45 (Figma Ellipse 6 - 751:7816) -->
                    <span class="flex size-[42px] sm:size-[45px] shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#213153] text-sm font-bold text-white shadow-xs">
                        @if(Auth::user()->foto_profil)
                            <img src="{{ asset('storage/' . Auth::user()->foto_profil) }}" alt="{{ Auth::user()->nama }}" class="size-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                        @endif
                    </span>

                    <!-- Nama & Unit/Bidang (Figma 751:7817 & 751:7818) -->
                    <span class="hidden min-w-0 flex-1 text-left sm:block">
                        <span class="block truncate text-[15px] font-semibold leading-tight text-black">
                            {{ Auth::user()->nama }}
                        </span>
                        <span class="mt-0.5 block truncate text-[12px] font-normal leading-tight text-black">
                            {{ Auth::user()->unit->nama_unit ?? Auth::user()->jabatan ?? 'Pegawai' }}
                        </span>
                    </span>

                    <!-- Chevron Down (Figma Vector - 751:7819) -->
                    <svg class="size-4 shrink-0 text-black transition-transform duration-200"
                         :class="openProfile ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- ================================================================ -->
                <!-- POPUP 1: MINI NOTIFIKASI (FIGMA NODE 751:7854 & 753:7964)        -->
                <!-- ================================================================ -->
                <div x-show="openNotif"
                     x-cloak
                     data-node-id="751:7854"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                     class="absolute right-2 sm:right-3 top-[76px] z-50 w-[calc(100vw-1.5rem)] max-w-[400px] sm:w-[400px] rounded-[10px] border border-[#AAAAAA]/80 bg-white pt-[22px] pb-[12px] shadow-[0px_4px_12px_rgba(0,0,0,0.22)]">

                    <!-- Judul Notifikasi (Figma 751:7886) -->
                    <h3 class="px-[28px] text-[24px] font-semibold leading-tight text-black">
                        Notifikasi
                    </h3>

                    <!-- Baris Filter Tab ("Semua", "Belum Dibaca") & "Lihat Semua ->" -->
                    <div class="mt-2.5 flex items-center justify-between px-[28px]">
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="notifTab = 'semua'"
                                    :class="notifTab === 'semua' ? 'border-b-2 border-[#3b82f6] text-[#3b82f6] font-medium' : 'border-b-2 border-transparent text-black hover:text-[#3b82f6]'"
                                    class="px-[13px] py-[4px] text-[11px] transition-colors cursor-pointer">
                                Semua
                            </button>
                            <button type="button"
                                    @click="notifTab = 'belum_dibaca'"
                                    :class="notifTab === 'belum_dibaca' ? 'border-b-2 border-[#3b82f6] text-[#3b82f6] font-medium' : 'border-b-2 border-transparent text-black hover:text-[#3b82f6]'"
                                    class="px-[13px] py-[4px] text-[11px] transition-colors cursor-pointer">
                                Belum Dibaca
                            </button>
                        </div>

                        <a href="{{ route('notifikasi.index') }}"
                           class="rounded-full px-[10px] py-[4px] text-[11px] font-normal text-black hover:text-[#3b82f6] transition-colors whitespace-nowrap">
                            Lihat Semua &rarr;
                        </a>
                    </div>

                    <!-- Daftar Item Notifikasi (Figma 753:7923) -->
                    @foreach(['semua' => $allHeaderNotifs, 'belum_dibaca' => $unreadHeaderNotifs] as $tabKey => $notifList)
                        <div x-show="notifTab === '{{ $tabKey }}'"
                             class="mt-2 max-h-[420px] overflow-y-auto px-[10px] space-y-0.5">
                            @forelse($notifList as $notif)
                                @php
                                    $teksGabung = strtolower($notif->judul . ' ' . $notif->pesan);
                                    $isRevisiNotif = str_contains($teksGabung, 'revisi') || str_contains($teksGabung, 'kembalikan') || str_contains($teksGabung, 'mengembalikan') || str_contains($teksGabung, 'tolak');
                                    $isSelesaiNotif = str_contains($teksGabung, 'selesai') || str_contains($teksGabung, 'lunas') || str_contains($teksGabung, 'cair');
                                @endphp
                                <a href="{{ $notif->action_url }}"
                                   class="flex min-h-[82px] items-center gap-[15px] rounded-[6px] bg-white px-[14px] py-[12px] hover:bg-slate-50 transition-colors">
                                    <!-- Ikon Lingkaran Berwarna Sesuai Status (50x50px Figma) -->
                                    @if($isRevisiNotif)
                                        <span class="flex size-[48px] shrink-0 items-center justify-center rounded-full bg-[#F3C635] text-white shadow-2xs">
                                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4m-4 4l4 4" />
                                            </svg>
                                        </span>
                                    @elseif($isSelesaiNotif)
                                        <span class="flex size-[48px] shrink-0 items-center justify-center rounded-full bg-[#1CD000] text-white shadow-2xs">
                                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="flex size-[48px] shrink-0 items-center justify-center rounded-full bg-[#087AE6] text-white shadow-2xs">
                                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </span>
                                    @endif

                                    <!-- Teks Judul & Waktu -->
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-[14px] sm:text-[15px] font-medium leading-snug {{ !$notif->is_read ? 'text-black' : 'text-[#6e6e6e]' }}">
                                            {{ $notif->judul }}
                                        </p>
                                        <span class="mt-1 block text-[11px] font-semibold text-[#aaaaaa]">
                                            {{ $notif->created_at ? $notif->created_at->translatedFormat('d M Y, H:i') : 'Baru saja' }}
                                        </span>
                                    </div>

                                    <!-- Titik Biru Belum Dibaca (Figma 753:7912) -->
                                    @if(!$notif->is_read)
                                        <span class="size-[10px] shrink-0 rounded-full bg-[#3b82f6]" title="Belum dibaca"></span>
                                    @endif
                                </a>
                            @empty
                                <div class="py-10 text-center text-xs text-slate-400">
                                    {{ $tabKey === 'belum_dibaca' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi terbaru.' }}
                                </div>
                            @endforelse
                        </div>
                    @endforeach
                </div>

                <!-- ================================================================ -->
                <!-- POPUP 2: PROFIL KECIL (FIGMA NODE 751:7823)                      -->
                <!-- ================================================================ -->
                <div x-show="openProfile"
                     x-cloak
                     data-node-id="751:7823"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                     class="absolute right-2 sm:right-3 top-[76px] z-50 w-[calc(100vw-1.5rem)] max-w-[400px] sm:w-[400px] rounded-[10px] border border-[#AAAAAA]/80 bg-white px-[28px] py-[22px] shadow-[0px_4px_12px_rgba(0,0,0,0.22)]">

                    <!-- Informasi Pengguna & Avatar Besar 90x90 (Figma 751:7853) -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <h4 class="truncate text-[22px] sm:text-[24px] font-semibold leading-tight text-black">
                                {{ Auth::user()->nama }}
                            </h4>
                            <p class="mt-1 truncate text-[16px] font-normal leading-snug text-black">
                                {{ Auth::user()->unit->nama_unit ?? Auth::user()->jabatan ?? 'Pegawai' }}
                            </p>
                            <span class="mt-2 inline-flex items-center justify-center rounded-full bg-[#5EB7EE]/50 px-[13px] py-[4px] text-[10px] font-normal text-black">
                                @if(Auth::user()->roleEntity && !Auth::user()->roleEntity->is_system)
                                    {{ Auth::user()->roleEntity->label }}
                                @else
                                    {{ ucwords(str_replace('_', ' ', Auth::user()->role)) }}
                                @endif
                            </span>
                        </div>

                        <!-- Avatar Foto Besar (Figma Ellipse 7 - 751:7838) -->
                        <div class="flex size-[80px] sm:size-[90px] shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#213153] text-2xl font-bold text-white shadow-xs">
                            @if(Auth::user()->foto_profil)
                                <img src="{{ asset('storage/' . Auth::user()->foto_profil) }}" alt="{{ Auth::user()->nama }}" class="size-full object-cover">
                            @else
                                {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                    </div>

                    <!-- Garis Pembatas Horizontal (Figma Line 27 - 751:7839) -->
                    <div class="my-[18px] h-px w-full bg-[#CCCCCC]"></div>

                    <!-- Menu Aksi: Profil Saya & Logout (Figma 751:7840 & 751:7845) -->
                    <div class="space-y-2">
                        <a href="{{ route('profile.index') }}"
                           class="flex items-center gap-[26px] rounded-lg px-[14px] py-2.5 text-[16px] font-normal text-black hover:bg-slate-50 transition-colors">
                            <svg class="size-[20px] shrink-0 text-black" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="4" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6" />
                            </svg>
                            <span>Profil Saya</span>
                        </a>

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-[26px] rounded-lg px-[14px] py-2.5 text-[16px] font-normal text-[#FF0000] hover:bg-rose-50/70 transition-colors cursor-pointer">
                                <svg class="size-[20px] shrink-0 text-[#FF0000]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 17l-5-5m0 0l5-5m-5 5h11m-3 7h4a2 2 0 002-2V7a2 2 0 00-2-2h-4" />
                                </svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            @endauth
        </header>

        <!-- KONTEN UTAMA HALAMAN -->
        <main class="min-w-0 flex-1 p-6 md:p-8">

            {{-- Banner Notifikasi OS Desktop --}}
            @auth
            <div x-data="desktopNotificationManager()"
                 x-init="init()"
                 x-show="showBanner"
                 x-cloak
                 x-transition
                 class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-gradient-to-r from-[#213153] to-[#0969da] p-4 text-white shadow-md">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/15 backdrop-blur-sm">
                        <svg class="size-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold">Aktifkan Notifikasi Desktop (OS Windows / Mac)</h4>
                        <p class="text-xs text-blue-100">Dapatkan peringatan batas waktu SPK, pengiriman rekanan, dan disposisi langsung di layar komputer Anda.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="requestPermission()"
                            type="button"
                            class="rounded-lg bg-white px-4 py-2 text-xs font-bold text-[#213153] shadow-sm hover:bg-blue-50 transition-colors cursor-pointer">
                        Izinkan Notifikasi OS
                    </button>
                    <button @click="dismissBanner()"
                            type="button"
                            class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white transition-colors cursor-pointer"
                            title="Tutup banner">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            @endauth

            <!-- Session Alerts -->
            @if(session('success'))
                <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium shadow-sm">
                    <svg class="size-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium shadow-sm">
                    <svg class="size-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- Injeksi Isi Halaman --}}
            @yield('content')

        </main>

        <!-- FOOTER -->
        <footer class="py-4 px-6 text-center text-xs text-slate-500 border-t border-slate-200/60 bg-white">
            &copy; {{ date('Y') }} <strong>SIM-PBJ Politeknik Negeri Jakarta</strong>. Jl. Prof. Dr. G.A. Siwabessy, Kampus UI Depok 16425.
        </footer>

    </div>

</body>
</html>