@extends('layouts.app')

@section('title', 'Tracking Pengajuan - SIM-PBJ')

@section('content')
@php
   $canCreate = in_array(auth()->user()->role ?? '', ['perencanaan', 'user']);
$isAdmin   = strtolower(auth()->user()->role ?? '') === 'admin';
$canDelete = $isAdmin || $canCreate;

    // Susun data pengajuan + riwayat di server, lalu kirim ke Alpine lewat @json
    // (aman dari tanda kutip / baris baru, tidak perlu addslashes lagi).
    $trackingItems = collect($applications ?? [])->map(function ($app) {
        $nomor  = $app->nomor_surat ?? $app->number ?? ($app->id ?? '-');
        $tgl    = !empty($app->tanggal_pengajuan) ? \Carbon\Carbon::parse($app->tanggal_pengajuan) : null;
        $events = [];

        // 1) Log awal pengajuan
        $events[] = [
            'ts' => $tgl ? $tgl->timestamp : 0,
            'time' => $tgl ? $tgl->format('d M Y - H:i') : '-',
            'title' => 'Pengajuan Berhasil Dibuat',
            'subtitle' => 'Form telah disampaikan ke verifikator',
            'badge' => 'Informasi', 'type' => 'info', 'badge_type' => 'info',
        ];

        // 2) Riwayat umum (jika relasi histories ada)
        foreach (($app->histories ?? []) as $h) {
            $raw  = strtolower((string) ($h->status ?? ''));
            $type = in_array($raw, ['disetujui', 'selesai']) ? 'success'
                  : (in_array($raw, ['ditolak']) ? 'danger'
                  : (in_array($raw, ['revisi', 'perlu_revisi']) ? 'warning' : 'info'));
            $at = !empty($h->created_at) ? \Carbon\Carbon::parse($h->created_at) : null;
            $events[] = [
                'ts' => $at ? $at->timestamp : 0,
                'time' => $at ? $at->format('d M Y - H:i') : '-',
                'title' => (string) ($h->status_title ?? $h->keterangan ?? 'Pembaruan Status'),
                'subtitle' => (string) ($h->keterangan ?? ''),
                'badge' => ucfirst(str_replace('_', ' ', $h->status ?? 'Informasi')),
                'type' => $type, 'badge_type' => $type,
            ];
        }

        // 3) Hasil verifikasi paralel yang sudah diputuskan
        foreach (($app->verifikasiParalel ?? []) as $vp) {
            if (empty($vp->tanggal_verifikasi)) { continue; }
            $at   = \Carbon\Carbon::parse($vp->tanggal_verifikasi);
            $st   = strtolower((string) $vp->status_verifikasi);
            $type = $st === 'disetujui' ? 'success' : ($st === 'ditolak' ? 'danger' : 'info');
            $events[] = [
                'ts' => $at->timestamp,
                'time' => $at->format('d M Y - H:i'),
                'title' => 'Verifikasi ' . ucwords(str_replace('_', ' ', $vp->peran_verifikator)),
                'subtitle' => (string) ($vp->catatan ?: (data_get($vp, 'user.nama') ?? '')),
                'badge' => ucfirst($st ?: 'Informasi'),
                'type' => $type, 'badge_type' => $type,
            ];
        }

        usort($events, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        foreach ($events as $i => &$e) { $e['id'] = $i + 1; unset($e['ts']); }
        unset($e);

        return [
            'id'           => (string) $nomor,
            'nomor'        => (string) $nomor,
            'fileName'     => str_replace(['/', '\\'], '-', (string) $nomor) . '.pdf',
            'judul'        => (string) ($app->perihal ?? $app->nama_pengadaan ?? $app->title ?? 'Pengadaan'),
            'jenis'        => (string) ($app->jenis_pengadaan ?? $app->jenis ?? ''),
            'statusRaw'    => (string) ($app->status_permohonan ?? $app->status ?? 'diajukan'),
            'tanggal'      => $tgl ? $tgl->format('d M Y, H:i') : '-',
            'tanggalIso'   => $tgl ? $tgl->format('Y-m-d') : '',
            'currentTahap' => max(1, min(8, (int) ($app->tahap_saat_ini ?? 1))),
            'pemohon'      => (string) (data_get($app, 'user.nama') ?? data_get($app, 'unit.nama_unit') ?? $app->pemohon ?? 'Unit Pengaju'),
            'pagu'         => $app->estimasi_total_pagu ?? '',
            'timeline'     => $events,
        ];
    })->values();
@endphp

<div x-data="trackingManager()" @keydown.escape.window="showModalDoc = false" class="space-y-6">

    <!-- 1. HEADER HALAMAN & TOMBOL AKSI -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
           <!-- Tombol Kembali Mode Fullscreen (dengan pembungkus mb-4) -->
<div x-show="viewMode === 'fullscreen'" class="mb-4">
    <button @click="viewMode = 'split'" type="button" 
            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm hover:shadow-md transition-all duration-200">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        <span>Kembali ke Daftar Pengajuan</span>
    </button>
</div>

            <h1 class="text-2xl md:text-3xl font-bold text-[#213153] tracking-tight"
                x-text="viewMode === 'fullscreen' ? 'Detail & Tracking Pengajuan' : 'Tracking Pengajuan'"></h1>
            <p class="text-sm text-slate-500 mt-1"
               x-text="viewMode === 'fullscreen'
                    ? 'Pantau status, dokumen, dan riwayat proses pengajuan ini.'
                    : 'Pantau status pengajuan pengadaan secara real-time. Pilih salah satu pengajuan untuk melihat detailnya.'"></p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
    @if($canCreate)
        <a x-show="viewMode === 'split'" href="{{ route('permohonan.create') }}"
           class="inline-flex items-center justify-center h-10 px-5 rounded-lg bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-sm transition-colors shadow-sm gap-2">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Permohonan Baru
        </a>
    @endif

    @if($canDelete && Route::has('permohonan.destroy'))
        <button x-show="viewMode === 'fullscreen'" x-cloak @click="hapusPengajuan()" type="button"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700">
            Hapus Pengajuan
        </button>
    @endif
</div>
    </div>

    {{-- Form tersembunyi untuk hapus pengajuan (aksi dibuat dinamis oleh Alpine) --}}
    @if(Route::has('permohonan.destroy'))
        <form x-ref="deleteForm" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    <!-- 2. FILTER -->
    <div x-show="viewMode === 'split'" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">

            <!-- Pencarian + tombol Cari -->
            <div class="lg:col-span-5 flex items-center gap-2">
                <div class="relative w-full">
                    <svg class="size-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="searchQuery"
                           @keydown.enter="applyFilters()"
                           @input="if (!searchQuery) applyFilters()"
                           placeholder="Cari nomor surat atau judul pengajuan..."
                           class="w-full h-10 pl-9 pr-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                </div>
                <button @click="applyFilters()" type="button"
                        class="h-10 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-sm rounded-lg transition-colors flex items-center gap-2 shrink-0 shadow-sm">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M16 16l4.5 4.5"/></svg>
                    Cari
                </button>
            </div>

            <!-- Status -->
            <div class="lg:col-span-2">
                <select x-model="filterStatus" class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="Semua">Semua Status</option>
                    <option value="Diajukan">Diajukan</option>
                    <option value="Dalam Proses">Dalam Proses</option>
                    <option value="Menunggu Persetujuan">Menunggu Persetujuan</option>
                    <option value="Disetujui">Disetujui</option>
                    <option value="Perlu Revisi">Perlu Revisi</option>
                    <option value="Ditolak">Ditolak</option>
                    <option value="Selesai">Selesai</option>
                </select>
            </div>

            <!-- Jenis -->
            <div class="lg:col-span-2">
                <select x-model="filterJenis" class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
                    <option value="Semua">Semua Jenis</option>
                    <option value="Barang">Barang</option>
                    <option value="Jasa">Jasa</option>
                    <option value="Konstruksi">Konstruksi</option>
                </select>
            </div>

            <!-- Tanggal + Reset -->
            <div class="lg:col-span-3 flex items-center gap-2">
                <input type="date" x-model="filterTanggal"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm text-slate-600 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none">
               <button @click="resetFilters()" type="button" title="Reset Filter"
        class="h-10 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-sm rounded-lg transition-colors flex items-center justify-center gap-1.5 shrink-0 shadow-sm">
    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
    Reset
</button>
            </div>
        </div>
    </div>

    <!-- 3. KONTEN UTAMA -->

    <!-- A. MODE FULLSCREEN -->
    <template x-if="viewMode === 'fullscreen' && selectedItem">
        <div class="space-y-6">

            <!-- 5 kartu ringkasan -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Nomor Pengajuan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.nomor" :title="selectedItem.nomor"></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Nama Pengadaan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.judul" :title="selectedItem.judul"></span>
                    </div>
                </div>

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

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Tanggal Pengajuan</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.tanggal"></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 sm:pt-0 sm:pl-4 min-w-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-slate-400 block">Pemohon</span>
                        <span class="text-sm font-semibold text-[#213153] block truncate" x-text="selectedItem.pemohon" :title="selectedItem.pemohon"></span>
                    </div>
                </div>
            </div>

            <!-- Stepper 8 tahap (lingkaran selalu sejajar, label tidak terpotong) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-6">
                <h3 class="font-semibold text-lg text-[#213153]" x-text="'Tahap ' + selectedItem.currentTahap + ' dari 8'"></h3>

                <div class="overflow-x-auto pb-2">
                    <div class="relative flex items-start justify-between min-w-[780px]">
                        <!-- Garis: dari pusat lingkaran pertama ke pusat lingkaran terakhir -->
                        <div class="absolute left-12 right-12 top-5 h-1 rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-[#0969da] transition-all duration-300"
                                 :style="'width: ' + stepPercent() + '%'"></div>
                        </div>

                        <template x-for="(tahap, idx) in tahapanList" :key="idx">
                            <div class="relative z-10 flex w-24 shrink-0 flex-col items-center">
                                <div class="size-11 rounded-full flex items-center justify-center font-semibold text-base border-2 transition-all"
                                     :class="idx + 1 <= selectedItem.currentTahap
                                        ? 'bg-[#0969da] text-white border-[#0969da] shadow-md shadow-[#0969da]/30'
                                        : 'bg-white text-slate-400 border-slate-300'">
                                    <span x-text="idx + 1"></span>
                                </div>
                                <span class="mt-2 text-[11px] font-medium text-center leading-tight"
                                      :class="idx + 1 <= selectedItem.currentTahap ? 'text-[#0969da]' : 'text-slate-400'"
                                      x-text="tahap"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Dua kolom bawah -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- KIRI: Dokumen + Aksi role -->
                <div class="lg:col-span-6 space-y-6">

                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-semibold text-base text-[#213153]">Surat dan Dokumen</h3>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#0969da]">1 Dokumen</span>
                        </div>

                        <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                            <div class="size-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 font-semibold text-xs">PDF</div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-xs text-[#213153] truncate" x-text="selectedItem.judul"></div>
                                <div class="text-[11px] text-slate-500 truncate" x-text="selectedItem.fileName"></div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <template x-if="pdfUrlTemplate">
                                    <a :href="pdfUrl(selectedItem)" target="_blank" rel="noopener" title="Buka di tab baru"
                                       class="size-8 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-[#0969da] hover:border-[#0969da] transition-colors">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                </template>
                                <button @click="showModalDoc = true" type="button"
                                        class="h-8 px-3 text-xs font-semibold text-[#0969da] border border-[#0969da] bg-white rounded-lg hover:bg-blue-50 transition-colors">
                                    Lihat
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Form aksi sesuai role -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm">
                        @include('permohonan.partials.role-actions-show')
                    </div>
                </div>

                <!-- KANAN: Timeline -->
                <div class="lg:col-span-6 bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="font-semibold text-[#213153] text-base">Riwayat Kronologis dan Revisi</h3>
                    </div>

                    <div class="pl-3">
                        <div class="relative pl-6 border-l-2 border-slate-200 space-y-5 pt-1">
                            <template x-for="log in selectedItem.timeline" :key="log.id">
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

    <!-- B. MODE SPLIT -->
    <template x-if="viewMode === 'split'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- PANEL KIRI: DAFTAR -->
            <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-semibold text-base text-[#213153]">Daftar Pengajuan</h2>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-[#0969da]" x-text="filteredList.length + ' pengajuan'"></span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 max-h-[720px] overflow-y-auto">
                    <template x-for="item in filteredList" :key="item.id">
                        <div @click="selectItem(item)"
                             class="p-4 flex items-center gap-3 cursor-pointer transition-all hover:bg-slate-50"
                             :class="selectedItem && selectedItem.id === item.id ? 'bg-blue-50/60 shadow-[inset_4px_0_0_0_#0969da]' : ''">

                            <div class="size-10 rounded-full flex items-center justify-center shrink-0"
                                 :class="statusMeta(item.statusRaw).circle">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" x-html="statusMeta(item.statusRaw).icon"></svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-sm text-[#213153] truncate" x-text="item.judul"></h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 whitespace-nowrap"
                                          :class="statusMeta(item.statusRaw).pill"
                                          x-text="statusMeta(item.statusRaw).label"></span>
                                </div>
                                <div class="text-xs font-medium text-slate-400 mt-0.5 truncate" x-text="item.nomor"></div>
                                <div class="text-[10px] text-slate-400 mt-1" x-text="item.tanggal"></div>
                            </div>

                            <svg class="size-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </template>

                    <!-- List kosong -->
                    <div x-show="filteredList.length === 0" x-cloak class="p-10 text-center">
                        <p class="text-sm font-medium text-slate-500">Tidak ada pengajuan yang sesuai.</p>
                        <button @click="resetFilters()" type="button" class="mt-2 text-xs font-semibold text-[#0969da] hover:underline">Reset filter</button>
                    </div>
                </div>
            </div>

            <!-- PANEL KANAN: DETAIL -->
            <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 min-h-[600px] flex flex-col justify-between">

                <!-- Empty state -->
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

                <!-- Item terpilih -->
                <template x-if="selectedItem">
                    <div class="space-y-6">

                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-lg text-[#213153] truncate" x-text="selectedItem.judul"></h3>
                                <div class="text-xs text-slate-400 mt-0.5 truncate" x-text="selectedItem.nomor"></div>
                            </div>
                            <button @click="viewMode = 'fullscreen'" type="button"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#0969da] hover:underline shrink-0">
                                Lihat Detail
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>

                        <!-- Stepper ringkas -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="flex items-center justify-between relative">
                                <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                                <div class="absolute top-1/2 left-0 h-1 bg-[#0969da] -translate-y-1/2 z-0 transition-all duration-300" :style="'width: ' + stepPercent() + '%'"></div>

                                <template x-for="n in 8" :key="n">
                                    <div class="relative z-10 size-9 rounded-full flex items-center justify-center font-semibold text-xs border-2 transition-all"
                                         :class="n <= selectedItem.currentTahap ? 'bg-[#0969da] text-white border-[#0969da]' : 'bg-white text-slate-400 border-slate-300'"
                                         x-text="n"></div>
                                </template>
                            </div>
                        </div>

                        <!-- Timeline (ikon tidak lagi terpotong di tepi kiri) -->
                        <div class="space-y-4 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h4 class="font-semibold text-sm text-[#213153]">Riwayat Kronologis dan Revisi</h4>
                            </div>

                            <div class="max-h-[350px] overflow-y-auto pl-3 pr-1">
                                <div class="relative pl-6 border-l-2 border-slate-200 space-y-4">
                                    <template x-for="log in selectedItem.timeline" :key="log.id">
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

                <!-- Banner aksi sesuai role (hanya jika ada item terpilih) -->
                <template x-if="selectedItem">
                    <div class="mt-6 pt-4 border-t border-slate-100">
                        @include('permohonan.partials.role-actions-index')
                    </div>
                </template>

            </div>
        </div>
    </template>

    <!-- MODAL PREVIEW DOKUMEN (klik latar gelap / Esc untuk menutup) -->
    <div x-show="showModalDoc" x-cloak x-transition.opacity
         @click.self="showModalDoc = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl flex flex-col max-h-[92vh]">
            <div class="flex items-start justify-between gap-4 p-5 border-b border-slate-100">
                <div class="min-w-0">
                    <h3 class="font-semibold text-lg text-[#213153]">Surat Pengajuan Pengadaan</h3>
                    <p class="text-xs text-slate-500 truncate" x-text="selectedItem ? selectedItem.fileName : ''"></p>
                </div>
                <button @click="showModalDoc = false" type="button" class="text-slate-400 hover:text-slate-700 transition-colors" title="Tutup">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 flex-1 min-h-0">
                <template x-if="showModalDoc && selectedItem && pdfUrlTemplate">
                    <iframe :src="pdfUrl(selectedItem)" class="w-full h-[65vh] rounded-xl border border-slate-200 bg-slate-100"></iframe>
                </template>
                <template x-if="!pdfUrlTemplate">
                    <div class="h-64 rounded-xl border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-sm text-slate-400">
                        Pratinjau dokumen belum tersedia.
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-end gap-2 p-5 border-t border-slate-100">
                <template x-if="pdfUrlTemplate && selectedItem">
                    <a :href="pdfUrl(selectedItem)" target="_blank" rel="noopener"
                       class="h-10 px-4 inline-flex items-center rounded-lg border border-slate-300 bg-white text-slate-700 font-semibold text-sm hover:bg-slate-50 transition-colors">
                        Buka di Tab Baru
                    </a>
                </template>
                <button @click="showModalDoc = false" type="button"
                        class="h-10 px-5 bg-[#0969da] hover:bg-blue-700 text-white font-semibold text-sm rounded-lg transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>

</div>

<script>
    function trackingManager() {
        return {
            viewMode: 'split',
            selectedItem: null,            // null = tampilkan empty state (Figma frame 1)
            searchQuery: '',
            appliedQuery: '',              // diterapkan saat klik Cari / Enter
            filterStatus: 'Semua',
            filterJenis: 'Semua',
            filterTanggal: '',
            showModalDoc: false,

            deleteUrlTemplate: @json(Route::has('permohonan.destroy') ? route('permohonan.destroy', '__ID__') : null),
            pdfUrlTemplate:    @json(Route::has('dokumen.pdf') ? route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => '__ID__']) : null),

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

            items: @json($trackingItems),

            // ---------- Status ----------
            statusMeta(raw) {
                const key = String(raw || '').toLowerCase().trim().replace(/[\s-]+/g, '_');
                const tone = {
                    sky:     { circle: 'bg-sky-100 text-sky-600',         pill: 'bg-sky-100 text-sky-700',         icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' },
                    amber:   { circle: 'bg-amber-100 text-amber-600',     pill: 'bg-amber-100 text-amber-700',     icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
                    emerald: { circle: 'bg-emerald-100 text-emerald-600', pill: 'bg-emerald-100 text-emerald-700', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' },
                    orange:  { circle: 'bg-orange-100 text-orange-600',   pill: 'bg-orange-100 text-orange-700',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>' },
                    rose:    { circle: 'bg-rose-100 text-rose-600',       pill: 'bg-rose-100 text-rose-700',       icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
                };
                const map = {
                    diajukan:            { label: 'Diajukan',             group: 'Diajukan',             t: 'sky' },
                    reviu_paralel:       { label: 'Reviu Paralel',        group: 'Dalam Proses',         t: 'amber' },
                    diproses:            { label: 'Diproses',             group: 'Dalam Proses',         t: 'amber' },
                    dalam_proses:        { label: 'Dalam Proses',         group: 'Dalam Proses',         t: 'amber' },
                    menunggu_persetujuan:{ label: 'Menunggu Persetujuan', group: 'Menunggu Persetujuan', t: 'amber' },
                    disetujui:           { label: 'Disetujui',            group: 'Disetujui',            t: 'emerald' },
                    selesai:             { label: 'Selesai',              group: 'Selesai',              t: 'emerald' },
                    revisi:              { label: 'Perlu Revisi',         group: 'Perlu Revisi',         t: 'orange' },
                    perlu_revisi:        { label: 'Perlu Revisi',         group: 'Perlu Revisi',         t: 'orange' },
                    ditolak:             { label: 'Ditolak',              group: 'Ditolak',              t: 'rose' },
                };
                const m = map[key] || {
                    label: key ? key.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()) : 'Diproses',
                    group: 'Dalam Proses',
                    t: 'sky'
                };
                return Object.assign({ label: m.label, group: m.group }, tone[m.t]);
            },

            // ---------- Timeline ----------
            timelineDot(type) {
                return ({
                    warning: 'bg-amber-500 text-white',
                    danger:  'bg-rose-500 text-white',
                    info:    'bg-[#0969da] text-white',
                    success: 'bg-emerald-500 text-white',
                    gray:    'bg-slate-400 text-white'
                })[type] || 'bg-slate-400 text-white';
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

            // ---------- Daftar & filter ----------
            get filteredList() {
                const q = this.appliedQuery.toLowerCase();
                return this.items.filter(item => {
                    const matchSearch = !q || item.judul.toLowerCase().includes(q) || item.nomor.toLowerCase().includes(q);
                    const matchStatus = this.filterStatus === 'Semua' || this.statusMeta(item.statusRaw).group === this.filterStatus;
                    const matchJenis  = this.filterJenis === 'Semua' || item.jenis === this.filterJenis;
                    const matchTgl    = !this.filterTanggal || item.tanggalIso === this.filterTanggal;
                    return matchSearch && matchStatus && matchJenis && matchTgl;
                });
            },

            selectItem(item) { this.selectedItem = item; },
            applyFilters() { this.appliedQuery = this.searchQuery.trim(); },
            resetFilters() {
                this.searchQuery = '';
                this.appliedQuery = '';
                this.filterStatus = 'Semua';
                this.filterJenis = 'Semua';
                this.filterTanggal = '';
            },

            stepPercent() {
                if (!this.selectedItem) return 0;
                return ((this.selectedItem.currentTahap - 1) / 7) * 100;
            },

            // ---------- Dokumen & hapus ----------
            idForUrl(item) {
                // Samakan perilaku route(): garis miring pada nomor surat tidak di-encode
                return encodeURIComponent(item.nomor).replace(/%2F/gi, '/');
            },
            pdfUrl(item) {
                return this.pdfUrlTemplate ? this.pdfUrlTemplate.replace('__ID__', this.idForUrl(item)) : '#';
            },
            hapusPengajuan() {
                if (!this.deleteUrlTemplate || !this.selectedItem || !this.$refs.deleteForm) return;
                if (!confirm('Apakah Anda yakin ingin menghapus pengajuan ini? Tindakan ini tidak dapat dibatalkan.')) return;
                this.$refs.deleteForm.action = this.deleteUrlTemplate.replace('__ID__', this.idForUrl(this.selectedItem));
                this.$refs.deleteForm.submit();
            }
        };
    }
</script>
@endsection