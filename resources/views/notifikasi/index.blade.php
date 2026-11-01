@extends('layouts.app')

@section('title', 'Notifikasi dan Reminder - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<div class="w-full space-y-5 font-sans" x-data @notification-updated.window="window.location.reload()">

    <!-- 1. HEADER HALAMAN & TOMBOL UJI COBA -->
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-0.5">
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-[#213153]">Notifikasi dan Reminder</h1>
            <p class="text-sm font-normal text-slate-500">Pantau perkembangan pengajuan, permintaan revisi, dan tenggat pengadaan Anda secara real-time.</p>
        </div>

        <div class="flex items-center gap-2">
            @if(($tabCounts['belum_dibaca'] ?? 0) > 0)
                <form action="{{ route('notifikasi.mark-all-read') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="h-9 px-3.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#0969da] transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
                        <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Tandai Semua Dibaca ({{ $tabCounts['belum_dibaca'] }})</span>
                    </button>
                </form>
            @endif

            <form action="{{ route('notifikasi.tes-kirim') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="jenis" value="persetujuan_wadir">
                <button type="submit"
                        class="h-9 px-4 rounded-xl bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700 transition-all inline-flex items-center gap-1.5 shadow-sm cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Uji Coba Notifikasi Masuk</span>
                </button>
            </form>
        </div>
    </div>

    <!-- 2. PENCARIAN & FILTER -->
    <form action="{{ route('notifikasi.index') }}" method="GET" class="w-full rounded-2xl bg-white p-3.5 shadow-sm border border-slate-100">
        <input type="hidden" name="tab" value="{{ $currentTab ?? 'semua' }}">

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

            <div class="md:col-span-5 space-y-1">
                <label for="search-input" class="text-[11px] font-medium text-slate-600">Cari no. surat / judul</label>
                <div class="relative w-full">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" id="search-input" name="q" value="{{ request('q') }}"
                           placeholder="Cari nomor surat atau kata kunci notifikasi..."
                           class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                </div>
            </div>

            <div class="md:col-span-3 space-y-1">
                <label for="type-filter" class="text-[11px] font-medium text-slate-600">Jenis Notifikasi</label>
                <select id="type-filter" name="jenis" onchange="this.form.submit()"
                        class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all cursor-pointer">
                    <option value="semua" {{ request('jenis', 'semua') === 'semua' ? 'selected' : '' }}>Semua Jenis</option>
                    <option value="verifikasi" {{ request('jenis') === 'verifikasi' ? 'selected' : '' }}>Verifikasi Usulan</option>
                    <option value="perlu_tindakan" {{ request('jenis') === 'perlu_tindakan' ? 'selected' : '' }}>Perlu Tindakan / Revisi</option>
                    <option value="pengingat" {{ request('jenis') === 'pengingat' ? 'selected' : '' }}>Pengingat Jadwal</option>
                    <option value="pembayaran" {{ request('jenis') === 'pembayaran' ? 'selected' : '' }}>Pembayaran &amp; SP2D</option>
                    <option value="pengiriman" {{ request('jenis') === 'pengiriman' ? 'selected' : '' }}>Pengiriman Barang</option>
                    <option value="spk" {{ request('jenis') === 'spk' ? 'selected' : '' }}>Tembusan SPK</option>
                </select>
            </div>

            <div class="md:col-span-3 space-y-1">
                <label for="period-filter" class="text-[11px] font-medium text-slate-600">Periode</label>
                <select id="period-filter" name="periode" onchange="this.form.submit()"
                        class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all cursor-pointer">
                    <option value="semua" {{ request('periode', 'semua') === 'semua' ? 'selected' : '' }}>Semua Periode</option>
                    <option value="hari_ini" {{ request('periode') === 'hari_ini' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="7_hari" {{ request('periode') === '7_hari' ? 'selected' : '' }}>7 Hari Terakhir</option>
                    <option value="30_hari" {{ request('periode') === '30_hari' ? 'selected' : '' }}>30 Hari Terakhir</option>
                    <option value="bulan_ini" {{ request('periode') === 'bulan_ini' ? 'selected' : '' }}>Bulan Ini</option>
                </select>
            </div>

            <div class="md:col-span-1 flex items-center justify-end gap-1.5">
                <button type="submit" title="Terapkan Filter"
                        class="h-9 w-9 rounded-xl bg-[#0969da] text-white flex items-center justify-center hover:bg-blue-700 transition-all shadow-xs cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                @if(request('q') || (request('jenis') && request('jenis') !== 'semua') || (request('periode') && request('periode') !== 'semua') || (($currentTab ?? 'semua') !== 'semua'))
                    <a href="{{ route('notifikasi.index') }}" title="Reset Filter"
                       class="h-9 w-9 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-all">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </a>
                @endif
            </div>
        </div>
    </form>

    <!-- 3. DAFTAR NOTIFIKASI -->
    <div class="w-full rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">

        <!-- TAB + TOOLBAR -->
        <div class="px-4 pt-3 pb-0 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/40 rounded-t-2xl">
            <div class="flex items-center gap-1 overflow-x-auto text-xs font-medium">
                @php
                    $activeTab = $currentTab ?? 'semua';
                    $tabs = [
                        ['key' => 'semua', 'label' => 'Semua', 'badge' => 'bg-blue-100 text-[#0969da]'],
                        ['key' => 'belum_dibaca', 'label' => 'Belum dibaca', 'badge' => 'bg-slate-200/70 text-slate-700'],
                        ['key' => 'perlu_tindakan', 'label' => 'Perlu tindakan', 'badge' => 'bg-rose-100 text-rose-600'],
                        ['key' => 'pengingat', 'label' => 'Pengingat', 'badge' => 'bg-amber-100 text-amber-700'],
                    ];
                @endphp

                @foreach($tabs as $t)
                    @php
                        $isActive = ($activeTab === $t['key']);
                        $tabUrl = route('notifikasi.index', array_merge(request()->query(), ['tab' => $t['key'], 'page' => 1]));
                    @endphp
                    <a href="{{ $tabUrl }}"
                       class="px-3.5 py-2.5 rounded-t-xl flex items-center gap-1.5 transition-all whitespace-nowrap {{ $isActive ? 'text-[#0969da] border-b-2 border-b-[#0969da] bg-white font-semibold' : 'text-slate-500 hover:text-slate-800' }}">
                        <span>{{ $t['label'] }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $t['badge'] }}">
                            {{ $tabCounts[$t['key']] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-4 py-2 text-xs">
                @if(($tabCounts['belum_dibaca'] ?? 0) > 0)
                    <form action="{{ route('notifikasi.mark-all-read') }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 font-medium text-[#0969da] hover:underline cursor-pointer">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>Tandai semua dibaca</span>
                        </button>
                    </form>
                @endif

                <button type="button" onclick="window.location.reload()" class="inline-flex items-center gap-1.5 font-medium text-[#0969da] hover:underline cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <!-- LIST DAFTAR NOTIFIKASI REAL DARI DATABASE -->
        <div class="divide-y divide-slate-100">
            @forelse($notifikasiList as $notif)
                @php
                    $isUnread = !$notif->is_read;
                    $badge = $notif->jenis_badge;
                    $judul = $notif->judul;
                    $pesan = $notif->pesan;
                    $referensi = $notif->referensi_teks;
                    $actionUrl = $notif->action_url;
                    $actionLabel = $notif->action_label;

                    $lowerJudul = strtolower($judul);
                    $lowerPesan = strtolower($pesan);

                    if (str_contains($lowerJudul, 'disetujui') || str_contains($lowerJudul, 'setuju') || str_contains($lowerPesan, 'disetujui')) {
                        $iconBg = 'bg-emerald-500 shadow-emerald-500/30';
                        $badgeClass = 'bg-emerald-100 text-emerald-700';
                        $iconSvg = 'M5 13l4 4L19 7';
                    } elseif (str_contains($lowerJudul, 'tolak') || str_contains($lowerJudul, 'revisi') || str_contains($lowerPesan, 'ditolak') || str_contains($lowerPesan, 'revisi') || $badge === 'Perlu Tindakan') {
                        $iconBg = 'bg-rose-600 shadow-rose-600/30';
                        $badgeClass = 'bg-rose-100 text-rose-700';
                        $iconSvg = 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
                    } elseif ($badge === 'Pengingat' || str_contains($lowerJudul, 'pengingat') || str_contains($lowerJudul, 'alarm') || str_contains($lowerJudul, 'batas')) {
                        $iconBg = 'bg-amber-400 shadow-amber-400/30';
                        $badgeClass = 'bg-amber-100 text-amber-700';
                        $iconSvg = 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9';
                    } else {
                        $iconBg = 'bg-[#0969da] shadow-blue-500/30';
                        $badgeClass = 'bg-blue-100 text-[#0969da]';
                        $iconSvg = 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
                    }
                @endphp

                <div class="p-3.5 sm:p-4 flex items-center justify-between gap-4 transition-all hover:bg-[#e6f0ff]/60 {{ $isUnread ? 'bg-[#f0f6ff]/80' : 'bg-white' }}">

                    <div class="flex items-center gap-3.5 min-w-0 flex-1">
                        <!-- Icon Kategori -->
                        <div class="{{ $iconBg }} size-9 rounded-full flex items-center justify-center text-white shrink-0 shadow-sm">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $iconSvg }}"/>
                            </svg>
                        </div>

                        <!-- Dot Penanda Belum Dibaca -->
                        @if($isUnread)
                            <span class="size-2 rounded-full shrink-0 bg-[#0969da]" title="Belum dibaca"></span>
                        @else
                            <span class="size-2 rounded-full shrink-0 bg-transparent"></span>
                        @endif

                        <!-- Konten Teks -->
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <h3 class="text-xs sm:text-sm text-[#213153] leading-snug {{ $isUnread ? 'font-semibold' : 'font-medium' }}">
                                {{ $judul }}
                            </h3>
                            <p class="text-[11px] text-slate-500 truncate font-mono">
                                {{ $referensi }}
                            </p>
                            <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                {{ $pesan }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="hidden sm:inline-flex rounded-md px-2.5 py-0.5 text-[10px] font-semibold {{ $badgeClass }}">
                            {{ $badge }}
                        </span>

                        <span class="text-[10px] text-slate-400 font-normal whitespace-nowrap" title="{{ $notif->created_at ? $notif->created_at->format('d/m/Y H:i:s') : '' }}">
                            {{ $notif->created_at ? $notif->created_at->diffForHumans() : 'Baru saja' }}
                        </span>

                        @if(!empty($actionUrl))
                            <a href="{{ $actionUrl }}"
                               class="inline-flex items-center gap-1 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-[#0969da] hover:bg-blue-50 transition-colors shadow-sm">
                                <span>{{ $actionLabel ?: 'Buka Rincian' }}</span>
                                <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        @endif

                        <!-- Menu Titik Tiga Dropdown -->
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition
                                 class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-slate-200 bg-white py-1 shadow-lg text-xs">
                                @if($isUnread)
                                    <form action="{{ route('notifikasi.read', $notif->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="w-full px-3 py-2 text-left text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">
                                            Tandai sudah dibaca
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('notifikasi.dismiss', $notif->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full px-3 py-2 text-left text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                        Hapus notifikasi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <svg class="size-10 text-slate-300 mx-auto mb-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <h3 class="text-sm font-bold text-[#213153]">Tidak Ada Notifikasi</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        Saat ini tidak ada notifikasi atau pengingat yang cocok dengan kriteria filter akun Anda.
                    </p>
                </div>
            @endforelse
        </div>

        <!-- PAGINASI SERVER LARAVEL -->
        @if($notifikasiList->hasPages())
            <div class="px-4 py-3 bg-slate-50/60 border-t border-slate-100 flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    Menampilkan {{ $notifikasiList->firstItem() }} - {{ $notifikasiList->lastItem() }} dari {{ $notifikasiList->total() }} notifikasi
                </div>
                <div>
                    {{ $notifikasiList->links() }}
                </div>
            </div>
        @endif

    </div>

</div>
@endsection
