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
<body x-data="{ sidebarExpanded: false, mobileSidebarOpen: false }" class="min-h-screen bg-[#F4F8FA] text-gray-800 flex flex-col antialiased">

    <!-- 1. HEADER UTAMA -->
    <header class="sticky top-0 z-30 flex h-[70px] w-full shrink-0 items-center justify-between border-b border-slate-200/80 bg-white px-4 sm:px-6 shadow-sm">
        
        <!-- Logo Favicon & Teks SIM-PBJ -->
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 group">
                <img src="{{ asset('favicon.ico') }}" alt="Logo SIM-PBJ" class="size-8 object-contain transition-transform group-hover:scale-105">
                <span class="font-iceland text-3xl font-bold tracking-wider text-[#213153] group-hover:text-[#0969da] transition-colors leading-none">
                    SIM-PBJ
                </span>
            </a>
        </div>

        <!-- HEADER KANAN: Lonceng Notifikasi Dropdown + Mini Profil -->
        @auth
        @php
            $headerNotifs = \App\Models\NotifikasiUser::with('reminder')
                ->where('id_user', Auth::id())
                ->where('is_read', false)
                ->latest()
                ->take(5)
                ->get();
            $unreadHeaderCount = \App\Models\NotifikasiUser::where('id_user', Auth::id())->where('is_read', false)->count();
        @endphp
        <div class="flex items-center gap-3 sm:gap-4">

            <!-- ICON NOTIFIKASI LONCENG DROPDOWN (REAL-TIME UPDATED) -->
            <div class="relative" 
                 x-data="{ 
                     openNotif: false, 
                     unreadCount: {{ $unreadHeaderCount }},
                     async fetchTerbaru() {
                         try {
                             const res = await fetch('/api/notifikasi-terbaru');
                             const data = await res.json();
                             if(data.status === 'success') {
                                 this.unreadCount = data.unread_count || (this.unreadCount + 1);
                             } else {
                                 this.unreadCount++;
                             }
                         } catch(e) {
                             this.unreadCount++;
                         }
                     }
                 }"
                 @notification-updated.window="fetchTerbaru()">

                <button @click="openNotif = !openNotif" 
                        type="button" 
                        title="Notifikasi" 
                        class="relative flex size-10 items-center justify-center rounded-full text-slate-600 bg-slate-50 border border-slate-200/60 hover:bg-slate-100 hover:text-[#0969da] focus:outline-none transition-all shadow-sm shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    
                    <!-- Titik Merah Unread Badge Dinamis -->
                    <template x-if="unreadCount > 0">
                        <span class="absolute top-0 right-0 flex size-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-3 bg-rose-500 border-2 border-white"></span>
                        </span>
                    </template>
                </button>

                <!-- POPOVER DROPDOWN NOTIFIKASI -->
                <div x-show="openNotif" 
                     @click.away="openNotif = false" 
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 mt-3 w-80 sm:w-96 rounded-2xl bg-white shadow-2xl border border-slate-100 z-50 overflow-hidden divide-y divide-slate-100">
                    
                    <div class="px-4 py-3 bg-slate-50/80 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-[#213153]">Notifikasi</h3>
                            @if($unreadHeaderCount > 0)
                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-600">
                                    {{ $unreadHeaderCount }} Baru
                                </span>
                            @endif
                        </div>
                        <a href="{{ route('notifikasi.index') }}" class="text-xs font-semibold text-[#0969da] hover:underline">
                            Tandai Dibaca
                        </a>
                    </div>

                    <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                        @forelse($headerNotifs as $notif)
                            <a href="{{ route('notifikasi.index') }}" class="flex items-start gap-3 p-3.5 hover:bg-slate-50 transition-colors {{ !$notif->is_read ? 'bg-blue-50/40' : '' }}">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[#0969da] mt-0.5">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-[#213153] leading-tight truncate">
                                        {{ $notif->reminder->judul_alert ?? 'Pemberitahuan Sistem' }}
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                                        {{ $notif->reminder->pesan_alert ?? 'Ada pembaruan status pengadaan barang.' }}
                                    </p>
                                    <span class="text-[10px] text-slate-400 mt-1 block">
                                        {{ $notif->created_at ? $notif->created_at->diffForHumans() : 'Baru saja' }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="p-6 text-center text-xs text-slate-400">
                                Tidak ada notifikasi terbaru.
                            </div>
                        @endforelse
                    </div>

                    <div class="p-2.5 bg-slate-50/50 text-center">
                        <a href="{{ route('notifikasi.index') }}" class="block w-full py-1.5 text-xs font-bold text-[#0969da] rounded-lg hover:bg-slate-100 transition-colors">
                            Lihat Semua Notifikasi &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pemisah Vertikal (Figma Line 5) -->
            <div class="h-8 w-px bg-slate-200/80"></div>

            <!-- Mini Profil & Dropdown -->
            <div class="flex items-center gap-3" x-data="{ openProfile: false }">
                <div class="hidden text-right sm:block">
                    <div class="text-sm font-semibold leading-tight text-[#213153]">{{ Auth::user()->nama }}</div>
                    <div class="mt-0.5 flex items-center justify-end gap-1.5 text-xs font-medium text-slate-500">
                        <span>{{ Auth::user()->unit->nama_unit ?? Auth::user()->jabatan ?? 'Pegawai' }}</span>
                        <span class="text-slate-300">•</span>
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#0969da]">
                            {{ str_replace('_', ' ', Auth::user()->role) }}
                        </span>
                    </div>
                </div>

                <div class="relative">
                    <button @click="openProfile = !openProfile" type="button"
                            class="flex size-9 items-center justify-center rounded-full bg-[#0969da] font-bold text-white shadow-sm transition-all hover:brightness-110 focus:ring-2 focus:ring-[#0969da]/30">
                        @if(Auth::user()->foto_profil)
                            <img src="{{ asset('storage/' . Auth::user()->foto_profil) }}" alt="Foto Profile" class="size-full rounded-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                        @endif
                    </button>

                <div x-show="openProfile" @click.away="openProfile = false" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 z-50 mt-3 w-60 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-white p-2 shadow-xl">
                    <div class="px-3 py-2.5">
                        <div class="text-sm font-bold leading-snug text-[#213153]">{{ Auth::user()->nama }}</div>
                        <div class="truncate text-xs text-slate-500">{{ Auth::user()->email }}</div>
                        <div class="mt-1 text-[11px] font-bold uppercase text-[#0969da]">{{ str_replace('_', ' ', Auth::user()->role) }}</div>
                    </div>
                    <div class="py-1 text-xs font-medium text-slate-600">
                        <a href="{{ route('profile.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors hover:bg-slate-50">
                            <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7H5a7 7 0 0012-7z"/></svg>
                            Profil Saya
                        </a>
                    </div>
                    <div class="pt-1">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Keluar (Logout)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Legacy profile popover kept hidden for compatibility -->
                <div x-show="false"
                     @click.away="openProfile = false" 
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 mt-3 w-72 sm:w-80 rounded-2xl bg-white p-4 shadow-2xl border border-slate-100 z-50 divide-y divide-slate-100">
                    
                    <!-- User Overview Card -->
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <div class="min-w-0 flex-1">
                            <h4 class="font-bold text-sm text-[#213153] leading-snug truncate">
                                {{ Auth::user()->nama }}
                            </h4>
                            <p class="text-xs text-slate-500 truncate mt-0.5">
                                {{ Auth::user()->unit->nama_unit ?? Auth::user()->jabatan ?? 'Pegawai Dinas' }}
                            </p>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-[#0969da] uppercase tracking-wide mt-1.5">
                                @if(Auth::user()->roleEntity && !Auth::user()->roleEntity->is_system)
                                    {{ Auth::user()->roleEntity->label }} ({{ strtoupper(Auth::user()->role) }})
                                @else
                                    {{ str_replace('_', ' ', Auth::user()->role) }}
                                @endif
                            </span>
                        <div class="py-1 text-xs text-slate-600 font-medium">
                            <a href="{{ route('profile.index') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Profil Saya
                            </a>
                        </div>

                        <!-- Avatar Foto Besar -->
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-[#0969da] text-white text-lg font-bold shadow-sm overflow-hidden border-2 border-slate-100">
                            @if(Auth::user()->foto_profil)
                                <img src="{{ asset('storage/' . Auth::user()->foto_profil) }}" alt="{{ Auth::user()->nama }}" class="size-full object-cover">
                            @else
                                {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                    </div>

                    <!-- Clean Actions: Profil Saya & Logout (NO AI SLOP) -->
                    <div class="py-2 text-xs font-semibold text-slate-700 space-y-1">
                        <a href="{{ route('profile.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-slate-50 transition-colors">
                            <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Profil Saya
                        </a>
                    </div>

                    <div class="pt-2">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 text-xs font-semibold text-rose-600 rounded-xl hover:bg-rose-50 transition-colors cursor-pointer">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        @endauth
    </header>

    <!-- 2. MAIN LAYOUT WRAPPER -->
    <div class="flex flex-1 relative">

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
             class="fixed inset-0 z-20 bg-slate-900/50 backdrop-blur-xs md:hidden"></div>

        <!-- SIDEBAR UTAMA PERSIS FIGMA (#213153) DENGAN EFEK BISA MELEBAR/EXPANDABLE -->
        @auth
@php
    $menuOn  = 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 font-semibold';
    $menuOff = 'text-slate-300 hover:bg-white/10 hover:text-white';
@endphp

<aside class="flex flex-col overflow-hidden bg-[#213153] py-4 shadow-xl transition-all duration-300 z-20 shrink-0 min-h-screen md:min-h-[calc(100vh-70px)]"
       :class="{
           'w-80 px-1': sidebarExpanded,
           'w-16 items-center px-1': !sidebarExpanded,
           'fixed inset-y-0 left-0 pt-[80px] md:pt-5 md:static md:translate-x-0': true,
           'translate-x-0': mobileSidebarOpen,
           '-translate-x-full md:translate-x-0': !mobileSidebarOpen
       }">

    <!-- Header Sidebar -->
    <div class="mb-4 flex h-12 w-full items-center justify-between px-3">
        <a href="{{ route('dashboard') }}" x-show="sidebarExpanded" x-cloak class="flex shrink-0 items-center gap-2">
            <img src="{{ asset('favicon.ico') }}" alt="Logo SIM-PBJ" class="size-9 object-contain">
        </a>
        <button @click="sidebarExpanded = !sidebarExpanded" 
                type="button" 
                title="Buka/Tutup Sidebar" 
                :class="{ 'ml-auto': sidebarExpanded, 'mx-auto': !sidebarExpanded }"
                class="flex size-9 items-center justify-center rounded-xl text-slate-200 hover:bg-white/10 transition-colors">
            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Garis Pembatas Atas -->
    <div class="mx-3 mb-4 h-px w-[calc(100%-1.5rem)] bg-white/15"></div>

    <!-- Menu Navigation Stack (Scrollable jika menu banyak) -->
    <nav class="flex w-full flex-1 flex-col gap-3 overflow-y-auto overflow-x-hidden px-1">

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

        <!-- 3. USER: Permohonan Pengadaan -->
        @if(Auth::user()->hasRole('user'))
            @php $aUserPerm = request()->routeIs('permohonan.*'); @endphp
            <a href="{{ route('permohonan.index') }}" 
               title="Permohonan Saya" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aUserPerm ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Permohonan Saya</span>
            </a>
        @endif

        <!-- 4. STAFF BIDANG 2 -->
        @if(Auth::user()->hasRole('staff_bidang_2'))
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

        <!-- 5. WADIR 2 -->
        @if(Auth::user()->hasRole('wadir_2'))
            @php 
                $aWadir2 = request()->routeIs('verifikasi.wadir-2*'); 
                $aWadirBayar = request()->routeIs('pembayaran.*');
            @endphp
            <a href="{{ route('verifikasi.wadir-2') }}" 
               title="Persetujuan Eksekutif" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aWadir2 ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Persetujuan Eksekutif</span>
            </a>

            <a href="{{ route('pembayaran.index') }}" 
               title="Tagihan & Pembayaran SP2D" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aWadirBayar ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Tagihan &amp; Pembayaran SP2D</span>
            </a>
        @endif

        <!-- 6. PERENCANAAN -->
        @if(!Auth::user()->hasRole('admin') && (Auth::user()->hasRole('perencanaan') || Auth::user()->hasPermission('anggaran.mak_view')))
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

        <!-- 7. PPBJ -->
        @if(!Auth::user()->hasRole('admin') && (Auth::user()->hasRole('ppbj') || Auth::user()->hasPermission('paket.view_all')))
            @php 
                $aPaket = request()->routeIs('paket.*');
                $aKontrakPPBJ = request()->routeIs('kontrak.*');
                $aBayarPPBJ = request()->routeIs('pembayaran.*');
            @endphp
            <a href="{{ route('paket.index') }}" 
               title="Paket Pengadaan" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPaket ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Paket Pengadaan</span>
            </a>

            <a href="{{ route('kontrak.index') }}" 
               title="Kontrak SPK" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aKontrakPPBJ ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Kontrak SPK</span>
            </a>

            <a href="{{ route('pembayaran.index') }}" 
               title="Perintah Bayar" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aBayarPPBJ ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Perintah Bayar</span>
            </a>
        @endif

        <!-- 8. PP -->
        @if(Auth::user()->hasRole('pp'))
            @php $aPPKontrak = request()->routeIs('kontrak.*'); @endphp
            <a href="{{ route('kontrak.index') }}" 
               title="Reviu & Negosiasi Harga" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPPKontrak ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Reviu &amp; Negosiasi Harga</span>
            </a>
        @endif

        <!-- 9. PPK -->
        @if(Auth::user()->hasRole('ppk'))
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
                $aPPKKomitmen = request()->routeIs('kontrak.*');
                $aPPKBayar = request()->routeIs('pembayaran.*');
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

            <a href="{{ route('kontrak.index') }}" 
               title="Approval Paket Komitmen" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPPKKomitmen ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Approval Paket Komitmen</span>
            </a>

            <a href="{{ route('pembayaran.index') }}" 
               title="Memo Bayar Level 1" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aPPKBayar ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Memo Bayar Level 1</span>
            </a>
        @endif

        <!-- 10. PERLENGKAPAN -->
        @if(Auth::user()->hasRole('perlengkapan'))
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

        <!-- 11. KEUANGAN -->
        @if(Auth::user()->hasRole('keuangan'))
            @php $aKeuangan = request()->routeIs('pembayaran.*'); @endphp
            <a href="{{ route('pembayaran.index') }}" 
               title="Tagihan & Pembayaran SP2D" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aKeuangan ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Tagihan &amp; Pembayaran SP2D</span>
            </a>
        @endif

        <!-- 12. MONITORING 8 SIKLUS (Non-Admin Roles) -->
        @if(!Auth::user()->hasRole('admin'))
            @php $aMonitoring = request()->routeIs('monitoring.*'); @endphp
            <a href="{{ route('monitoring.index') }}" 
               title="Monitoring 8 Siklus" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aMonitoring ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 8h8M17 8h4M3 16h2M11 16h10"/>
                    <circle cx="14" cy="8" r="2.5" fill="{{ $aMonitoring ? 'currentColor' : 'none' }}"/>
                    <circle cx="8" cy="16" r="2.5" fill="{{ $aMonitoring ? 'currentColor' : 'none' }}"/>
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Monitoring 8 Siklus</span>
            </a>
        @endif

        <!-- 13. LAPORAN REKAPITULASI (Wadir 2, Perencanaan, PPBJ, Keuangan) -->
        @if(Auth::user()->hasRole(['wadir_2', 'perencanaan', 'ppbj', 'keuangan']))
            @php $aLaporan = request()->routeIs('laporan.*'); @endphp
            <a href="{{ route('laporan.rekapitulasi') }}" 
               title="Laporan Rekapitulasi" 
               class="flex h-[49px] items-center gap-4 rounded-[10px] px-4 transition-all w-full {{ $aLaporan ? $menuOn : $menuOff }}">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3.5" y="3.5" width="17" height="17" rx="3" fill="{{ $aLaporan ? 'currentColor' : 'none' }}"/>
                    <rect x="7" y="7.5" width="3.5" height="3.5" rx="0.8" stroke="{{ $aLaporan ? '#0969da' : 'currentColor' }}" fill="{{ $aLaporan ? '#0969da' : 'none' }}"/>
                    <path d="M13.5 8.5h3M13.5 11h3M7 15.5h10" stroke="{{ $aLaporan ? '#0969da' : 'currentColor' }}"/>
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="whitespace-nowrap text-sm">Laporan Rekapitulasi</span>
            </a>
        @endif

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
</aside>

        </aside>
        @endauth

        <!-- 3. KONTEN UTAMA HALAMAN -->
        <main class="min-w-0 flex-1 p-6 md:p-10">
            
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
                            class="rounded-lg bg-white px-4 py-2 text-xs font-bold text-[#213153] shadow-sm hover:bg-blue-50 transition-colors">
                        Izinkan Notifikasi OS
                    </button>
                    <button @click="dismissBanner()" 
                            type="button" 
                            class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white transition-colors"
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
    </div>

    <!-- FOOTER -->
    <footer class="py-4 px-6 text-center text-xs text-slate-500 border-t border-slate-200/60 bg-white">
        &copy; {{ date('Y') }} <strong>SIM-PBJ Politeknik Negeri Jakarta</strong>. Jl. Prof. Dr. G.A. Siwabessy, Kampus UI Depok 16425.
    </footer>



</body>
</html>