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

    <!-- CDN Tailwind CSS & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Google Fonts: Iceland & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Iceland&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .font-iceland {
            font-family: 'Iceland', cursive, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-[#F4F8FA] text-gray-800 flex flex-col antialiased">

    <!-- 1. HEADER UTAMA -->
    <header class="sticky top-0 z-30 flex h-[70px] w-full shrink-0 items-center justify-between border-b border-slate-200/80 bg-white px-6 shadow-sm">
        
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
                ->latest()
                ->take(5)
                ->get();
            $unreadHeaderCount = \App\Models\NotifikasiUser::where('id_user', Auth::id())->where('is_read', false)->count();
        @endphp
        <div class="flex items-center gap-4">

            <!-- ICON NOTIFIKASI LONCENG DROPDOWN -->
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

            <!-- Mini Profil & Dropdown -->
            <div class="flex items-center gap-3" x-data="{ openProfile: false }">
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-semibold text-[#213153] leading-tight">{{ Auth::user()->nama }}</div>
                    <div class="text-xs text-slate-500 font-medium mt-0.5 flex items-center justify-end gap-1.5">
                        <span>{{ Auth::user()->unit->nama_unit ?? Auth::user()->jabatan ?? 'Pegawai' }}</span>
                        <span class="text-slate-300">•</span>
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-[#0969da] uppercase tracking-wide">
                            {{ str_replace('_', ' ', Auth::user()->role) }}
                        </span>
                    </div>
                </div>

                <div class="relative">
                    <button @click="openProfile = !openProfile" 
                            type="button"
                            class="flex size-9 items-center justify-center rounded-full bg-[#0969da] text-white font-bold shadow-sm hover:brightness-110 focus:ring-2 focus:ring-[#0969da]/30 transition-all">
                        @if(Auth::user()->foto_profil)
                            <img src="{{ asset('storage/' . Auth::user()->foto_profil) }}" alt="Foto Profile" class="size-full rounded-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                        @endif
                    </button>

                    <div x-show="openProfile" 
                         @click.away="openProfile = false" 
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-3 w-60 rounded-xl bg-white p-2 shadow-xl border border-slate-100 z-50 divide-y divide-slate-100">
                        
                        <div class="px-3 py-2.5">
                            <div class="font-bold text-sm text-[#213153] leading-snug">{{ Auth::user()->nama }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</div>
                            <div class="text-[11px] font-bold text-[#0969da] mt-1 uppercase">{{ str_replace('_', ' ', Auth::user()->role) }}</div>
                        </div>

                        <div class="py-1 text-xs text-slate-600 font-medium">
                            <a href="{{ route('profile.index') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Profil Saya
                            </a>
                        </div>

                        <div class="pt-1">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-xs font-semibold text-rose-600 rounded-lg hover:bg-rose-50 transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Keluar (Logout)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endauth
    </header>

    <!-- 2. MAIN LAYOUT WRAPPER -->
    <div class="flex flex-1 relative">

        <!-- SIDEBAR UTAMA PERSIS FIGMA (#213153) DENGAN EFEK BISA MELEBAR/EXPANDABLE -->
        @auth
        @php
            // Status menu aktif + class warna (dipakai ulang oleh semua item sidebar)
            $aHome  = request()->routeIs('dashboard');
            $aMon   = request()->routeIs('permohonan.*');
            $aRiw   = request()->routeIs('laporan.*');
            $aNotif = request()->routeIs('notifikasi.*');
            $aProf  = request()->routeIs('profile.*');
            $menuOn  = 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 font-semibold';
            $menuOff = 'text-slate-300 hover:bg-white/10 hover:text-white';
        @endphp
        <aside :class="sidebarOpen ? 'w-64' : 'w-16'" 
               class="flex shrink-0 flex-col bg-[#213153] py-5 shadow-xl transition-all duration-300 z-20 overflow-hidden min-h-screen">
            
            <!-- Tombol Toggle Hamburger Menu (≡) -->
            <div class="px-3.5 mb-6 flex items-center justify-between">
                <button @click="sidebarOpen = !sidebarOpen" 
                        type="button" 
                        title="Buka/Tutup Sidebar" 
                        class="flex size-9 items-center justify-center rounded-xl text-slate-200 hover:bg-white/10 transition-colors">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <!-- GARIS PEMBATAS ATAS -->
            <div class="mx-3 mb-6 h-px bg-white/15"></div>

            <!-- MENU ITEM FIGMA LIST -->
            <nav class="flex flex-col gap-3 px-2">
                
                <!-- 1. Dashboard -->
                <a href="{{ route('dashboard') }}" title="Dashboard" 
                   class="flex h-11 items-center gap-4 rounded-xl px-3 transition-all {{ $aHome ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 10.2 12 3.8l8 6.4V19a1.5 1.5 0 0 1-1.5 1.5H15v-4.5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v4.5H5.5A1.5 1.5 0 0 1 4 19z" fill="{{ $aHome ? 'currentColor' : 'none' }}"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap text-sm">Dashboard</span>
                </a>

                <!-- 2. Monitoring (Siklus Pengadaan) -->
                <a href="{{ route('permohonan.index') }}" title="Monitoring" 
                   class="flex h-11 items-center gap-4 rounded-xl px-3 transition-all {{ $aMon ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 8h8M17 8h4M3 16h2M11 16h10"/>
                        <circle cx="14" cy="8" r="2.5" fill="{{ $aMon ? 'currentColor' : 'none' }}"/>
                        <circle cx="8" cy="16" r="2.5" fill="{{ $aMon ? 'currentColor' : 'none' }}"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap text-sm">Monitoring</span>
                </a>

                <!-- 3. Riwayat Pengajuan / Laporan -->
                <a href="{{ route('laporan.rekapitulasi') }}" title="Riwayat Pengajuan" 
                   class="flex h-11 items-center gap-4 rounded-xl px-3 transition-all {{ $aRiw ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="3.5" width="17" height="17" rx="3" fill="{{ $aRiw ? 'currentColor' : 'none' }}"/>
                        <rect x="7" y="7.5" width="3.5" height="3.5" rx="0.8" stroke="{{ $aRiw ? '#0969da' : 'currentColor' }}" fill="{{ $aRiw ? '#0969da' : 'none' }}"/>
                        <path d="M13.5 8.5h3M13.5 11h3M7 15.5h10" stroke="{{ $aRiw ? '#0969da' : 'currentColor' }}"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap text-sm">Riwayat Pengajuan</span>
                </a>

                <!-- 4. Notifikasi -->
                <a href="{{ route('notifikasi.index') }}" title="Notifikasi" 
                   class="flex h-11 items-center gap-4 rounded-xl px-3 transition-all relative {{ $aNotif ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 10a6 6 0 1 1 12 0c0 3.2 1 4.7 2 5.8a.6.6 0 0 1-.45 1H4.45a.6.6 0 0 1-.45-1C5 14.7 6 13.2 6 10z" fill="{{ $aNotif ? 'currentColor' : 'none' }}"/>
                        <path d="M10 20a2 2 0 0 0 4 0"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap text-sm">Notifikasi</span>
                    
                    @if($unreadHeaderCount > 0)
                        <span x-show="!sidebarOpen" class="absolute top-2 right-2 flex size-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2.5 bg-cyan-400"></span>
                        </span>
                        <span x-show="sidebarOpen" x-cloak class="ml-auto rounded-full bg-cyan-400/20 px-2 py-0.5 text-[10px] font-bold text-cyan-300">
                            {{ $unreadHeaderCount }}
                        </span>
                    @endif
                </a>

            </nav>

            <!-- GARIS PEMBATAS BAWAH -->
            <div class="mx-3 my-6 h-px bg-white/15"></div>

            <!-- 5. Profil -->
            <div class="px-2">
                <a href="{{ route('profile.index') }}" title="Profil" 
                   class="flex h-11 items-center gap-4 rounded-xl px-3 transition-all {{ $aProf ? $menuOn : $menuOff }}">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.5" fill="{{ $aProf ? 'currentColor' : 'none' }}"/>
                        <path d="M5 20v-.5c0-2.6 3.1-4.5 7-4.5s7 1.9 7 4.5v.5z" fill="{{ $aProf ? 'currentColor' : 'none' }}"/>
                    </svg>
                    <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap text-sm">Profil</span>
                </a>
            </div>

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
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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

    @auth
    <!-- Script Notifikasi Desktop OS -->
    <script>
        function desktopNotificationManager() {
            return {
                permission: (typeof Notification !== 'undefined') ? Notification.permission : 'denied',
                showBanner: false,

                init() {
                    if (typeof Notification !== 'undefined') {
                        this.permission = Notification.permission;
                        if (this.permission === 'default' && !localStorage.getItem('simpbj_os_notif_dismissed')) {
                            this.showBanner = true;
                        }
                    }

                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('/sw.js').catch(function(err) {
                            console.warn('Gagal mendaftarkan Service Worker SIM-PBJ:', err);
                        });
                    }

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
                            .catch(err => {});
                    };

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