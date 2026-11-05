@extends('layouts.app')

@section('title', 'Tracking Pengajuan - SIM-PBJ')

@section('content')
<div x-data="trackingManager()" class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- 1. HEADER SECTION -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <!-- Tombol Kembali Mode Fullscreen (dengan pembungkus mb-4) -->
            <div x-show="viewMode === 'fullscreen'" class="mb-4">
                <button @click="viewMode = 'split'" type="button" 
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Kembali ke Daftar Pengajuan</span>
                </button>
            </div>

            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]"
                x-text="viewMode === 'fullscreen' ? 'Detail & Tracking Pengajuan' : 'Tracking Pengajuan'">
                Tracking Pengajuan
            </h1>
            <p class="mt-1 text-sm text-slate-500"
               x-text="viewMode === 'fullscreen' 
                    ? 'Pantau status, dokumen, dan riwayat proses pengajuan ini.' 
                    : 'Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detail pelacakan 8 siklus.'">
                Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detail pelacakan 8 siklus.
            </p>
        </div>

        <!-- AREA TOMBOL KANAN ATAS -->
        <div class="flex items-center gap-3">
            {{-- 1. Tombol Permohonan Baru (Logic PHP fullstuck + UI Frontend-pemula) --}}
            @if(! Auth::user()->hasRole('admin') && (Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create'])))
                <button x-show="viewMode !== 'fullscreen'" 
                        @click="openModalCreate()" 
                        data-action="openCreateModal()" 
                        type="button" 
                        class="inline-flex items-center justify-center h-10 px-5 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs transition-all shadow-sm gap-2 cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Permohonan Baru</span>
                </button>
            @endif

            {{-- 2. Tombol Hapus / Batalkan (Tampil saat mode Fullscreen Detail) --}}
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
                <template x-if="viewMode === 'fullscreen' && selectedItem && ['draft', 'diajukan', 'ditolak'].includes(String(selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
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

    <!-- 2. SECTION FILTER & PENCARIAN (Style Frontend-pemula + Koneksi Form Backend fullstuck) -->
    <form method="GET" action="{{ route('permohonan.index') }}" x-show="viewMode === 'split'" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">

            <!-- Pencarian -->
            <div class="lg:col-span-4 flex items-center gap-2">
                <div class="relative w-full">
                    <svg class="size-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" 
                           id="search-input"
                           name="q" 
                           value="{{ request('q') }}"
                           x-model="searchQuery"
                           placeholder="Cari nomor surat atau judul..." 
                           class="w-full h-10 pl-9 pr-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                </div>
            </div>

            <!-- Filter Status -->
            <div class="lg:col-span-3 sm:col-span-1">
                <select id="status-filter" 
                        name="status" 
                        onchange="this.form.submit()" 
                        class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="">Semua Status</option>
                    <option value="diajukan" {{ request('status') == 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="reviu_paralel" {{ request('status') == 'reviu_paralel' ? 'selected' : '' }}>Reviu Paralel (B2/MAK)</option>
                    <option value="disetujui" {{ request('status') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="perlu_revisi" {{ request('status') == 'perlu_revisi' ? 'selected' : '' }}>Perlu Revisi</option>
                    <option value="ditolak" {{ request('status') == 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            <!-- Filter Jenis -->
            <div class="lg:col-span-2 sm:col-span-1">
                <select id="jenis-filter" 
                        name="jenis" 
                        onchange="this.form.submit()" 
                        class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="">Semua Jenis</option>
                    <option value="barang" {{ request('jenis') == 'barang' ? 'selected' : '' }}>Barang</option>
                    <option value="jasa" {{ request('jenis') == 'jasa' ? 'selected' : '' }}>Jasa</option>
                    <option value="konstruksi" {{ request('jenis') == 'konstruksi' ? 'selected' : '' }}>Konstruksi</option>
                </select>
            </div>

            <!-- Tanggal + Tombol Cari & Reset -->
            <div class="lg:col-span-3 flex items-center gap-2">
                <input type="date" 
                       name="tanggal"
                       value="{{ request('tanggal') }}"
                       x-model="filterTanggal"
                       onchange="this.form.submit()"
                       class="w-full h-10 px-2.5 rounded-lg border border-slate-300 text-xs text-slate-600 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">

                <button type="submit" 
                        title="Terapkan Filter"
                        class="h-10 px-3.5 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1 shrink-0 shadow-sm cursor-pointer">
                    <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/>
                        <path stroke-linecap="round" d="M16 16l4.5 4.5"/>
                    </svg>
                    <span>Cari</span>
                </button>

                <a href="{{ route('permohonan.index') }}" 
                   title="Reset Filter"
                   class="h-10 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1 shrink-0 cursor-pointer">
                    <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Reset</span>
                </a>
            </div>

        </div>
    </form>

    <!-- 3. KONTEN UTAMA -->

    <!-- ==================== A. MODE FULLSCREEN (Figma Style Frontend-pemula) ==================== -->
    <template x-if="viewMode === 'fullscreen' && selectedItem">
        <div class="space-y-6">

            <!-- 5 Kartu Ringkasan -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                
                <!-- 1. Nomor Pengajuan -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Nomor Pengajuan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate font-mono" x-text="selectedItem.nomor" :title="selectedItem.nomor"></span>
                    </div>
                </div>

                <!-- 2. Nama Pengadaan -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Nama Pengadaan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.judul" :title="selectedItem.judul"></span>
                    </div>
                </div>

                <!-- 3. Status Saat Ini -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Status Saat Ini</span>
                        <span class="inline-flex mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                              :class="statusMeta(selectedItem.statusRaw).pill"
                              x-text="statusMeta(selectedItem.statusRaw).label"></span>
                    </div>
                </div>

                <!-- 4. Tanggal Pengajuan -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Tanggal Pengajuan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.tanggal"></span>
                    </div>
                </div>

                <!-- 5. Unit Pemohon -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Pemohon / Unit</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.pemohon" :title="selectedItem.pemohon"></span>
                    </div>
                </div>
            </div>

            <!-- Stepper 8 Tahap (Frontend-pemula) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-lg text-[#213153]" x-text="'Tahap ' + (selectedItem.currentTahap || 1) + ' dari 8'"></h3>
                    <span class="text-xs font-bold text-[#0969da] uppercase tracking-wider" x-text="tahapanList[(selectedItem.currentTahap || 1) - 1]"></span>
                </div>

                <div class="overflow-x-auto pb-2">
                    <div class="relative flex items-start justify-between min-w-[780px]">
                        <!-- Garis penghubung -->
                        <div class="absolute left-12 right-12 top-5 h-1 rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-[#0969da] transition-all duration-300"
                                 :style="'width: ' + stepPercent() + '%'"></div>
                        </div>

                        <template x-for="(tahap, idx) in tahapanList" :key="idx">
                            <div class="relative z-10 flex w-24 shrink-0 flex-col items-center">
                                <div class="size-11 rounded-full flex items-center justify-center font-semibold text-base border-2 transition-all"
                                     :class="idx + 1 <= (selectedItem.currentTahap || 1)
                                        ? 'bg-[#0969da] text-white border-[#0969da] shadow-md shadow-[#0969da]/30'
                                        : 'bg-white text-slate-400 border-slate-300'">
                                    <span x-text="idx + 1"></span>
                                </div>
                                <span class="mt-2 text-[11px] font-medium text-center leading-tight"
                                      :class="idx + 1 <= (selectedItem.currentTahap || 1) ? 'text-[#0969da] font-bold' : 'text-slate-400'"
                                      x-text="tahap"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Dua Kolom Bawah (Dokumen & Timeline) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- KIRI (6 COLS): Surat, Dokumen & Tindakan Role -->
                <div class="lg:col-span-6 space-y-6">

                    <!-- Kartu Surat dan Dokumen -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-semibold text-base text-[#213153]">Surat dan Dokumen</h3>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#0969da]">1 Dokumen</span>
                        </div>

                        <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                            <div class="size-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 font-semibold text-xs">PDF</div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-xs text-[#213153] truncate" x-text="selectedItem.judul"></div>
                                <div class="text-[11px] text-slate-500 truncate" x-text="selectedItem.fileName || 'Surat_Permohonan.pdf'"></div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="pdfUrl(selectedItem)" target="_blank" rel="noopener" title="Buka di tab baru"
                                   class="size-8 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-[#0969da] hover:border-[#0969da] transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                                <button @click="showModalDoc = true" type="button"
                                        class="h-8 px-3 text-xs font-semibold text-[#0969da] border border-[#0969da] bg-white rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
                                    Lihat
                                </button>
                            </div>
                        </div>

                        <!-- Ringkasan Anggaran Usulan (Logic Backend fullstuck) -->
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

                    <!-- Tindakan Cepat Role (Partial / Fallback Tombol fullstuck) -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-3">
                        <h4 class="font-semibold text-xs uppercase tracking-wider text-slate-400">Aksi &amp; Keputusan</h4>
                        
                        @includeIf('permohonan.partials.role-actions-show')

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.nomor || selectedItem.id)" 
                               class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                                <span>Buka Halaman Detail</span>
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>

                            @if(!Auth::user()->hasRole('admin'))
                                <template x-if="['ditolak', 'revisi', 'perlu_revisi'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
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

                <!-- KANAN (6 COLS): Timeline Riwayat & Catatan Revisi -->
                <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="font-semibold text-[#213153] text-base">Riwayat Kronologis dan Revisi</h3>
                    </div>

                    <!-- Catatan Penolakan/Revisi jika ada -->
                    <template x-if="selectedItem.alasan_revisi">
                        <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                            <div class="size-2.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></div>
                            <div class="text-xs space-y-0.5">
                                <div class="font-bold text-amber-900">Catatan Revisi / Telaah:</div>
                                <div class="text-amber-800" x-text="selectedItem.alasan_revisi"></div>
                            </div>
                        </div>
                    </template>

                    <!-- Timeline Loop -->
                    <div class="pl-3">
                        <div class="relative pl-6 border-l-2 border-slate-200 space-y-5 pt-1">
                            <template x-for="log in (selectedItem.timeline || generateTimeline(selectedItem))" :key="log.id">
                                <div class="relative">
                                    <div :class="timelineDot(log.type)" class="absolute -left-[35px] top-0 size-7 rounded-full flex items-center justify-center shadow-sm">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="timelineIcon(log.type)"></svg>
                                    </div>

                                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="text-[10px] font-medium text-slate-400 block" x-text="log.time"></span>
                                            <h4 class="font-semibold text-[#213153] text-xs mt-0.5" x-text="log.title"></h4>
                                            <p class="text-[11px] text-slate-500 mt-0.5 break-words" x-text="log.subtitle"></p>
                                        </div>
                                        <span :class="badgeClass(log.badge_type)" class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0" x-text="log.badge"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- ==================== B. MODE SPLIT (Daftar Kiri & Tracking Kanan) ==================== -->
    <template x-if="viewMode === 'split'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- PANEL KIRI (5 COLS): DAFTAR PENGAJUAN (Server Eloquent fullstuck + Style Frontend-pemula) -->
            <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-semibold text-base text-[#213153]">Daftar Pengajuan</h2>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-[#0969da]">
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
                                'tujuan_pengadaan_ringkas' => $item->tujuan_pengadaan_ringkas ?? null,
                                'currentTahap' => 1,
                                'fileName' => $item->file_surat_permohonan ? basename($item->file_surat_permohonan) : 'Surat_Permohonan.pdf',
                                'id_user' => $item->id_user ?? $item->user_id ?? null,
                            ];
                        @endphp
                        <div @click="selectItem({{ json_encode($normalizedItem) }})"
                             class="p-4 flex items-center gap-3 cursor-pointer transition-all hover:bg-slate-50"
                             :class="selectedItem && (selectedItem.nomor === '{{ $item->nomor_surat }}' || selectedItem.id === {{ $item->id }}) ? 'bg-blue-50/60 shadow-[inset_4px_0_0_0_#0969da]' : ''">

                            <!-- Status Circle Icon -->
                            <div class="size-10 rounded-full flex items-center justify-center shrink-0"
                                 :class="statusMeta('{{ $item->status_permohonan ?? $item->status }}').circle">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-html="statusMeta('{{ $item->status_permohonan ?? $item->status }}').icon"></svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-sm text-[#213153] truncate">{{ $item->perihal ?? $item->judul_pengadaan }}</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 whitespace-nowrap {{ strtolower($item->status_permohonan ?? $item->status) === 'disetujui' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-[#0969da]' }}">
                                        {{ ucfirst(str_replace('_', ' ', $item->status_permohonan ?? $item->status ?? 'Diajukan')) }}
                                    </span>
                                </div>
                                <div class="text-xs font-mono font-medium text-slate-400 mt-0.5 truncate">{{ $item->nomor_surat }}</div>
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

            <!-- PANEL KANAN (7 COLS): DETAIL SINGKAT ATAU EMPTY STATE -->
            <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 min-h-[600px] flex flex-col justify-between">

                <!-- Empty State (Frontend-pemula SVG & Typography) -->
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
                        <h3 class="font-bold text-2xl text-[#213153] tracking-tight">Pilih Pengajuan untuk Melihat Detail</h3>
                        <p class="text-sm text-slate-500 max-w-md mt-2 leading-relaxed">
                            Silakan pilih salah satu pengajuan dari daftar sebelah kiri untuk melihat informasi lengkap, dokumen, dan riwayat prosesnya.
                        </p>
                    </div>
                </template>

                <!-- Konten Terpilih (Split View Kanan) -->
                <template x-if="selectedItem">
                    <div class="space-y-6">

                        <!-- Header Item Terpilih -->
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-lg text-[#213153] truncate" x-text="selectedItem.judul"></h3>
                                <div class="text-xs font-mono font-medium text-slate-400 mt-0.5 truncate" x-text="selectedItem.nomor"></div>
                            </div>
                            <button @click="viewMode = 'fullscreen'" type="button"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#0969da] hover:underline shrink-0 cursor-pointer">
                                <span>Lihat Detail Penuh</span>
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>

                        <!-- Stepper Ringkas 8 Siklus -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tahap Siklus</span>
                                <span class="text-xs font-bold text-[#0969da]" x-text="'Tahap ' + (selectedItem.currentTahap || 1) + ' dari 8'"></span>
                            </div>
                            <div class="flex items-center justify-between relative py-2">
                                <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                                <div class="absolute top-1/2 left-0 h-1 bg-[#0969da] -translate-y-1/2 z-0 transition-all duration-300" :style="'width: ' + stepPercent() + '%'"></div>

                                <template x-for="n in 8" :key="n">
                                    <div class="relative z-10 size-8 rounded-full flex items-center justify-center font-bold text-xs border-2 transition-all"
                                         :class="n <= (selectedItem.currentTahap || 1) ? 'bg-[#0969da] text-white border-[#0969da]' : 'bg-white text-slate-400 border-slate-300'"
                                         x-text="n"></div>
                                </template>
                            </div>
                        </div>

                        <!-- Riwayat Singkat Kronologis -->
                        <div class="space-y-4 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h4 class="font-semibold text-sm text-[#213153]">Riwayat Kronologis &amp; Catatan</h4>
                            </div>

                            <div class="max-h-[320px] overflow-y-auto pl-3 pr-1">
                                <div class="relative pl-6 border-l-2 border-slate-200 space-y-4">
                                    <template x-for="log in (selectedItem.timeline || generateTimeline(selectedItem))" :key="log.id">
                                        <div class="relative">
                                            <div :class="timelineDot(log.type)" class="absolute -left-[35px] top-0 size-7 rounded-full flex items-center justify-center shadow-sm">
                                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="timelineIcon(log.type)"></svg>
                                            </div>

                                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <span class="text-[10px] font-medium text-slate-400 block" x-text="log.time"></span>
                                                    <h5 class="font-semibold text-[#213153] text-xs mt-0.5" x-text="log.title"></h5>
                                                    <p class="text-[11px] text-slate-500 mt-0.5 break-words" x-text="log.subtitle"></p>
                                                </div>
                                                <span :class="badgeClass(log.badge_type)" class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0" x-text="log.badge"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>

                <!-- Tombol Aksi Bawah Split Panel -->
                <template x-if="selectedItem">
                    <div class="mt-6 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.nomor || selectedItem.id)" 
                               class="h-9 px-4 inline-flex items-center rounded-lg bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors">
                                Detail Penuh
                            </a>
                            <a :href="pdfUrl(selectedItem)" target="_blank" rel="noopener"
                               class="h-9 px-4 inline-flex items-center rounded-lg border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors">
                                Unduh PDF
                            </a>
                        </div>

                        <div class="flex items-center gap-2">
                            @if(!Auth::user()->hasRole('admin'))
                                <template x-if="['ditolak', 'revisi', 'perlu_revisi'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
                                    <button type="button" 
                                            @click="openRevisiModal(selectedItem)"
                                            class="h-9 px-4 inline-flex items-center rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-colors cursor-pointer">
                                        Revisi
                                    </button>
                                </template>
                            @endif

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

    <!-- 4. MODAL PREVIEW DOKUMEN PDF (Frontend-pemula) -->
    <div x-show="showModalDoc" x-cloak x-transition.opacity
         @click.self="showModalDoc = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl flex flex-col max-h-[92vh]">
            <div class="flex items-start justify-between gap-4 p-5 border-b border-slate-100">
                <div class="min-w-0">
                    <h3 class="font-semibold text-lg text-[#213153]">Surat Pengajuan Pengadaan</h3>
                    <p class="text-xs text-slate-500 truncate" x-text="selectedItem ? (selectedItem.fileName || selectedItem.nomor) : ''"></p>
                </div>
                <button @click="showModalDoc = false" type="button" class="text-slate-400 hover:text-slate-700 transition-colors" title="Tutup">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 flex-1 min-h-0">
                <template x-if="showModalDoc && selectedItem">
                    <iframe :src="pdfUrl(selectedItem)" class="w-full h-[65vh] rounded-xl border border-slate-200 bg-slate-100"></iframe>
                </template>
            </div>

            <div class="flex items-center justify-end gap-2 p-5 border-t border-slate-100">
                <a :href="selectedItem ? pdfUrl(selectedItem) : '#'" target="_blank" rel="noopener"
                   class="h-10 px-4 inline-flex items-center rounded-lg border border-slate-300 bg-white text-slate-700 font-semibold text-sm hover:bg-slate-50 transition-colors">
                    Buka di Tab Baru
                </a>
                <button @click="showModalDoc = false" type="button"
                        class="h-10 px-5 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-sm rounded-lg transition-colors cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- 5. MODAL FORM: BUAT USULAN BARU (Hanya untuk non-admin) -->
    @if(! Auth::user()->hasRole('admin'))
    <x-modal id="modal-create-permohonan" title="Buat Usulan Permohonan Pengadaan" subtitle="Pendaftaran berkas naskah dinas Srikandi baru" maxWidth="3xl">
        <form action="{{ route('permohonan.store') }}" 
              method="POST" 
              enctype="multipart/form-data" 
              x-data="{ nominalModal: '' }"
              class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Nomor Surat Srikandi <span class="text-rose-500">*</span></label>
                    <input type="text" name="nomor_surat" required placeholder="Contoh: B/123/PL3/TIK/2026" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Unit Pengusul <span class="text-rose-500">*</span></label>
                    <select name="kode_unit" required 
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                        @if(isset($units))
                            @foreach($units as $u)
                                <option value="{{ $u->kode_unit }}" {{ Auth::user()->kode_unit === $u->kode_unit ? 'selected' : '' }}>
                                    [{{ $u->kode_unit }}] {{ $u->nama_unit }}
                                </option>
                            @endforeach
                        @else
                            <option value="{{ Auth::user()->kode_unit ?? 'UTAMA' }}">{{ Auth::user()->kode_unit ?? 'Unit Pengusul Utama' }}</option>
                        @endif
                    </select>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Perihal / Judul Kegiatan <span class="text-rose-500">*</span></label>
                    <input type="text" name="perihal" required placeholder="Contoh: Pengadaan Fasilitas Laboratorium Cloud AI" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-700">Estimasi Pagu Anggaran (Rp) <span class="text-rose-500">*</span></label>
                        <span class="text-xs font-bold text-[#0969da] font-mono" x-text="nominalModal ? 'Rp ' + Number(nominalModal).toLocaleString('id-ID') : 'Rp 0'"></span>
                    </div>
                    <input type="number" name="anggaran_diajukan" x-model="nominalModal" step="0.01" min="1" required placeholder="Contoh: 150000000" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Tanggal Usulan <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_pengajuan" value="{{ date('Y-m-d') }}" required 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Uraian Ringkas Tujuan</label>
                    <textarea name="tujuan_pengadaan_ringkas" rows="2" placeholder="Uraian singkat justifikasi pengadaan..." 
                              class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Unggah Berkas Lampiran (KAK / Spesifikasi - PDF Maks. 10 MB)</label>
                    <input type="file" name="file_surat_permohonan" accept=".pdf" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-600 focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-create-permohonan')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-[#0969da] px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20 transition-all cursor-pointer">
                    Kirim Usulan Pengadaan
                </button>
            </div>
        </form>
    </x-modal>
    @endif

    <!-- 6. MODAL FORM: REVISI USULAN (fullstuck) -->
    <x-modal id="modal-revisi-permohonan" title="Revisi Usulan Pengadaan" subtitle="Perbaiki usulan berdasarkan catatan telaah verifikator" maxWidth="2xl">
        <form action="{{ route('permohonan.revisi') }}" 
              method="POST" 
              enctype="multipart/form-data" 
              class="space-y-4">
            @csrf
            
            <input type="hidden" name="nomor_surat_lama" :value="revisiTarget?.nomor_surat || revisiTarget?.nomor">

            <!-- Catatan Alasan Penolakan -->
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-center justify-between text-xs font-bold text-amber-900 mb-1">
                    <span>Catatan Telaah / Alasan Penolakan:</span>
                    <span class="font-mono text-[11px]" x-text="'Revisi ke-' + ((revisiTarget?.revisi_ke || 0) + 1)"></span>
                </div>
                <p class="text-xs text-amber-800 leading-relaxed" 
                   x-text="revisiTarget?.alasan_revisi || revisiTarget?.catatan || 'Diperlukan penyesuaian berkas KAK atau estimasi pagu anggaran usulan.'"></p>
            </div>

            <!-- Nomor Surat Usulan -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Nomor Surat Usulan Srikandi <span class="text-slate-400 font-normal text-[11px]">(Tetap atau sesuaikan jika ada nomor agenda baru)</span></label>
                <input type="text" 
                       name="nomor_surat_baru" 
                       :value="revisiTarget?.nomor_surat || revisiTarget?.nomor || ''"
                       placeholder="Contoh: B/123/PL3/TIK/2026"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <!-- Perihal Baru -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Perihal / Judul Kegiatan <span class="text-rose-500">*</span></label>
                <input type="text" 
                       name="perihal" 
                       :value="revisiTarget?.perihal || revisiTarget?.judul || ''"
                       required 
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <!-- Penyesuaian Anggaran -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Penyesuaian Anggaran Diajukan (Rp) <span class="text-rose-500">*</span></label>
                <input type="number" 
                       name="anggaran_diajukan" 
                       :value="revisiTarget?.anggaran_diajukan || revisiTarget?.estimasi_total_pagu || ''"
                       step="0.01" 
                       min="1" 
                       required 
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <!-- Keterangan Revisi -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Uraian Perbaikan Berkas <span class="text-rose-500">*</span></label>
                <textarea name="keterangan_revisi" 
                          rows="3" 
                          required 
                          placeholder="Jelaskan penyesuaian yang telah dilakukan pada KAK atau spesifikasi harga..."
                          class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
            </div>

            <!-- Unggah KAK Baru -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Unggah KAK Baru / Dokumen Pendukung (PDF Maks. 10 MB)</label>
                <input type="file" 
                       name="file_kak" 
                       accept=".pdf"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-600 focus:outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-revisi-permohonan')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 text-xs font-bold shadow-md shadow-amber-600/20 transition-all cursor-pointer">
                    Kirim Dokumen Revisi
                </button>
            </div>
        </form>
    </x-modal>

    <!-- 7. MODAL KONFIRMASI HAPUS / BATALKAN (fullstuck + Frontend-pemula) -->
    <x-modal id="modal-delete-permohonan" :title="Auth::user()->hasRole('admin') ? 'Konfirmasi Hapus Pengajuan (Admin)' : 'Konfirmasi Pembatalan Pengajuan'" maxWidth="md">
        <form :action="'{{ url('permohonan') }}/' + (hapusTarget?.nomor_surat || hapusTarget?.nomor || hapusTarget?.id || '')" 
              method="POST" 
              class="space-y-4">
            @csrf
            @method('DELETE')

            <div class="text-center py-2">
                <div class="size-14 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-3 shadow-xs">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <h4 class="text-base font-bold text-slate-800">
                    {{ Auth::user()->hasRole('admin') ? 'Hapus Usulan Pengadaan?' : 'Batalkan Pengajuan Permohonan?' }}
                </h4>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    @if(Auth::user()->hasRole('admin'))
                        Anda akan menghapus usulan pengadaan <strong class="text-slate-800 font-mono" x-text="hapusTarget?.nomor_surat || hapusTarget?.nomor"></strong> beserta seluruh data relasi terkait secara tuntas (*cascade*). Tindakan ini bersifat permanen.
                    @else
                        Anda akan membatalkan berkas pengajuan <strong class="text-slate-800 font-mono" x-text="hapusTarget?.nomor_surat || hapusTarget?.nomor"></strong>. Tindakan ini tidak dapat dibatalkan.
                    @endif
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-delete-permohonan')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 text-xs font-bold shadow-md shadow-rose-600/20 transition-all cursor-pointer">
                    {{ Auth::user()->hasRole('admin') ? 'Ya, Hapus Permanen' : 'Ya, Batalkan' }}
                </button>
            </div>
        </form>
    </x-modal>

</div>

<!-- 8. ALPINEJS MANAGER DENGAN METODE LENGKAP -->
<script>
    function trackingManager() {
        return {
            viewMode: 'split',
            selectedItem: null,
            revisiTarget: null,
            hapusTarget: null,
            searchQuery: '{{ request('q') }}',
            filterStatus: '{{ request('status') }}',
            filterTanggal: '{{ request('tanggal') }}',
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

            init() {
                // Auto-pilih item pertama jika tersedia
                @if(isset($permohonanList) && count($permohonanList) > 0)
                    const firstItem = @json($permohonanList[0]);
                    if (firstItem) {
                        this.selectItem(firstItem);
                    }
                @endif
            },

            // Pemetaan Siklus 8 Tahap dari Database Status
            getStepNumber(statusRaw) {
                const s = String(statusRaw || '').toLowerCase().trim();
                if (['diajukan', 'draft', 'pending'].includes(s)) return 1;
                if (['reviu_paralel', 'verifikasi', 'telaah'].includes(s)) return 2;
                if (['penyusunan_dokumen', 'anggaran', 'disetujui'].includes(s)) return 3;
                if (['persetujuan_2', 'review_direktur'].includes(s)) return 4;
                if (['pembelian', 'surat_pesanan', 'kontrak'].includes(s)) return 5;
                if (['penerimaan', 'bapp', 'bast'].includes(s)) return 6;
                if (['pembayaran', 'spm', 'sp2d'].includes(s)) return 7;
                if (['selesai'].includes(s)) return 8;
                return 1;
            },

            selectItem(item) {
                if (!item) return;
                // Normalisasi struktur data
                const status = item.status_permohonan || item.status || item.statusRaw || 'diajukan';
                this.selectedItem = Object.assign({}, item, {
                    id: item.id,
                    nomor: item.nomor_surat || item.nomor,
                    nomor_surat: item.nomor_surat || item.nomor,
                    judul: item.perihal || item.judul_pengadaan || item.judul,
                    perihal: item.perihal || item.judul_pengadaan || item.judul,
                    statusRaw: status,
                    pemohon: item.pemohon || (item.unit ? item.unit.nama_unit : (item.kode_unit || 'Unit Pengusul')),
                    tanggal: item.tanggal || (item.tanggal_pengajuan ? String(item.tanggal_pengajuan).substring(0, 10) : '-'),
                    currentTahap: item.currentTahap || this.getStepNumber(status),
                    fileName: item.fileName || (item.file_surat_permohonan ? String(item.file_surat_permohonan).split('/').pop() : 'Dokumen_Pengajuan.pdf')
                });
            },

            stepPercent() {
                if (!this.selectedItem) return 0;
                const step = this.selectedItem.currentTahap || 1;
                return Math.max(0, Math.min(100, ((step - 1) / 7) * 100));
            },

            // Metadata Style Status (Frontend-pemula)
            statusMeta(raw) {
                const key = String(raw || '').toLowerCase().trim().replace(/[\s-]+/g, '_');
                const tone = {
                    sky:     { circle: 'bg-sky-100 text-sky-600',     pill: 'bg-sky-100 text-sky-700',     icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' },
                    amber:   { circle: 'bg-amber-100 text-amber-600', pill: 'bg-amber-100 text-amber-700', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
                    emerald: { circle: 'bg-emerald-100 text-emerald-600', pill: 'bg-emerald-100 text-emerald-700', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' },
                    orange:  { circle: 'bg-orange-100 text-orange-600',   pill: 'bg-orange-100 text-orange-700',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>' },
                    rose:    { circle: 'bg-rose-100 text-rose-600',       pill: 'bg-rose-100 text-rose-700',       icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
                };
                const map = {
                    diajukan:            { label: 'Diajukan',             t: 'sky' },
                    reviu_paralel:       { label: 'Reviu Paralel',        t: 'amber' },
                    diproses:            { label: 'Diproses',             t: 'amber' },
                    disetujui:           { label: 'Disetujui',            t: 'emerald' },
                    selesai:             { label: 'Selesai',              t: 'emerald' },
                    revisi:              { label: 'Perlu Revisi',         t: 'orange' },
                    perlu_revisi:        { label: 'Perlu Revisi',         t: 'orange' },
                    ditolak:             { label: 'Ditolak',              t: 'rose' },
                };
                const m = map[key] || {
                    label: key ? key.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()) : 'Diajukan',
                    t: 'sky'
                };
                return Object.assign({ label: m.label }, tone[m.t]);
            },

            // Helper Icon & Dot Timeline
            timelineDot(type) {
                return ({
                    warning: 'bg-amber-500 text-white',
                    danger:  'bg-rose-500 text-white',
                    info:    'bg-[#0969da] text-white',
                    success: 'bg-emerald-500 text-white',
                    gray:    'bg-slate-400 text-white'
                })[type] || 'bg-[#0969da] text-white';
            },

            timelineIcon(type) {
                if (type === 'danger')  return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
                if (type === 'warning') return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>';
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
            },

            badgeClass(type) {
                return ({
                    warning: 'bg-amber-100 text-amber-700',
                    danger:  'bg-rose-100 text-rose-700',
                    info:    'bg-blue-100 text-blue-700',
                    success: 'bg-emerald-100 text-emerald-700',
                    gray:    'bg-slate-100 text-slate-700'
                })[type] || 'bg-slate-100 text-slate-700';
            },

            // Fallback Generator Timeline Real-time jika Relasi Kosong
            generateTimeline(item) {
                if (!item) return [];
                const list = [];
                list.push({
                    id: 1,
                    time: item.tanggal || 'Hari ini',
                    title: 'Usulan Pengadaan Didaftarkan',
                    subtitle: 'Berkas naskah dinas resmi Srikandi dicatat oleh unit pengusul ' + (item.pemohon || ''),
                    badge: 'Didaftarkan',
                    badge_type: 'info',
                    type: 'info'
                });

                if (['reviu_paralel', 'disetujui', 'selesai', 'perlu_revisi', 'ditolak'].includes(String(item.statusRaw).toLowerCase())) {
                    list.push({
                        id: 2,
                        time: item.tanggal || 'Dalam Proses',
                        title: 'Proses Telaah & Verifikasi Anggaran',
                        subtitle: 'Berkas sedang ditelaah oleh B2 Perencanaan dan Verifikator MAK.',
                        badge: 'Telaah Berkas',
                        badge_type: 'warning',
                        type: 'warning'
                    });
                }

                if (['ditolak', 'perlu_revisi', 'revisi'].includes(String(item.statusRaw).toLowerCase())) {
                    list.push({
                        id: 3,
                        time: 'Catatan Telaah',
                        title: 'Pengembalian / Revisi Berkas',
                        subtitle: item.alasan_revisi || 'Diperlukan perbaikan pada berkas usulan atau penyesuaian pagu anggaran.',
                        badge: 'Perlu Revisi',
                        badge_type: 'danger',
                        type: 'danger'
                    });
                }

                if (['disetujui', 'selesai'].includes(String(item.statusRaw).toLowerCase())) {
                    list.push({
                        id: 4,
                        time: 'Persetujuan Selesai',
                        title: 'Usulan Disetujui Penuh',
                        subtitle: 'Persetujuan disposisi pimpinan diterbitkan. Lanjut ke proses pengadaan.',
                        badge: 'Disetujui',
                        badge_type: 'success',
                        type: 'success'
                    });
                }
                return list;
            },

            // PDF Route Helper
            pdfUrl(item) {
                if (!item) return '#';
                const id = encodeURIComponent(item.nomor_surat || item.nomor || item.id).replace(/%2F/gi, '/');
                return '/dokumen/pdf/permohonan/' + id;
            },

            // Modal Dispatcher
            openModalCreate() {
                if (typeof this.$dispatch === 'function') {
                    this.$dispatch('open-modal', 'modal-create-permohonan');
                }
            },

            openRevisiModal(item) {
                this.revisiTarget = item;
                if (typeof this.$dispatch === 'function') {
                    this.$dispatch('open-modal', 'modal-revisi-permohonan');
                }
            },

            openHapusModal(item) {
                this.hapusTarget = item;
                if (typeof this.$dispatch === 'function') {
                    this.$dispatch('open-modal', 'modal-delete-permohonan');
                }
            }
        };
    }
</script>
@endsection