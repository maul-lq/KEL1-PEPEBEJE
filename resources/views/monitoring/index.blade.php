@extends('layouts.app')

@section('title', 'Detail & Tracking Saya - SIM-PBJ')

@section('content')
<div class="mx-auto max-w-[1750px]">
    <h1 class="text-[32px] font-normal text-[#689EC2]">Detail &amp; Tracking Saya</h1>

    {{-- FORM PENCARIAN & FILTER --}}
    <form action="{{ route('monitoring.index') }}" method="GET" class="mx-auto mt-4 flex max-w-[1632px] flex-wrap items-end gap-6 lg:flex-nowrap">
        <label class="block min-w-0 flex-1 basis-full lg:basis-0 lg:flex-[940]">
            <span class="mb-1 block text-2xl text-[#689EC2]">Cari no. surat / judul</span>
            <input
                type="text"
                name="q"
                value="{{ request('q') ?? request('nomor_surat') }}"
                placeholder="Cari nomor surat atau perihal..."
                class="h-12 w-full rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
            />
        </label>

        <label class="block min-w-0 flex-1 basis-[240px] lg:flex-[465]">
            <span class="mb-1 block text-2xl text-[#689EC2]">Status</span>
            <div class="relative">
                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="h-12 w-full appearance-none rounded-[10px] border border-[#1B5B7E] bg-white px-5 text-2xl text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
                >
                    <option value="">Semua</option>
                    <option value="diajukan" {{ request('status') == 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="reviu_paralel" {{ request('status') == 'reviu_paralel' ? 'selected' : '' }}>Reviu Paralel</option>
                    <option value="disetujui" {{ request('status') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="ditolak" {{ request('status') == 'ditolak' ? 'selected' : '' }}>Ditahan / Ditolak</option>
                    <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
                <svg class="pointer-events-none absolute right-5 top-1/2 size-5 -translate-y-1/2 text-black" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </div>
        </label>

        <button type="submit" class="h-12 w-[91px] shrink-0 rounded-[10px] border border-[#1B5B7E] bg-[#BAE5FF] text-2xl text-black hover:brightness-95 transition-colors">
            Cari
        </button>
    </form>

    @php
        // Mengambil permohonan pertama/terkini sebagai fokus tracking
        $selectedPermohonan = $permohonanList->first();

        $stageMap = [
            'diajukan' => 1,
            'reviu_paralel' => 2,
            'disetujui' => 3,
            'paket_terdaftar' => 4,
            'spk_terbit' => 5,
            'bast_terbit' => 6,
            'pencairan' => 7,
            'selesai' => 8,
        ];

        $currentStep = $selectedPermohonan ? ($stageMap[$selectedPermohonan->status_permohonan ?? $selectedPermohonan->status] ?? 2) : 2;
        $totalSteps = 8;
    @endphp

    {{-- KONTROL INDIKATOR 8 SIKLUS TAHAPAN --}}
    <section class="mx-auto mt-10 max-w-[1632px] rounded-[20px] bg-gradient-to-t from-white to-white/0 px-6 pb-8 pt-10 sm:px-[62px] shadow-sm">
       <div class="flex items-center justify-between">
    <h2 class="text-[32px] font-normal text-gray-800">
        Tahap {{ $currentStep }} dari {{ $totalSteps }}
    </h2>
</div>

        <!-- 8 Bulatan Siklus Presisi Figma -->
        <div class="mt-6 overflow-x-auto pb-2">
            <ol class="relative flex min-w-[640px] items-center justify-between">
                <div class="absolute left-[43px] right-[43px] top-1/2 h-[3px] -translate-y-1/2 bg-[#0088E8]"></div>
                @for ($n = 1; $n <= $totalSteps; $n++)
                    @php $done = $n <= $currentStep; @endphp
                    <li class="relative z-10 flex h-[70px] w-[70px] items-center justify-center rounded-full border-[5px] border-[#0087E5] text-4xl font-bold transition-all {{ $done ? 'bg-[#0087E5] text-white' : 'bg-white text-[#0087E5]' }}">
                        {{ $n }}
                    </li>
                @endfor
            </ol>
        </div>
    </section>

    {{-- PANEL KIRI (DOKUMEN) & PANEL KANAN (RIWAYAT AUDIT KRONOLOGIS) --}}
    <div class="mx-auto mt-8 grid max-w-[1632px] gap-6 lg:grid-cols-[554fr_1039fr]">

        {{-- KARTU SURAT & DOKUMEN SAYA --}}
        <section class="rounded-t-[20px] bg-gradient-to-t from-white to-white/0 p-6 sm:px-[40px] shadow-sm">
            <h2 class="text-xl font-normal text-gray-800">Surat dan Dokumen saya</h2>
            <ul class="mt-6 flex flex-col gap-[10px] pb-4">
                @if($selectedPermohonan && ($selectedPermohonan->file_surat_permohonan || $selectedPermohonan->file_permohonan))
                <li class="flex items-center gap-3 rounded-md border border-[#DCE5E3] p-3 bg-white">
                    <svg class="size-6 shrink-0 text-[#087F79]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <div class="min-w-0 flex-1 font-[Inter,sans-serif]">
                        <div class="truncate text-[13px] font-semibold leading-[1.45] text-[#203638]">Surat_Permohonan_{{ $selectedPermohonan->nomor_surat }}.pdf</div>
                        <div class="truncate text-xs leading-[1.45] text-[#657A7C]">Srikandi &bull; {{ $selectedPermohonan->nomor_surat }}</div>
                    </div>
                    <a href="{{ asset('storage/' . ($selectedPermohonan->file_surat_permohonan ?? $selectedPermohonan->file_permohonan)) }}" target="_blank" class="shrink-0 text-xs text-[#087F79] font-bold hover:underline">
                        Lihat ↗
                    </a>
                </li>
                @endif

                @if($selectedPermohonan && $selectedPermohonan->file_kak)
                <li class="flex items-center gap-3 rounded-md border border-[#DCE5E3] p-3 bg-white">
                    <svg class="size-6 shrink-0 text-[#087F79]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <div class="min-w-0 flex-1 font-[Inter,sans-serif]">
                        <div class="truncate text-[13px] font-semibold leading-[1.45] text-[#203638]">Dokumen_KAK.pdf</div>
                        <div class="truncate text-xs leading-[1.45] text-[#657A7C]">Kerangka Acuan Kerja</div>
                    </div>
                    <a href="{{ asset('storage/' . $selectedPermohonan->file_kak) }}" target="_blank" class="shrink-0 text-xs text-[#087F79] font-bold hover:underline">
                        Lihat ↗
                    </a>
                </li>
                @endif

                @if(!$selectedPermohonan)
                <li class="p-4 text-center text-sm text-gray-500">
                    Belum ada dokumen permohonan.
                </li>
                @endif
            </ul>
        </section>

        {{-- KARTU RIWAYAT KRONOLOGIS (AUDIT TRAIL) --}}
        <section class="rounded-t-[20px] bg-gradient-to-t from-white to-white/0 p-6 sm:px-[40px] shadow-sm">
            <h2 class="text-xl font-normal text-gray-800">Riwayat Kronologis dan Revisi</h2>
            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[560px] table-fixed text-left">
                    <thead>
                        <tr class="h-10 border-b border-[#DCE5E3] bg-gradient-to-b from-[#BAE5FF] to-white text-xs text-[#657A7C]">
                            <th class="w-[130px] px-[10px] font-normal">Waktu</th>
                            <th class="px-[10px] font-normal">Peristiwa / pelaku</th>
                            <th class="w-[90px] px-[10px] font-normal">Versi surat</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px] leading-[1.5] text-[#203638]">
                        @forelse($auditLogs as $log)
                        <tr class="border-b border-[#DCE5E3] bg-white align-top">
                            <td class="px-[10px] py-[14px]">
                                {{ $log->created_at ? $log->created_at->format('d M · H.i') : '-' }}
                            </td>
                            <td class="px-[10px] py-[14px]">
                                <span class="font-bold">{{ $log->user->nama ?? 'Sistem' }}</span>: {{ $log->deskripsi_aktivitas ?? $log->jenis_aksi }}
                            </td>
                            <td class="px-[10px] py-[14px]">
                                Revisi 0
                            </td>
                        </tr>
                        @empty
                        <tr class="border-b border-[#DCE5E3] bg-white align-top">
                            <td colspan="3" class="px-[10px] py-[14px] text-center text-gray-500">
                                Belum ada catatan riwayat aktivitas.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</div>
@endsection