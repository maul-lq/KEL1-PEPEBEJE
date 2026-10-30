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
    <!-- (Isi popover dropdown tetap seperti kode lama Anda) -->

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

                <!-- Avatar Button -->
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

                    <!-- Menu Dropdown Profil -->
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
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                Dashboard Utama
                            </a>
                            <a href="{{ route('monitoring.index') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                Monitoring 8 Siklus
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
    <div class="flex flex-1">

        <!-- SIDEBAR UTAMA (#213153) -->
        @auth
        <aside class="flex w-16 shrink-0 flex-col items-center gap-4 bg-[#213153] py-6 shadow-xl transition-all z-20">
            
            <!-- Dashboard Beranda -->
            <a href="{{ route('dashboard') }}" title="Dashboard Beranda" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('dashboard') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </a>

            <!-- Permohonan Pengadaan -->
            @if(Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create', 'permohonan.view_own']))
            <a href="{{ route('permohonan.index') }}" title="Permohonan Saya" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('permohonan.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </a>
            @endif

            <!-- Notifikasi & Reminder (Sidebar) -->
            <a href="{{ route('notifikasi.index') }}" title="Notifikasi dan Reminder" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('notifikasi.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                @if($unreadHeaderCount > 0)
                    <span class="absolute top-1 right-1 flex size-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2.5 bg-cyan-400"></span>
                    </span>
                @endif
            </a>

            <!-- Menu Admin -->
            @if(Auth::user()->hasRole('admin'))
            <a href="{{ route('admin.persetujuan-registrasi.index') }}" title="Persetujuan Registrasi Pengguna" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('admin.persetujuan-registrasi.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </a>
            <a href="{{ route('admin.roles.index') }}" title="Manajemen Role & Hak Akses" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('admin.roles.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
            </a>
            @endif

            <!-- Verifikasi Staf Bidang 2 -->
            @if(Auth::user()->hasRole('staff_bidang_2'))
            <a href="{{ route('verifikasi.bidang-2') }}" title="Telaah Berkas Bidang 2" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('verifikasi.bidang-2') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </a>
            @endif

            <!-- Verifikasi Wadir 2 -->
            @if(Auth::user()->hasRole('wadir_2'))
            <a href="{{ route('verifikasi.wadir-2') }}" title="Persetujuan Eksekutif Wadir 2" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('verifikasi.wadir-2') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </a>
            @endif

            <!-- Verifikasi Perencanaan -->
            @if(Auth::user()->hasRole('perencanaan'))
            <a href="{{ route('verifikasi.perencanaan') }}" title="Verifikasi MAK & Pagu Perencanaan" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('verifikasi.perencanaan') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </a>
            @endif

            <!-- Paket PPBJ -->
            @if(Auth::user()->hasRole('ppbj'))
            <a href="{{ route('paket.index') }}" title="Paket Pengadaan PPBJ" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('paket.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </a>
            @endif

            <!-- Kontrak SPK -->
            @if(Auth::user()->hasRole(['ppbj', 'pp', 'ppk']))
            <a href="{{ route('kontrak.index') }}" title="Kontrak SPK & Reviu" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('kontrak.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </a>
            @endif

            <!-- Penerimaan Barang -->
            @if(Auth::user()->hasRole('perlengkapan'))
            <a href="{{ route('penerimaan.index') }}" title="Penerimaan Barang & BAST" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('penerimaan.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </a>
            @endif

            <!-- Pembayaran & SP2D -->
            @if(Auth::user()->hasRole(['keuangan', 'ppbj', 'ppk', 'wadir_2']))
            <a href="{{ route('pembayaran.index') }}" title="Tagihan & Pembayaran SP2D" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('pembayaran.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </a>
            @endif

            <!-- Monitoring 8 Siklus -->
            <a href="{{ route('monitoring.index') }}" title="Monitoring 8 Siklus Pengadaan" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('monitoring.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </a>

            <!-- Laporan Rekapitulasi -->
            <a href="{{ route('laporan.rekapitulasi') }}" title="Laporan Rekapitulasi Anggaran" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </a>

            <!-- Profil Saya -->
            <a href="{{ route('profile.index') }}" title="Profil Saya" 
               class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition-all {{ request()->routeIs('profile.*') ? 'bg-[#0969da] text-white shadow-lg shadow-[#0969da]/40 scale-105' : 'hover:bg-white/10 hover:text-white' }}">
                <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
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



</body>
</html>