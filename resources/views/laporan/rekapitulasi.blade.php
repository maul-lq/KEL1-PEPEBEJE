@extends('layouts.app')

@section('title', 'Laporan Rekapitulasi Anggaran & Realisasi DIPA - SIM-PBJ PNJ')

@section('content')
<div x-data="{ tab: 'laporan' }" class="space-y-6">

    {{-- HEADER HALAMAN & TOMBOL UNDUH PDF --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                    Feature 32: Rekapitulasi DIPA PNJ
                </span>
                <span class="text-xs text-slate-400 font-mono">Tahun Anggaran {{ $tahun }}</span>
            </div>
            <h1 class="text-2xl font-bold text-[#213153] mt-1 sm:text-3xl">
                Laporan Rekapitulasi Realisasi Anggaran
            </h1>
            <p class="mt-1 text-xs text-slate-500 sm:text-sm">
                Rekapitulasi serapan anggaran MAK, akumulasi kontrak SPK, realisasi pencairan SP2D, dan distribusi per unit kerja Politeknik Negeri Jakarta.
            </p>
        </div>

        {{-- Tombol Unduh Laporan PDF dengan parameter filter resmi --}}
        <a
            href="{{ route('laporan.rekapitulasi.pdf', ['tahun' => $tahun, 'bulan' => $bulan, 'unit' => $kodeUnit]) }}"
            target="_blank"
            class="flex h-11 items-center gap-2.5 rounded-xl bg-[#0969da] px-5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition"
        >
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span>Unduh Rekapitulasi (PDF)</span>
        </a>
    </div>

    {{-- TAB NAVIGASI SWITCHER --}}
    <div class="flex border-b border-slate-200">
        <button
            @click="tab = 'laporan'"
            type="button"
            :class="tab === 'laporan' ? 'border-[#0969da] text-[#0969da] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="flex h-11 items-center gap-2 border-b-2 px-5 text-xs transition-colors"
        >
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Laporan Realisasi DIPA
        </button>

        <button
            @click="tab = 'riwayat'"
            type="button"
            :class="tab === 'riwayat' ? 'border-[#0969da] text-[#0969da] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="flex h-11 items-center gap-2 border-b-2 px-5 text-xs transition-colors"
        >
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Petunjuk &amp; Ketentuan Pelaporan
        </button>
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 1: LAPORAN REALISASI DIPA                                             --}}
    {{-- ========================================================================= --}}
    <div x-show="tab === 'laporan'" class="space-y-6">

        {{-- Filter Multi-Dimensi: Tahun, Bulan, Unit Kerja --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('laporan.rekapitulasi') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end justify-between">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1 max-w-3xl">

                    {{-- Filter Tahun --}}
                    <div>
                        <label for="filter_tahun" class="block text-xs font-bold text-slate-700 mb-1">Tahun Anggaran</label>
                        <select
                            id="filter_tahun"
                            name="tahun"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            @php
                                $curYear = (int) date('Y');
                            @endphp
                            @for($y = $curYear + 1; $y >= $curYear - 3; $y--)
                                <option value="{{ $y }}" {{ (int) $tahun === $y ? 'selected' : '' }}>
                                    Tahun {{ $y }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    {{-- Filter Bulan --}}
                    <div>
                        <label for="filter_bulan" class="block text-xs font-bold text-slate-700 mb-1">Periode Bulan</label>
                        <select
                            id="filter_bulan"
                            name="bulan"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="">Semua Bulan (Tahunan)</option>
                            @foreach([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ] as $mNum => $mName)
                                <option value="{{ $mNum }}" {{ (int) $bulan === $mNum ? 'selected' : '' }}>
                                    {{ $mName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Jurusan / Unit --}}
                    <div>
                        <label for="filter_unit" class="block text-xs font-bold text-slate-700 mb-1">Jurusan / Unit Kerja</label>
                        <select
                            id="filter_unit"
                            name="unit"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="">Semua Unit Kerja PNJ</option>
                            @foreach($units as $u)
                                <option value="{{ $u->kode_unit }}" {{ $kodeUnit === $u->kode_unit ? 'selected' : '' }}>
                                    {{ $u->kode_unit }} &bull; {{ $u->nama_unit }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="submit"
                        class="h-10 px-5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Terapkan Filter
                    </button>

                    <a
                        href="{{ route('laporan.rekapitulasi') }}"
                        class="h-10 px-4 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition inline-flex items-center gap-1"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- 6 Kartu Ringkasan Keuangan Dinamis (Terkoneksi ke Database) --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center gap-2.5 text-base font-bold text-[#213153]">
                <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Ringkasan Eksekutif Keuangan &amp; Penyerapan Anggaran
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                {{-- Card 1: Total Pagu DIPA --}}
                <div class="flex items-center gap-4 rounded-xl bg-blue-50/70 border border-blue-200/60 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#0969da] text-white">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">Total Pagu Anggaran DIPA</div>
                        <div class="text-xl font-bold font-mono text-[#213153]">Rp {{ number_format($totalPaguDipa, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Card 2: Total Estimasi Usulan --}}
                <div class="flex items-center gap-4 rounded-xl bg-amber-50/70 border border-amber-200/60 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-600 text-white">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">Total Estimasi Usulan Permohonan</div>
                        <div class="text-xl font-bold font-mono text-[#213153]">Rp {{ number_format($totalUsulan, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Card 3: Total Kontrak SPK --}}
                <div class="flex items-center gap-4 rounded-xl bg-purple-50/70 border border-purple-200/60 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-purple-600 text-white">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">Total Nilai Kontrak / SPK</div>
                        <div class="text-xl font-bold font-mono text-[#213153]">Rp {{ number_format($totalKontrak, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Card 4: Realisasi Pencairan SP2D --}}
                <div class="flex items-center gap-4 rounded-xl bg-emerald-50/70 border border-emerald-200/60 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">Total Realisasi Pencairan SP2D</div>
                        <div class="text-xl font-bold font-mono text-emerald-700">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Card 5: Sisa Anggaran --}}
                <div class="flex items-center gap-4 rounded-xl bg-teal-50/70 border border-teal-200/60 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">Sisa Anggaran Belum Terserap</div>
                        <div class="text-xl font-bold font-mono text-[#213153]">Rp {{ number_format($sisaAnggaran, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Card 6: Persentase Serapan --}}
                <div class="flex items-center gap-4 rounded-xl bg-slate-100 border border-slate-200 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#213153] text-white">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">Persentase Serapan Anggaran</div>
                        <div class="text-2xl font-bold font-mono text-[#0969da]">{{ $persentaseSerapan }}%</div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Tabel Distribusi Anggaran per Jurusan dan Unit Kerja (Data Riil Koleksi $unitBreakdown) --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-[#213153]">Distribusi Usulan Anggaran per Jurusan dan Unit Kerja</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Rincian komitmen pagu dan jumlah berkas usulan per unit organisasi</p>
                </div>

                <a
                    href="{{ route('laporan.rekapitulasi.pdf', ['tahun' => $tahun, 'bulan' => $bulan, 'unit' => $kodeUnit]) }}"
                    target="_blank"
                    class="flex h-9 items-center gap-2 rounded-lg border border-[#0969da] bg-white px-3.5 text-xs font-bold text-[#0969da] hover:bg-blue-50 transition"
                >
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Unduh Detail (PDF)</span>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 text-center w-12">No.</th>
                            <th class="px-4 py-3">Kode Unit</th>
                            <th class="px-4 py-3">Nama Jurusan / Unit Kerja</th>
                            <th class="px-4 py-3 text-center">Jumlah Usulan</th>
                            <th class="px-4 py-3">Total Nilai Usulan Pagu</th>
                            <th class="px-4 py-3 text-center">Pangsa Usulan (%)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($unitBreakdown as $idx => $ub)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-3.5 text-center font-medium text-slate-400">{{ $idx + 1 }}</td>
                                <td class="px-4 py-3.5 font-mono font-bold text-[#213153]">{{ $ub['kode_unit'] }}</td>
                                <td class="px-4 py-3.5 font-semibold text-slate-800">{{ $ub['nama_unit'] }}</td>
                                <td class="px-4 py-3.5 text-center font-bold text-slate-700">{{ $ub['jumlah_usulan'] }} Berkas</td>
                                <td class="px-4 py-3.5 font-mono font-bold text-slate-900">
                                    Rp {{ number_format($ub['total_pagu'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold font-mono bg-blue-50 text-[#0969da] border border-blue-200">
                                        {{ $ub['persentase'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Tidak ada data alokasi unit kerja untuk kriteria filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                <span>Total Unit Terdaftar: {{ count($unitBreakdown) }} Unit Organisasi</span>
                <span>Sumber Data: DIPA PNJ {{ $tahun }}</span>
            </div>
        </div>

    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 2: PETUNJUK & KETENTUAN PELAPORAN                                     --}}
    {{-- ========================================================================= --}}
    <div x-show="tab === 'riwayat'" class="space-y-6">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-[#213153]">Pedoman Rekapitulasi &amp; Pelaporan Realisasi Pengadaan</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
                Laporan realisasi anggaran DIPA disusun berdasarkan keterhubungan data siklus pengadaan penuh:
            </p>
            <ul class="list-disc list-inside text-xs text-slate-700 space-y-2">
                <li><strong>Pagu DIPA:</strong> Pagu anggaran resmi yang ditetapkan pada DIPA Kementerian/Politeknik Negeri Jakarta per Mata Anggaran Keluaran (MAK).</li>
                <li><strong>Estimasi Usulan:</strong> Total usulan belanja yang diajukan oleh unit kerja/jurusan melalui berkas Srikandi dan disetujui Wadir II.</li>
                <li><strong>Kontrak SPK Sah:</strong> Total nilai kontrak perikatan resmi antara PPK/PPBJ dengan pihak rekanan vendor (telah mengunci MAK secara permanen).</li>
                <li><strong>Realisasi Pencairan SP2D:</strong> Total dana yang telah secara nyata dicairkan dan dipindahbukukan oleh Bagian Keuangan ke rekening rekanan berdasarkan bukti transaksi bank resmi.</li>
                <li><strong>Persentase Serapan:</strong> Rasio antara total realisasi pencairan kas SP2D terhadap total pagu DIPA yang dialokasikan.</li>
            </ul>
        </div>
    </div>

</div>
@endsection