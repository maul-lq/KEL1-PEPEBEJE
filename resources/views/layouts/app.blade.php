<!DOCTYPE html>
<html lang="id" class="h-full bg-zinc-100 text-zinc-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sistem Pengadaan Barang & Jasa (PBJ)' }} - Kampus</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Grayscale & High Contrast Enhancements */
        body {
            font-size: 16px;
            color: #18181b;
            background-color: #f4f4f5;
        }
        .btn-grayscale-primary {
            background-color: #18181b;
            color: #ffffff;
            transition: all 0.15s ease-in-out;
        }
        .btn-grayscale-primary:hover {
            background-color: #27272a;
        }
        .btn-grayscale-secondary {
            background-color: #e4e4e7;
            color: #18181b;
            border: 1px solid #d4d4d8;
            transition: all 0.15s ease-in-out;
        }
        .btn-grayscale-secondary:hover {
            background-color: #d4d4d8;
        }
    </style>
</head>
<body class="min-h-full flex flex-col font-sans antialiased text-zinc-900 bg-zinc-100">

    <!-- BAR SWITCH ROLE CEPAT UNTUK PROTOTIPE -->
    <div class="bg-zinc-900 text-white border-b border-zinc-800 text-sm py-2 px-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center space-x-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                <span class="font-bold text-zinc-200">Mode Prototipe PPBJ</span>
                <span class="text-zinc-400">| Peran Aktif:</span>
                <span class="font-semibold text-white bg-zinc-800 px-2.5 py-0.5 rounded border border-zinc-700">
                    {{ Auth::user()->role_label }} ({{ Auth::user()->name }})
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-zinc-400 font-medium mr-1">Ganti Peran:</span>
                <a href="{{ route('quick-login', 'user') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'user' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">1. User</a>
                <a href="{{ route('quick-login', 'wadir2') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'wadir2' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">2. Wadir 2</a>
                <a href="{{ route('quick-login', 'perencanaan') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'perencanaan' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">3. Perencanaan</a>
                <a href="{{ route('quick-login', 'ppbj') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'ppbj' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">4. PPBJ</a>
                <a href="{{ route('quick-login', 'ppk_pp') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'ppk_pp' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">5. PPK/PP</a>
                <a href="{{ route('quick-login', 'perlengkapan') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'perlengkapan' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">6. Perlengkapan</a>
                <a href="{{ route('quick-login', 'keuangan') }}" class="px-2 py-1 rounded {{ Auth::user()->role === 'keuangan' ? 'bg-white text-zinc-900 font-bold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200' }}">7. Keuangan</a>
            </div>
        </div>
    </div>

    <!-- NAVBAR UTAMA -->
    <header class="bg-white border-b border-zinc-300 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-18">
                <div class="flex items-center space-x-6">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-bold text-xl tracking-tight">
                            PBJ
                        </div>
                        <div>
                            <div class="font-extrabold text-lg text-zinc-900 leading-tight">SIP-PBJ KAMPUS</div>
                            <div class="text-xs text-zinc-500 font-medium">Pengelolaan Pengadaan Barang & Jasa</div>
                        </div>
                    </a>

                    <!-- NAV LINKS -->
                    <nav class="hidden md:flex space-x-1 ml-6">
                        <a href="{{ route('dashboard') }}" class="px-3.5 py-2 rounded-md text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-zinc-200 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('monitoring') }}" class="px-3.5 py-2 rounded-md text-sm font-semibold {{ request()->routeIs('monitoring') ? 'bg-zinc-200 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            Monitoring Berkas
                        </a>
                        <a href="{{ route('pengadaan.index') }}" class="px-3.5 py-2 rounded-md text-sm font-semibold {{ request()->routeIs('pengadaan.*') ? 'bg-zinc-200 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            Daftar Pengadaan
                        </a>
                        <a href="{{ route('vendor.index') }}" class="px-3.5 py-2 rounded-md text-sm font-semibold {{ request()->routeIs('vendor.*') ? 'bg-zinc-200 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            Data Rekanan/Vendor
                        </a>
                        <a href="{{ route('reminders.index') }}" class="px-3.5 py-2 rounded-md text-sm font-semibold {{ request()->routeIs('reminders.*') ? 'bg-zinc-200 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            Pengingat & Alarm
                        </a>
                    </nav>
                </div>

                <!-- USER PROFILE & LOGOUT -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-bold text-zinc-900">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-zinc-500">{{ Auth::user()->unit_kerja }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3.5 py-2 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 text-xs font-semibold rounded-md border border-zinc-300 cursor-pointer">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTENT WRAPPER -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- FLASH MESSAGES -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-lg bg-zinc-900 text-white border-l-4 border-zinc-400 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6 text-zinc-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="font-medium text-base">{{ session('success') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-zinc-400 hover:text-white font-bold text-lg px-2">&times;</button>
            </div>
        @endif

        @if (session('warning'))
            <div class="mb-6 p-4 rounded-lg bg-zinc-800 text-zinc-100 border-l-4 border-zinc-500 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6 text-zinc-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span class="font-medium text-base">{{ session('warning') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-zinc-400 hover:text-white font-bold text-lg px-2">&times;</button>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-zinc-900 text-white border-l-4 border-zinc-400 shadow-sm">
                <div class="font-bold text-base mb-1">Perhatian: Mohon periksa kembali isian formulir:</div>
                <ul class="list-disc list-inside text-sm space-y-1 text-zinc-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- FOOTER -->
    <footer class="bg-white border-t border-zinc-300 py-6 text-center text-xs text-zinc-500 mt-auto">
        <div class="max-w-7xl mx-auto px-4 space-y-1">
            <div class="font-bold text-zinc-700">Prototipe Sistem Informasi Pengadaan Barang dan Jasa (PPBJ) Kampus</div>
            <div>Berdasarkan Dokumen PBL Kelompok 1 & Functional Requirements (FR/Non-FR)</div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
