@extends('layouts.app')

@section('title', 'Verifikasi Eksekutif Wadir 2 - SIM-PBJ PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    <!-- 1. HEADER HALAMAN & ROLE PILL (Figma 565:20075) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Disposisi Eksekutif Wakil Direktur II
                </h1>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Wakil Direktur Bidang Umum dan Keuangan memegang wewenang eksekutif institusi untuk memberikan keputusan mutlak persetujuan (ACC), permintaan revisi, atau penolakan usulan pengadaan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-200/80 px-3.5 py-1 text-xs font-bold text-indigo-800">
                <span class="size-2 rounded-full bg-indigo-600 animate-pulse"></span>
                Wakil Direktur II (Otoritas Eksekutif)
            </span>
        </div>
    </div>

    <!-- 2. STEPPER SIKLUS PENGADAAN (TAHAP 2: VERIFIKASI PARALEL) -->
    <x-stepper-8-siklus :currentStep="2" status="reviu_paralel" />

    <!-- 3. KPI STAT CARDS -->
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card title="Antrean Disposisi" 
                     :value="$permohonanAntrean->count()" 
                     subtitle="Menunggu persetujuan" 
                     color="navy" />

        <x-stat-card title="Telah Disetujui (ACC)" 
                     :value="\App\Models\VerifikasiParalel::where('role_verifikator', 'wadir_2')->where('status_keputusan', 'disetujui')->count()" 
                     subtitle="Diteruskan ke PPBJ" 
                     color="emerald" />

        <x-stat-card title="Ditolak / Diminta Revisi" 
                     :value="\App\Models\VerifikasiParalel::where('role_verifikator', 'wadir_2')->whereIn('status_keputusan', ['ditolak', 'revisi'])->count()" 
                     subtitle="Dikembalikan ke unit" 
                     color="rose" />
    </div>

    <!-- 4. DAFTAR ANTREAN PERMOHONAN -->
    @if($permohonanAntrean->isEmpty())
        <div class="rounded-2xl bg-white p-12 text-center shadow-xs border border-slate-200/80">
            <div class="size-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-base font-bold text-[#213153]">Tidak Ada Antrean Usulan</h3>
            <p class="mt-1 text-xs text-slate-400 max-w-sm mx-auto">
                Saat ini tidak ada permohonan pengadaan yang menunggu keputusan disposisi eksekutif Wakil Direktur II.
            </p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($permohonanAntrean as $p)
                @php
                    $vpStaff = $p->verifikasiParalel->where('role_verifikator', 'staff_bidang_2')->first();
                    $vpPerencanaan = $p->verifikasiParalel->where('role_verifikator', 'perencanaan')->first();
                    $vpWadir = $p->verifikasiParalel->where('role_verifikator', 'wadir_2')->first();
                    $vpPpk = $p->verifikasiParalel->where('role_verifikator', 'ppk')->first();
                    $isWadirAcc = $vpWadir && $vpWadir->status_keputusan === 'disetujui';
                @endphp

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

                    <!-- Judul & Uraian -->
                    <div class="space-y-2">
                        <h2 class="text-lg font-bold text-[#213153] leading-snug">{{ $p->perihal ?? $p->judul_pengadaan }}</h2>
                        <p class="text-xs text-slate-600 leading-relaxed bg-slate-50/70 p-3 rounded-xl border border-slate-100">
                            {{ $p->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa untuk kebutuhan operasional dan peningkatan mutu institusi PNJ.' }}
                        </p>
                    </div>

                    <!-- Rekomendasi Staf Bidang 2 & Status Perencanaan -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <!-- Box Rekomendasi Staf B2 -->
                        <div class="rounded-xl bg-blue-50/70 p-3.5 border border-blue-200/70 space-y-1">
                            <div class="font-bold text-blue-900 flex items-center gap-1.5">
                                <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Rekomendasi Telaah Staf Bidang II:</span>
                            </div>
                            <p class="text-slate-700 leading-relaxed">
                                {{ $vpStaff && $vpStaff->catatan_alasan ? $vpStaff->catatan_alasan : 'Berkas administrasi dan KAK telah diverifikasi lengkap dan memenuhi standar.' }}
                            </p>
                        </div>

                        <!-- Box Status Verifikasi MAK Perencanaan -->
                        <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-200/70 space-y-1">
                            <div class="font-bold text-slate-800 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Verifikasi MAK Perencanaan:
                                </span>
                                @if($vpPerencanaan && $vpPerencanaan->status_keputusan === 'disetujui')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">MAK Dialokasikan</span>
                                @elseif($vpPerencanaan && $vpPerencanaan->status_keputusan === 'pending_anggaran')
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">Anggaran Ditahan (Hold)</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Menunggu Alokasi</span>
                                @endif
                            </div>
                            <p class="text-slate-600 leading-relaxed">
                                @if($p->kode_mak)
                                    Pos Rekening: <strong class="font-mono text-[#0969da]">{{ $p->kode_mak }}</strong> ({{ $p->anggaranMak->uraian_mak ?? 'DIPA PNJ' }})
                                @else
                                    Mata anggaran sedang ditinjau Subbagian Perencanaan untuk ketersediaan pagu DIPA.
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Lampiran Pemeriksaan Dokumen Wadir II -->
                    <div class="space-y-2 pt-1">
                        <span class="block text-xs font-bold text-slate-700">Berkas Lampiran Pengajuan ({{ count($p->dokumen_lampiran_list) }} Berkas):</span>
                        <div class="flex flex-wrap items-center gap-2.5">
                            @forelse($p->dokumen_lampiran_list as $doc)
                                @if(strtoupper($doc['type'] ?? '') === 'PDF')
                                    <button type="button"
                                            @click="$dispatch('open-preview-doc', { title: '{{ addslashes($doc['name']) }}', url: '{{ $doc['url'] }}', size: '{{ $doc['size'] ?? '' }}' })"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition-colors cursor-pointer">
                                        <svg class="size-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        <span class="truncate max-w-[220px]" title="{{ $doc['name'] }}">{{ $doc['name'] }}</span>
                                        <span class="text-[10px] text-[#0969da] font-bold">Lihat</span>
                                    </button>
                                @else
                                    <a href="{{ $doc['url'] }}" download
                                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition-colors">
                                        <svg class="size-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="truncate max-w-[220px]" title="{{ $doc['name'] }}">{{ $doc['name'] }}</span>
                                        <span class="text-[10px] text-slate-500 font-bold">Unduh</span>
                                    </a>
                                @endif
                            @empty
                                <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" 
                                   @click.prevent="$dispatch('open-preview-doc', { title: 'Surat_Permohonan.pdf', url: '{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}', size: 'PDF' })"
                                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer">
                                    <svg class="size-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span>Lihat Surat Permohonan (PDF)</span>
                                </a>
                            @endforelse

                            <a href="{{ route('permohonan.show', $p->nomor_surat) }}" 
                               class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                                <span>Detail Penuh &amp; Silsilah &rarr;</span>
                            </a>
                        </div>
                    </div>

                    @if($isWadirAcc)
                        <!-- STATUS PERSETUJUAN AKTIF WADIR 2 (TETAP DISETUJUI, TIDAK DI-RESET) -->
                        <div class="rounded-xl border border-emerald-300 bg-emerald-50/90 p-5 md:p-6 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-200/80 pb-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-emerald-600 text-white shadow-xs">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-bold text-emerald-950">Telah Disetujui oleh Wakil Direktur II (ACC Kegiatan)</h3>
                                        <p class="text-xs text-emerald-800">Persetujuan Anda telah tersimpan secara permanen dan tetap berlaku.</p>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-200/90 text-emerald-900 px-3 py-1 text-xs font-bold font-mono">
                                    {{ $vpWadir->nomor_memo ?? 'MEMO-WDIR2' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-emerald-950">
                                <div>
                                    <span class="text-emerald-700 block text-[11px] font-medium">Tanggal Persetujuan:</span>
                                    <strong class="font-mono">{{ $vpWadir->tanggal_keputusan ? date('d/m/Y H:i', strtotime($vpWadir->tanggal_keputusan)) : '-' }}</strong>
                                </div>
                                <div>
                                    <span class="text-emerald-700 block text-[11px] font-medium">Dokumen Fisik Memo Disposisi:</span>
                                    @if(!empty($vpWadir->file_memo))
                                        <a href="{{ asset('storage/' . $vpWadir->file_memo) }}" target="_blank" class="inline-flex items-center gap-1 font-bold text-[#0969da] hover:underline">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>Unduh Dokumen Memo Wadir II (PDF)</span>
                                        </a>
                                    @else
                                        <span class="text-slate-500 italic">Disetujui secara digital</span>
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/80 p-3.5 border border-emerald-200 text-xs text-slate-700 flex items-start gap-2.5">
                                <svg class="size-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <strong class="text-emerald-950 font-bold">Status Verifikasi Lanjutan:</strong> Usulan ini sedang menunggu verifikasi/persetujuan oleh Pejabat Pembuat Komitmen (PPK): <strong class="text-slate-900">{{ $p->ppkAwal->nama ?? 'PPK Terpilih' }}</strong>. Segera setelah PPK memberikan persetujuan, berkas otomatis diteruskan ke antrean PPBJ untuk diproses lebih lanjut.
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Callout Catatan Revisi Sebelumnya jika ada -->
                        @if(($p->revisi_ke > 0) || !empty($p->alasan_revisi) || ($vpWadir && in_array($vpWadir->status_keputusan, ['ditolak', 'revisi'])))
                            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 space-y-1.5">
                                <div class="font-bold flex items-center gap-1.5 text-amber-950">
                                    <svg class="size-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>Usulan Hasil Revisi Pengusul (Revisi ke-{{ $p->revisi_ke ?? 1 }}):</span>
                                </div>
                                @if(!empty($p->alasan_revisi))
                                    <p class="text-slate-700 italic bg-white/70 p-2.5 rounded-lg border border-amber-200/50">
                                        &ldquo;{{ $p->alasan_revisi }}&rdquo;
                                    </p>
                                @endif
                                @if($vpWadir && !empty($vpWadir->catatan_alasan))
                                    <p class="text-amber-800 text-[11px] pt-1">
                                        Catatan Penolakan/Revisi Wadir II Sebelumnya: <strong class="italic">&ldquo;{{ $vpWadir->catatan_alasan }}&rdquo;</strong>
                                    </p>
                                @endif
                            </div>
                        @endif

                        <!-- FORM KEPUTUSAN MUTLAK WADIR 2 (3 OPSI DISPOSISI - FIGMA 565:20075) -->
                        <form action="{{ route('verifikasi.wadir-2.keputusan') }}" 
                              method="POST" 
                              enctype="multipart/form-data"
                              x-data="{ 
                                  keputusan: 'disetujui', 
                                  get isMandatory() { return this.keputusan === 'ditolak' || this.keputusan === 'revisi'; } 
                              }"
                              class="rounded-xl bg-slate-50/70 p-5 md:p-6 border border-slate-200/80 space-y-4">
                            @csrf
                            <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                    <svg class="size-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Keputusan Mutlak Wakil Direktur II (3 Opsi Disposisi)
                                </h3>
                                <span class="text-[11px] text-slate-400">Pilih salah satu tindakan eksekutif</span>
                            </div>

                            <!-- 3-Way Radio Decision Cards -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                
                                <!-- 1. SETUJUI (ACC) -->
                                <label class="flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all"
                                       :class="keputusan === 'disetujui' ? 'border-emerald-500 bg-emerald-50/60 shadow-xs' : 'border-slate-200 bg-white hover:border-emerald-300'">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" 
                                               name="keputusan" 
                                               value="disetujui" 
                                               x-model="keputusan"
                                               class="size-4 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-xs font-extrabold text-emerald-800">SETUJUI USULAN (ACC)</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">
                                        Disetujui untuk diproses ke PPBJ. Catatan alasan bersifat opsional.
                                    </p>
                                </label>

                                <!-- 2. MINTA REVISI BERKAS -->
                                <label class="flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all"
                                       :class="keputusan === 'revisi' ? 'border-amber-500 bg-amber-50/60 shadow-xs' : 'border-slate-200 bg-white hover:border-amber-300'">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" 
                                               name="keputusan" 
                                               value="revisi" 
                                               x-model="keputusan"
                                               class="size-4 text-amber-600 focus:ring-amber-500">
                                        <span class="text-xs font-extrabold text-amber-800">MINTA REVISI BERKAS</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">
                                        Kembalikan berkas ke unit pengusul untuk perbaikan KAK/Pagu. <strong>Catatan wajib</strong>.
                                    </p>
                                </label>

                                <!-- 3. TOLAK USULAN -->
                                <label class="flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all"
                                       :class="keputusan === 'ditolak' ? 'border-rose-500 bg-rose-50/60 shadow-xs' : 'border-slate-200 bg-white hover:border-rose-300'">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" 
                                               name="keputusan" 
                                               value="ditolak" 
                                               x-model="keputusan"
                                               class="size-4 text-rose-600 focus:ring-rose-500">
                                        <span class="text-xs font-extrabold text-rose-800">TOLAK USULAN</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">
                                        Usulan ditolak permanen dari pengadaan. <strong>Catatan alasan wajib diisi</strong>.
                                    </p>
                                </label>

                            </div>

                            <!-- Kelengkapan Berkas Fisik Memo Permohonan/Disposisi Wadir 2 -->
                            <div x-show="keputusan === 'disetujui'" class="p-4 rounded-xl bg-indigo-50/60 border border-indigo-200/80 space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md bg-indigo-200/80 text-indigo-900 px-2 py-0.5 text-[10px] font-bold font-mono">Disposisi Wadir II</span>
                                    <h4 class="text-xs font-bold text-indigo-950">Memo Permohonan / Disposisi Resmi Wadir II</h4>
                                </div>
                                <p class="text-[11px] text-slate-600">
                                    Sesuai prosedur, penerbitan memo persetujuan ke PPBJ wajib menyertakan nomor memo dan unggahan dokumen fisik resmi.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                            Nomor Memo Disposisi Wadir II
                                        </label>
                                        <input type="text" 
                                               name="nomor_memo" 
                                               value="{{ 'MEMO-WDIR2/' . date('Y') . '/' . substr(md5($p->nomor_surat), 0, 6) }}"
                                               placeholder="Contoh: 025/PL3.WDIR2/PG/{{ date('Y') }}"
                                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                            Upload File Fisik Dokumen Memo <span class="text-rose-500">*</span>
                                        </label>
                                        <input type="file" 
                                               name="file_memo" 
                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                               class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-indigo-100 file:text-indigo-800 hover:file:bg-indigo-200 cursor-pointer">
                                    </div>
                                </div>
                            </div>

                            <!-- Catatan / Alasan Keputusan Wadir 2 -->
                            <div class="space-y-1.5">
                                <label for="catatan_alasan_{{ $p->nomor_surat }}" class="block text-xs font-bold text-slate-700">
                                    Catatan / Instruksi Disposisi Wadir II 
                                    <span x-show="!isMandatory" class="text-slate-400 font-normal">(Opsional untuk Persetujuan)</span>
                                    <span x-show="isMandatory" class="text-rose-600 font-bold">(Wajib Diisi)</span>
                                </label>
                                <textarea id="catatan_alasan_{{ $p->nomor_surat }}" 
                                          name="catatan_alasan" 
                                          rows="3"
                                          :required="isMandatory"
                                          placeholder="Tuliskan arahan disposisi, catatan efisiensi pagu anggaran, atau rincian perbaikan KAK yang diminta..."
                                          class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"></textarea>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end pt-2">
                                <button type="submit" 
                                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-700 transition-all cursor-pointer">
                                    <span>Simpan Keputusan Eksekutif Wadir II</span>
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        </form>
                    @endif

                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
