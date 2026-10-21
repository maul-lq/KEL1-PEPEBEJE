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
            <span class="text-[40px] font-normal leading-none text-white tracking-wide" style="-webkit-text-stroke: 1px #689EC2; text-shadow: 0 0 4px rgba(0,0,0,0.25), 0 4px 4px rgba(0,0,0,0.25);">
                SIM-PBJ
            </span>
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

                <!-- Dropdown Logout -->
                <div x-show="openProfile" @click.away="openProfile = false" x-transition class="absolute right-0 mt-2 w-48 rounded-lg bg-white p-2 shadow-xl border border-slate-200 z-50" style="display: none;">
                    <div class="px-3 py-2 text-xs border-b border-slate-100 sm:hidden">
                        <div class="font-bold text-gray-800">{{ Auth::user()->nama }}</div>
                        <div class="text-gray-500">{{ Auth::user()->jabatan }}</div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition-colors">
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

        <!-- SIDEBAR IKON RAMPING (Dinamis Berpindah Bulatan) -->
        @auth
        <aside class="flex w-16 shrink-0 flex-col items-center gap-6 bg-[#689EC2] pt-6 shadow-md">
            
            <!-- Icon 1: Permohonan / Dashboard -->
            <a href="{{ route('permohonan.index') }}" title="Permohonan Saya" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('permohonan.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
            </a>

            <!-- Icon 2: Analytics / Monitoring -->
            <a href="{{ route('monitoring.index') }}" title="Monitoring 8 Siklus" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('monitoring.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </a>

            <!-- Icon 3: Notifikasi -->
            <a href="#" title="Notifikasi" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('notifikasi.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                </svg>
            </a>

            <!-- Icon 4: Profil User -->
            <a href="#" title="Profil Saya" 
               class="flex h-10 items-center justify-center text-[#E8F5FD] transition-all {{ request()->routeIs('profile.*') ? 'w-[46px] rounded-[20px] bg-[#0088E8] shadow-[inset_0_0_9px_rgba(0,0,0,0.25)] scale-105' : 'w-10 hover:text-white hover:scale-110' }}">
                <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
            </a>
        </aside>
        @endauth

        <!-- 3. KONTEN UTAMA (Injeksi dari View) -->
        <main class="min-w-0 flex-1 p-6 md:p-10">
            
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

</body>
</html>