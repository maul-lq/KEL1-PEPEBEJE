@extends('layouts.app')

@section('title', 'Dashboard Utama - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<div class="space-y-8">

    <!-- 1. HERO BANNER WELCOME (Teks Tetap Utuh, Tanpa Tombol Kanan) -->
    <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-white via-slate-50 to-blue-50/50 p-6 md:p-8 shadow-sm">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#0969da]/10 px-3 py-1 text-xs font-semibold text-[#0969da]">
                        <span class="size-2 rounded-full bg-[#0969da] animate-pulse"></span>
                        Dashboard {{ strtoupper(str_replace('_', ' ', Auth::user()->role)) }}
                    </span>
                    <span class="text-xs text-slate-400 font-medium">• {{ date('d F Y') }}</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Selamat Datang, {{ Auth::user()->nama }}!
                </h1>
                <p class="mt-1 text-sm text-slate-500 max-w-2xl">
                    Kelola dan pantau seluruh alur pengadaan barang dan jasa Politeknik Negeri Jakarta secara real-time dan terintegrasi.
                </p>
            </div>
        </div>
    </div>

    <!-- 2. APPLICATION STATUS SUMMARY (4 Kartu Ringkasan Statistik) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Kartu 1: Total Permohonan / Usulan Pengadaan -->
        <div class="relative flex items-center justify-between p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
            <div class="space-y-1">
                <p class="text-xs font-medium text-slate-500">Usulan Pengadaan</p>
                <h3 class="text-2xl font-bold text-[#213153]">{{ $stats['total_permohonan'] ?? 0 }}</h3>
                <p class="text-[11px] text-slate-400">Total usulan tercatat di sistem</p>
            </div>
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0969da]">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>

        <!-- Kartu 2: Paket Pengadaan -->
        <div class="relative flex items-center justify-between p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
            <div class="space-y-1">
                <p class="text-xs font-medium text-slate-500">Paket Pengadaan</p>
                <h3 class="text-2xl font-bold text-[#213153]">{{ $stats['total_paket'] ?? 0 }}</h3>
                <p class="text-[11px] text-slate-400">Paket tender & penunjukan</p>
            </div>
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>

        <!-- Kartu 3: Rekanan Vendor -->
        <div class="relative flex items-center justify-between p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
            <div class="space-y-1">
                <p class="text-xs font-medium text-slate-500">Rekanan Vendor</p>
                <h3 class="text-2xl font-bold text-[#213153]">{{ $stats['total_vendor'] ?? 0 }}</h3>
                <p class="text-[11px] text-slate-400">Penyedia terverifikasi</p>
            </div>
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0a2 2 0 012-2h2a2 2 0 012 2m-6 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
            </div>
        </div>

        <!-- Kartu 4: Notifikasi Belum Dibaca -->
        <div class="relative flex items-center justify-between p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
            <div class="space-y-1">
                <p class="text-xs font-medium text-slate-500">Notifikasi Belum Dibaca</p>
                <h3 class="text-2xl font-bold text-[#213153]">{{ $notifications->count() }}</h3>
                <p class="text-[11px] text-slate-400">Perluan tindakan & alert</p>
            </div>
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
        </div>
    </div>

    <!-- 3. MAIN SECTION GRID: Perlu Tindakan Anda & Aktivitas Terbaru -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- SIDE KIRI (5 Cols): Perlu Tindakan Anda -->
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="size-2 rounded-full bg-amber-500"></div>
                    <h2 class="text-lg font-bold text-[#213153]">Perlu Tindakan Anda</h2>
                </div>
                <a href="{{ route('notifikasi.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Lihat Semua
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($notifications as $notif)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors flex items-start gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da] mt-0.5">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs font-bold text-[#213153] leading-snug truncate">
                                {{ $notif->reminder->judul_alert ?? 'Pemberitahuan Sistem' }}
                            </h4>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">
                                {{ $notif->reminder->pesan_alert ?? 'Ada berkas pengadaan yang memerlukan tindak lanjut Anda.' }}
                            </p>
                            <span class="text-[10px] text-slate-400 mt-1 block">
                                {{ $notif->created_at ? $notif->created_at->diffForHumans() : 'Baru saja' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center rounded-xl bg-white border border-slate-200/80">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Tidak ada tugas peninjauan pending saat ini.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- SIDE KANAN (7 Cols): Aktivitas Terbaru -->
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="size-2 rounded-full bg-blue-600"></div>
                    <h2 class="text-lg font-bold text-[#213153]">Aktivitas Pengadaan Terkini</h2>
                </div>
                <a href="{{ route('permohonan.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Lihat Semua Usulan
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <!-- TABEL RECENT ACTIVITY -->
            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">No. Surat / Pengusul</th>
                                <th class="px-4 py-3">Nama Pengadaan</th>
                                <th class="px-4 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($recentPermohonan as $p)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ \Carbon\Carbon::parse($p->tanggal_pengajuan)->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-semibold text-[#213153]">{{ $p->nomor_surat }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $p->unit->nama_unit ?? 'Unit' }}</div>
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $p->judul_pengadaan }}">
                                        {{ $p->judul_pengadaan }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if($p->status_permohonan === 'disetujui')
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                                Disetujui
                                            </span>
                                        @elseif($p->status_permohonan === 'ditolak')
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                                                Ditolak
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                                {{ ucfirst(str_replace('_', ' ', $p->status_permohonan)) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                                        Belum ada riwayat permohonan pengadaan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection