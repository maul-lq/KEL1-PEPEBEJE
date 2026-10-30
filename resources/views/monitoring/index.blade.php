@extends('layouts.app')

@section('title', 'Detail & Tracking Saya - SIM-PBJ')

@section('content')
<div class="mx-auto max-w-[1750px] space-y-6">
    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#0969da]">Monitoring Agregat 8 Siklus</span>
            <h1 class="text-2xl lg:text-3xl font-extrabold text-[#213153] mt-1">
                {{ Auth::user()->role === 'user' ? 'Detail & Tracking Pengadaan Saya' : 'Monitoring Siklus Pengadaan Institusi' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ Auth::user()->role === 'user' ? 'Pelacakan progres berkas usulan pengadaan khusus unit Anda (' . (Auth::user()->unit->nama_unit ?? Auth::user()->kode_unit) . ')' : 'Pemantauan komprehensif alur pengadaan barang dan jasa seluruh unit Politeknik Negeri Jakarta' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('monitoring.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh Data
            </a>
        </div>
    </div>

    @php
        $selectedNomor = request('selected') ?? request('nomor_surat');
        $selectedPermohonan = $selectedNomor
            ? ($permohonanList->firstWhere('nomor_surat', $selectedNomor) ?? $permohonanList->first())
            : $permohonanList->first();

        $stageMap = [
            'diajukan' => 1,
            'reviu_paralel' => 2,
            'disetujui' => 3,
            'paket_terdaftar' => 3,
            'reviu_dokumen' => 4,
            'spk_terbit' => 5,
            'pengiriman' => 6,
            'bast_terbit' => 7,
            'pencairan' => 8,
            'selesai' => 8,
        ];

        $currentStep = $selectedPermohonan ? ($stageMap[$selectedPermohonan->status_permohonan ?? $selectedPermohonan->status] ?? 2) : 1;

        $totalPermohonan = $permohonanList->total();
        $countSelesai = $permohonanList->whereIn('status_permohonan', ['selesai', 'disetujui'])->count();
        $countProses = $permohonanList->whereNotIn('status_permohonan', ['selesai', 'ditolak', 'batal'])->count();
        $countLogs = $auditLogs->count();
    @endphp

    {{-- 4 STAT CARDS FIGMA PALETTE --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Usulan Terpantau"
            :value="$totalPermohonan"
            color="navy"
            subtitle="Data usulan pengadaan aktif"
        >
            <x-slot:icon>
                <svg class="size-6 text-[#213153]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card
            title="Dalam Proses Berjalan"
            :value="$countProses"
            color="blue"
            subtitle="Tahap 1 s.d. 7 siklus"
        >
            <x-slot:icon>
                <svg class="size-6 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card
            title="Selesai / Lunas"
            :value="$countSelesai"
            color="emerald"
            subtitle="Siklus pengadaan tuntas"
        >
            <x-slot:icon>
                <svg class="size-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card
            title="Jejak Audit Trail"
            :value="$countLogs"
            color="amber"
            subtitle="Log mutasi terkini"
        >
            <x-slot:icon>
                <svg class="size-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    {{-- MULTI-DIMENSIONAL FILTER BAR (ZERO INLINE SCRIPTS) --}}
    <form action="{{ route('monitoring.index') }}" method="GET" class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            {{-- PENCARIAN TEKS --}}
            <div class="lg:col-span-2">
                <label for="filter-q" class="block text-xs font-bold text-slate-700 mb-1">Cari No. Surat / Judul</label>
                <div class="relative">
                    <input
                        type="text"
                        id="filter-q"
                        name="q"
                        value="{{ request('q') ?? request('nomor_surat') }}"
                        placeholder="Contoh: 001/UN25/PP/..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]"
                    />
                    <svg class="size-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- FILTER STATUS --}}
            <div>
                <label for="filter-status" class="block text-xs font-bold text-slate-700 mb-1">Status Alur</label>
                <select
                    id="filter-status"
                    name="status"
                    class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]"
                >
                    <option value="">Semua Status</option>
                    <option value="diajukan" {{ request('status') === 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="reviu_paralel" {{ request('status') === 'reviu_paralel' ? 'selected' : '' }}>Reviu Paralel</option>
                    <option value="disetujui" {{ request('status') === 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="ditolak" {{ request('status') === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            {{-- FILTER UNIT --}}
            <div>
                <label for="filter-unit" class="block text-xs font-bold text-slate-700 mb-1">Unit Pengusul</label>
                <select
                    id="filter-unit"
                    name="unit"
                    class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]"
                >
                    <option value="">Semua Unit</option>
                    @foreach($units as $u)
                        <option value="{{ $u->kode_unit }}" {{ request('unit') == $u->kode_unit ? 'selected' : '' }}>
                            {{ $u->nama_unit ?? $u->kode_unit }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- TOMBOL AKSI FILTER --}}
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#0969da] hover:bg-[#0756b2] text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                    Terapkan
                </button>
                @if(request()->hasAny(['q', 'nomor_surat', 'status', 'unit', 'tahun', 'bulan', 'selected']))
                    <a href="{{ route('monitoring.index') }}" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- KONTROL INDIKATOR 8 SIKLUS TAHAPAN RESMI --}}
    @if($selectedPermohonan)
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-4 border-b border-slate-100">
                <div>
                    <span class="text-xs font-bold text-[#0969da] uppercase tracking-wider">Usulan Dipantau</span>
                    <h2 class="text-xl font-bold text-[#213153] mt-0.5">
                        {{ $selectedPermohonan->judul_pengadaan }}
                    </h2>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">
                        No. Surat: {{ $selectedPermohonan->nomor_surat }} &bull; Unit: {{ $selectedPermohonan->unit->nama_unit ?? $selectedPermohonan->kode_unit }}
                    </p>
                </div>
                <div>
                    <x-status-badge :status="$selectedPermohonan->status_permohonan ?? $selectedPermohonan->status" size="lg" />
                </div>
            </div>

            <x-stepper-8-siklus :currentStep="$currentStep" :status="$selectedPermohonan->status_permohonan ?? 'diajukan'" />
        </div>
    @endif

    {{-- TABEL PEMILIHAN USULAN PENGADAAN AKTIF --}}
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200/80 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#213153]">Daftar Usulan Pengadaan</h3>
                <p class="text-xs text-slate-500">Pilih salah satu usulan untuk meninjau status siklus dan rincian berkasnya.</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full">
                {{ $permohonanList->count() }} dari {{ $permohonanList->total() }} usulan
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/80 text-xs text-slate-600 border-b border-slate-200/80">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nomor Surat</th>
                        <th class="px-5 py-3 font-semibold">Judul Pengadaan</th>
                        <th class="px-5 py-3 font-semibold">Unit Pengusul</th>
                        <th class="px-5 py-3 font-semibold">Tanggal</th>
                        <th class="px-5 py-3 font-semibold">Tahap / Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($permohonanList as $item)
                        @php
                            $isSelected = $selectedPermohonan && $selectedPermohonan->nomor_surat === $item->nomor_surat;
                        @endphp
                        <tr class="{{ $isSelected ? 'bg-sky-50/60 font-medium' : 'hover:bg-slate-50/60' }} transition-colors">
                            <td class="px-5 py-3 font-mono text-xs text-[#213153]">
                                {{ $item->nomor_surat }}
                            </td>
                            <td class="px-5 py-3 text-slate-800 max-w-xs truncate">
                                {{ $item->judul_pengadaan }}
                            </td>
                            <td class="px-5 py-3 text-slate-600 text-xs">
                                {{ $item->unit->nama_unit ?? $item->kode_unit }}
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs">
                                {{ $item->tanggal_pengajuan ? \Carbon\Carbon::parse($item->tanggal_pengajuan)->translatedFormat('d M Y') : '-' }}
                            </td>
                            <td class="px-5 py-3">
                                <x-status-badge :status="$item->status_permohonan ?? $item->status" size="sm" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if($isSelected)
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-[#0969da] px-2.5 py-1 bg-white border border-[#0969da]/30 rounded-lg">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Sedang Dipantau
                                    </span>
                                @else
                                    <a href="{{ route('monitoring.index', array_merge(request()->query(), ['selected' => $item->nomor_surat])) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-700 hover:text-[#0969da] px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                                        Pilih Usulan &rarr;
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-500">
                                Tidak ada data usulan pengadaan yang cocok dengan kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($permohonanList->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $permohonanList->links() }}
            </div>
        @endif
    </div>

    {{-- PANEL DETAIL DOKUMEN & RIWAYAT AUDIT TRAIL --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- PANEL KIRI: DOKUMEN TERKAIT USULAN --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                <h3 class="text-base font-bold text-[#213153] mb-1">Dokumen Resmi Usulan</h3>
                <p class="text-xs text-slate-500 mb-4">Berkas digital pendukung yang melekat pada usulan pengadaan ini.</p>

                @if($selectedPermohonan)
                    <div class="space-y-3">
                        {{-- SURAT PERMOHONAN SRIKANDI --}}
                        <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200/80 rounded-xl">
                            <div class="size-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-slate-800 truncate">Surat_Permohonan_{{ $selectedPermohonan->nomor_surat }}.pdf</div>
                                <div class="text-[11px] text-slate-500">Integrasi Srikandi &bull; Surat Pengantar</div>
                            </div>
                            @if($selectedPermohonan->file_surat_permohonan || $selectedPermohonan->file_permohonan)
                                <a href="{{ asset('storage/' . ($selectedPermohonan->file_surat_permohonan ?? $selectedPermohonan->file_permohonan)) }}" target="_blank" class="px-2.5 py-1 text-xs font-semibold text-[#0969da] bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                    Unduh
                                </a>
                            @else
                                <span class="text-[11px] text-slate-400 italic">Belum Ada</span>
                            @endif
                        </div>

                        {{-- DOKUMEN KAK --}}
                        <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200/80 rounded-xl">
                            <div class="size-9 rounded-lg bg-blue-100 text-[#0969da] flex items-center justify-center shrink-0">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-slate-800 truncate">Kerangka_Acuan_Kerja_KAK.pdf</div>
                                <div class="text-[11px] text-slate-500">Spesifikasi KAK & Rincian Kebutuhan</div>
                            </div>
                            @if($selectedPermohonan->file_kak)
                                <a href="{{ asset('storage/' . $selectedPermohonan->file_kak) }}" target="_blank" class="px-2.5 py-1 text-xs font-semibold text-[#0969da] bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                    Unduh
                                </a>
                            @else
                                <span class="text-[11px] text-slate-400 italic">Belum Ada</span>
                            @endif
                        </div>

                        {{-- DOKUMEN DOWNSTREAM JIKA ADA --}}
                        @if($selectedPermohonan->paket)
                            <div class="mt-4 pt-3 border-t border-slate-100">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Berkas Hilir Pengadaan</span>
                                <div class="mt-2 space-y-2">
                                    <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl text-xs">
                                        <span class="text-slate-700 font-medium">Paket: {{ $selectedPermohonan->paket->nomor_paket }}</span>
                                        <a href="{{ route('paket.show', $selectedPermohonan->paket->nomor_paket) }}" class="text-[#0969da] font-semibold hover:underline">Detail Paket &rarr;</a>
                                    </div>
                                    @if($selectedPermohonan->paket->kontrak)
                                        <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl text-xs">
                                            <span class="text-slate-700 font-medium">Kontrak: {{ $selectedPermohonan->paket->kontrak->nomor_spk }}</span>
                                            <a href="{{ route('kontrak.index') }}" class="text-[#0969da] font-semibold hover:underline">Lihat Kontrak &rarr;</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-slate-500 italic text-center py-6">
                        Pilih usulan pengadaan di atas untuk melihat dokumen terkait.
                    </p>
                @endif
            </div>
        </div>

        {{-- PANEL KANAN: RIWAYAT KRONOLOGIS & AUDIT TRAIL --}}
        <div class="lg:col-span-7">
            <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200/80 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-[#213153]">Riwayat Kronologis & Audit Trail</h3>
                        <p class="text-xs text-slate-500">Rekam jejak seluruh mutasi dan aksi para pihak berwenang.</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full">
                        {{ $auditLogs->count() }} Aktivitas
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50/80 text-xs text-slate-600 border-b border-slate-200/80">
                            <tr>
                                <th class="px-5 py-3 font-semibold w-36">Waktu</th>
                                <th class="px-5 py-3 font-semibold">Pelaku / Aktor</th>
                                <th class="px-5 py-3 font-semibold">Aksi / Mutasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse($auditLogs as $log)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-3 text-slate-500 font-mono">
                                        {{ $log->waktu_log ? \Carbon\Carbon::parse($log->waktu_log)->translatedFormat('d M Y, H:i') : ($log->created_at ? $log->created_at->translatedFormat('d M Y, H:i') : '-') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-bold text-[#213153]">{{ $log->user->nama ?? 'Sistem' }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $log->user->role ?? 'Sistem' }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-700">
                                        <span class="inline-block px-1.5 py-0.5 text-[10px] font-bold uppercase rounded bg-slate-100 text-slate-700 mr-1.5">
                                            {{ $log->jenis_aksi ?? 'LOG' }}
                                        </span>
                                        {{ $log->deskripsi_aktivitas ?? $log->rincian_perubahan }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-8 text-center text-slate-500">
                                        Belum ada catatan riwayat audit trail.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection