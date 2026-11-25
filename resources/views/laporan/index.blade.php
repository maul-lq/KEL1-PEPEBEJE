{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : View Blade ini rancang sebagai antarmuka SPA Riwayat Aktivitas dan Laporan
              Keuangan modular berbasis RBAC lengkap dengan switcher sub-modul, filter adaptif,
              paginasi klien, serta ekspor PDF dan CSV simultan.
--}}
@extends('layouts.app')

@section('title', 'Riwayat dan Laporan - SIM-PBJ Politeknik Negeri Jakarta')

{{--
   Seksi Konten Halaman Riwayat dan Laporan Terpadu (`laporan.index`).

  Tujuan: membungkus konfigurasi SSR awal dari `LaporanModularService` ke `data-config` untuk
          diambil komponen Alpine `laporanApp` agar pengguna multi-peran dapat berpindah sub-modul
          dan memfilter laporan tanpa pemuatan ulang halaman penuh.
  Parameter (Data dari LaporanController):
    - `$activeTab`, `$riwayatList`, `$modularPayload`, `$canDownload`, `$userUnitCode`, `$tahun`, `$bulan`, `$kodeUnit`.
  Nilai kembali: Halaman HTML SPA interaktif riwayat audit dan rekap laporan modular RBAC.
  Contoh pemanggilan: `return view('laporan.index', compact(...));`
--}}
@section('content')
@php
    $laporanConfig = [
        'initialTab' => $activeTab,
        'riwayatData' => $riwayatList,
        'modularPayload' => $modularPayload,
        'canDownload' => (bool) $canDownload,
        'userUnitCode' => (string) ($userUnitCode ?? ''),
        'userUnitName' => (string) ($userUnitName ?? ''),
        'endpointUrl' => route('laporan.index'),
        'pdfUrl' => route('laporan.unduh.pdf'),
        'csvUrl' => route('laporan.unduh.csv'),
        'defaultYear' => (string) date('Y'),
        'currentYear' => $tahun !== '' ? (string) $tahun : 'all',
        'currentMonth' => (string) ($bulan ?? ''),
        'currentUnit' => (string) ($kodeUnit ?? ''),
        'currentStatus' => (string) ($modularPayload['filters_applied']['status'] ?? ''),
        'currentRoleFilter' => (string) ($modularPayload['filters_applied']['role_filter'] ?? ''),
        'currentSearch' => (string) ($modularPayload['filters_applied']['q'] ?? ''),
    ];
@endphp

<div 
    x-data="laporanApp"
    data-config="{{ json_encode($laporanConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
    class="mx-auto max-w-[1780px] space-y-6 pb-12"
>
    {{-- ===================================================================== --}}
    {{-- HEADER HALAMAN (FIGMA 515:867 & 515:868)                               --}}
    {{-- ===================================================================== --}}
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-[#213153] tracking-tight mt-1.5">
                Riwayat dan Laporan
            </h1>
            <span class="sr-only">Laporan Rekapitulasi Anggaran</span>
            <span class="sr-only">Monitoring 8 Tahap</span>
        </div>

        {{-- Quick Refresh Button --}}
        <div class="flex items-center gap-2 self-start md:self-auto">
            <button
                type="button"
                @click="resetAllFilters()"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm"
                title="Muat ulang dan reset seluruh filter"
            >
                <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Segarkan Data</span>
            </button>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB SWITCHER (FIGMA 537:3280 & 537:3281)                              --}}
    {{-- ===================================================================== --}}
    <div class="flex items-center gap-2 border-b border-slate-200">
        {{-- Tab 1: Riwayat Aktivitas --}}
        <button
            type="button"
            @click="switchTab('riwayat')"
            :class="activeTab === 'riwayat' 
                ? 'border-[#0969da] text-[#0969da] bg-white font-extrabold shadow-sm' 
                : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50 font-semibold'"
            class="group inline-flex items-center gap-2.5 px-5 py-3 text-xs sm:text-sm border-b-2 rounded-t-xl transition-all"
        >
            <div 
                :class="activeTab === 'riwayat' ? 'bg-[#0969da] text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200'"
                class="size-6 rounded-lg flex items-center justify-center transition-colors"
            >
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 8v4l3 3" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="9"/>
                </svg>
            </div>
            <span>Riwayat Aktivitas</span>
            <span 
                x-text="filteredRiwayat.length"
                :class="activeTab === 'riwayat' ? 'bg-[#0969da]/10 text-[#0969da]' : 'bg-slate-100 text-slate-600'"
                class="px-2 py-0.5 rounded-full text-[11px] font-bold"
            ></span>
        </button>

        {{-- Tab 2: Laporan --}}
        <button
            type="button"
            @click="switchTab('laporan')"
            :class="activeTab === 'laporan' 
                ? 'border-[#0969da] text-[#0969da] bg-white font-extrabold shadow-sm' 
                : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50 font-semibold'"
            class="group inline-flex items-center gap-2.5 px-5 py-3 text-xs sm:text-sm border-b-2 rounded-t-xl transition-all"
        >
            <div 
                :class="activeTab === 'laporan' ? 'bg-[#0969da] text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200'"
                class="size-6 rounded-lg flex items-center justify-center transition-colors"
            >
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="3" width="18" height="18" rx="2" stroke-linecap="round"/>
                    <path d="M9 17V9M15 17v-4M3 13h18" stroke-linecap="round"/>
                </svg>
            </div>
            <span>Laporan</span>
            <span 
                x-text="filteredLaporan.length"
                :class="activeTab === 'laporan' ? 'bg-[#0969da]/10 text-[#0969da]' : 'bg-slate-100 text-slate-600'"
                class="px-2 py-0.5 rounded-full text-[11px] font-bold"
            ></span>
        </button>
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 1 CONTENT: RIWAYAT AKTIVITAS (FIGMA 515:863)                       --}}
    {{-- ===================================================================== --}}
    <div x-show="activeTab === 'riwayat'" x-cloak class="space-y-6">

        {{-- FILTER BAR RIWAYAT (FIGMA 537:3273 / FRAME 248) --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-end">
                
                {{-- Search Box (Figma Frame 85 / Rectangle 57) --}}
                <div class="md:col-span-5 space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">Pencarian</label>
                    <div class="relative flex items-center">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input
                            type="text"
                            x-model="riwayatSearch"
                            @input.debounce.300ms="riwayatPage = 1; syncUrl()"
                            placeholder="Cari nomor surat atau judul pengajuan..."
                            class="w-full h-10 pl-10 pr-24 rounded-xl border border-slate-200 bg-white text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                        />
                        <button
                            type="button"
                            @click="riwayatPage = 1; syncUrl()"
                            class="absolute right-1.5 h-7 px-3 rounded-lg bg-[#0969da] text-white text-[11px] font-bold hover:bg-blue-700 transition"
                        >
                            Cari
                        </button>
                    </div>
                </div>

                {{-- Filter Jenis Aktivitas (Figma Frame 222) --}}
                <div class="md:col-span-2 space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">Jenis Aktivitas</label>
                    <select
                        x-model="riwayatFilterAktivitas"
                        @change="riwayatPage = 1; syncUrl()"
                        class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                    >
                        <option value="">Semua Aktivitas</option>
                        <option value="Pengajuan">Pengajuan</option>
                        <option value="Persetujuan">Persetujuan</option>
                        <option value="Penolakan">Penolakan</option>
                        <option value="Revisi">Revisi</option>
                        <option value="Anggaran">Anggaran & Pos Anggaran</option>
                        <option value="Kontrak">Dokumen Kontrak</option>
                        <option value="Pengadaan Langsung (PP)">Pengadaan Langsung</option>
                        <option value="Penerimaan">Penerimaan & Serah Terima</option>
                        <option value="Pembayaran">Pembayaran & Pencairan</option>
                        <option value="Sistem">Sistem & Pengguna</option>
                    </select>
                </div>

                {{-- Filter Waktu (Figma Frame 231) --}}
                <div class="md:col-span-2 space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">Filter Waktu</label>
                    <select
                        x-model="riwayatFilterWaktu"
                        @change="riwayatPage = 1; syncUrl()"
                        class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                    >
                        <option value="">Semua Waktu</option>
                        <option value="hari_ini">Hari Ini</option>
                        <option value="minggu_ini">Minggu Ini</option>
                        <option value="bulan_ini">Bulan Ini</option>
                        <option value="tahun_ini">Tahun {{ $tahun }}</option>
                    </select>
                </div>

                {{-- Filter Status (Figma Frame 237) --}}
                <div class="md:col-span-2 space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">Filter Status</label>
                    <select
                        x-model="riwayatFilterStatus"
                        @change="riwayatPage = 1; syncUrl()"
                        class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                    >
                        <option value="">Semua Status</option>
                        <option value="Disetujui">Disetujui</option>
                        <option value="Ditolak">Ditolak</option>
                        <option value="Dalam Proses">Dalam Proses</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                </div>

                {{-- Tombol Reset (Figma Frame 221 / Rectangle 72) --}}
                <div class="md:col-span-1">
                    <button
                        type="button"
                        @click="resetRiwayatFilter()"
                        class="w-full h-10 flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 hover:border-slate-300 transition"
                        title="Reset seluruh filter riwayat"
                    >
                        <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- TABEL RIWAYAT AKTIVITAS (FIGMA 535:3258 & 526:2902) --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/90 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                            <th class="py-3.5 px-4 sm:px-6 w-44">Waktu</th>
                            <th class="py-3.5 px-4 sm:px-6">Nama Pengadaan</th>
                            <th class="py-3.5 px-4 sm:px-6 w-32 text-center">Revisi</th>
                            <th class="py-3.5 px-4 sm:px-6 w-72">Aktivitas</th>
                            <th class="py-3.5 px-4 sm:px-6 w-36 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <template x-for="(item, idx) in paginatedRiwayat" :key="item.id || idx">
                            <tr
                                @click="if (item.link) window.location.href = item.link"
                                :class="item.link ? 'hover:bg-blue-50/60 cursor-pointer' : 'hover:bg-slate-50/60'"
                                class="transition-colors group"
                                :title="item.link ? ('Klik untuk membuka detail ' + (item.nomor_surat || item.nama_pengadaan)) : (item.nomor_surat || item.nama_pengadaan)"
                            >
                                {{-- Kolom Waktu --}}
                                <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <svg class="size-3.5 text-slate-400 group-hover:text-[#0969da] transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M12 6v6l4 2"/>
                                        </svg>
                                        <span class="font-medium text-slate-700" x-text="item.waktu"></span>
                                    </div>
                                </td>

                                {{-- Kolom Nama Pengadaan --}}
                                <td class="py-3.5 px-4 sm:px-6">
                                    <div class="font-bold text-[#213153] group-hover:text-[#0969da] transition-colors line-clamp-2" x-text="item.nama_pengadaan"></div>
                                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400 font-mono">
                                        <span x-text="item.nomor_surat || '-'"></span>
                                        <template x-if="item.kode_unit">
                                            <span class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 font-sans font-semibold text-[10px]" x-text="item.kode_unit"></span>
                                        </template>
                                    </div>
                                </td>

                                {{-- Kolom Revisi --}}
                                <td class="py-3.5 px-4 sm:px-6 text-center whitespace-nowrap">
                                    <span 
                                        :class="item.revisi.includes('Revisi ke-0') ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border"
                                        x-text="item.revisi"
                                    ></span>
                                </td>

                                {{-- Kolom Aktivitas --}}
                                <td class="py-3.5 px-4 sm:px-6">
                                    <div class="text-slate-700 font-medium line-clamp-2" x-text="item.aktivitas"></div>
                                    <div class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold mt-0.5" x-text="item.jenis_aktivitas"></div>
                                </td>

                                {{-- Kolom Status --}}
                                <td class="py-3.5 px-4 sm:px-6 text-center whitespace-nowrap">
                                    <span
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border-emerald-200': item.status === 'Disetujui' || item.status === 'Selesai',
                                            'bg-rose-50 text-rose-700 border-rose-200': item.status === 'Ditolak',
                                            'bg-blue-50 text-[#0969da] border-blue-200': item.status === 'Dalam Proses'
                                        }"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                    >
                                        <template x-if="item.status === 'Disetujui' || item.status === 'Selesai'">
                                            <svg class="size-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </template>
                                        <template x-if="item.status === 'Ditolak'">
                                            <svg class="size-3 text-rose-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </template>
                                        <template x-if="item.status === 'Dalam Proses'">
                                            <svg class="size-3 text-[#0969da]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                        </template>
                                        <span x-text="item.status"></span>
                                    </span>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <template x-if="filteredRiwayat.length === 0">
                            <tr>
                                <td colspan="5" class="py-12 px-6 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="size-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 18m-4.5-4.5l-4.5 4.5m4.5-4.5l4.5 4.5M3 13.5V6a2.25 2.25 0 012.25-2.25h8.25a2.25 2.25 0 012.25 2.25v7.5"/>
                                            </svg>
                                        </div>
                                        <p class="text-sm font-bold text-slate-700">Belum ada data riwayat yang sesuai</p>
                                        <button
                                            type="button"
                                            @click="resetRiwayatFilter()"
                                            class="mt-3.5 px-4 py-1.5 text-xs font-bold text-[#0969da] bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                                        >
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION RIWAYAT (FIGMA 515:906) --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50/50">
                <div class="text-xs text-slate-500 font-medium">
                    Menampilkan 
                    <span class="font-bold text-slate-800" x-text="riwayatStartIndex"></span> 
                    - 
                    <span class="font-bold text-slate-800" x-text="riwayatEndIndex"></span> 
                    dari 
                    <span class="font-bold text-slate-800" x-text="filteredRiwayat.length"></span> 
                    data riwayat
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="if (riwayatPage > 1) { riwayatPage--; syncUrl(); }"
                        :disabled="riwayatPage <= 1"
                        :class="riwayatPage <= 1 ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-sm'"
                        class="px-3 py-1.5 text-xs font-bold border border-slate-200 rounded-lg transition"
                    >
                        Sebelumnya
                    </button>
                    <span class="px-3 py-1.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg shadow-xs">
                        Halaman <span x-text="riwayatPage"></span> / <span x-text="totalRiwayatPages"></span>
                    </span>
                    <button
                        type="button"
                        @click="if (riwayatPage < totalRiwayatPages) { riwayatPage++; syncUrl(); }"
                        :disabled="riwayatPage >= totalRiwayatPages"
                        :class="riwayatPage >= totalRiwayatPages ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-sm'"
                        class="px-3 py-1.5 text-xs font-bold border border-slate-200 rounded-lg transition"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 2 CONTENT: LAPORAN (FIGMA 526:3083)                                --}}
    {{-- ===================================================================== --}}
    <div x-show="activeTab === 'laporan'" x-cloak class="space-y-6">

        {{-- FILTER BAR LAPORAN ADAPTIF RBAC --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div class="flex flex-wrap items-end gap-3.5 flex-1">

                    {{-- Filter Tahun Anggaran --}}
                    <div x-show="currentFiltersConfig.tahun" class="space-y-1.5 min-w-[160px] flex-1 sm:flex-none sm:w-44">
                        <label class="block text-xs font-bold text-slate-700">Tahun Anggaran</label>
                        <select
                            x-model="laporanFilterTahun"
                            @change="onQueryFilterChange()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                        >
                            <option value="all">Semua Tahun</option>
                            @php $curYear = (int) date('Y'); @endphp
                            @for($y = $curYear + 1; $y >= $curYear - 3; $y--)
                                <option value="{{ $y }}">
                                    Tahun {{ $y }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    {{-- Filter Bulan Pengadaan --}}
                    <div x-show="currentFiltersConfig.bulan" class="space-y-1.5 min-w-[180px] flex-1 sm:flex-none sm:w-52">
                        <label class="block text-xs font-bold text-slate-700">Bulan Pengadaan</label>
                        <select
                            x-model="laporanFilterBulan"
                            @change="onQueryFilterChange()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                        >
                            <option value="">Semua Bulan (Januari - Desember)</option>
                            @php
                                $daftarBulan = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                            @endphp
                            @foreach($daftarBulan as $num => $namaB)
                                <option value="{{ $num }}">
                                    {{ $namaB }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Jurusan / Unit Kerja (Bebas Lintas Unit) --}}
                    <div x-show="currentFiltersConfig.unit === 'all'" class="space-y-1.5 min-w-[210px] flex-1 sm:max-w-xs">
                        <label class="block text-xs font-bold text-slate-700">Jurusan / Unit Kerja</label>
                        <select
                            x-model="laporanFilterUnit"
                            @change="onQueryFilterChange()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                        >
                            <option value="">Semua Jurusan & Unit Kerja</option>
                            @foreach($units as $u)
                                <option value="{{ $u->kode_unit }}">
                                    {{ $u->kode_unit }} - {{ $u->nama_unit }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Jurusan / Unit Kerja (Terkunci ke Unit Sendiri) --}}
                    <div x-show="currentFiltersConfig.unit === 'locked_own'" class="space-y-1.5 min-w-[210px] flex-1 sm:max-w-xs">
                        <label class="block text-xs font-bold text-slate-700">Jurusan / Unit Kerja</label>
                        <select
                            disabled
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs text-slate-500 transition cursor-not-allowed"
                        >
                            <option selected>{{ $userUnitCode !== '' ? $userUnitCode . ' - ' . $userUnitName : 'Unit Kerja Pengguna' }}</option>
                        </select>
                    </div>

                    {{-- Filter Status Dinamis per Modul --}}
                    <div x-show="(currentFiltersConfig.status_options || []).length > 0" class="space-y-1.5 min-w-[180px] flex-1 sm:flex-none sm:w-52">
                        <label class="block text-xs font-bold text-slate-700" x-text="currentFiltersConfig.status_label || 'Status'"></label>
                        <select
                            x-model="laporanFilterStatus"
                            @change="onQueryFilterChange()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                        >
                            <option value="">Semua Status</option>
                            <template x-for="opt in (currentFiltersConfig.status_options || [])" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="currentFiltersConfig.has_role_filter && rolesList.length > 0" class="space-y-1.5 min-w-[180px] flex-1 sm:flex-none sm:w-52">
                        <label class="block text-xs font-bold text-slate-700">Peran / Hak Akses</label>
                        <select
                            x-model="laporanFilterRole"
                            @change="onQueryFilterChange()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 transition"
                        >
                            <option value="">Semua Peran</option>
                            <template x-for="r in rolesList" :key="r.value">
                                <option :value="r.value" x-text="r.label"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Aksi Filter & Tombol Unduh Laporan Tunggal (PDF + CSV Simultan) --}}
                <div class="flex items-center gap-2 self-start lg:self-end shrink-0">
                    <span
                        x-show="isLoadingLaporan"
                        x-cloak
                        class="text-xs font-semibold text-slate-500 px-2"
                    >
                        Memuat...
                    </span>

                    <button
                        type="button"
                        @click="resetLaporanFilter()"
                        class="h-10 px-4 flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 hover:border-slate-300 transition cursor-pointer"
                        title="Reset filter laporan"
                    >
                        <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </button>

                    @if($canDownload)
                        <button
                            type="button"
                            @click="downloadLaporan()"
                            :disabled="isDownloading || activeModuleKeys.length === 0"
                            class="h-10 px-5 flex items-center gap-2 rounded-xl bg-[#0969da] hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-xs font-bold text-white shadow-sm transition cursor-pointer"
                            title="Unduh laporan (PDF dan Data CSV)"
                        >
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span x-text="isDownloading ? downloadFeedback : 'Unduh Laporan'">Unduh Laporan</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- 6 KARTU RINGKASAN KEUANGAN / SISTEM + TAB MODUL RBAC --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm space-y-4">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="size-7 rounded-lg bg-blue-50 text-[#0969da] flex items-center justify-center shrink-0">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-extrabold text-[#213153]" x-text="ringkasanTitle">
                        {{ $modularPayload['current_view']['ringkasan_title'] ?? 'Ringkasan Realisasi & Pengadaan' }}
                    </h2>
                </div>

                <template x-if="moduleTabs.length > 1">
                    <div class="flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                        <template x-for="tab in moduleTabs" :key="'ringkasan-' + tab.key">
                            <button
                                type="button"
                                @click="switchModule(tab.key)"
                                :class="selectedModule === tab.key
                                    ? 'bg-[#0969da] text-white border-[#0969da] shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-slate-800'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-bold transition cursor-pointer"
                                x-text="tab.label"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>

            <template x-if="activeModuleKeys.length === 0">
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-6 text-center">
                    <p class="text-sm font-bold text-slate-700">Belum ada modul laporan yang aktif pada akun Anda</p>
                    <p class="text-xs text-slate-500 mt-1">Hubungi Admin apabila peran Anda memerlukan akses laporan.</p>
                </div>
            </template>

            <div x-show="laporanCards.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="(card, cIdx) in laporanCards" :key="selectedModule + '-card-' + cIdx">
                    <div class="relative overflow-hidden rounded-xl border border-slate-200/80 bg-slate-50/50 p-4 hover:border-[#0969da]/40 hover:bg-white transition-all shadow-xs group">
                        <div class="flex items-center gap-3.5">
                            <div class="size-11 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center text-[#0969da] shadow-xs group-hover:scale-105 transition-transform">
                                <svg class="size-5.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-slate-500 truncate" x-text="card.label"></p>
                                <p class="text-xl sm:text-2xl font-black text-[#213153] tracking-tight mt-0.5 truncate" x-text="card.val"></p>
                                <p class="text-[11px] text-slate-400 mt-0.5 truncate" x-text="card.sub"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- TABEL LAPORAN RINCIAN + TAB MODUL RBAC --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm space-y-4">

            {{-- Header Tabel & Input Search --}}
            <div class="flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="size-7 rounded-lg bg-blue-50 text-[#0969da] flex items-center justify-center shrink-0">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-extrabold text-[#213153]" x-text="tableTitle">
                            {{ $modularPayload['current_view']['table_title'] ?? 'Rincian Data Laporan' }}
                        </h3>
                    </div>

                    {{-- Input Search Tabel Laporan --}}
                    <div class="relative w-full sm:w-64">
                        <input
                            type="text"
                            x-model="laporanSearch"
                            @input.debounce.300ms="onSearchInput()"
                            placeholder="Cari dalam tabel laporan..."
                            class="w-full h-9 pl-8 pr-3 rounded-lg border border-slate-200 bg-white text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da]/20 transition"
                        />
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <template x-if="moduleTabs.length > 1">
                    <div class="flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                        <template x-for="tab in moduleTabs" :key="'table-' + tab.key">
                            <button
                                type="button"
                                @click="switchModule(tab.key)"
                                :class="selectedModule === tab.key
                                    ? 'bg-[#0969da] text-white border-[#0969da] shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-slate-800'"
                                class="px-3 py-1.5 rounded-lg border text-xs font-bold transition cursor-pointer"
                                x-text="tab.label"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Table Grid --}}
            <div class="rounded-xl border border-slate-200/80 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/90 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                                <template x-for="(col, cIdx) in tableColumns" :key="selectedModule + '-col-' + cIdx">
                                    <th
                                        :class="{
                                            'w-16 text-center': cIdx === 0,
                                            'w-36': cIdx === 1,
                                            'text-right': (col || '').includes('Pagu') || (col || '').includes('Nilai') || (col || '').includes('HPS') || (col || '').includes('Harga') || (col || '').includes('Saldo') || (col || '').includes('Nominal') || (col || '').includes('%'),
                                            'text-center': (col || '').includes('Status') || (col || '').includes('Tahun') || (col || '').includes('Jumlah') || cIdx === 0
                                        }"
                                        class="py-3 px-4"
                                        x-text="col"
                                    ></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            <template x-for="(row, rIdx) in paginatedLaporan" :key="selectedModule + '-row-' + rIdx">
                                <tr
                                    @click="if (row.link) window.location.href = row.link"
                                    :class="row.link ? 'hover:bg-blue-50/50 cursor-pointer' : 'hover:bg-slate-50/60'"
                                    class="transition-colors group"
                                >
                                    {{-- No --}}
                                    <td class="py-3 px-4 text-center font-bold text-slate-400 group-hover:text-[#0969da]" x-text="row.no"></td>

                                    {{-- Col 1 --}}
                                    <td class="py-3 px-4 font-mono font-bold text-slate-800" x-text="row.col1"></td>

                                    {{-- Col 2 --}}
                                    <td class="py-3 px-4 font-semibold text-[#213153] group-hover:text-[#0969da]" x-text="row.col2"></td>

                                    {{-- Col 3 --}}
                                    <td
                                        :class="{
                                            'text-center': (tableColumns[3] || '').includes('Tahun') || (tableColumns[3] || '').includes('Status') || (tableColumns[3] || '').includes('Jumlah'),
                                            'text-right': (row.col3 || '').startsWith('Rp ') || (row.col3 || '').endsWith('%'),
                                            'font-medium text-slate-600': true
                                        }"
                                        class="py-3 px-4"
                                        x-text="row.col3"
                                    ></td>

                                    {{-- Col 4 --}}
                                    <td
                                        :class="{
                                            'text-right font-mono font-bold text-slate-800': (row.col4 || '').startsWith('Rp ') || (row.col4 || '').endsWith('%'),
                                            'font-medium text-slate-700': !(row.col4 || '').startsWith('Rp ') && !(row.col4 || '').endsWith('%')
                                        }"
                                        class="py-3 px-4"
                                        x-text="row.col4"
                                    ></td>

                                    {{-- Col 5 --}}
                                    <td
                                        :class="{
                                            'text-right font-mono font-bold text-[#0969da]': (row.col5 || '').startsWith('Rp ') || (row.col5 || '').endsWith('%'),
                                            'text-center': !(row.col5 || '').startsWith('Rp ') && !(row.col5 || '').endsWith('%')
                                        }"
                                        class="py-3 px-4"
                                    >
                                        <template x-if="(row.col5 || '').endsWith('%') || (row.col5 || '').startsWith('Rp ')">
                                            <span class="font-bold text-[#0969da]" x-text="row.col5"></span>
                                        </template>
                                        <template x-if="!(row.col5 || '').endsWith('%') && !(row.col5 || '').startsWith('Rp ')">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                                                :class="statusBadgeClass(row.col5)"
                                                x-text="row.col5"
                                            ></span>
                                        </template>
                                    </td>
                                </tr>
                            </template>

                            {{-- Empty State Laporan --}}
                            <template x-if="filteredLaporan.length === 0">
                                <tr>
                                    <td :colspan="Math.max(1, tableColumns.length)" class="py-10 px-6 text-center text-slate-500">
                                        <p class="text-sm font-bold text-slate-700">Tidak ada data laporan yang cocok</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION LAPORAN (FIGMA 538:3788 & 538:3805) --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-6 py-3.5 border-t border-slate-200 bg-slate-50/50">
                    <div class="text-xs text-slate-500 font-medium">
                        Menampilkan 
                        <span class="font-bold text-slate-800" x-text="laporanStartIndex"></span> 
                        - 
                        <span class="font-bold text-slate-800" x-text="laporanEndIndex"></span> 
                        dari 
                        <span class="font-bold text-slate-800" x-text="filteredLaporan.length"></span> 
                        data
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="if (laporanPage > 1) { laporanPage--; syncUrl(); }"
                            :disabled="laporanPage <= 1"
                            :class="laporanPage <= 1 ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-sm'"
                            class="px-3 py-1.5 text-xs font-bold border border-slate-200 rounded-lg transition"
                        >
                            Sebelumnya
                        </button>
                        <span class="px-3 py-1.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg shadow-xs">
                            Halaman <span x-text="laporanPage"></span> / <span x-text="totalLaporanPages"></span>
                        </span>
                        <button
                            type="button"
                            @click="if (laporanPage < totalLaporanPages) { laporanPage++; syncUrl(); }"
                            :disabled="laporanPage >= totalLaporanPages"
                            :class="laporanPage >= totalLaporanPages ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-sm'"
                            class="px-3 py-1.5 text-xs font-bold border border-slate-200 rounded-lg transition"
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
