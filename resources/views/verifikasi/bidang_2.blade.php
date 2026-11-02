@extends('layouts.app')

@section('title', 'Telaah Berkas Permohonan Masuk - Staf Bidang II PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    <!-- 1. HEADER HALAMAN & ROLE PILL (Figma 565:19857) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="rounded-lg bg-blue-100 text-[#0969da] px-2.5 py-1 text-xs font-bold font-mono">FR 5.1</span>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Telaah Berkas Administrasi &amp; Naskah Dinas Srikandi
                </h1>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Staf Tata Usaha Bidang II memantau berkas usulan naskah dinas Srikandi, KAK, dan Spesifikasi Teknis secara read-only. Pemeriksaan kelengkapan dokumen dan persetujuan (ACC/Tolak) dilakukan langsung oleh Wakil Direktur II.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200/80 px-3 py-1 text-xs font-bold text-[#0969da]">
                <span class="size-2 rounded-full bg-[#0969da] animate-pulse"></span>
                Staf Bidang II (Pemantau Berkas - Read Only)
            </span>
        </div>
    </div>

    <!-- 2. STEPPER SIKLUS PENGADAAN (TAHAP 2: VERIFIKASI BERANTAI) -->
    <x-stepper-8-siklus :currentStep="2" status="reviu_paralel" />

    <!-- 3. KPI STAT CARDS -->
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card title="Total Permohonan Masuk" 
                     :value="$permohonanMasuk->count()" 
                     subtitle="Antrean berkas baru" 
                     color="blue" />

        <x-stat-card title="Belum Ditelaah" 
                     :value="$permohonanMasuk->where('status_permohonan', 'diajukan')->count()" 
                     subtitle="Perlu verifikasi checklist" 
                     color="amber" />

        <x-stat-card title="Memo Cek MAK Terbit" 
                     :value="\App\Models\VerifikasiParalel::where('role_verifikator', 'staff_bidang_2')->where('status_keputusan', 'disetujui')->count()" 
                     subtitle="Diteruskan ke Perencanaan" 
                     color="teal" />
    </div>

    <!-- 4. ANTREAN BERKAS PERMOHONAN MASUK -->
    @if($permohonanMasuk->isEmpty())
        <div class="rounded-2xl bg-white p-12 text-center shadow-xs border border-slate-200/80">
            <div class="size-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-base font-bold text-[#213153]">Tidak Ada Antrean Berkas</h3>
            <p class="mt-1 text-xs text-slate-400 max-w-sm mx-auto">
                Seluruh berkas usulan permohonan baru telah ditelaah dan diteruskan dengan Memo Cek MAK ke Bagian Perencanaan.
            </p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($permohonanMasuk as $p)
                <div class="rounded-2xl bg-white p-6 md:p-7 shadow-xs border border-slate-200/80 space-y-5">
                    
                    <!-- Card Header -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-[#0969da]">{{ $p->nomor_surat }}</span>
                                <x-status-badge :status="$p->status_permohonan ?? $p->status" size="sm" />
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                Unit Pengusul: <strong class="text-slate-800">{{ $p->unit->nama_unit ?? $p->kode_unit }}</strong> ({{ $p->kode_unit }})
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 block">Tanggal Usulan: {{ $p->tanggal_pengajuan ? date('d/m/Y', strtotime($p->tanggal_pengajuan)) : '-' }}</span>
                            <div class="text-base font-extrabold text-[#0969da] font-mono">
                                Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="space-y-2">
                        <h2 class="text-lg font-bold text-[#213153] leading-snug">{{ $p->perihal ?? $p->judul_pengadaan }}</h2>
                        <p class="text-xs text-slate-600 leading-relaxed bg-slate-50/70 p-3 rounded-xl border border-slate-100">
                            {{ $p->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa untuk kebutuhan operasional dan peningkatan mutu institusi PNJ.' }}
                        </p>
                    </div>

                    <!-- Lampiran Preview Links -->
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" 
                           target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                            <svg class="size-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <span>Lihat Berkas Srikandi (PDF)</span>
                        </a>

                        <a href="{{ route('permohonan.show', $p->nomor_surat) }}" 
                           class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                            <span>Detail Penuh &amp; Silsilah &rarr;</span>
                        </a>
                    </div>

                    <!-- PANEL INFORMASI & STATUS TELAAH READ-ONLY (STAF BIDANG II) -->
                    <div class="rounded-xl bg-slate-50/70 p-5 md:p-6 border border-slate-200/80 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-[#0969da]"></span>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Status Berkas &amp; Informasi Pengadaan (Read-Only)</h3>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-[11px] font-semibold">
                                Staf B2: Pemantauan
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200/60 space-y-1">
                                <span class="text-slate-400 block text-[11px]">Surat Srikandi</span>
                                <span class="font-bold text-slate-800 font-mono">{{ $p->nomor_surat }}</span>
                            </div>
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200/60 space-y-1">
                                <span class="text-slate-400 block text-[11px]">Pemeriksaan Kelengkapan</span>
                                <span class="font-bold text-indigo-700">Wadir II &amp; Pengusul</span>
                            </div>
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200/60 space-y-1">
                                <span class="text-slate-400 block text-[11px]">Ketersediaan Pagu MAK</span>
                                <span class="font-bold text-teal-700">{{ $p->kode_mak ? $p->kode_mak : 'Ditinjau Perencanaan' }}</span>
                            </div>
                        </div>

                        <div class="rounded-xl bg-blue-50/60 border border-blue-100 p-3 text-xs text-blue-800 flex items-start gap-2">
                            <svg class="size-4 text-[#0969da] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Staf Bidang II memantau berkas secara read-only. Pemeriksaan kelengkapan dokumen administrasi (Surat Srikandi, KAK, Spesifikasi) dan penetapan keputusan eksekutif (ACC/Tolak/Revisi) dilakukan langsung oleh <strong>Wakil Direktur II</strong>.</span>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
