@extends('layouts.app')

@section('title', 'Notifikasi dan Reminder - SIM-PBJ')

@section('content')
<div class="space-y-6">

    {{-- 1. JUDUL HALAMAN (Sesuai Figma Node 121:54) --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl md:text-4xl font-normal text-[#203638] tracking-tight">
                Notifikasi dan Reminder
            </h1>
            <p class="text-sm text-[#657a7c] mt-1 font-normal">
                Pusat pengingat jadwal, alarm batas waktu SPK & termin, serta tugas verifikasi seluruh alur pengadaan barang dan jasa.
            </p>
        </div>

        {{-- Tombol Tindakan Cepat Header --}}
        <div class="flex flex-wrap items-center gap-2.5" x-data="desktopNotificationManager()" x-init="init()">
            {{-- Tombol Izin / Uji Coba Notifikasi OS --}}
            <template x-if="permission !== 'granted'">
                <button
                    type="button"
                    @click="requestPermission()"
                    class="inline-flex h-10 items-center gap-2 rounded-[10px] bg-white px-3.5 text-xs md:text-sm font-medium text-[#1B5B7E] border border-[#1B5B7E] shadow-sm hover:bg-[#CCE1EF] transition-all"
                    title="Aktifkan notifikasi pop-up native di OS Windows / Mac"
                >
                    <svg class="size-4 text-[#0088E8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    Aktifkan Notifikasi OS
                </button>
            </template>
            <template x-if="permission === 'granted'">
                <button
                    type="button"
                    @click="showNativeAlert('SIM-PBJ Politeknik Negeri Jakarta', 'Uji Coba: Notifikasi OS berhasil aktif dan siap menerima peringatan.', '/notifikasi')"
                    class="inline-flex h-10 items-center gap-2 rounded-[10px] bg-white px-3.5 text-xs md:text-sm font-medium text-[#087F79] border border-[#DCE5E3] shadow-sm hover:bg-[#EAF4FA] transition-all"
                    title="Uji coba kemunculan notifikasi di sistem operasi Windows / Mac"
                >
                    <svg class="size-4 text-[#087F79]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Tes Notifikasi OS
                </button>
            </template>

            {{-- Tombol Tindakan Cepat Tandai Semua Dibaca --}}
            @if($tabCounts['belum_dibaca'] > 0)
            <form action="{{ route('notifikasi.mark-all-read') }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="inline-flex h-10 items-center gap-2 rounded-[10px] bg-white px-4 text-xs md:text-sm font-medium text-[#087F79] border border-[#1B5B7E] shadow-sm hover:bg-[#CCE1EF] hover:text-[#1B5B7E] transition-all"
                    title="Tandai seluruh notifikasi yang belum dibaca sebagai telah dibaca"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Tandai Semua Dibaca ({{ $tabCounts['belum_dibaca'] }})
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- 2. BARIS FILTER & PENCARIAN (Sesuai Figma Node 175:507, 175:506, 127:2938) --}}
    <form action="{{ route('notifikasi.index') }}" method="GET" class="grid grid-cols-1 gap-4 lg:grid-cols-12 items-end">
        {{-- Simpan Tab Aktif saat melakukan Filter --}}
        <input type="hidden" name="tab" value="{{ $currentTab }}">

        {{-- Input Pencarian No. Surat / Judul (Figma Node 175:507) --}}
        <div class="lg:col-span-6 space-y-1.5">
            <label for="search-input" class="block text-sm font-normal text-[#203638]">
                Cari no. surat / judul
            </label>
            <div class="relative">
                <input
                    type="text"
                    id="search-input"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Ketik no. surat, paket, atau kata kunci judul..."
                    class="h-[48px] w-full rounded-[10px] border border-[#1B5B7E] bg-white pl-11 pr-4 text-sm text-[#203638] placeholder-slate-400 outline-none focus:ring-2 focus:ring-[#689EC2] transition-all"
                />
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                @if(request('q'))
                    <a href="{{ route('notifikasi.index', ['tab' => $currentTab, 'jenis' => request('jenis'), 'periode' => request('periode')]) }}" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600" title="Hapus pencarian">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- Dropdown Jenis Notifikasi (Figma Node 175:506) --}}
        <div class="lg:col-span-3 space-y-1.5">
            <label for="jenis-select" class="block text-sm font-normal text-[#203638]">
                Jenis Notifikasi
            </label>
            <div class="relative">
                <select
                    id="jenis-select"
                    name="jenis"
                    onchange="this.form.submit()"
                    class="h-[48px] w-full appearance-none rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-sm text-[#203638] outline-none focus:ring-2 focus:ring-[#689EC2] cursor-pointer transition-all pr-10"
                >
                    <option value="semua" {{ request('jenis', 'semua') === 'semua' ? 'selected' : '' }}>Semua Jenis</option>
                    <option value="pengingat" {{ request('jenis') === 'pengingat' ? 'selected' : '' }}>Pengingat Jadwal</option>
                    <option value="perlu_tindakan" {{ request('jenis') === 'perlu_tindakan' ? 'selected' : '' }}>Perlu Tindakan</option>
                    <option value="verifikasi" {{ request('jenis') === 'verifikasi' ? 'selected' : '' }}>Verifikasi Usulan</option>
                    <option value="pembayaran" {{ request('jenis') === 'pembayaran' ? 'selected' : '' }}>Pembayaran & SP2D</option>
                    <option value="pengiriman" {{ request('jenis') === 'pengiriman' ? 'selected' : '' }}>Pengiriman Barang</option>
                    <option value="spk" {{ request('jenis') === 'spk' ? 'selected' : '' }}>Tembusan SPK</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-[#1B5B7E]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Dropdown Periode Waktu (Figma Node 127:2938) --}}
        <div class="lg:col-span-3 space-y-1.5">
            <label for="periode-select" class="block text-sm font-normal text-[#203638]">
                Periode
            </label>
            <div class="relative">
                <select
                    id="periode-select"
                    name="periode"
                    onchange="this.form.submit()"
                    class="h-[48px] w-full appearance-none rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-sm text-[#203638] outline-none focus:ring-2 focus:ring-[#689EC2] cursor-pointer transition-all pr-10"
                >
                    <option value="semua" {{ request('periode', 'semua') === 'semua' ? 'selected' : '' }}>Semua Periode</option>
                    <option value="hari_ini" {{ request('periode') === 'hari_ini' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="7_hari" {{ request('periode') === '7_hari' ? 'selected' : '' }}>7 Hari Terakhir</option>
                    <option value="30_hari" {{ request('periode') === '30_hari' ? 'selected' : '' }}>30 Hari Terakhir</option>
                    <option value="bulan_ini" {{ request('periode') === 'bulan_ini' ? 'selected' : '' }}>Bulan Ini</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-[#1B5B7E]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>
    </form>

    {{-- 3. CARD KONTEN UTAMA NOTIFIKASI (Sesuai Figma Node 121:74) --}}
    <div class="rounded-t-[20px] bg-white border border-[#DCE5E3] shadow-sm overflow-hidden min-h-[500px] flex flex-col justify-between">

        <div>
            {{-- TAB BAR NAVIGASI (Sesuai Figma Node 175:612) --}}
            <div class="flex items-center gap-6 border-b border-[#DCE5E3] px-6 pt-4 overflow-x-auto">
                
                {{-- Tab 1: Semua --}}
                <a
                    href="{{ route('notifikasi.index', ['tab' => 'semua', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}"
                    class="relative pb-3 flex items-center gap-2 text-[13px] font-normal transition-all whitespace-nowrap {{ $currentTab === 'semua' ? 'text-[#1B5B7E] font-medium' : 'text-[#203638] hover:text-[#1B5B7E]' }}"
                >
                    <span>Semua</span>
                    <span class="rounded bg-[#BAE5FF] px-2 py-0.5 text-[10px] text-black font-normal inline-flex items-center justify-center">
                        {{ $tabCounts['semua'] }}
                    </span>
                    @if($currentTab === 'semua')
                        {{-- Garis Indikator Tab Aktif (Figma Line 4: stroke #1B5B7E) --}}
                        <span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#1B5B7E]"></span>
                    @endif
                </a>

                {{-- Tab 2: Belum dibaca --}}
                <a
                    href="{{ route('notifikasi.index', ['tab' => 'belum_dibaca', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}"
                    class="relative pb-3 flex items-center gap-2 text-[13px] font-normal transition-all whitespace-nowrap {{ $currentTab === 'belum_dibaca' ? 'text-[#1B5B7E] font-medium' : 'text-[#203638] hover:text-[#1B5B7E]' }}"
                >
                    <span>Belum dibaca</span>
                    <span class="rounded bg-[#BAE5FF] px-2 py-0.5 text-[10px] text-black font-normal inline-flex items-center justify-center">
                        {{ $tabCounts['belum_dibaca'] }}
                    </span>
                    @if($currentTab === 'belum_dibaca')
                        <span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#1B5B7E]"></span>
                    @endif
                </a>

                {{-- Tab 3: Perlu tindakan --}}
                <a
                    href="{{ route('notifikasi.index', ['tab' => 'perlu_tindakan', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}"
                    class="relative pb-3 flex items-center gap-2 text-[13px] font-normal transition-all whitespace-nowrap {{ $currentTab === 'perlu_tindakan' ? 'text-[#1B5B7E] font-medium' : 'text-[#203638] hover:text-[#1B5B7E]' }}"
                >
                    <span>Perlu tindakan</span>
                    <span class="rounded bg-[#BAE5FF] px-2 py-0.5 text-[10px] text-black font-normal inline-flex items-center justify-center">
                        {{ $tabCounts['perlu_tindakan'] }}
                    </span>
                    @if($currentTab === 'perlu_tindakan')
                        <span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#1B5B7E]"></span>
                    @endif
                </a>

                {{-- Tab 4: Pengingat --}}
                <a
                    href="{{ route('notifikasi.index', ['tab' => 'pengingat', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}"
                    class="relative pb-3 flex items-center gap-2 text-[13px] font-normal transition-all whitespace-nowrap {{ $currentTab === 'pengingat' ? 'text-[#1B5B7E] font-medium' : 'text-[#203638] hover:text-[#1B5B7E]' }}"
                >
                    <span>Pengingat</span>
                    <span class="rounded bg-[#BAE5FF] px-2 py-0.5 text-[10px] text-black font-normal inline-flex items-center justify-center">
                        {{ $tabCounts['pengingat'] }}
                    </span>
                    @if($currentTab === 'pengingat')
                        <span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#1B5B7E]"></span>
                    @endif
                </a>

            </div>

            {{-- DAFTAR ITEM NOTIFIKASI (Sesuai Figma Node 175:751, 175:735, 175:768) --}}
            <div class="divide-y divide-[#DCE5E3]">
                @forelse($notifikasiList as $notif)
                    @php
                        $isUnread = !$notif->is_read;
                        $badgeText = $notif->jenis_badge;
                        $refText = $notif->referensi_teks;
                        $actionLabel = $notif->action_label;
                        $actionUrl = $notif->action_url;
                    @endphp

                    <div class="flex flex-col md:flex-row items-start gap-4 p-5 md:p-[22px] transition-colors {{ $isUnread ? 'bg-[#CCE1EF]' : 'bg-white hover:bg-slate-50/60' }}">
                        
                        {{-- Kolom 1: Penanda Baca (Dot Bulat Hijau Teal Sesuai Figma #087F79) --}}
                        <div class="pt-1.5 shrink-0" title="{{ $isUnread ? 'Belum dibaca' : 'Sudah dibaca' }}">
                            @if($isUnread)
                                <svg width="10" height="15" viewBox="0 0 10 15" fill="none" class="block">
                                    <circle cx="4" cy="7" r="4" fill="#087F79" />
                                </svg>
                            @else
                                <svg width="10" height="15" viewBox="0 0 10 15" fill="none" class="block">
                                    <circle cx="4" cy="7" r="3.5" stroke="#94A3B8" stroke-width="1" fill="none" />
                                </svg>
                            @endif
                        </div>

                        {{-- Kolom 2: Isi Notifikasi (Peristiwa, Jenis Badge, Ref Surat, Uraian Pesan) --}}
                        <div class="min-w-0 flex-1 space-y-1.5">
                            
                            {{-- Baris Peristiwa & Badge --}}
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-[15px] font-normal leading-[1.4] text-[#203638] break-words">
                                    {{ $notif->judul }}
                                </h2>

                                {{-- Badge Jenis Notifikasi (Figma Node 175:641: bg-[#fff5df] text-[#99660d]) --}}
                                <span class="rounded-[4px] bg-[#FFF5DF] px-2 py-0.5 text-[11px] font-normal text-[#99660D] whitespace-nowrap">
                                    {{ $badgeText }}
                                </span>
                            </div>

                            {{-- No. Surat & Judul Paket / Usulan Terkait --}}
                            <p class="text-[13px] font-normal leading-[1.45] text-[#203638]">
                                {{ $refText }}
                            </p>

                            {{-- Detail Uraian Pesan Alarm --}}
                            <p class="text-[13px] font-normal leading-[1.5] text-[#657A7C] break-words">
                                {{ $notif->pesan }}
                            </p>
                        </div>

                        {{-- Kolom 3: Waktu dan Tindak Lanjut (Status Baca, Waktu WIB, Tombol Aksi) --}}
                        <div class="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-start gap-2.5 w-full md:w-[220px] shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-200/50">
                            
                            {{-- Status Baca --}}
                            @if($isUnread)
                                <span class="text-[12px] font-normal text-[#087F79] whitespace-nowrap">
                                    Belum dibaca
                                </span>
                            @else
                                <span class="text-[12px] font-normal text-[#657A7C] whitespace-nowrap">
                                    Sudah dibaca
                                </span>
                            @endif

                            {{-- Timestamp Waktu --}}
                            <span class="text-[12px] font-normal text-[#657A7C] whitespace-nowrap">
                                {{ $notif->created_at ? $notif->created_at->translatedFormat('d M Y · H.i') . ' WIB' : now()->translatedFormat('d M Y · H.i') . ' WIB' }}
                            </span>

                            {{-- Tombol Aksi (Figma Node 175:648: border border-[#dce5e3] text-[#087f79] h-[40px]) --}}
                            <div class="flex items-center gap-2 mt-1">
                                @if($isUnread)
                                    {{-- Quick Mark Read --}}
                                    <form action="{{ route('notifikasi.read', $notif->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="inline-flex h-[40px] items-center justify-center px-3 rounded-[6px] border border-[#DCE5E3] bg-white text-slate-500 hover:text-[#087F79] hover:bg-[#EAF4FA] transition-all text-xs"
                                            title="Tandai telah dibaca"
                                        >
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif

                                <a
                                    href="{{ $actionUrl }}"
                                    class="inline-flex h-[40px] items-center justify-center rounded-[6px] border border-[#DCE5E3] bg-white px-[14px] text-[13px] font-normal text-[#087F79] shadow-sm hover:bg-[#EAF4FA] hover:border-[#087F79] hover:text-[#065A56] transition-all whitespace-nowrap"
                                >
                                    {{ $actionLabel }}
                                </a>
                            </div>

                        </div>

                    </div>
                @empty
                    {{-- TAMPILAN KOSONG (EMPTY STATE) --}}
                    <div class="flex flex-col items-center justify-center py-20 px-4 text-center">
                        <div class="size-16 rounded-full bg-[#BAE5FF]/40 flex items-center justify-center text-[#1B5B7E] mb-4">
                            <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h2 class="text-base font-medium text-[#203638]">
                            Tidak ada notifikasi atau pengingat
                        </h2>
                        <p class="text-sm text-[#657A7C] max-w-md mt-1 font-normal">
                            @if(request('q') || request('jenis') !== 'semua' || request('periode') !== 'semua' || $currentTab !== 'semua')
                                Tidak ditemukan notifikasi yang cocok dengan filter pencarian Anda. Silakan reset filter untuk melihat data lengkap.
                            @else
                                Seluruh tugas dan jadwal pengingat untuk akun Anda saat ini telah selesai dan up-to-date.
                            @endif
                        </p>
                        @if(request('q') || request('jenis') !== 'semua' || request('periode') !== 'semua' || $currentTab !== 'semua')
                            <a
                                href="{{ route('notifikasi.index') }}"
                                class="mt-4 inline-flex h-9 items-center rounded-[8px] bg-[#1B5B7E] px-4 text-xs font-medium text-white hover:bg-[#144763] transition-all"
                            >
                                Reset Filter Pencarian
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        {{-- 4. PAGINASI (PAGINATION FOOTER) --}}
        @if($notifikasiList->hasPages())
        <div class="border-t border-[#DCE5E3] bg-white px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-[#657A7C]">
                Menampilkan <span class="font-medium text-[#203638]">{{ $notifikasiList->firstItem() ?? 0 }}</span> sampai <span class="font-medium text-[#203638]">{{ $notifikasiList->lastItem() ?? 0 }}</span> dari <span class="font-medium text-[#203638]">{{ $notifikasiList->total() }}</span> notifikasi
            </div>
            <div>
                {{ $notifikasiList->links() }}
            </div>
        </div>
        @endif

    </div>

</div>
@endsection
