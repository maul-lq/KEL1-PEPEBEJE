@extends('layouts.app')

@section('title', 'Tracking Pengajuan - SIM-PBJ')

@section('content')
<div x-data="trackingManager({{ isset($permohonanList) ? json_encode($permohonanList->items() ?? $permohonanList) : '[]' }})" class="space-y-6 mx-1 px-4 sm:px-6 lg:px-8 py-6">

    <!-- 1. HEADER SECTION (Figma 455:6666, 465:8991, 506:1335) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <!-- Tombol Kembali Mode Fullscreen (Figma 465:9002 / 506:1346) -->
            <div x-show="viewMode === 'fullscreen'" class="mb-3">
                <button @click="viewMode = 'split'" type="button" 
                        class="inline-flex items-center gap-2 text-sm font-bold text-[#4A4A4A] hover:text-gray-700 transition-colors cursor-pointer group">
                    <svg class="size-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Kembali</span>
                </button>
            </div>

            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]"
                x-text="viewMode === 'fullscreen' ? 'Detail & Tracking Pengajuan' : 'Tracking Pengajuan'">
                Tracking Pengajuan
            </h1>
            <p class="mt-1 text-sm text-slate-500"
               x-text="viewMode === 'fullscreen' 
                    ? 'Pantau status, dokumen, dan riwayat proses pengajuan ini.' 
                    : 'Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detailnya.'">
                Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detailnya.
            </p>
        </div>

        <!-- AREA TOMBOL KANAN ATAS (Figma 498:4945 / 498:5127) -->
        <div class="flex items-center gap-3">
            {{-- Tombol Permohonan Baru (Hanya untuk non-admin) --}}
            @if(! Auth::user()->hasRole('admin') && (Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create'])))
                <button x-show="viewMode !== 'fullscreen'" 
                        @click="openCreateModal()" 
                        data-action="openCreateModal()" 
                        type="button" 
                        class="inline-flex items-center justify-center h-11 px-5 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-sm transition-all shadow-sm hover:shadow-md gap-2 cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Permohonan Baru</span>
                </button>
            @endif

            {{-- Tombol Hapus / Batalkan saat mode Fullscreen --}}
            @if(Auth::user()->hasRole('admin'))
                <button x-show="viewMode === 'fullscreen' && selectedItem" 
                        @click="openHapusModal(selectedItem)" 
                        type="button" 
                        class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition-all shadow-sm gap-2 cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Hapus Pengajuan (Admin)</span>
                </button>
            @else
                <template x-if="viewMode === 'fullscreen' && selectedItem && ['draft', 'diajukan', 'ditolak', 'perlu_revisi'].includes(String(selectedItem.statusRaw || '').toLowerCase())">
                    <button @click="openHapusModal(selectedItem)" 
                            type="button" 
                            class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition-all shadow-sm gap-2 cursor-pointer">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span>Batalkan Pengajuan</span>
                    </button>
                </template>
            @endif
        </div>
    </div>

    {{-- Banner Notifikasi Flash Message Feedback Operasional & Verifikasi --}}
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 shadow-xs flex items-center justify-between gap-3 text-emerald-800">
            <div class="flex items-center gap-2.5">
                <svg class="size-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs font-bold leading-relaxed">{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 text-xs font-bold px-2 py-1 rounded">✕</button>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs flex items-center justify-between gap-3 text-rose-800">
            <div class="flex items-center gap-2.5">
                <svg class="size-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs font-bold leading-relaxed">{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 text-xs font-bold px-2 py-1 rounded">✕</button>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs text-rose-800 space-y-1">
            <div class="flex items-center gap-2 font-bold text-xs text-rose-900">
                <svg class="size-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Mohon periksa kendala berikut:</span>
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 pl-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 2. SECTION FILTER & PENCARIAN (Figma 462:7620 / Rectangle 57) -->
    <form method="GET" action="{{ route('permohonan.index') }}" x-show="viewMode === 'split'" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">

            <!-- Pencarian (Frame 85 / Rectangle 57) -->
            <div class="lg:col-span-4 space-y-1">
                <label class="block text-xs font-medium text-slate-500">Pencarian</label>
                <div class="flex items-center gap-2">
                    <div class="relative w-full">
                        <svg class="size-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="search" 
                               id="search-input"
                               name="q" 
                               value="{{ request('q') }}"
                               x-model="searchQuery"
                               placeholder="Cari nomor surat atau judul pengajuan" 
                               class="w-full h-10 pl-9 pr-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    </div>
                    <button type="submit" 
                            title="Cari"
                            class="h-10 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1.5 shrink-0 shadow-sm cursor-pointer">
                        <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M16 16l4.5 4.5"/>
                        </svg>
                        <span>Cari</span>
                    </button>
                </div>
            </div>

            <!-- Filter Status (Frame 86 / Status Tracking) -->
            <div class="lg:col-span-3 sm:col-span-1 space-y-1">
                <label class="block text-xs font-medium text-slate-500">Status</label>
                <select id="status-filter" 
                        name="status" 
                        onchange="this.form.submit()" 
                        class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="">Status Tracking</option>
                    <option value="diajukan" {{ request('status') == 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="reviu_paralel" {{ request('status') == 'reviu_paralel' ? 'selected' : '' }}>Dalam Proses</option>
                    <option value="disetujui" {{ request('status') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="perlu_revisi" {{ request('status') == 'perlu_revisi' ? 'selected' : '' }}>Perlu Revisi</option>
                    <option value="ditolak" {{ request('status') == 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            <!-- Filter Jenis (Frame 87 / Jenis Pengadaan) -->
            <div class="lg:col-span-2 sm:col-span-1 space-y-1">
                <label class="block text-xs font-medium text-slate-500">Jenis Pengadaan</label>
                <select id="jenis-filter" 
                        name="jenis" 
                        onchange="this.form.submit()" 
                        class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="">Jenis Pengadaan</option>
                    <option value="barang" {{ request('jenis') == 'barang' ? 'selected' : '' }}>Barang</option>
                    <option value="jasa" {{ request('jenis') == 'jasa' ? 'selected' : '' }}>Jasa</option>
                    <option value="konstruksi" {{ request('jenis') == 'konstruksi' ? 'selected' : '' }}>Konstruksi</option>
                </select>
            </div>

            <!-- Tanggal + Tombol Reset (Frame 103 / Rectangle 72) -->
            <div class="lg:col-span-3 space-y-1">
                <label class="block text-xs font-medium text-slate-500">Tanggal</label>
                <div class="flex items-center gap-2">
                    <input type="date" 
                           name="tanggal"
                           value="{{ request('tanggal') }}"
                           x-model="filterTanggal"
                           onchange="this.form.submit()"
                           class="w-full h-10 px-2.5 rounded-lg border border-slate-300 text-xs text-slate-600 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">

                    <a href="{{ route('permohonan.index') }}" 
                       class="h-10 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1.5 shrink-0">
                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                </div>
            </div>

        </div>
    </form>

    <!-- ========================================================================= -->
    <!-- 3. KONTEN UTAMA: A. MODE FULLSCREEN vs B. MODE SPLIT                       -->
    <!-- ========================================================================= -->

    <!-- ==================== A. MODE FULLSCREEN (Figma 465:8991 & 506:1335) ==================== -->
    <template x-if="viewMode === 'fullscreen' && selectedItem">
        <div class="space-y-6">

            <!-- Hero Summary Bar 5 Kolom (Rectangle 78 / Frame 161) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">

                <!-- 1. Nomor Pengajuan -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Nomor Pengajuan</span>
                        <span class="text-sm font-extrabold text-[#213153] block truncate font-mono" x-text="selectedItem.nomor" :title="selectedItem.nomor"></span>
                    </div>
                </div>

                <!-- 2. Nama Pengadaan -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Nama Pengadaan</span>
                        <span class="text-sm font-bold text-[#213153] block truncate" x-text="selectedItem.judul" :title="selectedItem.judul"></span>
                    </div>
                </div>

                <!-- 3. Status Saat Ini -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Status Saat Ini</span>
                        <span class="inline-flex mt-0.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                              :class="statusMeta(selectedItem.statusRaw).pill"
                              x-text="statusMeta(selectedItem.statusRaw).label"></span>
                    </div>
                </div>

                <!-- 4. Terakhir Diperbarui (akar-icons:calendar) -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Terakhir Diperbarui</span>
                        <span class="text-sm font-bold text-[#213153] block truncate" x-text="selectedItem.tanggal + ', 15:00'"></span>
                    </div>
                </div>

                <!-- 5. Penanggung Jawab -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Penanggung Jawab</span>
                        <span class="text-sm font-bold text-[#213153] block truncate" x-text="selectedItem.pemohon" :title="selectedItem.pemohon"></span>
                    </div>
                </div>

            </div>

            <!-- Stepper 8 Tahap Lebar Penuh (Rectangle 79 / Frame 162 - Figma 470:3160) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-6 sm:p-7 shadow-xs space-y-6">
                <!-- Header Card Stepper (Figma 470:3163) -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-7 items-center justify-center rounded-full bg-[#0969da] text-white text-xs font-bold" x-text="selectedItem.currentTahap || 1"></span>
                        <div>
                            <h3 class="font-extrabold text-base sm:text-lg text-[#213153]" x-text="'Tahap ' + (selectedItem.currentTahap || 1) + ' dari 8'"></h3>
                            <p class="text-xs text-slate-500">Alur Pelacakan Eksekusi Pengadaan 8 Tahap SIM-PBJ</p>
                        </div>
                    </div>
                    <span class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200" x-text="tahapanList[(selectedItem.currentTahap || 1) - 1]"></span>
                </div>

                <!-- Stepper Nodes & Track (Figma 470:3179 / Frame 162 & Frame 179) -->
                <div class="overflow-x-auto pb-4 pt-2">
                    <div class="relative flex items-start justify-between min-w-[920px] px-8">
                        <!-- Garis Penghubung Track Background -->
                        <div class="absolute left-16 right-16 top-6 h-1 rounded-full bg-slate-200">
                            <!-- Garis Penghubung Progress Aktif -->
                            <div class="h-full rounded-full bg-[#0969da] transition-all duration-500"
                                 :style="'width: ' + stepPercent() + '%'"></div>
                        </div>

                        <!-- 8 Node Lingkaran & Label -->
                        <template x-for="(tahap, idx) in tahapanList" :key="idx">
                            <div class="relative z-10 flex w-28 shrink-0 flex-col items-center text-center">
                                <!-- Lingkaran Node (Figma Ellipse 9, Ellipse 11 & 12) -->
                                <div class="size-12 rounded-full flex items-center justify-center font-bold text-sm transition-all"
                                     :class="idx + 1 === (selectedItem.currentTahap || 1)
                                        ? 'bg-[#0969da] text-white ring-4 ring-[#0969da]/30 border-2 border-white shadow-lg scale-110'
                                        : (idx + 1 < (selectedItem.currentTahap || 1)
                                            ? 'bg-[#0969da] text-white border-2 border-[#0969da]'
                                            : 'bg-white text-slate-400 border-2 border-slate-300')">
                                    <template x-if="idx + 1 < (selectedItem.currentTahap || 1)">
                                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </template>
                                    <template x-if="idx + 1 >= (selectedItem.currentTahap || 1)">
                                        <span x-text="idx + 1"></span>
                                    </template>
                                </div>

                                <!-- Label Nama Tahap (Figma 470:3226 - 470:3243) -->
                                <span class="mt-3 text-xs leading-snug line-clamp-2 px-1"
                                      :class="idx + 1 === (selectedItem.currentTahap || 1)
                                         ? 'text-[#0969da] font-extrabold'
                                         : (idx + 1 < (selectedItem.currentTahap || 1)
                                             ? 'text-slate-700 font-semibold'
                                             : 'text-slate-400 font-medium')"
                                      x-text="tahap"></span>

                                <!-- Checkmark Hijau di bawah tahap selesai (Figma Vector 470:3233) -->
                                <template x-if="idx + 1 < (selectedItem.currentTahap || 1)">
                                    <div class="mt-1 flex items-center justify-center text-emerald-600" title="Tahap Selesai">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Dua Kolom Bawah: Dokumen & Timeline -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- KIRI (6 COLS): Surat, Dokumen & Alert Revisi -->
                <div class="lg:col-span-6 space-y-6">

                    <!-- Kartu Khusus 'Perlu Revisi' (HANYA tampil saat Revisi, Figma 506:1335 / Frame 212) -->
                    <template x-if="['ditolak', 'revisi', 'perlu_revisi'].includes(String(selectedItem.statusRaw || '').toLowerCase())">
                        <div class="bg-amber-50/60 rounded-xl border border-amber-200 p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-amber-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <svg class="size-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <h3 class="font-bold text-base text-amber-900">Perlu Revisi</h3>
                                </div>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">1 Dokumen</span>
                            </div>

                            <div class="flex items-center gap-3 p-3.5 rounded-xl border border-amber-200 bg-white">
                                <div class="size-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs">PDF</div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-[#213153] truncate" x-text="selectedItem.judul"></div>
                                    <div class="text-[11px] text-slate-500 truncate" x-text="(selectedItem.fileName || 'Surat_Pengajuan.pdf') + ' • PDF • 1,1 MB • Revisi ' + (selectedItem.revisi_ke || 0)"></div>
                                    <div class="text-[11px] font-semibold text-rose-600 mt-0.5 truncate" x-text="selectedItem.alasan_revisi ? 'Catatan: ' + selectedItem.alasan_revisi : 'Batas terakhir: 19 Jan 2026 • 15:00'"></div>
                                </div>
                                <div class="shrink-0">
                                    <button @click="openRevisiModal(selectedItem)" type="button"
                                            class="h-8 px-4 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm transition-colors cursor-pointer">
                                        Revisi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Kartu Surat dan Dokumen (Figma 465:8991 / Rectangle 74 / Frame 132) -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="font-bold text-base text-[#213153]">Surat dan Dokumen</h3>
                            </div>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-[#0969da]">1 Dokumen</span>
                        </div>

                        <div class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                            <div class="size-11 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs">PDF</div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-xs text-[#213153] truncate" x-text="selectedItem.judul"></div>
                                <div class="text-[11px] text-slate-500 truncate" x-text="(selectedItem.fileName || 'Surat_Pengajuan.pdf') + ' • PDF • 1,1 MB • Revisi ' + (selectedItem.revisi_ke || 0)"></div>
                                <div class="text-[11px] text-slate-400 mt-0.5" x-text="'Diunggah ' + (selectedItem.tanggal || '16 Jan 2026') + ', 11:00'"></div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="pdfUrl(selectedItem) + '?download=1'" target="_blank" rel="noopener" title="Unduh Dokumen PDF"
                                   class="size-8 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-[#0969da] hover:border-[#0969da] transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                                <button @click="openPreview(selectedItem)" type="button"
                                        class="h-8 px-3.5 text-xs font-bold text-[#0969da] border border-[#0969da] bg-white rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
                                    Lihat
                                </button>
                            </div>
                        </div>

                        <!-- Ringkasan Anggaran Usulan -->
                        <div class="grid grid-cols-2 gap-3 pt-2 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Estimasi Pagu:</span>
                                <span class="font-bold text-[#0969da] text-sm font-mono" 
                                      x-text="selectedItem.estimasi_total_pagu || selectedItem.anggaran_diajukan ? 'Rp ' + Number(selectedItem.estimasi_total_pagu || selectedItem.anggaran_diajukan).toLocaleString('id-ID') : 'Rp -'"></span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Tanggal Pengajuan:</span>
                                <span class="font-bold text-slate-700" x-text="selectedItem.tanggal"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Tindakan Cepat Role -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-3">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-400">Aksi &amp; Keputusan</h4>
                        
                        @includeIf('permohonan.partials.role-actions-show')

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.nomor || selectedItem.id)" 
                               class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                                <span>Buka Halaman Detail</span>
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>

                            @if(!Auth::user()->hasRole('admin'))
                                <template x-if="['ditolak', 'revisi', 'perlu_revisi'].includes(String(selectedItem.statusRaw || selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
                                    <button type="button" 
                                            @click="openRevisiModal(selectedItem)"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-2 text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Ajukan Revisi</span>
                                    </button>
                                </template>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- KANAN (6 COLS): Timeline Riwayat Kronologis dan Revisi (Figma 465:8991 & 506:1335 / Rectangle 76) -->
                <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="font-bold text-[#213153] text-base">Riwayat Kronologis dan Revisi</h3>
                    </div>

                    <!-- Catatan Penolakan/Revisi jika ada (Wadir II & PPK) -->
                    <template x-if="selectedItem.alasan_revisi || selectedItem.catatan_wadir || selectedItem.catatan_ppk">
                        <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="size-2 rounded-full bg-amber-500 shrink-0"></div>
                                <div class="font-bold text-xs text-amber-900 uppercase tracking-wider">Catatan Telaah / Alasan Penolakan:</div>
                            </div>
                            
                            <template x-if="selectedItem.catatan_wadir">
                                <div class="rounded-lg bg-white/80 p-2.5 border border-purple-100 text-xs">
                                    <div class="font-bold text-purple-900 flex items-center justify-between">
                                        <span>1. Wakil Direktur II:</span>
                                        <span class="text-[10px] font-semibold text-purple-700" x-text="selectedItem.status_wadir"></span>
                                    </div>
                                    <div class="text-slate-700 mt-0.5" x-text="selectedItem.catatan_wadir"></div>
                                </div>
                            </template>

                            <template x-if="selectedItem.catatan_ppk">
                                <div class="rounded-lg bg-white/80 p-2.5 border border-blue-100 text-xs">
                                    <div class="font-bold text-blue-900 flex items-center justify-between">
                                        <span>2. Pejabat Pembuat Komitmen (PPK):</span>
                                        <span class="text-[10px] font-semibold text-blue-700" x-text="selectedItem.status_ppk"></span>
                                    </div>
                                    <div class="text-slate-700 mt-0.5" x-text="selectedItem.catatan_ppk"></div>
                                </div>
                            </template>

                            <template x-if="selectedItem.alasan_revisi">
                                <div class="text-[11px] text-amber-800 pt-1 border-t border-amber-200/60" x-text="'Catatan Sistem: ' + selectedItem.alasan_revisi"></div>
                            </template>
                        </div>
                    </template>

                    <!-- Timeline Loop (Figma Frame 180 / Rectangle 83) -->
                    <div class="space-y-3 pt-1">
                        <template x-for="log in (selectedItem.timeline || generateTimeline(selectedItem))" :key="log.id">
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex items-start gap-3.5 transition-all hover:bg-slate-50">
                                <div :class="timelineDot(log.type)" class="size-9 rounded-full flex items-center justify-center shrink-0 shadow-xs mt-0.5">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="timelineIcon(log.type)"></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[11px] font-medium text-slate-400" x-text="log.time"></span>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span :class="badgeClass(log.badge_type)" class="px-2 py-0.5 rounded-full text-[10px] font-bold" x-text="log.badge"></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200" x-text="log.revisi || 'Revisi 0'"></span>
                                        </div>
                                    </div>
                                    <h4 class="font-bold text-[#213153] text-xs mt-1" x-text="log.title"></h4>
                                    <p class="text-[11px] text-slate-500 mt-0.5 break-words" x-text="log.subtitle"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- ==================== B. MODE SPLIT (Figma 455:6666, 506:1026, 498:4973) ==================== -->
    <template x-if="viewMode === 'split'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- PANEL KIRI (5 COLS): DAFTAR PENGAJUAN (Figma 455:6666 / Rectangle 74) -->
            <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-bold text-base text-[#213153]">Daftar Pengajuan</h2>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-[#0969da]">
                            {{ isset($permohonanList) ? ($permohonanList->total() ?? $permohonanList->count()) : count($trackingItems ?? []) }} pengajuan
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 max-h-[720px] overflow-y-auto">
                    @forelse($permohonanList ?? [] as $item)
                        @php
                            $normalizedItem = [
                                'id' => $item->id,
                                'nomor' => $item->nomor_surat,
                                'nomor_surat' => $item->nomor_surat,
                                'judul' => $item->perihal ?? $item->judul_pengadaan,
                                'perihal' => $item->perihal ?? $item->judul_pengadaan,
                                'statusRaw' => $item->status_permohonan ?? $item->status ?? 'diajukan',
                                'status_permohonan' => $item->status_permohonan ?? $item->status ?? 'diajukan',
                                'pemohon' => $item->unit->nama_unit ?? $item->kode_unit ?? ($item->user->name ?? 'Unit Pengusul'),
                                'unit' => $item->unit ?? ['nama_unit' => $item->kode_unit ?? 'Utama'],
                                'kode_unit' => $item->kode_unit ?? '',
                                'tanggal' => $item->tanggal_pengajuan ? date('d M Y', strtotime($item->tanggal_pengajuan)) : date('d M Y'),
                                'tanggal_pengajuan' => $item->tanggal_pengajuan ?? '',
                                'estimasi_total_pagu' => $item->estimasi_total_pagu ?? $item->anggaran_diajukan ?? 0,
                                'anggaran_diajukan' => $item->anggaran_diajukan ?? $item->estimasi_total_pagu ?? 0,
                                'alasan_revisi' => $item->alasan_revisi ?? null,
                                'status_wadir' => $item->verifikasiParalel->firstWhere('role_verifikator', 'wadir_2')?->status_keputusan,
                                'catatan_wadir' => $item->verifikasiParalel->firstWhere('role_verifikator', 'wadir_2')?->catatan_alasan,
                                'status_ppk' => $item->verifikasiParalel->firstWhere('role_verifikator', 'ppk')?->status_keputusan,
                                'catatan_ppk' => $item->verifikasiParalel->firstWhere('role_verifikator', 'ppk')?->catatan_alasan,
                                'tujuan_pengadaan_ringkas' => $item->tujuan_pengadaan_ringkas ?? null,
                                'currentTahap' => (int) ($item->current_tahap ?? $item->hitungTahapSiklus()),
                                'current_tahap' => (int) ($item->current_tahap ?? $item->hitungTahapSiklus()),
                                'fileName' => $item->file_surat_permohonan ? basename($item->file_surat_permohonan) : 'Surat_Permohonan.pdf',
                                'id_user' => $item->id_user ?? $item->user_id ?? null,
                                'revisi_ke' => $item->revisi_ke ?? 0,
                            ];
                        @endphp
                        <div @click="selectItem({{ json_encode($normalizedItem) }})"
                             class="p-4 flex items-center gap-3.5 cursor-pointer transition-all hover:bg-slate-50"
                             :class="selectedItem && (selectedItem.nomor === '{{ $item->nomor_surat }}' || selectedItem.nomor_surat === '{{ $item->nomor_surat }}') ? 'bg-blue-50/70 border-l-4 border-l-[#0969da] shadow-xs' : ''">

                            <!-- Status Circle Icon (Frame 128) -->
                            <div class="size-11 rounded-full flex items-center justify-center shrink-0"
                                 :class="statusMeta('{{ $item->status_permohonan ?? $item->status }}').circle">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-html="statusMeta('{{ $item->status_permohonan ?? $item->status }}').icon"></svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-bold text-sm text-[#213153] truncate">{{ $item->perihal ?? $item->judul_pengadaan }}</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 whitespace-nowrap {{ strtolower($item->status_permohonan ?? $item->status) === 'disetujui' ? 'bg-emerald-100 text-emerald-700' : (in_array(strtolower($item->status_permohonan ?? $item->status), ['perlu_revisi', 'revisi']) ? 'bg-amber-100 text-amber-700' : (strtolower($item->status_permohonan ?? $item->status) === 'ditolak' ? 'bg-rose-100 text-rose-700' : 'bg-blue-100 text-[#0969da]')) }}">
                                        {{ ucfirst(str_replace('_', ' ', $item->status_permohonan ?? $item->status ?? 'Diajukan')) }}
                                    </span>
                                </div>
                                <div class="text-xs font-mono font-medium text-slate-400 mt-0.5 truncate">{{ $item->nomor_surat }}</div>
                                @if($item->tujuan_pengadaan_ringkas)
                                    <p class="text-[11px] text-slate-500 mt-0.5 truncate">{{ $item->tujuan_pengadaan_ringkas }}</p>
                                @endif
                                <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1">
                                    <span>{{ $item->unit->nama_unit ?? $item->kode_unit }}</span>
                                    <span class="font-bold font-mono text-[#213153]">Rp {{ number_format($item->anggaran_diajukan ?? $item->estimasi_total_pagu ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <svg class="size-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    @empty
                        <div class="p-10 text-center">
                            <p class="text-sm font-medium text-slate-500">Tidak ada pengajuan yang sesuai.</p>
                            <a href="{{ route('permohonan.index') }}" class="mt-2 inline-block text-xs font-semibold text-[#0969da] hover:underline">Reset filter</a>
                        </div>
                    @endforelse
                </div>

                @if(isset($permohonanList) && method_exists($permohonanList, 'hasPages') && $permohonanList->hasPages())
                    <div class="p-3 border-t border-slate-100 bg-slate-50/50">
                        {{ $permohonanList->links() }}
                    </div>
                @endif
            </div>

            <!-- PANEL KANAN (7 COLS): DETAIL SINGKAT ATAU EMPTY STATE (Figma 455:6666, 498:4973, & 506:1026) -->
            <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 min-h-[640px] flex flex-col justify-between">

                <!-- 1. EMPTY STATE SAAT USER BELUM MEMILIH APAPUN (Figma 455:6666 / Frame 210) -->
                <template x-if="!selectedItem">
                    <div class="my-auto flex flex-col items-center justify-center text-center py-16 px-4">
                        <div class="w-44 h-44 mb-6 flex items-center justify-center shrink-0">
                            <svg class="w-full h-full" viewBox="0 0 178 178" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="35" y="30" width="85" height="110" rx="8" fill="white" stroke="#1E293B" stroke-width="4.5"/>
                                <rect x="48" y="48" width="30" height="22" rx="4" fill="#FACC15" stroke="#1E293B" stroke-width="3.5"/>
                                <line x1="86" y1="52" x2="106" y2="52" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                                <line x1="86" y1="64" x2="106" y2="64" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                                <line x1="48" y1="84" x2="106" y2="84" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                                <line x1="48" y1="98" x2="106" y2="98" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                                <line x1="48" y1="112" x2="88" y2="112" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                                <circle cx="115" cy="105" r="22" fill="#38BDF8" fill-opacity="0.2" stroke="#1E293B" stroke-width="5"/>
                                <path d="M131 121L148 138" stroke="#FACC15" stroke-width="10" stroke-linecap="round"/>
                                <path d="M131 121L148 138" stroke="#1E293B" stroke-width="5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3 class="font-extrabold text-2xl text-[#213153] tracking-tight">Pilih Pengajuan untuk Melihat Detail</h3>
                        <p class="text-sm text-slate-500 max-w-md mt-2 leading-relaxed">
                            Silahkan pilih salah satu pengajuan dari daftar sebelah kiri untuk melihat informasi lengkap, dokumen, dan riwayat prosesnya.
                        </p>
                    </div>
                </template>

                <!-- 2. KONTEN SAAT PENGAJUAN DIPILIH (Figma 498:4973 Bukan Revisi / 506:1026 Revisi) -->
                <template x-if="selectedItem">
                    <div class="space-y-6">

                        <!-- Header Item Terpilih (Figma 506:1180 / 504:868) -->
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                            <div class="min-w-0">
                                <h3 class="font-bold text-lg text-[#213153] truncate" x-text="selectedItem.judul"></h3>
                                <div class="text-xs font-mono font-medium text-slate-400 mt-0.5 truncate" x-text="selectedItem.nomor"></div>
                            </div>
                            <button @click="viewMode = 'fullscreen'" type="button"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0969da] hover:text-blue-700 hover:underline shrink-0 cursor-pointer">
                                <span>Lihat Detail</span>
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>

                        <!-- Stepper 8 Tahap Ringkas (Figma 506:1189 & 504:870 / Frame 179 & Frame 163) -->
                        <div class="p-4 sm:p-5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex size-5 items-center justify-center rounded-full bg-[#0969da] text-white text-[10px] font-bold" x-text="selectedItem.currentTahap || 1"></span>
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tahap Siklus</span>
                                </div>
                                <span class="text-xs font-bold text-[#0969da]" x-text="'Tahap ' + (selectedItem.currentTahap || 1) + ' dari 8: ' + tahapanList[(selectedItem.currentTahap || 1) - 1]"></span>
                            </div>

                            <div class="relative flex items-center justify-between px-2 py-2">
                                <!-- Track Penghubung Background -->
                                <div class="absolute top-1/2 left-5 right-5 h-1 bg-slate-200 -translate-y-1/2 z-0 rounded-full"></div>
                                <!-- Track Penghubung Aktif -->
                                <div class="absolute top-1/2 left-5 h-1 bg-[#0969da] -translate-y-1/2 z-0 transition-all duration-300 rounded-full"
                                     :style="'width: ' + ((selectedItem.currentTahap > 1 ? (selectedItem.currentTahap - 1) / 7 : 0) * 100) + '%'"></div>

                                <template x-for="n in 8" :key="n">
                                    <div class="relative z-10 size-8 sm:size-9 rounded-full flex items-center justify-center font-bold text-xs transition-all"
                                         :class="n === (selectedItem.currentTahap || 1)
                                            ? 'bg-[#0969da] text-white ring-4 ring-[#0969da]/30 border-2 border-white shadow-md scale-110 z-20'
                                            : (n < (selectedItem.currentTahap || 1)
                                                ? 'bg-[#0969da] text-white border-2 border-[#0969da]'
                                                : 'bg-white text-slate-400 border-2 border-slate-300')"
                                         x-text="n"></div>
                                </template>
                            </div>
                        </div>

                        <!-- Riwayat Kronologis dan Revisi (Figma 506:1215 & 504:906) -->
                        <div class="space-y-4 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h4 class="font-bold text-sm text-[#213153]">Riwayat Kronologis dan Revisi</h4>
                            </div>

                            <div class="max-h-[340px] overflow-y-auto space-y-3 pr-1">
                                <template x-for="log in (selectedItem.timeline || generateTimeline(selectedItem))" :key="log.id">
                                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-start gap-3 transition-all hover:bg-slate-50">
                                        <div :class="timelineDot(log.type)" class="size-8 rounded-full flex items-center justify-center shrink-0 shadow-xs mt-0.5">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="timelineIcon(log.type)"></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-[10px] font-medium text-slate-400" x-text="log.time"></span>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <span :class="badgeClass(log.badge_type)" class="px-2 py-0.5 rounded-full text-[10px] font-bold" x-text="log.badge"></span>
                                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-medium bg-slate-100 text-slate-600 border border-slate-200" x-text="log.revisi || 'Revisi 0'"></span>
                                                </div>
                                            </div>
                                            <h5 class="font-bold text-[#213153] text-xs mt-0.5" x-text="log.title"></h5>
                                            <p class="text-[11px] text-slate-500 mt-0.5 break-words" x-text="log.subtitle"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Banner Aksi Cepat Sesuai Hak Akses Peran RBAC -->
                        <div class="pt-2">
                            @include('permohonan.partials.role-actions-index')
                        </div>

                    </div>
                </template>

                <!-- Tombol Aksi Bawah Split Panel -->
                <template x-if="selectedItem">
                    <div class="mt-6 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            {{-- <button @click="viewMode = 'fullscreen'" type="button"
                                    class="h-9 px-4 inline-flex items-center rounded-lg bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors cursor-pointer">
                                Detail Penuh
                            </button>
                            <button @click="openPreview(selectedItem)" type="button"
                                    class="h-9 px-4 inline-flex items-center rounded-lg border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors cursor-pointer">
                                Lihat Dokumen
                            </button>
                            <a :href="pdfUrl(selectedItem) + '?download=1'" target="_blank" rel="noopener"
                               class="h-9 px-3.5 inline-flex items-center rounded-lg border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors">
                                Unduh PDF
                            </a> --}}
                            @if(!Auth::user()->hasRole('admin'))
                                <template x-if="['ditolak', 'revisi', 'perlu_revisi'].includes(String(selectedItem.statusRaw || selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
                                    <button type="button" 
                                            @click="openRevisiModal(selectedItem)"
                                            class="h-9 px-4 inline-flex items-center rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-colors cursor-pointer">
                                        Revisi
                                    </button>
                                </template>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">

                            <button type="button" 
                                    @click="openHapusModal(selectedItem)"
                                    class="h-9 px-3.5 inline-flex items-center rounded-lg text-rose-600 hover:bg-rose-50 text-xs font-semibold transition-colors cursor-pointer">
                                {{ Auth::user()->hasRole('admin') ? 'Hapus' : 'Batalkan' }}
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </template>

    <!-- ========================================================================= -->
    <!-- 4. MODAL 1: PRATINJAU DOKUMEN (Figma 715:6528 & 744:7446)               -->
    <!-- ========================================================================= -->
    <div x-show="showPreviewModal" x-cloak x-transition.opacity
         @click.self="closePreview()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-[12px] w-full max-w-[1100px] shadow-2xl flex flex-col max-h-[94vh] overflow-hidden border border-slate-200">
            
            <!-- Header Modal Pratinjau (Figma 715:6537 & 744:7580) -->
            <div class="h-[64px] border-b border-[#d2d2d2] px-7 flex items-center justify-between bg-white shrink-0">
                <h3 class="font-semibold text-[24px] text-black">Pratinjau Dokumen</h3>
                <button @click="closePreview()" type="button" class="text-slate-500 hover:text-black rounded-lg p-1 transition-colors cursor-pointer" title="Tutup">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Card Info Dokumen (Figma 715:6531 / 744:7447) -->
            <div class="mx-7 my-3 px-6 py-3.5 bg-[#fbfbfb] rounded-[6px] border border-slate-100 flex flex-col justify-center shrink-0">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="size-11 rounded bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs shadow-xs">
                        <svg class="size-6 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="font-semibold text-[18px] text-black truncate" x-text="previewDocData.title">Surat Permohonan Pengadaan.pdf</h4>
                        <p class="font-normal text-[14px] text-black" x-text="'PDF  •  ' + previewDocData.pages + ' halaman  •  ' + previewDocData.size">PDF  •  2 halaman  •  245 KB</p>
                    </div>
                </div>

                <!-- Catatan Revisi (HANYA tampil saat Revisi, Figma 744:7446 line 398) -->
                <template x-if="previewDocData.isRevision">
                    <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center gap-2 text-[13px] text-black">
                        <svg class="size-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span x-text="previewDocData.note ? (previewDocData.note.toLowerCase().startsWith('catatan') ? previewDocData.note : 'Catatan revisi: ' + previewDocData.note) : 'Catatan revisi: Halaman terpotong, tidak terbaca'"></span>
                    </div>
                </template>
            </div>

            <!-- Viewer Area: Split Thumbnails 1 & 2 + Main Official PNJ Document Sheet (Figma 716:6548 & 744:7448) -->
            <div class="flex-1 min-h-[520px] mx-7 mb-3 bg-[#343a40] rounded-[6px] overflow-hidden flex flex-col shadow-inner">
                
                <!-- PDF Viewer Top Bar (Figma 716:6549) -->
                <div class="h-[48px] bg-[#3f464d] rounded-t-[6px] px-6 flex items-center justify-between text-xs text-slate-300 font-mono shadow-md shrink-0">
                    <span class="truncate font-semibold text-slate-200" x-text="previewDocData.title">Surat Permohonan Pengadaan.pdf</span>
                    <span>Halaman <strong class="text-white" x-text="activePreviewPage"></strong> / 2</span>
                </div>

                <div class="flex-1 flex overflow-hidden min-h-0">
                    <!-- Sisi Kiri: Thumbnail Halaman (Figma 717:6717) -->
                    <div class="w-32 bg-[#343a40] p-4 flex flex-col items-center gap-5 overflow-y-auto hidden sm:flex shrink-0 border-r border-[#3f464d]">
                        
                        <!-- Thumbnail 1 (Surat Permohonan) -->
                        <div @click="setPreviewPage(1)"
                             class="cursor-pointer transition-all flex flex-col items-center group">
                            <div class="w-[88px] h-[124px] bg-white rounded shadow-md border p-1.5 flex flex-col justify-between text-[6px] text-slate-400 font-mono transition-all overflow-hidden"
                                 :class="activePreviewPage === 1 ? 'ring-3 ring-[#3b82f6] border-[#3b82f6]' : 'border-slate-300 opacity-80 group-hover:opacity-100'">
                                <div class="w-full text-center">
                                    <span class="font-bold text-[5px] text-black block truncate">POLITEKNIK NEGERI JAKARTA</span>
                                    <div class="w-full h-px bg-black my-0.5"></div>
                                    <span class="block text-[4.5px] text-slate-700 font-bold truncate">SURAT PERMOHONAN</span>
                                </div>
                                <div class="w-full space-y-1 px-0.5">
                                    <div class="h-1 bg-slate-200 rounded w-full"></div>
                                    <div class="h-1 bg-slate-200 rounded w-4/5"></div>
                                    <div class="h-1 bg-slate-200 rounded w-3/4"></div>
                                </div>
                                <div class="w-full text-right text-[4px] text-slate-400">Tanda Tangan</div>
                            </div>
                            <p class="font-bold text-[15px] text-white text-center mt-1.5"
                               :class="activePreviewPage === 1 ? 'text-[#3b82f6]' : 'text-white'">1</p>
                        </div>

                        <!-- Thumbnail 2 (Lampiran KAK & Rincian) -->
                        <div @click="setPreviewPage(2)"
                             class="cursor-pointer transition-all flex flex-col items-center group">
                            <div class="w-[88px] h-[124px] bg-white rounded shadow-md border p-1.5 flex flex-col justify-between text-[6px] text-slate-400 font-mono transition-all overflow-hidden"
                                 :class="activePreviewPage === 2 ? 'ring-3 ring-[#3b82f6] border-[#3b82f6]' : 'border-slate-300 opacity-80 group-hover:opacity-100'">
                                <div class="w-full text-center">
                                    <span class="font-bold text-[5px] text-black block truncate">POLITEKNIK NEGERI JAKARTA</span>
                                    <div class="w-full h-px bg-black my-0.5"></div>
                                    <span class="block text-[4.5px] text-slate-700 font-bold truncate">LAMPIRAN KAK &amp; SPEK</span>
                                </div>
                                <div class="w-full space-y-1 px-0.5">
                                    <div class="h-2 bg-slate-100 rounded w-full border border-slate-200"></div>
                                    <div class="h-2 bg-slate-100 rounded w-full border border-slate-200"></div>
                                </div>
                                <div class="w-full text-right text-[4px] text-slate-400">Tanda Tangan</div>
                            </div>
                            <p class="font-bold text-[15px] text-white text-center mt-1.5"
                               :class="activePreviewPage === 2 ? 'text-[#3b82f6]' : 'text-white'">2</p>
                        </div>

                    </div>

                    <!-- Sisi Kanan: Lembar Naskah Dinas Resmi PNJ (Figma 716:6551 & 744:7452) -->
                    <div class="flex-1 bg-[#494f57] p-6 sm:p-8 overflow-y-auto flex justify-center items-start">
                        
                        <!-- LEMBAR SURAT RESMI (Rectangle width 733px, shadow-2xl, bg-white) -->
                        <div class="w-full max-w-[733px] bg-white rounded-xs shadow-2xl p-8 sm:p-10 text-slate-800 text-xs space-y-6 leading-relaxed">
                        
                        <!-- HALAMAN 1: SURAT PERMOHONAN PENGADAAN -->
                        <div x-show="activePreviewPage === 1" class="space-y-6">
                            
                            <!-- KOP SURAT PNJ (Figma 717:6562, image 3, Line 28) -->
                            <div class="text-center pb-3 border-b-2 border-slate-900 relative">
                                <div class="flex items-center justify-center gap-4">
                                    <img src="{{ asset('favicon.ico') }}" alt="Logo PNJ" class="size-16 object-contain shrink-0">
                                    <div>
                                        <h4 class="text-[11px] font-semibold tracking-wider text-slate-600 uppercase">KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI</h4>
                                        <h2 class="text-base sm:text-lg font-black tracking-wide text-slate-900 uppercase">POLITEKNIK NEGERI JAKARTA</h2>
                                        <p class="text-[10px] text-slate-500">Jl. Prof. Dr. G.A. Siwabessy, Kampus Universitas Indonesia, Depok 16425</p>
                                        <p class="text-[10px] text-slate-500">Telepon (021) 7270036, Hunting, Fax (021) 7270034, Laman: www.pnj.ac.id</p>
                                    </div>
                                </div>
                                <div class="mt-2 border-t border-slate-900"></div>
                            </div>

                            <!-- JUDUL SURAT (Figma 717:6572) -->
                            <div class="text-center space-y-1">
                                <h3 class="font-extrabold text-sm sm:text-base text-slate-900 tracking-wide uppercase underline">
                                    SURAT PERMOHONAN PENGADAAN BARANG / JASA
                                </h3>
                                <p class="text-xs font-mono text-slate-600" x-text="'Nomor: ' + (previewDocData.nomor || 'PBJ-2026-0017')"></p>
                            </div>

                            <!-- KEPADA & PENGANTAR -->
                            <div class="space-y-2 text-xs">
                                <div class="grid grid-cols-12 gap-1">
                                    <span class="col-span-2 font-semibold">Lampiran</span>
                                    <span class="col-span-10">: 1 (satu) Berkas Rincian KAK &amp; Spesifikasi</span>
                                </div>
                                <div class="grid grid-cols-12 gap-1">
                                    <span class="col-span-2 font-semibold">Perihal</span>
                                    <span class="col-span-10 font-bold" x-text="': Permohonan ' + previewDocData.judul"></span>
                                </div>
                                
                                <div class="pt-3">
                                    <p>Yth. <strong>Pejabat Pembuat Komitmen (PPK)</strong></p>
                                    <p>Politeknik Negeri Jakarta</p>
                                    <p>di Tempat</p>
                                </div>

                                <p class="pt-2 text-justify">
                                    Dengan hormat, sehubungan dengan kebutuhan pemenuhan sarana dan prasarana penunjang kegiatan Tridharma Perguruan Tinggi di lingkungan unit kerja <strong x-text="previewDocData.unit"></strong>, dengan ini kami mengajukan permohonan pengadaan barang/jasa sebagai berikut:
                                </p>
                            </div>

                            <!-- TABEL RINCIAN BARANG / JASA (Figma Rectangle 158 - 169) -->
                            <div class="border border-slate-300 rounded-lg overflow-hidden">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300">
                                        <tr>
                                            <th class="p-2 border-r border-slate-300 w-10 text-center">No</th>
                                            <th class="p-2 border-r border-slate-300">Uraian Pekerjaan / Nama Barang</th>
                                            <th class="p-2 border-r border-slate-300 text-center w-20">Volume</th>
                                            <th class="p-2 border-r border-slate-300 text-right w-36">Estimasi Biaya</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr>
                                            <td class="p-2.5 text-center border-r border-slate-200">1</td>
                                            <td class="p-2.5 border-r border-slate-200">
                                                <strong class="block text-slate-800" x-text="previewDocData.judul"></strong>
                                                <span class="text-[11px] text-slate-500" x-text="previewDocData.tujuan"></span>
                                            </td>
                                            <td class="p-2.5 text-center border-r border-slate-200">1 Paket</td>
                                            <td class="p-2.5 text-right font-mono font-bold text-slate-800" x-text="previewDocData.nominalFormatted"></td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-slate-50 font-bold border-t border-slate-300">
                                        <tr>
                                            <td colspan="3" class="p-2 text-right border-r border-slate-300">Total Usulan Anggaran:</td>
                                            <td class="p-2 text-right font-mono text-[#0969da]" x-text="previewDocData.nominalFormatted"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <p class="text-justify text-xs">
                                Demikian surat permohonan ini kami sampaikan. Atas perhatian, arahan, dan persetujuan Bapak/Ibu, kami ucapkan terima kasih.
                            </p>

                            <!-- TANDA TANGAN (Figma Rectangle 172 & 173) -->
                            <div class="pt-4 grid grid-cols-2 gap-8 text-center text-xs">
                                <div>
                                    <p class="text-slate-500">Mengetahui,</p>
                                    <p class="font-bold text-slate-800">Kepala Bagian / Pimpinan Unit</p>
                                    <div class="h-16"></div>
                                    <p class="font-bold underline text-slate-900">Dr. Ir. Pengusul Utama, M.T.</p>
                                    <p class="text-[10px] text-slate-500">NIP. 197805122003121002</p>
                                </div>
                                <div>
                                    <p class="text-slate-500" x-text="'Depok, ' + previewDocData.tanggal"></p>
                                    <p class="font-bold text-slate-800">Pemohon / Pengusul,</p>
                                    <div class="h-16"></div>
                                    <p class="font-bold underline text-slate-900" x-text="previewDocData.pemohon"></p>
                                    <p class="text-[10px] text-slate-500">NIP. 199001012020121001</p>
                                </div>
                            </div>

                        </div>

                        <!-- HALAMAN 2: LAMPIRAN KAK & SPESIFIKASI TEKNIS -->
                        <div x-show="activePreviewPage === 2" class="space-y-6">
                            
                            <div class="text-center pb-2 border-b border-slate-300">
                                <span class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">LAMPIRAN SURAT PERMOHONAN</span>
                                <h3 class="font-extrabold text-sm sm:text-base text-slate-900 uppercase">
                                    KERANGKA ACUAN KERJA (KAK) &amp; SPESIFIKASI TEKNIS
                                </h3>
                                <p class="text-xs font-mono text-slate-500" x-text="'Nomor: ' + (previewDocData.nomor || 'PBJ-2026-0017')"></p>
                            </div>

                            <div class="space-y-3 text-xs leading-relaxed">
                                <div>
                                    <h4 class="font-bold text-slate-900">1. Latar Belakang &amp; Tujuan</h4>
                                    <p class="text-slate-600 mt-0.5" x-text="previewDocData.tujuan || 'Pengadaan perangkat dalam rangka menunjang kegiatan operasional dan praktikum institusi.'"></p>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900">2. Rincian Spesifikasi Kebutuhan</h4>
                                    <div class="border border-slate-300 rounded-lg overflow-hidden mt-1.5">
                                        <table class="w-full text-left text-xs border-collapse">
                                            <thead class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300">
                                                <tr>
                                                    <th class="p-2 border-r border-slate-300 w-12 text-center">Item</th>
                                                    <th class="p-2 border-r border-slate-300">Parameter / Komponen</th>
                                                    <th class="p-2">Spesifikasi Minimal yang Dipersyaratkan</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200">
                                                <tr>
                                                    <td class="p-2 text-center border-r border-slate-200 font-mono">01</td>
                                                    <td class="p-2 border-r border-slate-200 font-semibold">Prosesor &amp; Memori</td>
                                                    <td class="p-2">Min. Core i7 / Ryzen 7, RAM 16 GB DDR5, SSD 512 GB NVMe</td>
                                                </tr>
                                                <tr>
                                                    <td class="p-2 text-center border-r border-slate-200 font-mono">02</td>
                                                    <td class="p-2 border-r border-slate-200 font-semibold">Layar &amp; Grafis</td>
                                                    <td class="p-2">14-15.6 inch FHD IPS, Dedicated GPU min. 4 GB GDDR6</td>
                                                </tr>
                                                <tr>
                                                    <td class="p-2 text-center border-r border-slate-200 font-mono">03</td>
                                                    <td class="p-2 border-r border-slate-200 font-semibold">Garansi &amp; Layanan</td>
                                                    <td class="p-2">Garansi resmi pabrikan minimal 2 (dua) tahun on-site service</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900">3. Waktu dan Tempat Pelaksanaan</h4>
                                    <p class="text-slate-600 mt-0.5">Jangka waktu pengiriman dan instalasi maksimal 30 (tiga puluh) hari kalender terhitung sejak diterbitkannya Surat Perintah Kerja (SPK).</p>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900">4. Alokasi Anggaran &amp; Sumber Dana</h4>
                                    <p class="text-slate-600 mt-0.5 font-mono">Alokasi Pagu MAK: <strong class="text-[#0969da]" x-text="previewDocData.nominalFormatted"></strong> (Sumber Dana PNBP/BOPTN Tahun Anggaran 2026).</p>
                                </div>
                            </div>

                            <div class="pt-6 grid grid-cols-2 gap-8 text-center text-xs">
                                <div>
                                    <p class="text-slate-500">Diverifikasi oleh:</p>
                                    <p class="font-bold text-slate-800">Verifikator Anggaran MAK / PPBJ</p>
                                    <div class="h-16"></div>
                                    <p class="font-bold underline text-slate-900">Verifikator Pengadaan PNJ</p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Disusun oleh:</p>
                                    <p class="font-bold text-slate-800">Tim Teknis / Pengusul,</p>
                                    <div class="h-16"></div>
                                    <p class="font-bold underline text-slate-900" x-text="previewDocData.pemohon"></p>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>

            <!-- Footer Action Bar (Figma 723:6725 / 744:7583) -->
            <div class="h-[80px] bg-white border-t border-[#d2d2d2] px-7 flex items-center justify-end gap-[25px] rounded-b-[12px] shrink-0">
                <!-- Jika REVISI: Tombol Revisi (Figma 744:7584) -->
                <template x-if="previewDocData.isRevision">
                    <button @click="triggerRevisiFromPreview()" type="button"
                            class="h-[44px] w-[120px] bg-white border-2 border-[#ffa600] text-[#eeae00] hover:bg-amber-50 rounded-[6px] font-semibold text-[16px] inline-flex items-center justify-center gap-2 transition-colors cursor-pointer shadow-xs">
                        <svg class="size-4 shrink-0 text-[#ffa600]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Revisi</span>
                    </button>
                </template>

                <!-- Jika BUKAN REVISI: Tombol Unduh (Figma 723:6734) -->
                <template x-if="!previewDocData.isRevision">
                    <a :href="previewDocData.downloadUrl" target="_blank" rel="noopener"
                       class="h-[44px] w-[120px] bg-white border-2 border-[#3b82f6] text-[#3b82f6] hover:bg-blue-50 rounded-[6px] font-semibold text-[16px] inline-flex items-center justify-center gap-2 transition-colors shadow-xs">
                        <svg class="size-4 shrink-0 text-[#3b82f6]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Unduh</span>
                    </a>
                </template>

                <!-- Tombol Tutup (Figma 723:6736 & 744:7588) -->
                <button @click="closePreview()" type="button"
                        class="h-[44px] w-[100px] bg-[#3b82f6] hover:bg-blue-600 text-white rounded-[6px] font-semibold text-[16px] inline-flex items-center justify-center transition-colors cursor-pointer shadow-xs">
                    Tutup
                </button>
            </div>

        </div>
    </div>

    {{-- Banner Notifikasi Flash Message Feedback Operasional & Verifikasi --}}
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 shadow-xs flex items-center justify-between gap-3 text-emerald-800">
            <div class="flex items-center gap-2.5">
                <svg class="size-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs font-bold leading-relaxed">{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 text-xs font-bold px-2 py-1 rounded">✕</button>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs flex items-center justify-between gap-3 text-rose-800">
            <div class="flex items-center gap-2.5">
                <svg class="size-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs font-bold leading-relaxed">{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 text-xs font-bold px-2 py-1 rounded">✕</button>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs text-rose-800 space-y-1">
            <div class="flex items-center gap-2 font-bold text-xs text-rose-900">
                <svg class="size-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Mohon periksa kendala berikut:</span>
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 pl-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 5. MODAL 2: BUAT PERMOHONAN BARU MULTI-DOKUMEN (737:7343 & 737:7339)      -->
    <!-- ========================================================================= -->
    @if(! Auth::user()->hasRole('admin'))
    <x-modal id="modal-create-permohonan" title="Buat Permohonan Baru" subtitle="Masukkan nomor surat Srikandi, judul, tujuan, serta dokumen resmi" maxWidth="596" borderTop="border-t-[5px] border-[#3b82f6]">
        <form action="{{ route('permohonan.store') }}" 
              method="POST" 
              enctype="multipart/form-data" 
              class="space-y-4">
            @csrf

            <!-- No Srikandi (Frame 135) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">No Srikandi <span class="text-rose-500">*</span></label>
                <input type="text" name="nomor_surat" required placeholder="Masukkan nomor Srikandi" 
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#3b82f6]/20 focus:border-[#3b82f6] outline-none">
            </div>

            <!-- Judul Pengadaan (Frame 134) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Judul Pengadaan <span class="text-rose-500">*</span></label>
                <input type="text" name="perihal" required placeholder="Masukkan Judul Pengadaan" 
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#3b82f6]/20 focus:border-[#3b82f6] outline-none">
            </div>

            <!-- Tujuan Pengadaan (Frame 53) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Tujuan Pengadaan <span class="text-rose-500">*</span></label>
                <textarea name="tujuan_pengadaan_ringkas" rows="2" required placeholder="Masukkan Tujuan Pengadaan" 
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#3b82f6]/20 focus:border-[#3b82f6] outline-none"></textarea>
            </div>

            <!-- Nominal & Unit Pengusul (Frame 136) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Nominal <span class="text-rose-500">*</span></label>
                    <input type="number" name="anggaran_diajukan" x-model="createNominal" step="0.01" min="1" required placeholder="Masukkan Nominal" 
                           class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm font-mono focus:ring-2 focus:ring-[#3b82f6]/20 focus:border-[#3b82f6] outline-none">
                    <span class="block text-[11px] font-bold text-[#3b82f6] font-mono mt-0.5" x-text="createNominal ? 'Rp ' + Number(createNominal).toLocaleString('id-ID') : ''"></span>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Unit Pengusul</label>
                    <input type="hidden" name="kode_unit" value="{{ Auth::user()->kode_unit ?? 'TIK' }}">
                    <input type="text" readonly disabled 
                           value="{{ Auth::user()->unit ? '[' . Auth::user()->kode_unit . '] ' . Auth::user()->unit->nama_unit : (Auth::user()->kode_unit ?? 'Unit Pengusul') }}" 
                           class="w-full h-10 px-3 rounded-lg border border-slate-200 bg-slate-100 text-slate-700 text-sm font-semibold cursor-not-allowed">
                </div>
            </div>

            <input type="hidden" name="tanggal_pengajuan" value="{{ date('Y-m-d') }}">

            <!-- Dokumen Dropzone & Multi Dokumen Cards (Figma 737:7339 vs 737:7343) -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-700">Dokumen <span class="text-rose-500">*</span></label>
                    <span class="text-[11px] font-medium text-slate-400" x-text="(createFiles ? createFiles.length : 0) + '/5 File (Maksimal 5 File • Maksimal 15 MB)'"></span>
                </div>

                <!-- Error Message Validasi File Modal -->
                <div x-show="createFileError" x-text="createFileError" class="p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-600 text-xs font-medium"></div>

                <!-- Daftar Berkas Terpilih / Multi Dokumen (Figma 737:7343 / Frame 380) -->
                <template x-if="createFiles.length > 0">
                    <div class="space-y-2 mb-2">
                        <template x-for="(f, idx) in createFiles" :key="f.id">
                            <div class="h-[70px] bg-[#f4f4f4] rounded-[6px] px-4 flex items-center justify-between gap-3 border border-slate-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-9 rounded bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs">
                                        <svg class="size-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-black truncate" x-text="f.name"></span>
                                        <span class="block text-[10px] text-black" x-text="'PDF  •  ' + f.pageCount + ' halaman  •  ' + f.formattedSize"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" @click="previewLocalCreateFile(f)"
                                            class="h-[32px] w-[73px] rounded-[6px] bg-[#3b82f6] hover:bg-blue-600 text-white text-[15px] font-medium flex items-center justify-center transition-colors cursor-pointer">
                                        Lihat
                                    </button>
                                    <button type="button" @click="removeCreateFile(idx)"
                                            class="size-[32px] flex items-center justify-center text-slate-500 hover:text-rose-600 rounded transition-colors cursor-pointer" title="Hapus Berkas">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Dropzone Unggah (Figma 737:7339 & 737:7343) -->
                <div @dragover.prevent="isDraggingCreate = true"
                     @dragleave.prevent="isDraggingCreate = false"
                     @drop.prevent="isDraggingCreate = false; handleCreateFilesDrop($event)"
                     @click="document.getElementById('input-file-create').click()"
                     :class="isDraggingCreate ? 'border-[#3b82f6] bg-blue-100 scale-[0.99]' : 'border-[#3b82f6] bg-[#ebf5ff] hover:bg-blue-100/50'"
                     class="h-[115px] border rounded-[5px] flex flex-col items-center justify-center text-center cursor-pointer transition-all select-none">
                    <div class="size-10 rounded-full shadow-[0px_4px_10px_1px_rgba(59,130,246,0.5)] flex items-center justify-center mb-1 bg-gradient-to-br from-[#3b82f6] to-white text-[#3b82f6]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                    </div>
                    <span class="font-bold text-[15px] text-black">Taruh file disini</span>
                    <span class="font-normal text-[8px] sm:text-[10px] text-[#657a7c]">Atau klik untuk memilih file &bull; Maksimal 5 file, Maksimal 15 MB</span>
                    <input type="file" id="input-file-create" name="file_surat_permohonan" accept=".pdf" multiple class="hidden" @change="onCreateFilesChange($event)">
                </div>
            </div>

            <!-- Footer Buttons (Figma 737:7348 & 737:7351) -->
            <div class="flex items-center justify-between gap-4 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-create-permohonan')" 
                        class="h-[48px] w-[185px] bg-white border border-[rgba(59,130,246,0.85)] text-[#3b82f6] hover:bg-blue-50 font-medium text-[18px] rounded-[10px] flex items-center justify-center transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" 
                        :class="createFiles.length > 0 ? 'bg-[#3b82f6] border border-[rgba(59,130,246,0.85)] text-white shadow-md hover:bg-blue-600' : 'bg-white border border-[rgba(59,130,246,0.85)] text-[#3b82f6] hover:bg-blue-50'"
                        class="h-[48px] w-[222px] rounded-[10px] font-medium text-[18px] flex items-center justify-center transition-all cursor-pointer">
                    Buat Pengajuan
                </button>
            </div>
        </form>
    </x-modal>
    @endif

    <!-- ========================================================================= -->
    <!-- 6. MODAL 3: REVISI PERMOHONAN MULTI-DOKUMEN (744:7706 & 744:7705)         -->
    <!-- ========================================================================= -->
    <x-modal id="modal-revisi-permohonan" title="Revisi Permohonan" maxWidth="596" borderTop="border-t-[5px] border-[#ffa600]">
        <form action="{{ route('permohonan.revisi') }}" 
              method="POST" 
              enctype="multipart/form-data" 
              class="space-y-4">
            @csrf
            
            <input type="hidden" name="nomor_surat_lama" :value="revisiTarget?.nomor_surat || revisiTarget?.nomor">

            <!-- Catatan Alasan Penolakan (Figma 744:7708) -->
            <div class="text-[#4a4a4a] text-[15px] leading-relaxed pb-1">
                <span>Catatan bagian Perencanaan: </span>
                <span class="font-normal" x-text="revisiTarget?.alasan_revisi || 'File tidak dapat dilihat, pastikan upload file yang benar'"></span>
            </div>

            <!-- No Srikandi (Frame 135) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">No Srikandi <span class="text-rose-500">*</span></label>
                <input type="text" 
                       name="nomor_surat_baru" 
                       :value="revisiTarget?.nomor_surat || revisiTarget?.nomor || ''"
                       placeholder="Masukkan nomor Srikandi"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#ffa600]/20 focus:border-[#ffa600] outline-none">
            </div>

            <!-- Judul Pengadaan (Frame 134) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Judul Pengadaan <span class="text-rose-500">*</span></label>
                <input type="text" 
                       name="perihal" 
                       :value="revisiTarget?.perihal || revisiTarget?.judul || ''"
                       required 
                       placeholder="Masukkan Judul Pengadaan"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#ffa600]/20 focus:border-[#ffa600] outline-none">
            </div>

            <!-- Tujuan Pengadaan (Frame 53) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Tujuan Pengadaan <span class="text-rose-500">*</span></label>
                <textarea name="tujuan_pengadaan_ringkas" rows="2" required placeholder="Masukkan Tujuan Pengadaan" 
                          x-text="revisiTarget?.tujuan_pengadaan_ringkas || revisiTarget?.perihal || ''"
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#ffa600]/20 focus:border-[#ffa600] outline-none"></textarea>
            </div>

            <!-- Nominal (Frame 136) -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Nominal <span class="text-rose-500">*</span></label>
                <input type="number" 
                       name="anggaran_diajukan" 
                       x-model="revisiNominal"
                       step="0.01" 
                       min="1" 
                       required 
                       placeholder="Masukkan Nominal"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm font-mono focus:ring-2 focus:ring-[#ffa600]/20 focus:border-[#ffa600] outline-none">
            </div>

            <!-- Keterangan Uraian Revisi -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Uraian Perbaikan Berkas</label>
                <textarea name="keterangan_revisi" rows="2" placeholder="Jelaskan penyesuaian berkas..." 
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#ffa600]/20 focus:border-[#ffa600] outline-none"></textarea>
            </div>

            <!-- Dokumen Berkas Multi-Dokumen (Figma 744:7706 & 744:7705) -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700">Dokumen <span class="text-rose-500">*</span></label>

                <!-- 1. Daftar Berkas Lama (Figma 744:7706 / Frame 380) -->
                <template x-if="revisiExistingFiles.length > 0">
                    <div class="space-y-2 mb-2">
                        <template x-for="(ef, eIdx) in revisiExistingFiles" :key="ef.id">
                            <div class="h-[70px] bg-[#f4f4f4] rounded-[6px] px-4 flex items-center justify-between gap-3 border border-slate-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-9 rounded bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs">
                                        <svg class="size-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-black truncate" x-text="ef.name"></span>
                                        <span class="block text-[10px] text-black" x-text="'PDF  •  ' + ef.pageCount + ' halaman  •  ' + ef.formattedSize"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" @click="previewRevisiExistingFile(ef)" 
                                            class="h-[32px] w-[73px] rounded-[6px] bg-[#3b82f6] hover:bg-blue-600 text-white text-[15px] font-medium flex items-center justify-center transition-colors cursor-pointer">
                                        Lihat
                                    </button>
                                    <button type="button" @click="removeExistingRevisiFile(eIdx)" 
                                            class="size-[32px] flex items-center justify-center text-slate-500 hover:text-rose-600 rounded transition-colors cursor-pointer" title="Hapus Berkas Lama">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- 2. Daftar Berkas Baru yang Diunggah -->
                <template x-if="revisiNewFiles.length > 0">
                    <div class="space-y-2 mb-2">
                        <template x-for="(nf, nIdx) in revisiNewFiles" :key="nf.id">
                            <div class="h-[70px] bg-emerald-50/50 rounded-[6px] px-4 flex items-center justify-between gap-3 border border-emerald-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-9 rounded bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-800 truncate" x-text="nf.name"></span>
                                        <span class="block text-[10px] text-slate-500 font-mono" x-text="nf.formattedSize"></span>
                                    </div>
                                </div>
                                <button type="button" @click="removeRevisiNewFile(nIdx)" class="size-[32px] flex items-center justify-center text-slate-400 hover:text-rose-600 rounded transition-colors cursor-pointer" title="Hapus Berkas">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- 3. Dropzone Unggah Berkas Baru (Figma 744:7705 & 744:7706) -->
                <div @click="document.getElementById('input-file-revisi').click()"
                     class="h-[115px] bg-[#ebf5ff] border border-[#3b82f6] rounded-[5px] flex flex-col items-center justify-center text-center cursor-pointer hover:bg-blue-100/50 transition-all select-none">
                    <div class="size-10 rounded-full shadow-[0px_4px_10px_1px_rgba(59,130,246,0.5)] flex items-center justify-center mb-1 bg-gradient-to-br from-[#3b82f6] to-white text-[#3b82f6]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                    </div>
                    <span class="font-bold text-[15px] text-black">Taruh file disini</span>
                    <span class="font-normal text-[8px] sm:text-[10px] text-[#657a7c]">Atau klik untuk memilih file</span>
                    <input type="file" id="input-file-revisi" name="file_kak" accept=".pdf" multiple class="hidden" @change="onRevisiFilesChange($event)">
                </div>
            </div>

            <!-- Footer Buttons (Figma 744:7711 & 744:7714) -->
            <div class="flex items-center justify-between gap-4 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-revisi-permohonan')" 
                        class="h-[48px] w-[185px] bg-white border border-[rgba(59,130,246,0.85)] text-[#3b82f6] hover:bg-blue-50 font-medium text-[18px] rounded-[10px] flex items-center justify-center transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" 
                        :class="(revisiExistingFiles.length > 0 || revisiNewFiles.length > 0) ? 'bg-[#ffa600] text-white shadow-md hover:bg-amber-600' : 'bg-white border border-[#ffa600] text-[#ffa600] hover:bg-amber-50'"
                        class="h-[48px] w-[222px] rounded-[10px] font-medium text-[18px] flex items-center justify-center transition-all cursor-pointer">
                    Revisi Pengajuan
                </button>
            </div>
        </form>
    </x-modal>

    <!-- ========================================================================= -->
    <!-- 7. MODAL 4: HAPUS PENGAJUAN (Figma 744:7604 / Frame 385)                   -->
    <!-- ========================================================================= -->
    <x-modal id="modal-delete-permohonan" title="Hapus Pengajuan?" subtitle="Hanya dapat dihapus apabila belum disetujui oleh pihak manapun." maxWidth="md">
        <form :action="'{{ url('permohonan') }}/' + (hapusTarget?.nomor_surat || hapusTarget?.nomor || hapusTarget?.id || '')" 
              method="POST" 
              class="space-y-4">
            @csrf
            @method('DELETE')

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                <span>Pengajuan yang akan dihapus: </span>
                <strong class="font-mono text-[#213153]" x-text="hapusTarget?.nomor_surat || hapusTarget?.nomor"></strong>
            </div>

            <!-- Input Konfirmasi Password (Figma Frame 15 / Rectangle 10 / Eyes instance) -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Konfirmasi Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <input :type="showHapusPassword ? 'text' : 'password'" 
                           name="password" 
                           x-model="hapusPassword"
                           placeholder="Ulangi password baru"
                           class="w-full h-11 pl-3.5 pr-10 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <button type="button" 
                            @click="toggleHapusPassword()"
                            class="absolute right-3 top-3 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
                            title="Tampilkan / Sembunyikan Password">
                        <template x-if="!showHapusPassword">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </template>
                        <template x-if="showHapusPassword">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Footer Buttons (Figma Rectangle 53 & Rectangle 54) -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-delete-permohonan')" 
                        class="h-11 px-6 rounded-xl border border-slate-300 bg-white text-slate-700 font-bold text-sm hover:bg-slate-50 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" 
                        class="h-11 px-6 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-md shadow-rose-600/20 transition-all cursor-pointer">
                    Hapus Pengajuan
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection
