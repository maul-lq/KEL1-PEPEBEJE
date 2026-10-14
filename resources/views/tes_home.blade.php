<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Dashboard - SIM-PBJ Politeknik Negeri Jakarta</title>
    
    <!-- Google Fonts: Inder & Instrument Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inder&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .font-inder {
            font-family: 'Inder', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen w-full bg-slate-900 font-sans text-slate-100 selection:bg-indigo-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 w-full bg-slate-900/95 backdrop-blur border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            
            <!-- Brand / Logo -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-sky-400 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20">
                    PBJ
                </div>
                <div>
                    <span class="text-base font-bold text-white tracking-tight flex items-center gap-2">
                        SIM-PBJ
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            v2.0 Backend Ready
                        </span>
                    </span>
                    <p class="text-xs text-slate-400">Politeknik Negeri Jakarta</p>
                </div>
            </div>

            <!-- User Info & Logout -->
            <div class="flex items-center gap-4">
                @if ($user)
                    <div class="hidden sm:flex flex-col text-right">
                        <span class="text-sm font-semibold text-slate-200">{{ $user->nama }}</span>
                        <div class="flex items-center justify-end gap-1.5 text-xs text-slate-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>{{ strtoupper(str_replace('_', ' ', $user->role)) }}</span>
                            <span>&bull;</span>
                            <span>{{ $user->unit ? $user->unit->nama_unit : $user->kode_unit }}</span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button 
                        type="submit" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 hover:text-rose-200 border border-rose-500/30 transition-all flex items-center gap-1.5 cursor-pointer"
                        title="Keluar dari sesi saat ini"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>

        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Welcome Banner -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-800 via-indigo-950 to-slate-800 border border-slate-700/60 p-6 sm:p-8 shadow-xl">
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            Sistem Terhubung ke Database
                        </span>
                        <span class="text-xs text-slate-400 font-mono">18 Tabel Relasional ERD v2</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                        Selamat Datang, {{ $user ? $user->nama : 'Pengguna PBJ' }}!
                    </h1>
                    <p class="text-slate-300 text-sm mt-1 max-w-2xl">
                        Aplikasi Manajemen Pengadaan Barang & Jasa terintegrasi: Validasi Unary Split/Revisi, Auto-Routing PP/PPK, Verifikasi Paralel, Alokasi MAK Terkunci, dan BAST Fisik.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <a 
                        href="{{ route('test.api') }}" 
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                        <span>Halaman Tes API (18 Model)</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- KPI / Metrics Grid -->
        <section class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            
            <div class="p-5 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur">
                <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block mb-1">Total Unit Kerja</span>
                <span class="text-2xl font-bold text-sky-400">{{ $stats['total_units'] ?? 0 }}</span>
                <p class="text-[11px] text-slate-500 mt-1">Jurusan & Unit Kampus</p>
            </div>

            <div class="p-5 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur">
                <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block mb-1">Permohonan</span>
                <span class="text-2xl font-bold text-indigo-400">{{ $stats['total_permohonan'] ?? 0 }}</span>
                <p class="text-[11px] text-slate-500 mt-1">Usulan Masuk</p>
            </div>

            <div class="p-5 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur col-span-2 sm:col-span-1">
                <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block mb-1">Pagu Anggaran MAK</span>
                <span class="text-xl font-bold text-emerald-400">
                    Rp {{ number_format($stats['total_anggaran'] ?? 0, 0, ',', '.') }}
                </span>
                <p class="text-[11px] text-slate-500 mt-1">Total Alokasi MAK PNJ</p>
            </div>

            <div class="p-5 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur">
                <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block mb-1">Paket Pengadaan</span>
                <span class="text-2xl font-bold text-amber-400">{{ $stats['total_paket'] ?? 0 }}</span>
                <p class="text-[11px] text-slate-500 mt-1">Ter-registrasi PP/PPK</p>
            </div>

            <div class="p-5 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur">
                <span class="text-xs font-medium text-slate-400 uppercase tracking-wider block mb-1">Rekanan Vendor</span>
                <span class="text-2xl font-bold text-purple-400">{{ $stats['total_vendor'] ?? 0 }}</span>
                <p class="text-[11px] text-slate-500 mt-1">Terverifikasi Legalitas</p>
            </div>

        </section>

        <!-- User Profile Card Details -->
        @if ($user)
            <section class="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-700/60">
                    <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span>Informasi Profil Pengguna Terotentikasi</span>
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-medium {{ $user->status_aktif ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300' }}">
                        Status: {{ $user->status_aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Username</span>
                        <span class="font-mono font-semibold text-slate-200 text-sm">{{ $user->username }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">NIP Pegawai</span>
                        <span class="font-mono font-semibold text-slate-200 text-sm">{{ $user->nip }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Alamat Email</span>
                        <span class="font-semibold text-slate-200 text-sm">{{ $user->email }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Peran / Role</span>
                        <span class="font-bold text-indigo-300 text-sm uppercase">{{ $user->role }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Unit Kerja</span>
                        <span class="font-semibold text-slate-200 text-sm">
                            {{ $user->unit ? $user->unit->nama_unit . ' (' . $user->kode_unit . ')' : $user->kode_unit }}
                        </span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Jabatan Kedinasan</span>
                        <span class="font-semibold text-slate-200 text-sm">{{ $user->jabatan }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Nomor Handphone</span>
                        <span class="font-semibold text-slate-200 text-sm">{{ $user->no_hp }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400 block mb-0.5">Telepon Kantor</span>
                        <span class="font-semibold text-slate-200 text-sm">{{ $user->no_telp_kantor ?? '-' }}</span>
                    </div>
                </div>
            </section>
        @endif

        <!-- Role-Specific Action Center (FR/NFR Priority 1 & 2 Workspace) -->
        @if ($user)
            <section class="bg-gradient-to-br from-slate-800/80 via-slate-850 to-indigo-950/40 border border-indigo-500/30 rounded-2xl p-6 shadow-lg shadow-indigo-950/20">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5 pb-3 border-b border-slate-700/60">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 animate-pulse"></span>
                            <h2 class="text-base font-bold text-white tracking-wide">
                                Ruang Kerja & Aksi Operasional: <span class="text-indigo-300 uppercase">{{ str_replace('_', ' ', $user->role) }}</span>
                            </h2>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Menu dan otorisasi backend aktif sesuai hak akses peran (RBAC FR 1, FR 2, dan NFR Prioritas 1 & 2).
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 text-slate-300 border border-slate-700 font-mono">
                            ID Sesi: {{ substr(session()->getId(), 0, 8) }}... (Timeout 15m)
                        </span>
                    </div>
                </div>

                {{-- Action Cards Grid based on Role --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                    @if ($user->role === 'user')
                        {{-- Unit Pemohon --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Ajukan Permohonan Baru</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 font-mono">POST</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Buat usulan belanja modal/operasional unit dengan pagu, KAK, dan berkas pendukung (&le;10MB).</p>
                            <a href="{{ url('/api/permohonan-pengadaan') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Kelola Permohonan &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Revisi & Unary Split</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono">FR 3.4</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Pecah dan revisi item usulan yang ditolak parsial oleh Wadir 2/Staff B2 menjadi draft baru.</p>
                            <span class="text-xs text-slate-500">Otomatisasi Unary Split Relational ID</span>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Status Usulan Saya</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono">ISOLASI</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Hanya menampilkan permohonan yang diajukan oleh akun unit Anda sendiri.</p>
                            <span class="text-xs text-emerald-400 font-medium">Terisolasi ({{ $recentPermohonan->count() }} Usulan Aktif)</span>
                        </div>

                    @elseif ($user->role === 'staff_bidang_2')
                        {{-- Staff Bidang 2 --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Verifikasi Kelengkapan Berkas</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono">PARALEL</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Cek format KAK, spesifikasi teknis, dan dokumen pendukung usulan pengadaan unit kerja.</p>
                            <a href="{{ url('/api/verifikasi-paralel') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Buka Verifikasi &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Catatan Revisi & Rekomendasi</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono">NOTE</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Berikan catatan perbaikan sebelum berkas diajukan ke Wakil Direktur II.</p>
                            <span class="text-xs text-slate-400">Sinkronisasi status usulan</span>
                        </div>

                    @elseif ($user->role === 'wadir_2')
                        {{-- Wakil Direktur 2 --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Persetujuan Prinsip Pengadaan</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono">APPROVAL</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Tinjau dan putuskan persetujuan atau penolakan permohonan yang telah diverifikasi Staff B2.</p>
                            <a href="{{ url('/api/verifikasi-paralel') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Keputusan Usulan &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Disposisi ke Perencanaan</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono">FLOW</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Usulan yang disetujui otomatis masuk antrean alokasi MAK Bagian Perencanaan.</p>
                            <span class="text-xs text-slate-400">Integrasi Otomatis Antar-Tahap</span>
                        </div>

                    @elseif ($user->role === 'perencanaan')
                        {{-- Perencanaan --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Manajemen MAK & Pagu PNJ</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 font-mono">MASTER</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Kelola kode MAK, uraian mata anggaran, dan pagu dana RM/BLU/BOPTN tahun berjalan.</p>
                            <a href="{{ url('/api/anggaran-mak') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Kelola MAK &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Proteksi Immutabilitas MAK</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 font-mono">LOCKED</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Pagu MAK yang telah diterbitkan Kontrak/SPK otomatis dikunci permanen oleh sistem (NFR Prio 1).</p>
                            <span class="text-xs text-emerald-400 font-medium">&check; Enforced by Eloquent Hook</span>
                        </div>

                    @elseif ($user->role === 'ppbj')
                        {{-- PPBJ --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Auto-Routing Paket Pengadaan</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono">ROUTING</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Registrasi paket dan penetapan otomatis: &le;50jt (Langsung), 50-200jt (PP), &gt;200jt (PPK).</p>
                            <a href="{{ url('/api/paket-pengadaan') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Registrasi Paket &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Disposisi & Reassign PPK</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-blue-500/20 text-blue-300 font-mono">DELEGATION</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Tugaskan paket &gt;200jt ke salah satu dari 4 akun individual PPK resmi PNJ.</p>
                            <span class="text-xs text-slate-400">Endpoint: /reassign-ppk</span>
                        </div>

                    @elseif ($user->role === 'pp')
                        {{-- Pejabat Pengadaan (1 Akun) --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-blue-500/30 hover:border-blue-400 transition bg-blue-950/20">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-blue-200">Paket Jalur PP (Rp 50jt - 200jt)</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-blue-500/20 text-blue-300 font-mono">1 AKUN PP</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Menangani paket pengadaan langsung bernilai Rp 50jt s/d Rp 200jt di lingkungan PNJ.</p>
                            <a href="{{ url('/api/paket-pengadaan') }}" target="_blank" class="text-xs font-semibold text-blue-300 hover:text-blue-200 inline-flex items-center gap-1">
                                Tinjau Paket PP &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Reviu Dokumen & HPS</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono">REVIU</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Periksa spesifikasi, harga perkiraan sendiri (HPS), dan rancangan SPK vendor.</p>
                            <a href="{{ url('/api/reviu-pengadaan') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Kelola Reviu &rarr;
                            </a>
                        </div>

                    @elseif ($user->role === 'ppk')
                        {{-- Pejabat Pembuat Komitmen (4 Akun Individual Terisolasi) --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-purple-500/40 hover:border-purple-400 transition bg-purple-950/20">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-purple-200">Paket Pengadaan Ditugaskan ke Saya</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono">ISOLASI PPK</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Data paket strictly terisolasi: Anda hanya dapat melihat dan memproses paket yang ditugaskan ke NIP/akun Anda.</p>
                            <a href="{{ url('/api/paket-pengadaan') }}" target="_blank" class="text-xs font-semibold text-purple-300 hover:text-purple-200 inline-flex items-center gap-1">
                                Buka Paket Saya ({{ $recentPaket->count() }} Paket) &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Terbitkan Kontrak / SPK</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono">KONTRAK</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Terbitkan SPK/Kontrak vendor rekanan. Penerbitan otomatis mengunci alokasi MAK (Immutabilitas).</p>
                            <a href="{{ url('/api/kontrak-spk') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Manajemen Kontrak &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Penerbitan Surat Perintah Bayar (SPP)</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 font-mono">SPP/SPM</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Terbitkan instruksi pembayaran termin/lunas setelah BAST selesai diverifikasi.</p>
                            <a href="{{ url('/api/pembayaran') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Data Pembayaran &rarr;
                            </a>
                        </div>

                    @elseif ($user->role === 'perlengkapan')
                        {{-- Bagian Perlengkapan --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Penerimaan & Pemeriksaan Fisik</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono">FISIK</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Verifikasi kesesuaian kuantitas, spesifikasi teknis, dan fungsi barang yang dikirimkan vendor.</p>
                            <a href="{{ url('/api/penerimaan-barang') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Data Penerimaan &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">BAST Digital Signature (HMAC)</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono">HMAC SHA-256</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Penerbitan Berita Acara Serah Terima dengan token digital terotentikasi & bukti foto barang (&le;10MB).</p>
                            <span class="text-xs text-purple-300 font-medium">&check; Enkripsi Token SHA-256 Aktif</span>
                        </div>

                    @elseif ($user->role === 'keuangan')
                        {{-- Bagian Keuangan --}}
                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Verifikasi Berkas SPP/SPM</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 font-mono">VERIFIKASI</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Validasi kesesuaian berkas penagihan, kwitansi, faktur pajak, dan BAST sebelum pencairan dana.</p>
                            <a href="{{ url('/api/pembayaran') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Berkas SPP &rarr;
                            </a>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-200">Penerbitan SP2D & Pencairan</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono">SP2D</span>
                            </div>
                            <p class="text-xs text-slate-400 mb-3">Catat nomor SP2D, tanggal transfer, dan unggah bukti transfer perbankan (&le;10MB PDF/JPG).</p>
                            <a href="{{ url('/api/transaksi-pencairan') }}" target="_blank" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                                Pencairan Dana &rarr;
                            </a>
                        </div>

                    @endif

                    {{-- Common Card: Testing Environment --}}
                    <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-slate-200">Tes Lengkap 18 Model & API</h3>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono">DEBUG</span>
                        </div>
                        <p class="text-xs text-slate-400 mb-3">Akses konsol uji mandiri untuk memastikan integritas data, validasi role, dan relasi tabel ERD.</p>
                        <a href="{{ route('test.api') }}" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 inline-flex items-center gap-1">
                            Buka Konsol Tes &rarr;
                        </a>
                    </div>

                </div>
            </section>
        @endif

        <!-- Recent Permohonan & Recent Paket Pengadaan Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Permohonan Pengadaan Terbaru -->
            <section class="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-700/60">
                        <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                            <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span>Permohonan Pengadaan Terbaru</span>
                        </h2>
                        <a href="{{ url('/api/permohonan-pengadaan') }}" target="_blank" class="text-xs text-sky-400 hover:text-sky-300 font-medium">
                            JSON API &rarr;
                        </a>
                    </div>

                    @if ($recentPermohonan->isEmpty())
                        <div class="text-center py-8 text-slate-400 text-sm">
                            Belum ada data permohonan pengadaan di database.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($recentPermohonan as $p)
                                <div class="p-3.5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition">
                                    <div class="flex items-start justify-between gap-2 mb-1.5">
                                        <span class="font-mono text-xs font-semibold text-indigo-300">
                                            {{ $p->nomor_surat }}
                                        </span>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider 
                                            {{ $p->status_permohonan === 'disetujui' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : ($p->status_permohonan === 'ditolak' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30') }}">
                                            Status: {{ str_replace('_', ' ', $p->status_permohonan) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-200 font-medium line-clamp-1 mb-2">
                                        {{ $p->judul_pengadaan }}
                                    </p>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                                        <span>Unit: <strong class="text-slate-300">{{ $p->unit ? $p->unit->nama_unit : $p->kode_unit }}</strong></span>
                                        <span class="text-emerald-400 font-semibold font-mono">
                                            Rp {{ number_format($p->estimasi_total_pagu ?? 0, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-4 pt-3 border-t border-slate-700/60 text-right">
                    <span class="text-[11px] text-slate-400">Mendukung relasi unary split & revisi penolakan (FR 3.4)</span>
                </div>
            </section>

            <!-- Paket Pengadaan Terbaru -->
            <section class="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-700/60">
                        <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <span>Paket Pengadaan & Auto-Routing</span>
                        </h2>
                        <a href="{{ url('/api/paket-pengadaan') }}" target="_blank" class="text-xs text-amber-400 hover:text-amber-300 font-medium">
                            JSON API &rarr;
                        </a>
                    </div>

                    @if ($recentPaket->isEmpty())
                        <div class="text-center py-8 text-slate-400 text-sm">
                            Belum ada paket pengadaan terdaftar di database.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($recentPaket as $paket)
                                <div class="p-3.5 rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 transition">
                                    <div class="flex items-start justify-between gap-2 mb-1.5">
                                        <span class="font-mono text-xs font-semibold text-amber-300">
                                            {{ $paket->nomor_paket }}
                                        </span>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider 
                                            {{ $paket->jalur_routing === 'ppk_diatas_200jt' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : ($paket->jalur_routing === 'pp_50jt_sd_200jt' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30') }}">
                                            {{ str_replace('_', ' ', $paket->jalur_routing) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-200 font-medium line-clamp-1 mb-2">
                                        {{ $paket->nama_paket }}
                                    </p>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                                        <span>
                                            Petugas: 
                                            <strong class="text-slate-300">
                                                {{ $paket->ppkUser ? $paket->ppkUser->nama : ($paket->ppUser ? $paket->ppUser->nama : 'Belum Ditugaskan') }}
                                            </strong>
                                        </span>
                                        <span class="text-amber-400 font-semibold font-mono">
                                            Rp {{ number_format($paket->nilai_hps, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-4 pt-3 border-t border-slate-700/60 text-right">
                    <span class="text-[11px] text-slate-400">Threshold: &le;50jt Langsung | 50-200jt PP | &gt;200jt PPK</span>
                </div>
            </section>

        </div>

        <!-- Notifications Drawer / Section -->
        <section class="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-700/60">
                <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span>Notifikasi & Reminder User (Real-time DB)</span>
                </h2>
                <span class="text-xs text-slate-400">{{ $notifications->count() }} notifikasi aktif</span>
            </div>

            @if ($notifications->isEmpty())
                <div class="text-center py-6 text-slate-400 text-xs">
                    Tidak ada notifikasi atau pengingat jatuh tempo saat ini.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($notifications as $notif)
                        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-xs text-slate-200">{{ $notif->reminder ? $notif->reminder->judul_alert : 'Pemberitahuan Sistem' }}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono">
                                    {{ $notif->reminder ? strtoupper($notif->reminder->tipe_reminder) : 'INFO' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mb-2">{{ $notif->reminder ? $notif->reminder->pesan_alert : 'Detail notifikasi dapat dilihat pada menu terkait.' }}</p>
                            @if ($notif->reminder)
                                <div class="text-[10px] text-slate-500 font-mono">
                                    Paket: {{ $notif->reminder->nomor_paket }} | Due: {{ $notif->reminder->waktu_trigger ? $notif->reminder->waktu_trigger->format('d M Y') : '-' }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <!-- Backend REST API Explorer Section -->
        <section class="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-700/60">
                <div>
                    <h2 class="text-base font-bold text-slate-200">
                        Eksplorasi CRUD & Endpoint API Backend (18 Tabel ERD v2)
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Klik tautan di bawah ini untuk melihat data JSON langsung dari masing-masing resource controller
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5 text-xs">
                @php
                    $endpoints = [
                        ['label' => 'Units (Master)', 'url' => '/api/units'],
                        ['label' => 'Users (9 Roles)', 'url' => '/api/users'],
                        ['label' => 'Vendors & Legalitas', 'url' => '/api/vendors'],
                        ['label' => 'Anggaran MAK', 'url' => '/api/anggaran-mak'],
                        ['label' => 'Permohonan Pengadaan', 'url' => '/api/permohonan-pengadaan'],
                        ['label' => 'Verifikasi Paralel', 'url' => '/api/verifikasi-paralel'],
                        ['label' => 'Paket Pengadaan', 'url' => '/api/paket-pengadaan'],
                        ['label' => 'Reviu Dokumen', 'url' => '/api/reviu-pengadaan'],
                        ['label' => 'Kontrak & SPK', 'url' => '/api/kontrak-spk'],
                        ['label' => 'Penerimaan / BAST', 'url' => '/api/penerimaan-barang'],
                        ['label' => 'Pembayaran SPP/SPM', 'url' => '/api/pembayaran'],
                        ['label' => 'Pencairan Dana (SP2D)', 'url' => '/api/transaksi-pencairan'],
                        ['label' => 'Reminder & Deadline', 'url' => '/api/reminder'],
                        ['label' => 'Audit Log & History', 'url' => '/api/audit-log'],
                    ];
                @endphp

                @foreach ($endpoints as $ep)
                    <a 
                        href="{{ url($ep['url']) }}" 
                        target="_blank"
                        class="p-2.5 rounded-xl bg-slate-900/60 hover:bg-indigo-900/40 border border-slate-800 hover:border-indigo-500/50 transition-all flex flex-col justify-between group"
                    >
                        <span class="font-medium text-slate-300 group-hover:text-indigo-200 truncate">
                            {{ $ep['label'] }}
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono mt-1 group-hover:text-indigo-400">
                            GET {{ $ep['url'] }} &nearr;
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

    </main>

    <!-- Footer Copyright -->
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-slate-500 border-t border-slate-800/80">
        &copy; {{ date('Y') }} Sistem Informasi Manajemen Pengadaan Barang dan Jasa (SIM-PBJ) Politeknik Negeri Jakarta.
    </footer>

</body>
</html>
