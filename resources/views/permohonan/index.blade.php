@extends('layouts.app')

@section('title', 'Tracking Pengajuan - SIM-PBJ')

@section('content')
<div x-data="trackingManager()" class="space-y-6">

    <!-- 1. HEADER HALAMAN & TOMBOL AKSES -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <!-- Tombol Kembali (Hanya muncul saat Mode Fullscreen Detail) -->
            <template x-if="viewMode === 'fullscreen'">
                <button @click="viewMode = 'split'" type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-[#0969da] mb-2 transition-colors">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali
                </button>
            </template>

            <h1 class="text-2xl md:text-3xl font-extrabold text-[#213153] tracking-tight">
                <span x-text="viewMode === 'fullscreen' ? 'Detail & Tracking Pengajuan' : 'Tracking Pengajuan'"></span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detailnya.
            </p>
        </div>

        <!-- Tombol Permohonan Baru (Hanya untuk Unit Pengaju / Perencanaan) -->
        @if(in_array(auth()->user()->role ?? '', ['perencanaan', 'user']))
            <div x-show="viewMode === 'split'" class="shrink-0">
                <a href="{{ route('permohonan.create') }}" 
                   class="inline-flex items-center justify-center h-11 px-6 rounded-lg bg-[#0969da] hover:bg-blue-700 text-white font-medium text-sm transition-colors shadow-sm gap-2">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Permohonan Baru
                </a>
            </div>
        @endif

        <!-- Tombol Hapus Pengajuan (Fullscreen mode untuk Pengaju) -->
        @if(in_array(auth()->user()->role ?? '', ['perencanaan', 'user']))
            <div x-show="viewMode === 'fullscreen'" class="shrink-0">
                <button @click="hapusPengajuan()" type="button" class="inline-flex items-center justify-center h-10 px-5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-medium text-sm transition-colors shadow-sm">
                    Hapus Pengajuan
                </button>
            </div>
        @endif
    </div>

    <!-- 2. FILTER BARIS ATAS -->
    <div x-show="viewMode === 'split'" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            
            <!-- Pencarian & Tombol Cari -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <div class="relative w-full">
                    <input type="text" 
                           x-model="searchQuery" 
                           placeholder="Cari nomor surat atau judul pengajuan..." 
                           class="w-full h-10 pl-9 pr-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <svg class="size-4 text-slate-400 absolute left-3 top-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 0 0114 0z" />
                    </svg>
                </div>
                <button @click="applyFilters()" type="button" class="h-10 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-colors flex items-center gap-2 shrink-0 shadow-sm">
                    <svg class="size-4 shrink-0 fill-none stroke-current" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7" />
                        <path stroke-linecap="round" d="M16 16l4.5 4.5" />
                    </svg>
                    <span>Cari</span>
                </button>
            </div>

            <!-- Filter Status -->
            <div>
                <select x-model="filterStatus" class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="Semua">Semua Status</option>
                    <option value="Dalam Proses">Dalam Proses</option>
                    <option value="Selesai">Selesai</option>
                    <option value="Perlu Revisi">Perlu Revisi</option>
                    <option value="Ditolak">Ditolak</option>
                    <option value="Menunggu Persetujuan">Menunggu Persetujuan</option>
                </select>
            </div>

            <!-- Filter Jenis Pengadaan -->
            <div>
                <select x-model="filterJenis" class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="Semua">Semua Jenis</option>
                    <option value="Barang">Barang</option>
                    <option value="Jasa">Jasa</option>
                    <option value="Konstruksi">Konstruksi</option>
                </select>
            </div>

            <!-- Input Tanggal & Tombol Reset -->
            <div class="flex gap-2">
                <input type="date" x-model="filterTanggal" class="w-full h-10 px-3 rounded-lg border border-slate-300 text-xs text-slate-600 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                <button @click="resetFilters()" type="button" class="h-10 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-sm rounded-lg transition-colors flex items-center justify-center shrink-0 shadow-sm" title="Reset Filter">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span class="ml-1.5">Reset</span>
                </button>
            </div>

        </div>
    </div>

    <!-- 3. KONTEN UTAMA: SPLIT VIEW ATAU FULLSCREEN -->
    
    <!-- A. VIEW MODE: FULLSCREEN DETAIL -->
    <template x-if="viewMode === 'fullscreen' && selectedItem">
        <div class="space-y-6">
            
            <!-- 5 Card Informasi Ringkasan -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Nomor Pengajuan</span>
                        <span class="text-sm font-bold text-[#213153]" x-text="selectedItem.nomor"></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Nama Pengadaan</span>
                        <span class="text-sm font-bold text-[#213153] line-clamp-1" x-text="selectedItem.judul"></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Status Saat Ini</span>
                        <span class="text-sm font-bold text-[#0969da]" x-text="selectedItem.status"></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Tanggal Pengajuan</span>
                        <span class="text-sm font-bold text-[#213153]" x-text="selectedItem.tanggal"></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Pemohon</span>
                        <span class="text-sm font-bold text-[#213153]" x-text="selectedItem.penanggungJawab || 'Unit Pengaju'"></span>
                    </div>
                </div>
            </div>

            <!-- Stepper 8 Tahap Lebar dengan Label Teks -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-6">
                <h3 class="font-bold text-lg text-[#213153]" x-text="'Tahap ' + selectedItem.currentTahap + ' dari 8'"></h3>
                
                <div class="relative flex items-center justify-between overflow-x-auto pb-2">
                    <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                    <div class="absolute top-1/2 left-0 h-1 bg-[#0969da] -translate-y-1/2 z-0 transition-all duration-300" :style="'width: ' + ((selectedItem.currentTahap - 1) / 7 * 100) + '%'"></div>

                    <template x-for="(tahap, idx) in tahapanList" :key="idx">
                        <div class="relative z-10 flex flex-col items-center group cursor-pointer shrink-0 min-w-[80px]" @click="selectedItem.currentTahap = idx + 1">
                            <div class="size-11 rounded-full flex items-center justify-center font-bold text-base transition-all border-2"
                                 :class="idx + 1 <= selectedItem.currentTahap ? 'bg-[#0969da] text-white border-[#0969da] shadow-md shadow-[#0969da]/30' : 'bg-white text-slate-400 border-slate-300'">
                                <span x-text="idx + 1"></span>
                            </div>
                            <span class="text-[11px] font-semibold text-center mt-2 max-w-[100px] line-clamp-2"
                                  :class="idx + 1 <= selectedItem.currentTahap ? 'text-[#0969da]' : 'text-slate-400'"
                                  x-text="tahap"></span>
                        </div>
                    </template>
                </div>
            </div>

          <!-- TWO COLUMNS BOTTOM (DOKUMEN + FORM AKSI DI KIRI | TIMELINE DI KANAN) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    
    <!-- KOLOM KIRI (6/12): SURAT DOKUMEN & FORM AKSI ROLE -->
    <div class="lg:col-span-6 space-y-6">
        
        <!-- CARD SURAT DAN DOKUMEN -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-base text-[#213153]">Surat dan Dokumen</h3>
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-[#0969da]">1 Dokumen</span>
            </div>

            <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                <div class="size-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs">
                    PDF
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-xs text-[#213153] truncate" x-text="selectedItem ? selectedItem.judul : 'Pengadaan'"></div>
                    <div class="text-[11px] text-slate-500 font-medium" x-text="selectedItem ? selectedItem.nomor + '.pdf' : 'dokumen.pdf'"></div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button @click="showModalDoc = true" type="button" class="px-3 py-1.5 text-xs font-bold text-[#0969da] border border-[#0969da] bg-white rounded-lg hover:bg-blue-50 transition-colors">
                        Lihat
                    </button>
                </div>
            </div>
        </div>

        <!-- FORM AKSI INTERAKTIF KHUSUS ROLE -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm">
            @include('permohonan.partials.role-actions-show')
        </div>

    </div>

    <!-- KOLOM KANAN (6/12): RIWAYAT KRONOLOGIS TIMELINE -->
    <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
            <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="font-bold text-[#213153] text-base">Riwayat Kronologis dan Revisi</h3>
        </div>

        <div class="relative pl-6 border-l-2 border-slate-200 space-y-5 pt-1">
            <template x-for="log in selectedItem.timeline" :key="log.id">
                <div class="relative">
                    <div :class="{
                        'bg-amber-500 text-white': log.type === 'warning',
                        'bg-rose-500 text-white': log.type === 'danger',
                        'bg-[#0969da] text-white': log.type === 'info',
                        'bg-emerald-500 text-white': log.type === 'success',
                        'bg-slate-400 text-white': log.type === 'gray'
                    }" class="absolute -left-[35px] top-0 size-7 rounded-full flex items-center justify-center shadow-sm">
                        <template x-if="log.type === 'warning'"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></template>
                        <template x-if="log.type === 'danger'"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></template>
                        <template x-if="log.type === 'info' || log.type === 'success' || log.type === 'gray'"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></template>
                    </div>

                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 flex items-start justify-between gap-3">
                        <div>
                            <span class="text-[10px] font-semibold text-slate-400 block" x-text="log.time"></span>
                            <h4 class="font-bold text-[#213153] text-xs mt-0.5" x-text="log.title"></h4>
                            <p class="text-[11px] text-slate-500 mt-0.5" x-text="log.subtitle"></p>
                        </div>
                        <span :class="{
                            'bg-amber-100 text-amber-700': log.badge_type === 'warning',
                            'bg-rose-100 text-rose-700': log.badge_type === 'danger',
                            'bg-blue-100 text-blue-700': log.badge_type === 'info',
                            'bg-emerald-100 text-emerald-700': log.badge_type === 'success',
                            'bg-slate-100 text-slate-700': log.badge_type === 'gray'
                        }" class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0" x-text="log.badge"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>

        </div>
    </template>

    <!-- B. VIEW MODE: SPLIT VIEW (FRAME 1, 2, & 3) -->
    <template x-if="viewMode === 'split'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- PANEL KIRI: DAFTAR PENGAJUAN -->
            <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-bold text-base text-[#213153]">Daftar Pengajuan</h2>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-[#0969da]" x-text="filteredList.length + ' pengajuan'"></span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 max-h-[720px] overflow-y-auto">
                    <template x-for="item in filteredList" :key="item.id">
                        <div @click="selectItem(item)" 
                             class="p-4 flex items-center gap-3 cursor-pointer transition-all hover:bg-slate-50"
                             :class="selectedItem && selectedItem.id === item.id ? 'bg-blue-50/60 border-l-4 border-l-[#0969da]' : ''">
                            
                            <div class="size-10 rounded-full flex items-center justify-center shrink-0 shadow-sm"
                                 :class="getStatusBadgeClass(item.status).circle">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-html="getStatusBadgeClass(item.status).icon"></svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-bold text-sm text-[#213153] truncate" x-text="item.judul"></h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0" 
                                          :class="getStatusBadgeClass(item.status).pill" 
                                          x-text="item.status"></span>
                                </div>
                                <div class="text-xs font-semibold text-slate-400 mt-0.5" x-text="item.nomor"></div>
                                <div class="text-[10px] text-slate-400 mt-1" x-text="item.tanggal"></div>
                            </div>

                            <svg class="size-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </template>
                </div>
            </div>

            <!-- PANEL KANAN: DETAIL DISAMPING (SPLIT VIEW) -->
            <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 min-h-[600px] flex flex-col justify-between">
                
                <!-- 1. EMPTY STATE SAMA PERSIS FIGMA (FRAME 1) -->
                <template x-if="!selectedItem">
                    <div class="my-auto flex flex-col items-center justify-center text-center py-10 px-4">
                        <div class="w-40 h-40 mb-6 flex items-center justify-center shrink-0">
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

                <!-- 2. TERPILIH ITEM (FRAME 2 & 3 USER) -->
                <template x-if="selectedItem">
                    <div class="space-y-6">
                        
                        <!-- Header Tracking & Link Fullscreen -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="font-bold text-lg text-[#213153]" x-text="selectedItem.judul"></h3>
                                <div class="text-xs text-slate-400 mt-0.5" x-text="selectedItem.nomor"></div>
                            </div>
                            <button @click="viewMode = 'fullscreen'" type="button" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0969da] hover:underline">
                                Lihat Detail
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>

                        <!-- Stepper 8 Tahap Ringkas Circle (Frame 2 & 3) -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="flex items-center justify-between relative">
                                <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                                <div class="absolute top-1/2 left-0 h-1 bg-[#0969da] -translate-y-1/2 z-0 transition-all duration-300" :style="'width: ' + ((selectedItem.currentTahap - 1) / 7 * 100) + '%'"></div>

                                <template x-for="n in 8" :key="n">
                                    <div class="relative z-10 size-9 rounded-full flex items-center justify-center font-bold text-xs border-2 transition-all"
                                         :class="n <= selectedItem.currentTahap ? 'bg-[#0969da] text-white border-[#0969da]' : 'bg-white text-slate-400 border-slate-300'"
                                         x-text="n"></div>
                                </template>
                            </div>
                        </div>

                        <!-- Timeline Kronologis & Revisi (Mode Split User) -->
                        <div class="space-y-4 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h4 class="font-bold text-sm text-[#213153]">Riwayat Kronologis dan Revisi</h4>
                            </div>

                            <div class="relative pl-6 border-l-2 border-slate-200 space-y-4 max-h-[350px] overflow-y-auto">
                                <template x-for="log in selectedItem.timeline" :key="log.id">
                                    <div class="relative">
                                        <div :class="{
                                            'bg-amber-500 text-white': log.type === 'warning',
                                            'bg-rose-500 text-white': log.type === 'danger',
                                            'bg-[#0969da] text-white': log.type === 'info',
                                            'bg-emerald-500 text-white': log.type === 'success',
                                            'bg-slate-400 text-white': log.type === 'gray'
                                        }" class="absolute -left-[35px] top-0 size-7 rounded-full flex items-center justify-center shadow-sm">
                                            <template x-if="log.type === 'warning'"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></template>
                                            <template x-if="log.type === 'danger'"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                            <template x-if="log.type === 'info' || log.type === 'success' || log.type === 'gray'"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></template>
                                        </div>

                                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex items-start justify-between gap-3">
                                            <div>
                                                <span class="text-[10px] font-semibold text-slate-400 block" x-text="log.time"></span>
                                                <h5 class="font-bold text-[#213153] text-xs mt-0.5" x-text="log.title"></h5>
                                                <p class="text-[11px] text-slate-500 mt-0.5" x-text="log.subtitle"></p>
                                            </div>
                                            <span :class="{
                                                'bg-amber-100 text-amber-700': log.badge_type === 'warning',
                                                'bg-rose-100 text-rose-700': log.badge_type === 'danger',
                                                'bg-blue-100 text-blue-700': log.badge_type === 'info',
                                                'bg-emerald-100 text-emerald-700': log.badge_type === 'success',
                                                'bg-slate-100 text-slate-700': log.badge_type === 'gray'
                                            }" class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0" x-text="log.badge"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                    </div>
                </template>

             <!-- Banner Widget Khusus Role di Bagian Bawah Split View (Hanya muncul jika item terpilih) -->
<template x-if="selectedItem">
    <div class="mt-4 pt-4 border-t border-slate-100">
        @include('permohonan.partials.role-actions-index')
    </div>
</template>

            </div>

        </div>
    </template>

    <!-- MODAL POPUP PREVIEW DOKUMEN -->
    <div x-show="showModalDoc" 
         x-cloak
         @click.away="showModalDoc = false" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <h3 class="font-bold text-lg text-[#213153]">Surat Pengajuan Pengadaan</h3>
            <p class="text-xs text-slate-500" x-text="selectedItem ? selectedItem.nomor + '.pdf' : ''"></p>
            <div class="p-8 bg-slate-100 rounded-xl flex items-center justify-center border border-dashed border-slate-300 text-slate-400 text-xs">
                [ Preview PDF Document ]
            </div>
            <div class="flex justify-end">
                <button @click="showModalDoc = false" type="button" class="px-4 py-2 bg-[#0969da] text-white font-bold text-xs rounded-lg">
                    Tutup
                </button>
            </div>
        </div>
    </div>

</div>

<!-- SCRIPT ALPINE.JS MANAGEMENT STATE (DINAMIS DENGAN LARAVEL DATA) -->
<script>
    function trackingManager() {
        return {
            viewMode: 'split',
            selectedItem: null, // Null secara default untuk menampilkan Empty State (Frame 1)
            searchQuery: '',
            filterStatus: 'Semua',
            filterJenis: 'Semua',
            filterTanggal: '',
            showModalDoc: false,

            tahapanList: [
                'Pengajuan',
                'Persetujuan 1',
                'Penyusunan Dokumen & Level Anggaran',
                'Persetujuan 2 (Kondisional)',
                'Pembelian & Surat Kontrak',
                'Penerimaan Barang/Jasa',
                'Pembayaran',
                'Selesai'
            ],

            // Konversi data Laravel $applications secara dinamis ke objek Alpine.js
            items: [
                @foreach($applications as $app)
                {
                    id: '{{ $app->id_permohonan ?? $app->nomor_surat ?? $app->id }}',
                    nomor: '{{ $app->nomor_surat ?? $app->number ?? 'PBJ-2026-0000' }}',
                    judul: '{{ addslashes($app->nama_pengadaan ?? $app->title ?? 'Pengadaan') }}',
                    jenis: '{{ $app->jenis_pengadaan ?? $app->type ?? 'Barang' }}',
                    status: '{{ $app->status ?? 'Dalam Proses' }}',
                    tanggal: '{{ isset($app->tanggal_pengajuan) ? \Carbon\Carbon::parse($app->tanggal_pengajuan)->format('d M Y, H:i') : '' }}',
                    currentTahap: {{ $app->tahap_saat_ini ?? 1 }},
                    penanggungJawab: '{{ $app->pemohon ?? $app->unit_pengaju ?? 'Unit Pengaju' }}',
                    timeline: [
                        @if(isset($app->histories) && count($app->histories) > 0)
                            @foreach($app->histories as $hist)
                            {
                                id: {{ $loop->iteration }},
                                time: '{{ \Carbon\Carbon::parse($hist->created_at)->format('d M Y - H:i') }}',
                                title: '{{ addslashes($hist->status_title ?? $hist->keterangan) }}',
                                subtitle: '{{ addslashes($hist->keterangan ?? '') }}',
                                badge: '{{ $hist->status ?? 'Informasi' }}',
                                type: '{{ strtolower($hist->status) === 'disetujui' ? 'success' : (strtolower($hist->status) === 'ditolak' ? 'danger' : 'info') }}',
                                badge_type: '{{ strtolower($hist->status) === 'disetujui' ? 'success' : (strtolower($hist->status) === 'ditolak' ? 'danger' : 'info') }}'
                            },
                            @endforeach
                        @else
                            {
                                id: 1,
                                time: '{{ isset($app->tanggal_pengajuan) ? \Carbon\Carbon::parse($app->tanggal_pengajuan)->format('d M Y - H:i') : '' }}',
                                title: 'Pengajuan Berhasil Dibuat',
                                subtitle: 'Form telah disampaikan ke verifikator',
                                badge: 'Informasi',
                                type: 'info',
                                badge_type: 'info'
                            }
                        @endif
                    ]
                },
                @endforeach
            ],

            get filteredList() {
                return this.items.filter(item => {
                    const matchSearch = item.judul.toLowerCase().includes(this.searchQuery.toLowerCase()) || 
                                        item.nomor.toLowerCase().includes(this.searchQuery.toLowerCase());
                    const matchStatus = this.filterStatus === 'Semua' || item.status === this.filterStatus;
                    const matchJenis = this.filterJenis === 'Semua' || item.jenis === this.filterJenis;
                    return matchSearch && matchStatus && matchJenis;
                });
            },

            selectItem(item) {
                this.selectedItem = item;
            },

            resetFilters() {
                this.searchQuery = '';
                this.filterStatus = 'Semua';
                this.filterJenis = 'Semua';
                this.filterTanggal = '';
            },

            applyFilters() {},

            hapusPengajuan() {
                if (confirm('Apakah Anda yakin ingin menghapus pengajuan ini?')) {
                    alert('Pengajuan berhasil dihapus.');
                    this.selectedItem = null;
                    this.viewMode = 'split';
                }
            },

            getStatusBadgeClass(status) {
                switch(status) {
                    case 'Selesai':
                    case 'Disetujui':
                    case 'disetujui':
                        return {
                            circle: 'bg-emerald-100 text-emerald-600',
                            pill: 'bg-emerald-100 text-emerald-700',
                            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>'
                        };
                    case 'Ditolak':
                    case 'ditolak':
                        return {
                            circle: 'bg-rose-100 text-rose-600',
                            pill: 'bg-rose-100 text-rose-700',
                            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                        };
                    case 'Perlu Revisi':
                    case 'Menunggu Persetujuan':
                        return {
                            circle: 'bg-amber-100 text-amber-600',
                            pill: 'bg-amber-100 text-amber-700',
                            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                        };
                    default:
                        return {
                            circle: 'bg-blue-100 text-[#0969da]',
                            pill: 'bg-blue-100 text-[#0969da]',
                            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 01-2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'
                        };
                }
            }
        };
    }
</script>
@endsection