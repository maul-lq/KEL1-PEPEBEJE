<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIM-PBJ Politeknik Negeri Jakarta')</title>

    <!-- Dynamic Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- CDN Tailwind CSS & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inder&display=swap');
        body {
            font-family: 'Inder', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-b from-white to-[#BAE5FF] text-gray-800 flex flex-col">

    <!-- 1. HEADER ATAS FULL (Sesuai Figma) -->
    <header class="z-20 flex h-[83px] w-full shrink-0 items-center justify-between bg-[#CCE1EF] px-6 shadow-[0_4px_4px_rgba(0,0,0,0.25)]">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="text-[40px] font-normal leading-none text-white tracking-wide" style="-webkit-text-stroke: 1px #689EC2; text-shadow: 0 0 4px rgba(0,0,0,0.25), 0 4px 4px rgba(0,0,0,0.25);">
                SIM-PBJ
            </a>
        </div>

        <!-- Profil User & Logout (Header Kanan) -->
        @auth
        <div class="flex items-center gap-4" x-data="{ openProfile: false }">
            <div class="text-right hidden sm:block">
                <div class="text-sm font-semibold text-[#1E3156]">{{ Auth::user()->nama }}</div>
                <div class="text-xs text-[#689EC2] font-medium">
                    {{ Auth::user()->jabatan ?? 'Pegawai' }} &bull;
                    <span class="uppercase font-bold text-blue-700">{{ str_replace('_', ' ', Auth::user()->role) }}</span>
                </div>
            </div>

            <!-- Avatar Button -->
            <div class="relative">
                <button @click="openProfile = !openProfile" class="flex size-10 items-center justify-center rounded-full bg-[#0088E8] text-white font-bold shadow-md hover:brightness-110">
                    {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                </button>

                <!-- Dropdown Logout & Quick Links -->
                <div x-show="openProfile" @click.away="openProfile = false" x-transition class="absolute right-0 mt-2 w-56 rounded-lg bg-white p-2 shadow-xl border border-slate-200 z-50" style="display: none;">
                    <div class="px-3 py-2 text-xs border-b border-slate-100">
                        <div class="font-bold text-gray-800">{{ Auth::user()->nama }}</div>
                        <div class="text-gray-500">{{ Auth::user()->unit->nama_unit ?? Auth::user()->kode_unit }}</div>
                        <div class="text-xs font-semibold text-blue-600 mt-0.5 uppercase">{{ str_replace('_', ' ', Auth::user()->role) }}</div>
                    </div>
                    <div class="py-1 text-xs text-slate-600 border-b border-slate-100">
                        <a href="{{ route('dashboard') }}" class="block px-3 py-1.5 hover:bg-slate-50 rounded">Dashboard</a>
                        <a href="{{ route('monitoring.index') }}" class="block px-3 py-1.5 hover:bg-slate-50 rounded">Monitoring 8 Siklus</a>
                        <a href="{{ route('laporan.rekapitulasi') }}" class="block px-3 py-1.5 hover:bg-slate-50 rounded">Laporan Rekapitulasi</a>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition-colors mt-1">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Keluar (Logout)
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endauth
    </header>

    <!-- 2. WRAPPER UTAMA (Sidebar Ikon + Konten) -->
    <div class="flex flex-1">

        <!-- SIDEBAR IKON (Dinamis Sesuai Hak Peran) -->
        @auth
        <aside class="flex w-16 shrink-0 flex-col items-center gap-4 bg-[#689EC2] pt-6 pb-6 shadow-md">
            
            <!-- Icon Dashboard -->
            <a href="{{ route('dashboard') }}" title="Dashboard Beranda" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('dashboard') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </a>

            <!-- Icon Permohonan (User / Pemohon) -->
            @if(Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create', 'permohonan.view_own']))
            <a href="{{ route('permohonan.index') }}" title="Permohonan Saya" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('permohonan.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </a>
            @endif

            <!-- Icon Notifikasi & Reminder (Semua Role - Sesuai Figma 121:45) -->
            <a href="{{ route('notifikasi.index') }}" title="Notifikasi dan Reminder" 
               class="relative flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('notifikasi.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                @php
                    $unreadNotifNavCount = \App\Models\NotifikasiUser::where('id_user', Auth::id())->where('is_read', false)->count();
                @endphp
                @if($unreadNotifNavCount > 0)
                    <span class="absolute top-1 right-1 flex size-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#087F79] opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2.5 bg-[#087F79]"></span>
                    </span>
                @endif
            </a>

            <!-- Icon Admin: Persetujuan Registrasi & Roles -->
            @if(Auth::user()->hasRole('admin'))
            <a href="{{ route('admin.persetujuan-registrasi.index') }}" title="Persetujuan Registrasi Pengguna" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('admin.persetujuan-registrasi.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </a>
            <a href="{{ route('admin.roles.index') }}" title="Manajemen Role & Hak Akses" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('admin.roles.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
            </a>
            @endif

            <!-- Icon Verifikasi Staf Bidang 2 -->
            @if(Auth::user()->hasRole('staff_bidang_2'))
            <a href="{{ route('verifikasi.bidang-2') }}" title="Telaah Berkas Bidang 2" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('verifikasi.bidang-2') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </a>
            @endif

            <!-- Icon Verifikasi Wadir 2 -->
            @if(Auth::user()->hasRole('wadir_2'))
            <a href="{{ route('verifikasi.wadir-2') }}" title="Persetujuan Eksekutif Wadir 2" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('verifikasi.wadir-2') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </a>
            @endif

            <!-- Icon Perencanaan: Verifikasi MAK -->
            @if(Auth::user()->hasRole('perencanaan'))
            <a href="{{ route('verifikasi.perencanaan') }}" title="Verifikasi MAK & Pagu Perencanaan" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('verifikasi.perencanaan') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </a>
            @endif

            <!-- Icon Paket Pengadaan (PPBJ) -->
            @if(Auth::user()->hasRole('ppbj'))
            <a href="{{ route('paket.index') }}" title="Paket Pengadaan PPBJ" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('paket.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </a>
            @endif

            <!-- Icon Kontrak SPK (PPBJ, PP, PPK) -->
            @if(Auth::user()->hasRole(['ppbj', 'pp', 'ppk']))
            <a href="{{ route('kontrak.index') }}" title="Kontrak SPK & Reviu" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('kontrak.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </a>
            @endif

            <!-- Icon Penerimaan Barang (Perlengkapan) -->
            @if(Auth::user()->hasRole('perlengkapan'))
            <a href="{{ route('penerimaan.index') }}" title="Penerimaan Barang & BAST" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('penerimaan.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </a>
            @endif

            <!-- Icon Pembayaran & SP2D (Keuangan, PPBJ, PPK, Wadir 2) -->
            @if(Auth::user()->hasRole(['keuangan', 'ppbj', 'ppk', 'wadir_2']))
            <a href="{{ route('pembayaran.index') }}" title="Tagihan & Pembayaran SP2D" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('pembayaran.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </a>
            @endif

            <!-- Icon Monitoring 8 Siklus (Semua Role) -->
            <a href="{{ route('monitoring.index') }}" title="Monitoring 8 Siklus Pengadaan" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('monitoring.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </a>

            <!-- Icon Laporan Rekapitulasi Anggaran -->
            <a href="{{ route('laporan.rekapitulasi') }}" title="Laporan Rekapitulasi Anggaran" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('laporan.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </a>

        </aside>
        @endauth

        <!-- 3. KONTEN UTAMA (Injeksi dari View) -->
        <main class="min-w-0 flex-1 p-6 md:p-10">
            
            {{-- Banner Permintaan Izin Notifikasi Desktop OS (Windows Action Center / Browser) --}}
            @auth
            <div 
                x-data="desktopNotificationManager()" 
                x-init="init()" 
                x-show="showBanner" 
                x-transition 
                class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-[12px] bg-gradient-to-r from-[#1B5B7E] to-[#0088E8] p-4 text-white shadow-md"
                style="display: none;"
            >
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/20">
                        <svg class="size-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold">Aktifkan Notifikasi Desktop (OS Windows / Mac)</h4>
                        <p class="text-xs text-blue-100">Dapatkan peringatan batas waktu SPK, jadwal pengiriman rekanan, dan disposisi verifikasi langsung di layar komputer Anda.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        @click="requestPermission()" 
                        type="button" 
                        class="rounded-[8px] bg-white px-4 py-2 text-xs font-semibold text-[#1B5B7E] shadow-sm hover:bg-blue-50 transition-colors"
                    >
                        Izinkan Notifikasi OS
                    </button>
                    <button 
                        @click="dismissBanner()" 
                        type="button" 
                        class="rounded-[8px] p-2 text-white/80 hover:bg-white/10 hover:text-white transition-colors"
                        title="Tutup banner"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            @endauth

            <!-- Notifikasi Success / Error -->
            @if(session('success'))
                <div class="mb-6 p-4 rounded-[10px] bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 rounded-[10px] bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Tempat Konten Halaman Ditempatkan --}}
            @yield('content')

        </main>
    </div>

    <!-- FOOTER -->
    <footer class="py-3 px-6 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} <strong>SIM-PBJ Politeknik Negeri Jakarta</strong>. Jl. Prof. Dr. G.A. Siwabessy, Kampus UI Depok 16425.
    </footer>

    @auth
    <!-- Script Web Notification & Service Worker (OS Desktop Alert) -->
    <script>
        function desktopNotificationManager() {
            return {
                permission: (typeof Notification !== 'undefined') ? Notification.permission : 'denied',
                showBanner: false,

                init() {
                    const dismissed = localStorage.getItem('simpbj_os_notif_dismissed') === 'true';
                    if (typeof Notification !== 'undefined' && Notification.permission === 'default' && !dismissed) {
                        this.showBanner = true;
                    }

                    // Daftarkan Service Worker untuk notifikasi OS di latar belakang
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('/sw.js').catch(function(err) {
                            console.warn('Gagal mendaftarkan Service Worker SIM-PBJ:', err);
                        });
                    }

                    // Mulai polling notifikasi baru jika izin telah diberikan
                    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                        this.startPolling();
                    }
                },

                requestPermission() {
                    if (typeof Notification === 'undefined') {
                        alert('Peramban Anda tidak mendukung Web Notification API.');
                        return;
                    }

                    const self = this;
                    Notification.requestPermission().then(function(perm) {
                        self.permission = perm;
                        self.showBanner = false;

                        if (perm === 'granted') {
                            self.showNativeAlert(
                                'SIM-PBJ Politeknik Negeri Jakarta',
                                'Notifikasi Desktop aktif! Anda akan menerima alarm pengadaan langsung di OS.',
                                '/notifikasi'
                            );
                            self.startPolling();
                        }
                    });
                },

                dismissBanner() {
                    localStorage.setItem('simpbj_os_notif_dismissed', 'true');
                    this.showBanner = false;
                },

                startPolling() {
                    let lastSeenId = parseInt(localStorage.getItem('simpbj_last_notified_id') || '0', 10);
                    const self = this;

                    // Periksa notifikasi unread terbaru setiap 30 detik
                    const checkNewAlerts = function() {
                        fetch('/api/notifikasi-terbaru?since_id=' + lastSeenId, { credentials: 'same-origin' })
                            .then(res => res.json())
                            .then(data => {
                                if (data.status === 'success' && data.data && data.data.length > 0) {
                                    data.data.forEach(item => {
                                        self.showNativeAlert(
                                            item.judul || 'SIM-PBJ: Notifikasi Pengadaan',
                                            item.pesan + (item.referensi ? ' (' + item.referensi + ')' : ''),
                                            item.action_url || '/notifikasi'
                                        );
                                        if (item.id > lastSeenId) {
                                            lastSeenId = item.id;
                                        }
                                    });
                                    localStorage.setItem('simpbj_last_notified_id', lastSeenId.toString());
                                }
                            })
                            .catch(err => {
                                // Silent fail pada background polling
                            });
                    };

                    // Jalankan pertama kali setelah 4 detik, kemudian setiap 30 detik
                    setTimeout(checkNewAlerts, 4000);
                    setInterval(checkNewAlerts, 30000);
                },

                showNativeAlert(title, body, url) {
                    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
                        return;
                    }

                    const options = {
                        body: body,
                        icon: '/favicon.ico',
                        badge: '/favicon.ico',
                        data: { url: url || '/notifikasi' },
                        tag: 'simpbj-notif-' + Date.now(),
                        renotify: true
                    };

                    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                        navigator.serviceWorker.ready.then(reg => {
                            reg.showNotification(title, options);
                        });
                    } else {
                        try {
                            const notif = new Notification(title, options);
                            notif.onclick = function() {
                                window.focus();
                                window.location.href = url || '/notifikasi';
                            };
                        } catch (e) {
                            console.warn('Fallback Notification gagal:', e);
                        }
                    }
                }
            };
        }
    </script>
    @endauth

</body>
</html>