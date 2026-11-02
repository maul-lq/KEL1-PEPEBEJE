@extends('layouts.app')

@section('title', 'Direktori Rekanan & Vendor - Unit Pengadaan PPBJ PNJ')

@section('content')
<div x-data="vendorManager()" class="space-y-6">

    {{-- 1. HEADER HALAMAN & ROLE BADGE --}}
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-xs border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                        Master Data Rekanan Penyedia
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Unit Kerja Pengadaan PPBJ</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-[#213153] mt-1">
                    Direktori Rekanan &amp; Vendor Penyedia
                </h1>
                <p class="text-xs md:text-sm text-slate-500 mt-1 max-w-3xl">
                    Basis data resmi rekanan penyedia barang dan jasa Politeknik Negeri Jakarta. Digunakan oleh Pejabat Pengadaan (PP) dan PPBJ dalam penetapan rekanan belanja langsung, non-tender, maupun tender SPSE.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <button
                    type="button"
                    @click="$dispatch('open-modal', 'modal-create-vendor')"
                    class="px-4 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20 transition-all inline-flex items-center gap-2 cursor-pointer"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    <span>+ Daftarkan Rekanan Baru</span>
                </button>
            </div>
        </div>
    </div>

    {{-- 2. NOTIFIKASI FLASH MESSAGE --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-xs font-medium text-emerald-900 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <svg class="size-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 text-sm font-bold ml-4">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-xs font-medium text-rose-900 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <svg class="size-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-700 hover:text-rose-900 text-sm font-bold ml-4">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 space-y-1 shadow-xs">
            <div class="font-bold flex items-center gap-2 text-rose-900">
                <svg class="size-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Terdapat kesalahan dalam pengisian formulir:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 3. KPI STAT CARDS (4 KOLOM) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Rekanan Terdaftar"
            :value="$stats['total_vendor'] ?? 0"
            color="navy"
            icon="document"
            subtitle="Seluruh penyedia dalam basis data"
        />

        <x-stat-card
            title="Rekanan Kemitraan Aktif"
            :value="$stats['vendor_aktif'] ?? 0"
            color="emerald"
            icon="check"
            badge="Siap Dipilih"
            badgeColor="bg-emerald-50 text-emerald-800 border-emerald-200"
            subtitle="Memenuhi syarat legalitas pengadaan"
        />

        <x-stat-card
            title="Rekanan Non-Aktif"
            :value="$stats['vendor_nonaktif'] ?? 0"
            color="rose"
            icon="bell"
            badge="Ditangguhkan"
            badgeColor="bg-rose-50 text-rose-700 border-rose-200"
            subtitle="Kemitraan dinonaktifkan sementara"
        />

        <x-stat-card
            title="Terikat Paket Pengadaan"
            :value="$stats['vendor_dalam_paket'] ?? 0"
            color="blue"
            icon="package"
            badge="Terekam"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Memiliki riwayat kontrak / paket"
        />
    </div>

    {{-- 4. FILTER & SEARCH BAR --}}
    <form method="GET" action="{{ route('vendors.index') }}" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            
            <div class="lg:col-span-6 space-y-1">
                <label for="search-input" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pencarian Rekanan</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" 
                           id="search-input"
                           name="q" 
                           value="{{ request('q') }}"
                           placeholder="Cari nama perusahaan, NPWP, NIB, PIC, atau rekening bank..." 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3.5 py-2 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                </div>
            </div>

            <div class="lg:col-span-3 space-y-1">
                <label for="status-filter" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status Keaktifan</label>
                <select id="status-filter" name="is_active" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <option value="">Semua Status Rekanan</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif Saja</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Non-Aktif Saja</option>
                </select>
            </div>

            <div class="lg:col-span-3 flex items-center gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0969da] py-2 px-3 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition-all cursor-pointer">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Cari Rekanan
                </button>
                <a href="{{ route('vendors.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white py-2 px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                    Reset
                </a>
            </div>

        </div>
    </form>

    {{-- 5. TABEL DIREKTORI REKANAN VENDOR --}}
    <div class="rounded-2xl bg-white shadow-xs border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Daftar Penyedia Barang &amp; Jasa Terdaftar</h3>
                <p class="text-xs text-slate-400 mt-0.5">Basis data rekanan resmi untuk pemenuhan pengadaan PNJ</p>
            </div>
            <span class="text-xs font-bold font-mono text-[#0969da] bg-blue-50 px-2.5 py-1 rounded-full border border-blue-100">
                {{ $vendors->total() }} Rekanan
            </span>
        </div>

        @if($vendors->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <svg class="size-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p class="text-sm font-semibold text-slate-600">Tidak ada rekanan penyedia yang sesuai kriteria pencarian.</p>
                <p class="text-xs text-slate-400 mt-1">Gunakan tombol "Daftarkan Rekanan Baru" untuk menambahkan mitra penyedia.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3.5">Perusahaan &amp; Legalitas</th>
                            <th class="px-5 py-3.5">Kontak PIC</th>
                            <th class="px-5 py-3.5">Rekening Bank (SP2D)</th>
                            <th class="px-5 py-3.5 text-center">Status Kemitraan</th>
                            <th class="px-5 py-3.5 text-center">Dokumen Legalitas</th>
                            <th class="px-5 py-3.5 text-center">Histori Paket</th>
                            <th class="px-5 py-3.5 text-right">Aksi PPBJ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                        @foreach($vendors as $v)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                
                                {{-- Kolom Perusahaan & Legalitas --}}
                                <td class="px-5 py-4">
                                    <div class="font-bold text-sm text-[#213153] leading-snug">
                                        {{ $v->nama_perusahaan }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2 font-mono">
                                        <span>NPWP: <strong class="text-slate-700">{{ $v->npwp }}</strong></span>
                                        <span>&bull;</span>
                                        <span>NIB: <strong class="text-slate-700">{{ $v->nib }}</strong></span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 line-clamp-1 max-w-xs" title="{{ $v->alamat }}">
                                        {{ $v->alamat }}
                                    </div>
                                </td>

                                {{-- Kolom Kontak PIC --}}
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-800">{{ $v->nama_pic }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1.5">
                                        <svg class="size-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        <span>{{ $v->telepon_pic }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                                        <svg class="size-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        <span class="truncate max-w-[140px]">{{ $v->email_pic }}</span>
                                    </div>
                                </td>

                                {{-- Kolom Rekening Bank --}}
                                <td class="px-5 py-4">
                                    <div class="font-bold text-xs text-[#0969da]">{{ $v->nama_bank }}</div>
                                    <div class="font-mono text-xs font-bold text-slate-800 mt-0.5">{{ $v->nomor_rekening }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">a.n. {{ $v->atas_nama }}</div>
                                </td>

                                {{-- Kolom Status Kemitraan --}}
                                <td class="px-5 py-4 text-center">
                                    <form action="{{ route('vendors.toggle-status', $v->npwp) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" 
                                                title="Klik untuk mengubah status kemitraan"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition cursor-pointer {{ $v->is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200' }}">
                                            <span class="size-1.5 rounded-full {{ $v->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            <span>{{ $v->is_active ? 'Aktif' : 'Non-Aktif' }}</span>
                                        </button>
                                    </form>
                                </td>

                                {{-- Kolom Dokumen Legalitas --}}
                                <td class="px-5 py-4 text-center">
                                    <button
                                        type="button"
                                        @click="openLegalitas({{ json_encode($v) }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-[#0969da] hover:bg-blue-100 border border-blue-200 transition cursor-pointer"
                                    >
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span>{{ $v->filesLegalitas->count() }} Berkas</span>
                                    </button>
                                </td>

                                {{-- Kolom Histori Paket --}}
                                <td class="px-5 py-4 text-center">
                                    @php $jmlPaket = $v->paketPengadaan->count(); @endphp
                                    @if($jmlPaket > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono">
                                            {{ $jmlPaket }} Paket
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400 italic">Belum ada</span>
                                    @endif
                                </td>

                                {{-- Kolom Aksi --}}
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button
                                            type="button"
                                            @click="openEdit({{ json_encode($v) }})"
                                            title="Edit Data Rekanan"
                                            class="size-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-[#0969da] hover:border-blue-300 flex items-center justify-center transition shadow-xs cursor-pointer"
                                        >
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>

                                        @if($v->paketPengadaan->count() === 0)
                                            <button
                                                type="button"
                                                @click="openDelete({{ json_encode($v) }})"
                                                title="Hapus Rekanan"
                                                class="size-8 rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 flex items-center justify-center transition shadow-xs cursor-pointer"
                                            >
                                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                disabled
                                                title="Rekanan tidak dapat dihapus karena sudah terikat paket pengadaan aktif"
                                                class="size-8 rounded-lg border border-slate-200 bg-slate-100 text-slate-300 flex items-center justify-center cursor-not-allowed"
                                            >
                                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/30">
                {{ $vendors->links() }}
            </div>
        @endif
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 1: DAFTARKAN REKANAN BARU                                       --}}
    {{-- ===================================================================== --}}
    <x-modal id="modal-create-vendor" title="Pendaftaran Rekanan Vendor Baru" subtitle="Input profil resmi perusahaan rekanan, kontak PIC, rekening perbankan, dan berkas legalitas" maxWidth="2xl">
        <form action="{{ route('vendors.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Nama Perusahaan --}}
                <div class="md:col-span-2 space-y-1">
                    <label for="nama_perusahaan" class="block text-xs font-bold text-slate-700">
                        Nama Resmi Perusahaan / Rekanan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nama_perusahaan" name="nama_perusahaan" required placeholder="Contoh: PT Mitra Cipta Solusindo" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                {{-- NPWP Perusahaan --}}
                <div class="space-y-1">
                    <label for="npwp" class="block text-xs font-bold text-slate-700">
                        Nomor Pokok Wajib Pajak (NPWP) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="npwp" name="npwp" required placeholder="Contoh: 01.234.567.8-012.000" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                {{-- NIB Perusahaan --}}
                <div class="space-y-1">
                    <label for="nib" class="block text-xs font-bold text-slate-700">
                        Nomor Induk Berusaha (NIB) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nib" name="nib" required placeholder="Contoh: 1234567890123" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                {{-- Alamat Lengkap --}}
                <div class="md:col-span-2 space-y-1">
                    <label for="alamat" class="block text-xs font-bold text-slate-700">
                        Alamat Domisili Perusahaan <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="alamat" name="alamat" rows="2" required placeholder="Alamat kantor rekanan, gedung, jalan, kota, dan kode pos..." class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>
            </div>

            {{-- Kontak PIC --}}
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3">
                <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Data Kontak Perwakilan (PIC Perusahaan)
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label for="nama_pic" class="block text-[11px] font-bold text-slate-700 mb-1">Nama Lengkap PIC <span class="text-rose-500">*</span></label>
                        <input type="text" id="nama_pic" name="nama_pic" required placeholder="Nama narahubung" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="telepon_pic" class="block text-[11px] font-bold text-slate-700 mb-1">Nomor Telepon / WA <span class="text-rose-500">*</span></label>
                        <input type="text" id="telepon_pic" name="telepon_pic" required placeholder="Contoh: 08123456789" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-mono text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="email_pic" class="block text-[11px] font-bold text-slate-700 mb-1">Email Resmi Rekanan <span class="text-rose-500">*</span></label>
                        <input type="email" id="email_pic" name="email_pic" required placeholder="pic@perusahaan.co.id" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                </div>
            </div>

            {{-- Rekening Bank Penyaluran SP2D --}}
            <div class="p-3.5 rounded-xl bg-blue-50/50 border border-blue-100 space-y-3">
                <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Informasi Rekening Perbankan (Tujuan Transfer SP2D Keuangan)
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label for="nama_bank" class="block text-[11px] font-bold text-slate-700 mb-1">Nama Bank <span class="text-rose-500">*</span></label>
                        <input type="text" id="nama_bank" name="nama_bank" required placeholder="Contoh: Bank Mandiri / BRI / BNI" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="nomor_rekening" class="block text-[11px] font-bold text-slate-700 mb-1">Nomor Rekening <span class="text-rose-500">*</span></label>
                        <input type="text" id="nomor_rekening" name="nomor_rekening" required placeholder="Contoh: 1230009876543" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="atas_nama" class="block text-[11px] font-bold text-slate-700 mb-1">Nama Pemilik Rekening <span class="text-rose-500">*</span></label>
                        <input type="text" id="atas_nama" name="atas_nama" required placeholder="Sesuai buku tabungan" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                </div>
            </div>

            {{-- Lampiran Berkas Legalitas Awal --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                <div>
                    <label for="nama_dokumen" class="block text-xs font-bold text-slate-700 mb-1">Nama Dokumen Legalitas (Opsional)</label>
                    <input type="text" id="nama_dokumen" name="nama_dokumen" placeholder="Contoh: NIB &amp; NPWP Perusahaan" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                </div>
                <div>
                    <label for="file_legalitas" class="block text-xs font-bold text-slate-700 mb-1">Unggah Berkas Legalitas (PDF/Gambar &le; 10MB)</label>
                    <input type="file" id="file_legalitas" name="file_legalitas" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                <button type="button" @click="$dispatch('close-modal', 'modal-create-vendor')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20 transition">
                    Simpan Data Rekanan
                </button>
            </div>
        </form>
    </x-modal>

    {{-- ===================================================================== --}}
    {{-- MODAL 2: EDIT DATA REKANAN VENDOR                                    --}}
    {{-- ===================================================================== --}}
    <x-modal id="modal-edit-vendor" title="Edit Data Rekanan Vendor" subtitle="Perbarui profil perusahaan, kontak perwakilan, dan rekening perbankan" maxWidth="2xl">
        <form :action="'/vendors/' + (selectedVendor ? encodeURIComponent(selectedVendor.npwp) : '')" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- NPWP (Read-only Primary Key) --}}
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">NPWP Perusahaan (Primary Key Tetap)</label>
                    <input type="text" :value="selectedVendor ? selectedVendor.npwp : ''" disabled class="w-full h-10 rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-mono font-bold text-slate-500 cursor-not-allowed">
                </div>

                {{-- NIB Perusahaan --}}
                <div class="space-y-1">
                    <label for="edit_nib" class="block text-xs font-bold text-slate-700">Nomor Induk Berusaha (NIB) <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_nib" name="nib" :value="selectedVendor ? selectedVendor.nib : ''" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                {{-- Nama Perusahaan --}}
                <div class="md:col-span-2 space-y-1">
                    <label for="edit_nama_perusahaan" class="block text-xs font-bold text-slate-700">Nama Resmi Perusahaan <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_nama_perusahaan" name="nama_perusahaan" :value="selectedVendor ? selectedVendor.nama_perusahaan : ''" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                {{-- Alamat --}}
                <div class="md:col-span-2 space-y-1">
                    <label for="edit_alamat" class="block text-xs font-bold text-slate-700">Alamat Perusahaan <span class="text-rose-500">*</span></label>
                    <textarea id="edit_alamat" name="alamat" rows="2" required x-text="selectedVendor ? selectedVendor.alamat : ''" class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>
            </div>

            {{-- Kontak PIC --}}
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3">
                <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wider">Kontak Perwakilan (PIC)</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label for="edit_nama_pic" class="block text-[11px] font-bold text-slate-700 mb-1">Nama PIC <span class="text-rose-500">*</span></label>
                        <input type="text" id="edit_nama_pic" name="nama_pic" :value="selectedVendor ? selectedVendor.nama_pic : ''" required class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="edit_telepon_pic" class="block text-[11px] font-bold text-slate-700 mb-1">Telepon PIC <span class="text-rose-500">*</span></label>
                        <input type="text" id="edit_telepon_pic" name="telepon_pic" :value="selectedVendor ? selectedVendor.telepon_pic : ''" required class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-mono text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="edit_email_pic" class="block text-[11px] font-bold text-slate-700 mb-1">Email PIC <span class="text-rose-500">*</span></label>
                        <input type="email" id="edit_email_pic" name="email_pic" :value="selectedVendor ? selectedVendor.email_pic : ''" required class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                </div>
            </div>

            {{-- Rekening Bank --}}
            <div class="p-3.5 rounded-xl bg-blue-50/50 border border-blue-100 space-y-3">
                <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wider">Rekening Bank Rekanan</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label for="edit_nama_bank" class="block text-[11px] font-bold text-slate-700 mb-1">Nama Bank <span class="text-rose-500">*</span></label>
                        <input type="text" id="edit_nama_bank" name="nama_bank" :value="selectedVendor ? selectedVendor.nama_bank : ''" required class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="edit_nomor_rekening" class="block text-[11px] font-bold text-slate-700 mb-1">Nomor Rekening <span class="text-rose-500">*</span></label>
                        <input type="text" id="edit_nomor_rekening" name="nomor_rekening" :value="selectedVendor ? selectedVendor.nomor_rekening : ''" required class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="edit_atas_nama" class="block text-[11px] font-bold text-slate-700 mb-1">Atas Nama Rekening <span class="text-rose-500">*</span></label>
                        <input type="text" id="edit_atas_nama" name="atas_nama" :value="selectedVendor ? selectedVendor.atas_nama : ''" required class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                </div>
            </div>

            {{-- Tambah Dokumen Tambahan Saat Edit --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                <div>
                    <label for="nama_dokumen_baru" class="block text-xs font-bold text-slate-700 mb-1">Tambah Dokumen Baru (Opsional)</label>
                    <input type="text" id="nama_dokumen_baru" name="nama_dokumen_baru" placeholder="Contoh: Akta Perubahan Notaris" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                </div>
                <div>
                    <label for="file_legalitas_baru" class="block text-xs font-bold text-slate-700 mb-1">Unggah Berkas Baru (PDF/Gambar &le; 10MB)</label>
                    <input type="file" id="file_legalitas_baru" name="file_legalitas_baru" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                <button type="button" @click="$dispatch('close-modal', 'modal-edit-vendor')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20 transition">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </x-modal>

    {{-- ===================================================================== --}}
    {{-- MODAL 3: KELOLA BERKAS LEGALITAS REKANAN                              --}}
    {{-- ===================================================================== --}}
    <x-modal id="modal-legalitas-vendor" title="Dokumen Legalitas Rekanan" subtitle="Daftar berkas legalitas dan form pengunggahan dokumen kualifikasi penyedia" maxWidth="xl">
        <div class="space-y-4">
            
            {{-- Info Vendor Terpilih --}}
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                <h4 class="text-xs font-bold text-[#213153]" x-text="legalitasVendor ? legalitasVendor.nama_perusahaan : ''"></h4>
                <p class="text-[11px] text-slate-500 font-mono mt-0.5">NPWP: <span x-text="legalitasVendor ? legalitasVendor.npwp : ''"></span></p>
            </div>

            {{-- Daftar Berkas yang Telah Terunggah --}}
            <div class="space-y-2">
                <span class="text-xs font-bold text-slate-700 block">Berkas Legalitas Tersimpan:</span>
                
                <template x-if="legalitasVendor && legalitasVendor.files_legalitas && legalitasVendor.files_legalitas.length > 0">
                    <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white max-h-56 overflow-y-auto">
                        <template x-for="f in legalitasVendor.files_legalitas" :key="f.id">
                            <div class="p-3 flex items-center justify-between gap-3 text-xs">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-800 truncate" x-text="f.nama_dokumen"></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">Diunggah: <span x-text="new Date(f.uploaded_at || f.created_at).toLocaleDateString('id-ID')"></span></div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a :href="'/storage/' + f.file_legalitas" target="_blank" class="px-2.5 py-1 rounded-lg bg-blue-50 text-[#0969da] hover:bg-blue-100 font-bold text-[11px] inline-flex items-center gap-1 transition">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Buka Berkas
                                    </a>
                                    <form :action="'/vendors/legalitas/' + f.id" method="POST" onsubmit="return confirm('Hapus dokumen legalitas ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="size-7 rounded-lg text-rose-500 hover:bg-rose-50 flex items-center justify-center transition cursor-pointer" title="Hapus Berkas">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!legalitasVendor || !legalitasVendor.files_legalitas || legalitasVendor.files_legalitas.length === 0">
                    <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        Belum ada dokumen legalitas kualifikasi yang diunggah untuk rekanan ini.
                    </div>
                </template>
            </div>

            {{-- Form Upload Dokumen Legalitas Tambahan --}}
            <form :action="'/vendors/' + (legalitasVendor ? encodeURIComponent(legalitasVendor.npwp) : '') + '/upload-legalitas'" method="POST" enctype="multipart/form-data" class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                @csrf
                <h5 class="text-xs font-bold text-[#213153] uppercase tracking-wider">+ Unggah Dokumen Baru</h5>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="upload_nama_dokumen" class="block text-[11px] font-bold text-slate-700 mb-1">Nama / Jenis Dokumen <span class="text-rose-500">*</span></label>
                        <input type="text" id="upload_nama_dokumen" name="nama_dokumen" required placeholder="Contoh: Akta Notaris / Sertifikat ISO" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-[#0969da]">
                    </div>
                    <div>
                        <label for="upload_file_legalitas" class="block text-[11px] font-bold text-slate-700 mb-1">File Dokumen (PDF/Gambar &le; 10MB) <span class="text-rose-500">*</span></label>
                        <input type="file" id="upload_file_legalitas" name="file_legalitas" required accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition cursor-pointer">
                        Unggah Berkas Legalitas
                    </button>
                </div>
            </form>

            <div class="flex justify-end pt-2 border-t border-slate-100">
                <button type="button" @click="$dispatch('close-modal', 'modal-legalitas-vendor')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Tutup
                </button>
            </div>
        </div>
    </x-modal>

    {{-- ===================================================================== --}}
    {{-- MODAL 4: KONFIRMASI HAPUS REKANAN VENDOR                             --}}
    {{-- ===================================================================== --}}
    <x-modal id="modal-delete-vendor" title="Konfirmasi Hapus Rekanan" subtitle="Penghapusan permanen data rekanan dari basis data sistem SIM-PBJ" maxWidth="md">
        <form :action="'/vendors/' + (deleteVendor ? encodeURIComponent(deleteVendor.npwp) : '')" method="POST" class="space-y-4">
            @csrf
            @method('DELETE')

            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 space-y-2">
                <p>
                    Apakah Anda yakin ingin menghapus rekanan <strong x-text="deleteVendor ? deleteVendor.nama_perusahaan : ''"></strong>?
                </p>
                <p class="text-[11px] text-rose-700">
                    Seluruh dokumen legalitas fisik yang terkait dengan rekanan ini juga akan dibersihkan dari server penyimpanan.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" @click="$dispatch('close-modal', 'modal-delete-vendor')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 shadow-sm transition">
                    Ya, Hapus Rekanan
                </button>
            </div>
        </form>
    </x-modal>

</div>

<script>
    function vendorManager() {
        return {
            selectedVendor: null,
            deleteVendor: null,
            legalitasVendor: null,
            openEdit(vendor) {
                this.selectedVendor = JSON.parse(JSON.stringify(vendor));
                this.$dispatch('open-modal', 'modal-edit-vendor');
            },
            openLegalitas(vendor) {
                this.legalitasVendor = vendor;
                this.$dispatch('open-modal', 'modal-legalitas-vendor');
            },
            openDelete(vendor) {
                this.deleteVendor = vendor;
                this.$dispatch('open-modal', 'modal-delete-vendor');
            }
        };
    }
</script>
@endsection
