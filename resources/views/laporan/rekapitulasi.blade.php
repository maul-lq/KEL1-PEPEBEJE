@extends('layouts.app')

@section('title', 'Riwayat dan Laporan - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<div x-data="riwayatLaporanManager()" x-init="init()" class="space-y-6">

    <!-- HEADER HALAMAN & TOMBOL UNDUH PDF -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-[#213153] sm:text-[40px] sm:leading-[48px]">
                Riwayat dan Laporan
            </h1>
            <p class="mt-1 text-sm font-medium text-slate-500 sm:text-base">
                Lihat rekapitulasi pengajuan, telusuri riwayat aktivitas, dan unduh laporan berdasarkan periode tertentu.
            </p>
        </div>
        
        <!-- Tombol Unduh Laporan PDF (Aktif di Tab Laporan) -->
        <a x-show="tab === 'laporan'" 
           href="{{ route('laporan.rekapitulasi.pdf') }}" 
           target="_blank" 
           class="flex h-[46px] items-center gap-3 rounded-lg bg-[#0969da] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#0858b7] transition-colors">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Unduh Laporan (PDF)
        </a>
    </div>

    <!-- TAB NAVIGASI SWITCHER -->
    <div class="flex border-b border-slate-200">
        <button @click="tab = 'riwayat'" 
                type="button"
                :class="tab === 'riwayat' ? 'border-[#0969da] text-[#0969da] font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="flex h-[42px] items-center gap-2.5 border-b-2 px-4 text-sm transition-colors">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Riwayat Aktivitas
        </button>
        <button @click="tab = 'laporan'" 
                type="button"
                :class="tab === 'laporan' ? 'border-[#0969da] text-[#0969da] font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="flex h-[42px] items-center gap-2.5 border-b-2 px-4 text-sm transition-colors">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Laporan
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: RIWAYAT AKTIVITAS -->
    <!-- ========================================================================= -->
    <div x-show="tab === 'riwayat'" x-cloak class="space-y-6">
        
        <!-- Filter Panel Riwayat -->
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-end">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="text-xs font-medium text-slate-600">Pencarian</span>
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" 
                           x-model="searchQuery" 
                           placeholder="Cari nomor surat atau judul pengajuan" 
                           class="h-[38px] w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-800 outline-none focus:border-[#0969da]" />
                </div>
            </div>

            <button @click="applyFilter()" 
                    type="button" 
                    class="flex h-[38px] w-full items-center justify-center gap-2 rounded-lg bg-[#0969da] px-6 text-sm font-medium text-white shadow-sm hover:bg-[#0858b7] transition-colors lg:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Cari
            </button>

            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-slate-600">Jenis Aktivitas</span>
                    <select x-model="selectedActivity" class="h-[38px] w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-800 outline-none focus:border-[#0969da]">
                        <option value="semua">Semua Aktivitas</option>
                        <option value="Pemeriksaan oleh bagian perencanaan">Pemeriksaan oleh bagian perencanaan</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-slate-600">Filter Waktu</span>
                    <select class="h-[38px] w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-800 outline-none">
                        <option>Semua waktu</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-slate-600">Filter Status</span>
                    <select x-model="selectedStatus" class="h-[38px] w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-800 outline-none">
                        <option value="semua">Semua status</option>
                        <option value="Disetujui">Disetujui</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                </div>
            </div>

            <button @click="resetFilter()" 
                    type="button" 
                    class="flex h-[38px] w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors lg:w-auto">
                Reset
            </button>
        </div>

        <!-- Tabel Riwayat -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#DEEEFF] font-semibold text-[#213153] border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Nama Pengadaan</th>
                            <th class="px-4 py-3">Pengusul</th>
                            <th class="px-4 py-3">Revisi</th>
                            <th class="px-4 py-3">Aktivitas</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <template x-for="(row, i) in filteredRows" :key="i">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap" x-text="row.time"></td>
                                <td class="px-4 py-3 font-medium text-[#213153]" x-text="row.name"></td>
                                <td class="px-4 py-3" x-text="row.user"></td>
                                <td class="px-4 py-3 font-semibold" x-text="row.rev"></td>
                                <td class="px-4 py-3" x-text="row.activity"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                                          :class="row.status === 'Disetujui' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                          x-text="row.status">
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: LAPORAN & REKAPITULASI (Sesuai Kode Builder.io Anda) -->
    <!-- ========================================================================= -->
    <div x-show="tab === 'laporan'" x-cloak class="space-y-6">
        
        <!-- Filter Periode Laporan -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-2 lg:grid-cols-[1fr_1fr_1.35fr_auto]">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-slate-700">Tahun Anggaran</label>
                    <select class="h-[38px] w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-800 outline-none focus:border-[#0969da]">
                        <option>2026</option>
                        <option>2025</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-slate-700">Bulan Pengadaan</label>
                    <select class="h-[38px] w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-800 outline-none focus:border-[#0969da]">
                        <option>Semua Bulan</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-slate-700">Unit Kerja / Jurusan</label>
                    <select class="h-[38px] w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-800 outline-none focus:border-[#0969da]">
                        <option>Semua Unit</option>
                    </select>
                </div>
                <button type="button" class="flex h-[38px] items-center justify-center gap-2 rounded-lg bg-[#0969da] px-8 text-sm font-medium text-white shadow-sm hover:bg-[#0858b7] transition-colors">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </button>
            </div>
        </section>

        <!-- 6 Card Ringkasan Keuangan (Identik dengan Builder.io) -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-center gap-2.5 text-base font-semibold text-[#213153]">
                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Ringkasan Keuangan
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                
                <!-- Card 1 -->
                <div class="flex h-[79px] items-center gap-4 rounded-lg bg-[#BAE5FF]/40 px-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#5998FF] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-600">Total Pagu Anggaran DIPA</div>
                        <div class="text-lg font-bold text-[#213153]">Rp 405.000.000</div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="flex h-[79px] items-center gap-4 rounded-lg bg-[#EEFFD9] px-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#0BE400] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-600">Total Estimasi Usulan Permohonan</div>
                        <div class="text-lg font-bold text-[#213153]">Rp 285.000.000</div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="flex h-[79px] items-center gap-4 rounded-lg bg-[#FAEFFF] px-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#D059FF] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-600">Total Nilai Kontrak / SPK</div>
                        <div class="text-lg font-bold text-[#213153]">Rp 0</div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="flex h-[79px] items-center gap-4 rounded-lg bg-[#FFFCE2] px-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#FF9E59] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-600">Total Realisasi Pencairan SP2D</div>
                        <div class="text-lg font-bold text-[#213153]">Rp 0</div>
                    </div>
                </div>

                <!-- Card 5 -->
                <div class="flex h-[79px] items-center gap-4 rounded-lg bg-[#E8FEFF] px-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#00C2CB] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-600">Sisa Anggaran Belum Terserap</div>
                        <div class="text-lg font-bold text-[#213153]">Rp 405.000.000</div>
                    </div>
                </div>

                <!-- Card 6 -->
                <div class="flex h-[79px] items-center gap-4 rounded-lg bg-[#FFE2E2] px-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#FF595C] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-600">Persentase Serapan Anggaran</div>
                        <div class="text-lg font-bold text-[#213153]">0%</div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Tabel Distribusi Anggaran per Jurusan -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 text-base font-semibold text-[#213153]">
                    <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Distribusi Anggaran per Jurusan dan Unit Kerja
                </div>
                <a href="{{ route('laporan.rekapitulasi.pdf') }}" target="_blank" class="flex h-[38px] items-center gap-2 rounded-lg border border-[#0969da] bg-white px-4 text-xs font-semibold text-[#0969da] hover:bg-blue-50 transition-colors">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Detail (PDF)
                </a>
            </div>

            <!-- Input Cari Jurusan -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" 
                           x-model="jurusanSearch" 
                           placeholder="Cari kode unit atau nama jurusan" 
                           class="h-[38px] w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-800 outline-none focus:border-[#0969da]" />
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#E3F2FF] font-semibold text-[#213153]">
                        <tr>
                            <th class="border-b border-slate-200 px-3.5 py-3 w-12 text-center">No.</th>
                            <th class="border-b border-slate-200 px-3.5 py-3">Kode Unit</th>
                            <th class="border-b border-slate-200 px-3.5 py-3">Nama Jurusan / Unit Kerja</th>
                            <th class="border-b border-slate-200 px-3.5 py-3 text-center">Jumlah Usulan</th>
                            <th class="border-b border-slate-200 px-3.5 py-3">Total Nilai Usulan</th>
                            <th class="border-b border-slate-200 px-3.5 py-3 text-center">Pangsa Usulan (%)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-slate-700">
                        <template x-for="(r, i) in filteredJurusan" :key="r.kode">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-3.5 py-3 text-center font-medium" x-text="i + 1"></td>
                                <td class="px-3.5 py-3 font-semibold text-[#213153]" x-text="r.kode"></td>
                                <td class="px-3.5 py-3 font-medium" x-text="r.nama"></td>
                                <td class="px-3.5 py-3 text-center" x-text="r.jumlah"></td>
                                <td class="px-3.5 py-3 font-bold text-slate-800" x-text="r.nilai"></td>
                                <td class="px-3.5 py-3 text-center font-bold text-[#0969da]" x-text="r.pangsa"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                <span>Menampilkan 1-5 dari 5 data unit kerja</span>
            </div>
        </section>

    </div>

</div>

<!-- SCRIPT MANAGEMENT ALPINE.JS -->
<script>
    function riwayatLaporanManager() {
        return {
            tab: 'laporan', // Tab default
            searchQuery: '',
            selectedActivity: 'semua',
            selectedStatus: 'semua',
            jurusanSearch: '',

            // Data Dummy Riwayat
            rows: [
                { time: '16 Jan 2026, 13:00', name: 'Pengadaan Laptop untuk Laboratorium TI', user: 'Riyan Sohibul Anam · TIK', rev: 'Revisi ke-0', activity: 'Pemeriksaan oleh bagian perencanaan', status: 'Ditolak' },
                { time: '16 Jan 2026, 13:00', name: 'Pengadaan Laptop untuk Laboratorium TI', user: 'Riyan Sohibul Anam · TIK', rev: 'Revisi ke-0', activity: 'Pemeriksaan oleh bagian perencanaan', status: 'Disetujui' },
            ],

            // Data Distribusi Jurusan
            jurusanList: [
                { kode: "TIK", nama: "Jurusan Teknik Informatika dan Komputer", jumlah: "1 Berkas", nilai: "Rp 240.000.000", pangsa: "84.2%" },
                { kode: "TM", nama: "Jurusan Teknik Mesin", jumlah: "0 Berkas", nilai: "Rp 0", pangsa: "0%" },
                { kode: "TS", nama: "Jurusan Teknik Sipil", jumlah: "0 Berkas", nilai: "Rp 0", pangsa: "0%" },
                { kode: "TE", nama: "Jurusan Teknik Elektro", jumlah: "1 Berkas", nilai: "Rp 45.000.000", pangsa: "15.8%" },
                { kode: "TGP", nama: "Jurusan Teknik Grafika Penerbitan", jumlah: "0 Berkas", nilai: "Rp 0", pangsa: "0%" },
            ],

            init() {},

            get filteredRows() {
                return this.rows.filter(r => {
                    const matchQ = !this.searchQuery || (r.name + r.user).toLowerCase().includes(this.searchQuery.toLowerCase());
                    const matchA = this.selectedActivity === 'semua' || r.activity === this.selectedActivity;
                    const matchS = this.selectedStatus === 'semua' || r.status === this.selectedStatus;
                    return matchQ && matchA && matchS;
                });
            },

            get filteredJurusan() {
                return this.jurusanList.filter(r => 
                    (r.kode + r.nama).toLowerCase().includes(this.jurusanSearch.toLowerCase())
                );
            },

            applyFilter() {},
            resetFilter() {
                this.searchQuery = '';
                this.selectedActivity = 'semua';
                this.selectedStatus = 'semua';
            }
        };
    }
</script>
@endsection