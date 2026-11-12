@extends('layouts.app')

@section('title', 'Tracking Pengajuan - SIM-PBJ')

@section('content')
@php
    // Normalisasi sumber data dari controller fullstuck ($permohonanMasuk atau $permohonanList)
    $rawCollection = isset($permohonanMasuk) ? $permohonanMasuk : ($permohonanList ?? collect());
    
    // Perhitungan statistik KPI dari data backend
    $totalPermohonanCount = $rawCollection->count();
    $belumDitelaahCount = $rawCollection->where('status_permohonan', 'diajukan')->count();
    $memoMakCount = class_exists(\App\Models\VerifikasiParalel::class)
        ? \App\Models\VerifikasiParalel::where('role_verifikator', 'staff_bidang_2')->where('status_keputusan', 'disetujui')->count()
        : 0;

    // Transformasi data Eloquent ke representasi JSON untuk AlpineJS Frontend-pemula
    $normalizedItems = $rawCollection->map(function($p) {
        $status = $p->status_permohonan ?? $p->status ?? 'diajukan';
        return [
            'id' => $p->nomor_surat,
            'nomor' => $p->nomor_surat,
            'nomor_surat' => $p->nomor_surat,
            'judul' => $p->perihal ?? $p->judul_pengadaan ?? 'Pengadaan Barang/Jasa',
            'perihal' => $p->perihal ?? $p->judul_pengadaan ?? 'Pengadaan Barang/Jasa',
            'jenis' => ucfirst($p->jenis_pengadaan ?? 'Barang'),
            'deskripsi' => $p->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa untuk kebutuhan operasional institusi.',
            'status' => ucfirst(str_replace('_', ' ', $status)),
            'statusRaw' => $status,
            'pemohon' => $p->unit->nama_unit ?? $p->kode_unit ?? ($p->user->name ?? 'Unit Pengusul'),
            'kode_unit' => $p->kode_unit ?? '',
            'kode_mak' => $p->kode_mak ?? null,
            'penanggungJawab' => $p->unit->nama_unit ?? $p->kode_unit ?? 'Bagian Perencanaan',
            'anggaran' => (float)($p->anggaran_diajukan ?? $p->estimasi_total_pagu ?? 0),
            'anggaran_formatted' => 'Rp ' . number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu ?? 0, 0, ',', '.'),
            'tanggal' => $p->tanggal_pengajuan ? date('d M Y', strtotime($p->tanggal_pengajuan)) : date('d M Y'),
            'tanggal_pengajuan' => $p->tanggal_pengajuan ?? '',
            'fileName' => $p->file_surat_permohonan ? basename($p->file_surat_permohonan) : 'Surat_Permohonan.pdf',
            'dokumenList' => $p->dokumen_lampiran_list,
            'alasan_revisi' => $p->alasan_revisi ?? null,
            'isApproved' => in_array(strtolower($status), ['disetujui', 'selesai']),
        ];
    })->values();
@endphp

<div x-data="trackingManager({{ json_encode($normalizedItems, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) }})" class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- 1. HEADER HALAMAN & ROLE BADGE -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <!-- Tombol Kembali (Hanya tampil saat mode Fullscreen Detail) -->
            <div x-show="viewMode === 'fullscreen'" class="mb-3">
                <button @click="viewMode = 'split'" type="button" 
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-slate-100 hover:bg-[#0969da] hover:text-white text-slate-700 font-bold text-xs rounded-xl transition-all duration-200 cursor-pointer shadow-xs">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Kembali ke Daftar Pengajuan</span>
                </button>
            </div>

            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]"
                    x-text="viewMode === 'fullscreen' ? 'Detail & Tracking Pengajuan' : 'Tracking Pengajuan Pengadaan'">
                    Tracking Pengajuan Pengadaan
                </h1>
            </div>
            <p class="mt-1 text-xs md:text-sm text-slate-500 leading-relaxed"
               x-text="viewMode === 'fullscreen' 
                    ? 'Pantau status siklus, kelengkapan berkas naskah dinas Srikandi, dan riwayat verifikasi.' 
                    : 'Pantau status berkas naskah dinas Srikandi, KAK, dan pagu MAK secara real-time melalui 8 siklus pengadaan.'">
                Pantau status berkas naskah dinas Srikandi, KAK, dan pagu MAK secara real-time melalui 8 siklus pengadaan.
            </p>
        </div>

        <!-- Role Badge & Action -->
        <div class="flex items-center gap-2 shrink-0">
            @if(Auth::check() && Auth::user()->hasRole('admin'))
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200 px-3 py-1 text-xs font-bold text-rose-700">
                    <span class="size-2 rounded-full bg-rose-600"></span>
                    Administrator SIM-PBJ
                </span>
            @else
                {{-- <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200/80 px-3 py-1 text-xs font-bold text-[#0969da]">
                    <span class="size-2 rounded-full bg-[#0969da] animate-pulse"></span>
                    Staf Bidang II (Pemantau Berkas - Read-Only)
                </span> --}}
            @endif

            @if(!Auth::user()->hasRole('admin') && (Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create'])))
                <button x-show="viewMode !== 'fullscreen'" 
                        @click="openModalCreate()" 
                        type="button" 
                        class="inline-flex items-center justify-center h-9 px-4 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs transition-all shadow-sm gap-1.5 cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Permohonan Baru</span>
                </button>
            @endif
        </div>
    </div>

    <!-- 2. KPI STAT CARDS (Data PHP fullstuck dalam style Frontend-pemula) -->
    <div x-show="viewMode === 'split'" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Card 1: Total Masuk -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-400 block">Total Permohonan Masuk</span>
                <span class="text-2xl font-extrabold text-[#213153] mt-0.5 block font-mono">{{ $totalPermohonanCount }}</span>
                <span class="text-[11px] text-slate-500 font-medium">Antrean berkas Srikandi</span>
            </div>
            <div class="size-11 rounded-xl bg-blue-50 text-[#0969da] flex items-center justify-center shrink-0">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
        </div>

        <!-- Card 2: Belum Ditelaah -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-400 block">Belum Ditelaah</span>
                <span class="text-2xl font-extrabold text-amber-600 mt-0.5 block font-mono">{{ $belumDitelaahCount }}</span>
                <span class="text-[11px] text-slate-500 font-medium">Perlu verifikasi checklist</span>
            </div>
            <div class="size-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <!-- Card 3: Memo Cek MAK Terbit -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-400 block">Memo Cek MAK Terbit</span>
                <span class="text-2xl font-extrabold text-teal-600 mt-0.5 block font-mono">{{ $memoMakCount }}</span>
                <span class="text-[11px] text-slate-500 font-medium">Diteruskan ke Perencanaan</span>
            </div>
            <div class="size-11 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- 3. FILTER & PENCARIAN (Presisi Figma Frontend-pemula) -->
    <div x-show="viewMode === 'split'" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-center">

            <!-- Input Pencarian -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <div class="relative w-full">
                    <input type="text" 
                           x-model="searchQuery" 
                           placeholder="Cari nomor surat atau judul pengadaan..." 
                           class="w-full h-10 pl-9 pr-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <svg class="size-4 text-slate-400 absolute left-3 top-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="button" 
                        class="h-10 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-xs rounded-lg transition-colors flex items-center gap-1.5 shrink-0 shadow-sm cursor-pointer">
                    <svg class="size-3.5 shrink-0 fill-none stroke-current" stroke-width="2.5" viewBox="0 0 24 24">
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
                    <option value="diajukan">Diajukan</option>
                    <option value="reviu_paralel">Reviu Paralel (B2/MAK)</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="perlu_revisi">Perlu Revisi</option>
                    <option value="ditolak">Ditolak</option>
                    <option value="selesai">Selesai</option>
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

            <!-- Filter Tanggal & Reset -->
            <div class="flex items-center gap-2">
                <input type="date" x-model="filterTanggal" class="w-full h-10 px-2.5 rounded-lg border border-slate-300 text-xs text-slate-600 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                <button @click="resetFilters()" type="button" class="h-10 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center shrink-0 cursor-pointer" title="Reset Filter">
                    <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span class="ml-1">Reset</span>
                </button>
            </div>

        </div>
    </div>

    <!-- 4. KONTEN UTAMA: FULLSCREEN DETAIL ATAU SPLIT VIEW -->

    <!-- ==================== A. MODE FULLSCREEN (FRAME 4 & 5 FIGMA) ==================== -->
    <template x-if="viewMode === 'fullscreen' && selectedItem">
        <div class="space-y-6">

            <!-- 5 Kartu Ringkasan Informasi -->
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
                        <span class="inline-flex mt-0.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                              :class="getStatusBadgeClass(selectedItem.statusRaw).pill"
                              x-text="selectedItem.status"></span>
                    </div>
                </div>

                <!-- 4. Tanggal Usulan -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Tanggal Usulan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.tanggal"></span>
                    </div>
                </div>

                <!-- 5. Unit Pemohon -->
                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Unit Pemohon</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.pemohon" :title="selectedItem.pemohon"></span>
                    </div>
                </div>
            </div>

            <!-- Stepper 8 Tahap Lebar dengan Label Teks (Frontend-pemula) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-lg text-[#213153]" x-text="'Tahap ' + (selectedItem.currentTahap || 1) + ' dari 8'"></h3>
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
                                <div class="size-11 rounded-full flex items-center justify-center font-bold text-base border-2 transition-all"
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

            <!-- Dua Kolom Bawah (Kiri: Surat & Dokumen, Kanan: Riwayat/Timeline) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- KIRI (6 COLS): Surat, Dokumen & Keputusan -->
                <div class="lg:col-span-6 space-y-6">

                    <!-- Kartu Berkas Naskah Dinas Srikandi & Dokumen -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-base text-[#213153]">Surat dan Dokumen</h3>
                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-[#0969da]" x-text="(selectedItem.dokumenList && selectedItem.dokumenList.length > 0 ? selectedItem.dokumenList.length : 1) + ' Dokumen'"></span>
                        </div>

                        <!-- DAFTAR DOKUMEN DINAMIS -->
                        <div class="space-y-2.5">
                            <template x-if="selectedItem.dokumenList && selectedItem.dokumenList.length > 0">
                                <template x-for="(doc, dIdx) in selectedItem.dokumenList" :key="dIdx">
                                    <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                                        <div :class="doc.colorClass || 'bg-rose-600'" class="size-9 rounded-lg text-white flex items-center justify-center shrink-0 font-bold text-[10px] shadow-xs" x-text="doc.type || 'PDF'"></div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-bold text-xs text-[#213153] truncate" x-text="doc.name"></div>
                                            <div class="text-[11px] text-slate-500 truncate" x-text="(doc.type || 'Berkas') + ' • ' + (doc.size || 'Lampiran')"></div>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <template x-if="String(doc.type || '').toUpperCase() === 'PDF'">
                                                <button type="button" 
                                                        @click="$dispatch('open-preview-doc', { title: doc.name, url: doc.url, size: doc.size })"
                                                        class="h-7 px-3 text-xs font-bold text-[#0969da] border border-[#0969da] bg-white rounded-lg hover:bg-blue-50 transition-colors inline-flex items-center justify-center cursor-pointer">
                                                    Lihat
                                                </button>
                                            </template>
                                            <template x-if="String(doc.type || '').toUpperCase() !== 'PDF'">
                                                <a :href="doc.url" download
                                                   class="h-7 px-3 text-xs font-bold text-slate-700 border border-slate-300 bg-white rounded-lg hover:bg-slate-50 transition-colors inline-flex items-center justify-center">
                                                    Unduh
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </template>

                            <template x-if="!selectedItem.dokumenList || selectedItem.dokumenList.length === 0">
                                <div class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                                    <div class="size-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V7.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 1H7a2 2 0 00-2 2v16a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-xs text-[#213153] truncate" x-text="selectedItem.judul"></div>
                                        <div class="text-[11px] text-slate-500 font-medium truncate" x-text="(selectedItem.fileName || 'Surat_Permohonan.pdf') + ' • PDF Resmi'"></div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button @click="$dispatch('open-preview-doc', { title: selectedItem.fileName || 'Surat_Permohonan.pdf', url: pdfUrl(selectedItem), size: 'PDF' })" type="button" class="px-3 py-1.5 text-xs font-bold text-[#0969da] border border-[#0969da] bg-white rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
                                            Lihat
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Ringkasan Anggaran Usulan (fullstuck) -->
                        <div class="grid grid-cols-2 gap-3 pt-1 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Estimasi Pagu Anggaran:</span>
                                <span class="font-extrabold text-[#0969da] text-sm font-mono mt-0.5 block" x-text="selectedItem.anggaran_formatted"></span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Ketersediaan Pagu MAK:</span>
                                <span class="font-bold text-teal-700 text-sm mt-0.5 block" x-text="selectedItem.kode_mak || 'Ditinjau Perencanaan'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Informasi & Keputusan Telaah (fullstuck) -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-base text-[#213153]">Informasi Status &amp; Tindakan</h3>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-[11px] font-semibold">
                                Pemantauan Staf B2
                            </span>
                        </div>

                        <!-- Info Read-Only Staf Bidang II -->
                        <div class="rounded-xl bg-blue-50/70 border border-blue-100 p-3.5 text-xs text-blue-900 flex items-start gap-2.5 leading-relaxed">
                            <svg class="size-4 text-[#0969da] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Staf Bidang II memantau berkas secara <strong>read-only</strong>. Pemeriksaan kelengkapan dokumen administrasi (Surat Srikandi, KAK, Spesifikasi) dan penetapan keputusan eksekutif (ACC/Tolak/Revisi) dilakukan langsung oleh <strong>Wakil Direktur II</strong>.</span>
                        </div>

                        <!-- 3 Status Grid Administrasi dari fullstuck -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 space-y-1">
                                <span class="text-slate-400 block text-[11px]">Naskah Srikandi:</span>
                                <span class="font-bold text-slate-800 font-mono text-xs truncate block" x-text="selectedItem.nomor"></span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 space-y-1">
                                <span class="text-slate-400 block text-[11px]">Kelengkapan:</span>
                                <span class="font-bold text-indigo-700 text-xs block">Wadir II &amp; Pengusul</span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 space-y-1">
                                <span class="text-slate-400 block text-[11px]">Alokasi MAK:</span>
                                <span class="font-bold text-teal-700 text-xs block" x-text="selectedItem.kode_mak || 'Dalam Telaah'"></span>
                            </div>
                        </div>

                        <!-- Link navigasi detail bawaan Laravel -->
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
                            <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.nomor)" 
                               class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                                <span>Detail Penuh &amp; Silsilah &rarr;</span>
                            </a>
                            <a :href="pdfUrl(selectedItem)" 
                               @click.prevent="$dispatch('open-preview-doc', { title: selectedItem.fileName || 'Surat_Permohonan.pdf', url: pdfUrl(selectedItem), size: 'PDF' })"
                               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                                <svg class="size-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span>Buka Naskah Srikandi (PDF)</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- KANAN (6 COLS): Timeline Riwayat Kronologis & Catatan Telaah -->
                <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="font-bold text-[#213153] text-base">Riwayat Kronologis &amp; Catatan Telaah</h3>
                    </div>

                    <!-- Catatan Telaah / Alasan jika ditolak atau perlu revisi -->
                    <template x-if="selectedItem.alasan_revisi">
                        <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                            <div class="size-2.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></div>
                            <div class="text-xs space-y-0.5">
                                <div class="font-bold text-amber-900">Catatan Telaah Verifikator:</div>
                                <div class="text-amber-800 leading-relaxed" x-text="selectedItem.alasan_revisi"></div>
                            </div>
                        </div>
                    </template>

                    <!-- Timeline Loop Dinamis -->
                    <div class="pl-3 pt-2">
                        <div class="relative pl-6 border-l-2 border-slate-200 space-y-5">
                            <template x-for="log in generateTimeline(selectedItem)" :key="log.id">
                                <div class="relative">
                                    <div :class="timelineDot(log.type)" class="absolute -left-[35px] top-0 size-7 rounded-full flex items-center justify-center shadow-sm">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="timelineIcon(log.type)"></svg>
                                    </div>

                                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="text-[10px] font-medium text-slate-400 block" x-text="log.time"></span>
                                            <h4 class="font-bold text-[#213153] text-xs mt-0.5" x-text="log.title"></h4>
                                            <p class="text-[11px] text-slate-500 mt-0.5 break-words" x-text="log.subtitle"></p>
                                        </div>
                                        <span :class="badgeClass(log.badge_type)" class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0" x-text="log.badge"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- ==================== B. MODE SPLIT (FRAME 1, 2, & 3 FIGMA) ==================== -->
    <template x-if="viewMode === 'split'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- PANEL KIRI (5 COLS): DAFTAR PENGAJUAN -->
            <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-bold text-base text-[#213153]">Daftar Pengajuan</h2>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-[#0969da]" 
                              x-text="filteredList.length + ' berkas'"></span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 max-h-[720px] overflow-y-auto">
                    <template x-for="item in filteredList" :key="item.id">
                        <div @click="selectItem(item)" 
                             class="p-4 flex items-center gap-3 cursor-pointer transition-all hover:bg-slate-50"
                             :class="selectedItem && selectedItem.id === item.id ? 'bg-blue-50/60 border-l-4 border-l-[#0969da]' : ''">

                            <!-- Status Circle Icon -->
                            <div class="size-10 rounded-full flex items-center justify-center shrink-0 shadow-xs"
                                 :class="getStatusBadgeClass(item.statusRaw).circle">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-html="getStatusBadgeClass(item.statusRaw).icon"></svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-bold text-sm text-[#213153] truncate" x-text="item.judul"></h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0" 
                                          :class="getStatusBadgeClass(item.statusRaw).pill" 
                                          x-text="item.status"></span>
                                </div>
                                <div class="text-xs font-mono font-medium text-slate-400 mt-0.5 truncate" x-text="item.nomor"></div>
                                <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                                    <span class="truncate" x-text="item.pemohon"></span>
                                    <span class="font-bold font-mono text-[#0969da] shrink-0" x-text="item.anggaran_formatted"></span>
                                </div>
                            </div>

                            <svg class="size-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </template>

                    <!-- Empty List Feedback -->
                    <template x-if="filteredList.length === 0">
                        <div class="p-10 text-center">
                            <div class="size-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-600">Tidak ada pengajuan yang sesuai.</p>
                            <button @click="resetFilters()" type="button" class="mt-2 text-xs font-bold text-[#0969da] hover:underline cursor-pointer">
                                Reset kriteria filter
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- PANEL KANAN (7 COLS): DETAIL DISAMPING ATAU EMPTY STATE -->
            <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 min-h-[600px] flex flex-col justify-between">

                <!-- 1. EMPTY STATE PRESISI FIGMA (FRAME 1) -->
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
                            Silakan pilih salah satu pengajuan dari daftar sebelah kiri untuk melihat informasi lengkap, dokumen naskah dinas, dan riwayat verifikasi siklusnya.
                        </p>
                    </div>
                </template>

                <!-- 2. KONTEN ITEM TERPILIH (FRAME 2 & 3 FIGMA) -->
                <template x-if="selectedItem">
                    <div class="space-y-6">

                        <!-- Header Item Terpilih -->
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                            <div class="min-w-0">
                                <h3 class="font-bold text-lg text-[#213153] truncate" x-text="selectedItem.judul"></h3>
                                <div class="text-xs font-mono font-medium text-slate-400 mt-0.5 truncate" x-text="selectedItem.nomor"></div>
                            </div>
                            <button @click="viewMode = 'fullscreen'" type="button" 
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0969da] hover:underline shrink-0 cursor-pointer">
                                <span>Lihat Detail Penuh</span>
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>

                        <!-- Stepper 8 Tahap Ringkas Circle (Frame 2 & 3) -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tahap Siklus</span>
                                <span class="text-xs font-bold text-[#0969da]" x-text="'Tahap ' + (selectedItem.currentTahap || 1) + ' dari 8'"></span>
                            </div>
                            <div class="flex items-center justify-between relative py-2">
                                <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                                <div class="absolute top-1/2 left-0 h-1 bg-[#0969da] -translate-y-1/2 z-0 transition-all duration-300" 
                                     :style="'width: ' + stepPercent() + '%'"></div>

                                <template x-for="n in 8" :key="n">
                                    <div class="relative z-10 size-8 rounded-full flex items-center justify-center font-bold text-xs border-2 transition-all"
                                         :class="n <= (selectedItem.currentTahap || 1) ? 'bg-[#0969da] text-white border-[#0969da]' : 'bg-white text-slate-400 border-slate-300'"
                                         x-text="n"></div>
                                </template>
                            </div>
                        </div>

                        <!-- Surat dan Dokumen Ringkas -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-sm text-[#213153]">Surat dan Dokumen</h4>
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-[#0969da]">1 Dokumen</span>
                            </div>

                            <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white shadow-xs">
                                <div class="size-9 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V7.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 1H7a2 2 0 00-2 2v16a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-[#213153] truncate" x-text="selectedItem.judul"></div>
                                    <div class="text-[10px] text-slate-400 font-mono truncate" x-text="selectedItem.fileName"></div>
                                </div>
                                <button @click="showModalDoc = true" type="button" class="px-3 py-1 text-xs font-bold text-[#0969da] border border-[#0969da] rounded-lg hover:bg-blue-50 cursor-pointer">
                                    Lihat
                                </button>
                            </div>
                        </div>

                        <!-- Panel Status Berkas & MAK (fullstuck) -->
                        <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-3 shadow-xs">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-sm text-[#213153]">Status Telaah Administrasi</h4>
                                <span class="text-xs font-bold font-mono text-[#0969da]" x-text="selectedItem.anggaran_formatted"></span>
                            </div>

                            <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Unit Pengusul:</span>
                                    <span class="font-semibold text-slate-800" x-text="selectedItem.pemohon"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Alokasi Pagu MAK:</span>
                                    <span class="font-semibold text-teal-700" x-text="selectedItem.kode_mak || 'Ditinjau Perencanaan'"></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>

                <!-- Tombol Aksi Bawah Split Panel -->
                <template x-if="selectedItem">
                    <div class="mt-6 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.nomor)" 
                               class="h-9 px-4 inline-flex items-center rounded-lg bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors">
                                Detail Penuh
                            </a>
                            <a :href="pdfUrl(selectedItem)" target="_blank" rel="noopener"
                               class="h-9 px-4 inline-flex items-center rounded-lg border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors">
                                Unduh PDF
                            </a>
                        </div>

                        <button @click="viewMode = 'fullscreen'" type="button" 
                                class="h-9 px-4 inline-flex items-center rounded-lg bg-[#0969da] hover:bg-blue-700 text-white text-xs font-bold transition-colors cursor-pointer shadow-xs">
                            Buka Tracking Lengkap
                        </button>
                    </div>
                </template>

            </div>
        </div>
    </template>

    <!-- 6. MODAL FORM: BUAT USULAN PERMOHONAN BARU (fullstuck) -->
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
                    <label class="text-xs font-bold text-slate-700">Unit Pengusul</label>
                    <input type="hidden" name="kode_unit" value="{{ Auth::user()->kode_unit ?? 'TIK' }}">
                    <input type="text" readonly disabled
                           value="{{ Auth::user()->unit ? '[' . Auth::user()->kode_unit . '] ' . Auth::user()->unit->nama_unit : (Auth::user()->kode_unit ?? 'Unit Pengusul') }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 cursor-not-allowed">
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

</div>
@endsection
