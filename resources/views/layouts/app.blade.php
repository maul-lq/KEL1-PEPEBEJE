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
        
        <!-- Logo Favicon & Teks SIM-PBJ & Toggle Sidebar -->
        <div class="flex items-center gap-3">
            @auth
            <!-- Hamburger Toggle Button -->
            <button @click="sidebarExpanded = !sidebarExpanded; mobileSidebarOpen = !mobileSidebarOpen" 
                    type="button" 
                    title="Buka/Tutup Navigasi"
                    class="flex size-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 hover:text-[#0969da] focus:outline-none transition-colors shrink-0 cursor-pointer">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            @endauth

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
                        class="relative flex size-10 items-center justify-center rounded-full text-slate-600 bg-slate-50 border border-slate-200/60 hover:bg-slate-100 hover:text-[#0969da] focus:outline-none transition-all shadow-sm shrink-0 cursor-pointer">
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

                    <!-- DAFTAR ITEM NOTIFIKASI -->
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

                    <!-- FOOTER DROPDOWN -->
                    <div class="p-2.5 bg-slate-50/50 text-center">
                        <a href="{{ route('notifikasi.index') }}" class="block w-full py-1.5 text-xs font-bold text-[#0969da] rounded-lg hover:bg-slate-100 transition-colors">
                            Lihat Semua Notifikasi &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pemisah Vertikal (Figma Line 5) -->
            <div class="h-8 w-px bg-slate-200/80"></div>

            <!-- Mini Profil Trigger & Dropdown (Figma Node 395:318 / 395:320) -->
            <div class="relative" x-data="{ openProfile: false }">
                <button @click="openProfile = !openProfile" 
                        type="button" 
                        class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-slate-50 focus:outline-none transition-colors group cursor-pointer">
                    
                    <!-- Avatar Kiri (40px circular size-10) -->
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0969da] text-white font-bold shadow-sm overflow-hidden border border-slate-200/80">
                        @if(Auth::user()->foto_profil)
                            <img src="{{ asset('storage/' . Auth::user()->foto_profil) }}" alt="{{ Auth::user()->nama }}" class="size-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                        @endif
                    </div>

                    <!-- Nama & Unit/Role (Tengah, Rata Kiri) -->
                    <div class="text-left hidden sm:block min-w-0 max-w-[180px]">
                        <div class="text-xs font-bold text-[#213153] leading-tight truncate group-hover:text-[#0969da] transition-colors">
                            {{ Auth::user()->nama }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium truncate mt-0.5">
                            {{ Auth::user()->unit->nama_unit ?? Auth::user()->jabatan ?? 'Pegawai' }}
                        </div>
                    </div>

                    <!-- Chevron Kanan (w-[11px] h-[6px] text-slate-400) -->
                    <svg class="w-[11px] h-[6px] text-slate-400 group-hover:text-slate-600 transition-transform" :class="{ 'rotate-180': openProfile }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Popover Dropdown Profil (Figma Node 395:320) -->
                <div x-show="openProfile" 
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

        <!-- SIDEBAR UTAMA (#213153) -->
        @auth
        <aside class="flex flex-col bg-[#213153] py-5 shadow-xl transition-all duration-300 z-20 shrink-0 min-h-[calc(100vh-70px)]"
               :class="{
                   'w-[321px] px-4': sidebarExpanded,
                   'w-16 items-center px-2': !sidebarExpanded,
                   'fixed inset-y-0 left-0 pt-[80px] md:pt-5 md:static md:translate-x-0': true,
                   'translate-x-0': mobileSidebarOpen,
                   '-translate-x-full md:translate-x-0': !mobileSidebarOpen
               }">
            
            <!-- Menu Navigation Stack -->
            <div class="flex flex-col gap-1.5 w-full flex-1">

                <!-- 1. DASHBOARD (All Roles) -->
                <a href="{{ route('dashboard') }}" 
                   title="Dashboard" 
                   class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('dashboard') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Dashboard</span>
                </a>

                <!-- 2. ADMIN ONLY: RBAC, User CRUD & Master Management -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['admin.approval_registrasi', 'admin.user_manage', 'admin.role_manage']))
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.approval_registrasi'))
                        @php
                            $pendingRegistrasiCount = \App\Models\User::where('status_registrasi', \App\Models\User::STATUS_REGISTRASI_MENUNGGU)->count();
                        @endphp
                        <a href="{{ route('admin.persetujuan-registrasi.index') }}" 
                           title="Persetujuan Registrasi" 
                           class="relative flex items-center justify-between rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('admin.persetujuan-registrasi.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Persetujuan Registrasi</span>
                            </div>
                            @if($pendingRegistrasiCount > 0)
                                <span x-show="sidebarExpanded" x-cloak class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white">
                                    {{ $pendingRegistrasiCount }}
                                </span>
                            @endif
                        </a>
                    @endif

                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.user_manage'))
                        <a href="{{ route('admin.users.index') }}" 
                           title="Manajemen Pengguna" 
                           class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('admin.users.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Manajemen Pengguna</span>
                        </a>
                    @endif

                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.role_manage'))
                        <a href="{{ route('admin.roles.index') }}" 
                           title="Master Role & Hak Akses" 
                           class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('admin.roles.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Master Role &amp; Hak Akses</span>
                        </a>
                    @endif

                    @if(Auth::user()->hasRole('admin'))
                        <a href="{{ route('permohonan.index') }}" 
                           title="Kelola / Hapus Pengajuan" 
                           class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('permohonan.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Kelola / Hapus Pengajuan</span>
                        </a>
                    @endif
                @endif

                <!-- 3. USER: Permohonan Pengadaan -->
                @if(Auth::user()->hasRole('user'))
                    <a href="{{ route('permohonan.index') }}" 
                       title="Permohonan Saya" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('permohonan.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Permohonan Saya</span>
                    </a>
                @endif

                <!-- 4. STAFF BIDANG 2 -->
                @if(Auth::user()->hasRole('staff_bidang_2'))
                    <a href="{{ route('verifikasi.bidang-2') }}" 
                       title="Telaah Berkas Bidang II" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('verifikasi.bidang-2*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Telaah Berkas Bidang II</span>
                    </a>
                @endif

                <!-- 5. WADIR 2 -->
                @if(Auth::user()->hasRole('wadir_2'))
                    <a href="{{ route('verifikasi.wadir-2') }}" 
                       title="Persetujuan Eksekutif" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('verifikasi.wadir-2*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Persetujuan Eksekutif</span>
                    </a>

                    <a href="{{ route('pembayaran.index') }}" 
                       title="Tagihan & Pembayaran SP2D" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('pembayaran.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Tagihan &amp; Pembayaran SP2D</span>
                    </a>
                @endif

                <!-- 6. PERENCANAAN -->
                @if(!Auth::user()->hasRole('admin') && (Auth::user()->hasRole('perencanaan') || Auth::user()->hasPermission('anggaran.mak_view')))
                    <a href="{{ route('verifikasi.perencanaan') }}" 
                       title="Verifikasi MAK & Pagu" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('verifikasi.perencanaan*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Verifikasi MAK &amp; Pagu</span>
                    </a>
                @endif

                <!-- 7. PPBJ -->
                @if(!Auth::user()->hasRole('admin') && (Auth::user()->hasRole('ppbj') || Auth::user()->hasPermission('paket.view_all')))
                    <a href="{{ route('paket.index') }}" 
                       title="Paket Pengadaan" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('paket.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Paket Pengadaan</span>
                    </a>

                    <a href="{{ route('vendors.index') }}" 
                       title="Direktori Rekanan & Vendor" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('vendors.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Direktori Rekanan</span>
                    </a>

                    <a href="{{ route('kontrak.index') }}" 
                       title="Kontrak SPK" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('kontrak.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Kontrak SPK</span>
                    </a>

                    <a href="{{ route('pembayaran.index') }}" 
                       title="Perintah Bayar" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('pembayaran.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Perintah Bayar</span>
                    </a>
                @endif

                <!-- 8. PP -->
                @if(Auth::user()->hasRole('pp'))
                    <a href="{{ route('kontrak.index') }}" 
                       title="Reviu & Negosiasi Harga" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('kontrak.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Reviu &amp; Negosiasi Harga</span>
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
                    @endphp
                    <a href="{{ route('verifikasi.ppk') }}" 
                       title="Persetujuan Awal Usulan PPK" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('verifikasi.ppk*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Persetujuan Awal Usulan</span>
                        @if($ppkAntreanCount > 0)
                            <span class="absolute top-2 right-2 flex size-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full size-2.5 bg-cyan-400"></span>
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('kontrak.index') }}" 
                       title="Approval Paket Komitmen" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('kontrak.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Approval Paket Komitmen</span>
                    </a>

                    <a href="{{ route('pembayaran.index') }}" 
                       title="Memo Bayar Level 1" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('pembayaran.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Memo Bayar Level 1</span>
                    </a>
                @endif

                <!-- 10. PERLENGKAPAN -->
                @if(Auth::user()->hasRole('perlengkapan'))
                    <a href="{{ route('penerimaan.index') }}" 
                       title="Penerimaan Barang & BAST" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('penerimaan.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Penerimaan Barang &amp; BAST</span>
                    </a>
                @endif

                <!-- 11. KEUANGAN -->
                @if(Auth::user()->hasRole('keuangan'))
                    <a href="{{ route('pembayaran.index') }}" 
                       title="Tagihan & Pembayaran SP2D" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('pembayaran.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Tagihan &amp; Pembayaran SP2D</span>
                    </a>
                @endif

                <!-- 12. MONITORING 8 SIKLUS (Non-Admin Roles) -->
                @if(!Auth::user()->hasRole('admin'))
                    <a href="{{ route('monitoring.index') }}" 
                       title="Monitoring 8 Siklus" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('monitoring.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Monitoring 8 Siklus</span>
                    </a>
                @endif

                <!-- 13. LAPORAN REKAPITULASI (Wadir 2, Perencanaan, PPBJ, Keuangan) -->
                @if(Auth::user()->hasRole(['wadir_2', 'perencanaan', 'ppbj', 'keuangan']))
                    <a href="{{ route('laporan.rekapitulasi') }}" 
                       title="Laporan Rekapitulasi" 
                       class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('laporan.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Laporan Rekapitulasi</span>
                    </a>
                @endif

                <!-- 14. NOTIFIKASI & REMINDER (All Roles) -->
                <a href="{{ route('notifikasi.index') }}" 
                   title="Notifikasi & Reminder" 
                   class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('notifikasi.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <div class="relative size-5 shrink-0 flex items-center justify-center">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($unreadHeaderCount > 0)
                            <span class="absolute -top-1 -right-1 size-2 rounded-full bg-rose-500"></span>
                        @endif
                    </div>
                    <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Notifikasi &amp; Reminder</span>
                    @if($unreadHeaderCount > 0)
                        <span x-show="sidebarExpanded" x-cloak class="ml-auto inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white">
                            {{ $unreadHeaderCount }}
                        </span>
                    @endif
                </a>

            </div>

            <!-- Horizontal Divider (Figma Line 3) -->
            <div class="my-2 border-t border-white/10 w-full"></div>

            <!-- PROFIL SAYA (All Roles) -->
            <a href="{{ route('profile.index') }}" 
               title="Profil" 
               class="relative flex items-center gap-3 rounded-[10px] p-2.5 transition-colors w-full {{ request()->routeIs('profile.*') ? 'bg-[#0969da]/80 text-white font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span x-show="sidebarExpanded" x-cloak class="text-sm font-medium whitespace-nowrap">Profil</span>
            </a>

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