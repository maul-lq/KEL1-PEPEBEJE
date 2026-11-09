@extends('layouts.app')

@section('title', 'Detail Paket Pengadaan ' . ($paket->kode_paket ?? $paket->nomor_paket) . ' - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6">

    @php
        $stepMap = [
            'terdaftar' => 3,
            'reviu_dokumen' => 4,
            'menunggu_reviu_pp' => 4,
            'menunggu_approval_ppk' => 4,
            'menunggu_reviu_ppk' => 4,
            'perlu_perbaikan' => 4,
            'reviu_dokumen_disetujui' => 4,
            'disetujui_ppk_siap_belanja_pp' => 5,
            'siap_upload_dokumen_ppbj' => 5,
            'belanja_langsung' => 5,
            'belanja_langsung_selesai' => 5,
            'spk_terbit' => 5,
            'surat_pesanan_terbit' => 5,
            'surat_kontrak_terbit' => 5,
            'pengiriman' => 6,
            'penerimaan_pending' => 6,
            'bast_terbit' => 7,
            'penerimaan_selesai' => 7,
            'pengajuan_pembayaran' => 8,
            'reviu_memo' => 8,
            'perintah_bayar' => 8,
            'spp_terbit' => 8,
            'pencairan' => 8,
            'sp2d_lunas' => 8,
            'lunas' => 8,
            'selesai' => 8,
        ];
        $currentStep = $stepMap[$paket->status_paket] ?? 3;
        $isBelanjaLangsung = $paket->jalur_routing === 'langsung_sd_50jt' || (float)($paket->nilai_hps ?: $paket->alokasi_pagu_paket) <= 50000000;
    @endphp

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="$currentStep" :status="$paket->status_paket" />

    {{-- 2. Header & Action Bar --}}
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-100 pb-5 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200 font-mono">
                        {{ $paket->kode_paket ?? $paket->nomor_paket }}
                    </span>
                    <x-status-badge :status="$paket->status_paket" size="sm" />
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">{{ $paket->nama_paket }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Terdaftar pada {{ $paket->tanggal_registrasi_paket ? \Carbon\Carbon::parse($paket->tanggal_registrasi_paket)->format('d F Y') : '-' }} &bull; Koordinator PPBJ: {{ $paket->ppbjUser->nama ?? '-' }}
                </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                <a
                    href="{{ route('paket.index') }}"
                    class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    &larr; Kembali
                </a>

                @if($isBelanjaLangsung)
                    @if($paket->nomor_kwitansi_belanja)
                        <span class="px-3.5 py-2 rounded-xl bg-teal-50 border border-teal-200 text-teal-800 text-xs font-bold inline-flex items-center gap-1.5">
                            <svg class="size-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Kwitansi Sah: {{ $paket->nomor_kwitansi_belanja }}
                        </span>
                    @elseif(Auth::user() && (Auth::user()->hasRole('admin') || Auth::user()->hasPermission('paket.create')))
                        <a
                            href="{{ route('paket.index') }}"
                            class="px-4 py-2 rounded-xl bg-teal-600 text-xs font-bold text-white hover:bg-teal-700 shadow-sm transition inline-flex items-center gap-1.5"
                        >
                            <span>Eksekusi Belanja Langsung</span>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    @endif
                @else
                    <a
                        href="{{ route('kontrak.index') }}"
                        class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <span>Modul Dokumen Perikatan / SPK</span>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- 3. Grid Spesifikasi Paket & Pagu --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- Kolom 1: Spesifikasi Pengadaan --}}
            <div class="rounded-xl bg-slate-50/70 p-5 border border-slate-100 space-y-3">
                <h3 class="text-xs font-bold text-[#213153] uppercase tracking-wider">Spesifikasi Pengadaan</h3>
                <dl class="space-y-2 text-xs">
                    <div>
                        <dt class="text-slate-400">Jenis Pengadaan:</dt>
                        <dd class="font-bold text-slate-800 capitalize">{{ str_replace('_', ' ', $paket->jenis_pengadaan) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Metode Pengadaan:</dt>
                        <dd class="font-bold text-slate-800">{{ $paket->metode_pengadaan }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Jalur Routing HPS:</dt>
                        <dd class="mt-0.5">
                            @if($paket->jalur_routing === 'ppk_diatas_200jt')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                    &ge; 200 Juta (PPK Mandiri)
                                </span>
                            @elseif($paket->jalur_routing === 'pp_50jt_sd_200jt')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                    50 - 200 Juta (PP &amp; PPK)
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800">
                                    &le; 50 Juta (Pengadaan Langsung)
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Nomor Memo PPBJ:</dt>
                        <dd class="font-mono text-slate-800">
                            {{ $paket->nomor_memo_ppbj ?? '(Tanpa Memo Pengadaan)' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Kolom 2: Keuangan & Pagu HPS --}}
            <div class="rounded-xl bg-slate-50/70 p-5 border border-slate-100 space-y-3">
                <h3 class="text-xs font-bold text-[#213153] uppercase tracking-wider">Keuangan &amp; Pagu HPS</h3>
                <dl class="space-y-2 text-xs">
                    <div>
                        <dt class="text-slate-400">Harga Perkiraan Sendiri (HPS):</dt>
                        <dd class="text-lg font-bold font-mono text-emerald-600">
                            Rp {{ number_format($paket->nilai_hps, 0, ',', '.') }}
                        </dd>
                    </div>
                    @if($paket->nilai_hasil_negosiasi)
                        <div>
                            <dt class="text-slate-400">Nilai Hasil Negosiasi PP:</dt>
                            <dd class="text-sm font-bold font-mono text-[#0969da]">
                                Rp {{ number_format($paket->nilai_hasil_negosiasi, 0, ',', '.') }}
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-slate-400">Rekanan Terhubung:</dt>
                        <dd class="font-semibold text-slate-800">
                            {{ $paket->vendor->nama_vendor ?? 'Belum ada rekanan terkait' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Kolom 3: Pejabat Penanggung Jawab --}}
            <div class="rounded-xl bg-slate-50/70 p-5 border border-slate-100 space-y-3">
                <h3 class="text-xs font-bold text-[#213153] uppercase tracking-wider">Aktor &amp; Pejabat Berwenang</h3>
                <dl class="space-y-2 text-xs">
                    <div>
                        <dt class="text-slate-400">Koordinator PPBJ:</dt>
                        <dd class="font-semibold text-slate-800">{{ $paket->ppbjUser->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Pejabat Pengadaan (PP):</dt>
                        <dd class="font-semibold text-slate-800">{{ $paket->ppUser->nama ?? 'Tidak Ditugaskan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Pejabat Pembuat Komitmen (PPK):</dt>
                        <dd class="font-bold text-[#213153]">
                            {{ $paket->ppkUser->nama ?? ($paket->jalur_routing === 'langsung_sd_50jt' ? '(Pengadaan Langsung PPBJ)' : 'Belum Ditugaskan') }}
                        </dd>
                    </div>
                </dl>
            </div>

        </div>
    </div>

    {{-- 4. Berkas Usulan Permohonan Induk & Kelengkapan BPMN (Form, MAK & Memo) --}}
    @if($paket->permohonan)
        <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-[#213153]">Dasar Berkas Permohonan Masuk (Form Pengadaan, MAK &amp; Memo)</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Dokumen usulan dan disposisi persetujuan pimpinan yang mendasari paket pengadaan ini</p>
                </div>
                <a
                    href="{{ route('permohonan.show', $paket->permohonan->nomor_surat) }}"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs text-xs inline-flex items-center gap-1"
                >
                    Lihat Detail Usulan &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                {{-- 1. Form Pengadaan & Dokumen Teknis --}}
                <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">1. Form Pengadaan &amp; Teknis</span>
                    <div>
                        <span class="font-mono font-bold text-[#0969da] block">{{ $paket->permohonan->nomor_surat }}</span>
                        <h4 class="font-bold text-slate-800 text-xs mt-0.5">{{ $paket->permohonan->perihal ?? $paket->permohonan->judul_pengadaan }}</h4>
                        <p class="text-slate-500 text-[11px] mt-0.5">Unit: {{ $paket->permohonan->unit->nama_unit ?? $paket->permohonan->kode_unit }}</p>
                    </div>
                    <div class="pt-2 border-t border-slate-200/60 flex flex-wrap gap-2">
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $paket->permohonan->nomor_surat]) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#0969da] hover:underline">
                            <svg class="size-3 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Surat Permohonan
                        </a>
                        @if($paket->permohonan->file_kak)
                            <a href="{{ asset('storage/' . $paket->permohonan->file_kak) }}" target="_blank" class="text-[11px] font-semibold text-[#0969da] hover:underline">
                                KAK
                            </a>
                        @endif
                        @if($paket->permohonan->file_spesifikasi_teknis)
                            <a href="{{ asset('storage/' . $paket->permohonan->file_spesifikasi_teknis) }}" target="_blank" class="text-[11px] font-semibold text-[#0969da] hover:underline">
                                Spek Teknis
                            </a>
                        @endif
                    </div>
                </div>

                {{-- 2. Penetapan Rekening MAK DIPA --}}
                <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">2. Penetapan MAK DIPA</span>
                    <div>
                        <span class="font-mono font-bold text-emerald-700 block">{{ $paket->permohonan->kode_mak ?? '(MAK Belum Terhubung)' }}</span>
                        <p class="text-xs text-slate-800 font-medium mt-0.5">{{ $paket->permohonan->anggaranMak->uraian_mak ?? 'Pagu DIPA Kampus' }}</p>
                        <p class="text-slate-500 text-[11px] mt-0.5">Pagu Usulan: <strong class="font-mono text-slate-800">Rp {{ number_format($paket->permohonan->anggaran_diajukan ?? $paket->permohonan->estimasi_total_pagu, 0, ',', '.') }}</strong></p>
                    </div>
                    <div class="pt-2 border-t border-slate-200/60">
                        <span class="text-[11px] text-emerald-700 font-bold inline-flex items-center gap-1">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Mata Anggaran Telah Terkunci
                        </span>
                    </div>
                </div>

                @php
                    $vpWadir = $paket->permohonan?->verifikasiParalel?->firstWhere('role_verifikator', 'wadir_2');
                    $vpPpk = $paket->permohonan?->verifikasiParalel?->firstWhere('role_verifikator', 'ppk');
                    $fileWadir = $vpWadir?->file_memo ?? $paket->permohonan?->file_memo_ketersediaan_mak;
                    $filePpk = $vpPpk?->file_memo ?? $paket->permohonan?->file_memo_cek_mak;
                @endphp

                {{-- 3. Memo Ketersediaan MAK & Persetujuan Paralel --}}
                <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">3. Memo Masuk (Wadir II &amp; PPK)</span>
                    <div class="space-y-2">
                        {{-- Memo Wadir 2 --}}
                        <div class="text-[11px] space-y-0.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-purple-900">1. Disposisi Wadir II:</span>
                                <span class="font-bold text-emerald-700">{{ $vpWadir ? ucwords($vpWadir->status_keputusan) : 'Disetujui' }}</span>
                            </div>
                            <p class="font-mono text-slate-700 text-[10px]">No: {{ $vpWadir?->nomor_memo ?? $paket->permohonan->nomor_memo_ketersediaan_mak ?? 'Terbit Resmi' }}</p>
                            @if($fileWadir)
                                <a href="{{ asset('storage/' . $fileWadir) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-[#0969da] hover:underline">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Unduh Berkas Fisik Memo Wadir</span>
                                </a>
                            @endif
                        </div>

                        {{-- Memo PPK --}}
                        <div class="text-[11px] space-y-0.5 pt-1.5 border-t border-slate-200/60">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-blue-900">2. Persetujuan PPK:</span>
                                <span class="font-bold text-emerald-700">{{ $vpPpk ? ucwords($vpPpk->status_keputusan) : 'Disetujui' }}</span>
                            </div>
                            <p class="text-slate-800 text-[10px]">Pejabat: <strong>{{ $paket->permohonan->ppkAwal->nama ?? ($vpPpk?->user?->nama ?? 'PPK Penanggung Jawab') }}</strong></p>
                            <p class="font-mono text-slate-700 text-[10px]">No: {{ $vpPpk?->nomor_memo ?? $paket->permohonan->nomor_memo_cek_mak ?? 'Terbit Resmi' }}</p>
                            @if($filePpk)
                                <a href="{{ asset('storage/' . $filePpk) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-[#0969da] hover:underline">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Unduh Berkas Fisik Memo PPK</span>
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="pt-2 border-t border-slate-200/60">
                        <span class="text-[10px] text-slate-400 italic">Disetujui penuh sebelum registrasi paket</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 5. Dokumen Turunan Siklus Berikutnya (SPK, BAST, Pembayaran) --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <h3 class="text-sm font-bold text-[#213153] mb-4">Dokumen Rangkaian Siklus Pengadaan</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Dokumen Perikatan / Kwitansi --}}
            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                <div>
                    @if($isBelanjaLangsung)
                        <span class="text-[10px] font-bold uppercase tracking-wider text-teal-600">Tahap 5: Belanja Langsung (Kwitansi)</span>
                        <h4 class="text-sm font-bold text-slate-800 mt-1">
                            {{ $paket->nomor_kwitansi_belanja ?? 'Belum Ada Kwitansi' }}
                        </h4>
                        <p class="text-xs text-slate-500 mt-1">
                            Realisasi: Rp {{ number_format($paket->nominal_harga_akhir ?: $paket->nilai_hps, 0, ',', '.') }}
                        </p>
                        @if($paket->file_kwitansi_belanja)
                            <a href="{{ asset('storage/' . $paket->file_kwitansi_belanja) }}" target="_blank" class="text-[11px] text-teal-700 underline mt-1 block font-semibold">
                                Unduh Kwitansi Pembelian (PDF/Foto)
                            </a>
                        @endif
                    @else
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tahap 5: Dokumen Perikatan</span>
                        <h4 class="text-sm font-bold text-slate-800 mt-1">
                            {{ $paket->kontrakSpk->nomor_spk ?? 'Belum Diterbitkan' }}
                        </h4>
                        @if($paket->kontrakSpk)
                            <p class="text-xs text-slate-500 mt-1">Tipe: {{ strtoupper($paket->kontrakSpk->tipe_dokumen ?? 'SPK') }} &bull; Nilai: Rp {{ number_format($paket->kontrakSpk->nilai_kontrak, 0, ',', '.') }}</p>
                        @else
                            <p class="text-xs text-slate-400 mt-1">Menunggu penelaahan reviu teknis atau penerbitan SPK.</p>
                        @endif
                    @endif
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200/60">
                    @if(! $isBelanjaLangsung)
                        <a href="{{ route('kontrak.index') }}" class="text-xs font-bold text-[#0969da] hover:underline">
                            Buka Modul Dokumen Perikatan &rarr;
                        </a>
                    @else
                        <span class="text-xs text-slate-400">Kwitansi sah Belanja Langsung</span>
                    @endif
                </div>
            </div>

            {{-- Dokumen BAST --}}
            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tahap 7: BAST Fisik</span>
                    @php
                        $bast = $paket->penerimaanBarang->first();
                    @endphp
                    <h4 class="text-sm font-bold text-slate-800 mt-1">
                        {{ $bast->nomor_bast ?? 'Belum Diperiksa' }}
                    </h4>
                    @if($bast)
                        <p class="text-xs text-slate-500 mt-1">Status: {{ strtoupper($bast->kondisi_barang ?? 'Diterima') }}</p>
                    @else
                        <p class="text-xs text-slate-400 mt-1">Menunggu barang dikirim rekanan &amp; diperiksa Perlengkapan.</p>
                    @endif
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200/60">
                    <a href="{{ route('penerimaan.index') }}" class="text-xs font-bold text-[#0969da] hover:underline">
                        Buka Modul Penerimaan &rarr;
                    </a>
                </div>
            </div>

            {{-- Dokumen Pembayaran --}}
            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tahap 8: SP2D Cair</span>
                    @php
                        $bayar = $paket->pembayaran->first();
                    @endphp
                    <h4 class="text-sm font-bold text-slate-800 mt-1">
                        {{ $bayar->nomor_pembayaran ?? 'Belum Diajukan' }}
                    </h4>
                    @if($bayar)
                        <p class="text-xs text-slate-500 mt-1">Status: {{ strtoupper($bayar->status_pembayaran ?? 'Diproses') }}</p>
                    @else
                        <p class="text-xs text-slate-400 mt-1">Menunggu BAST terbit untuk pengajuan pembayaran.</p>
                    @endif
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200/60">
                    <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline">
                        Buka Modul Pembayaran &rarr;
                    </a>
                </div>
            </div>

        </div>
    </div>

    {{-- 6. Berita Acara Telaah & Persetujuan Dokumen Pengadaan (BPMN Fase 2 - N30, N33, N35, N36) --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-[#213153] flex items-center gap-2">
                    <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Berita Acara Telaah &amp; Persetujuan Dokumen Pengadaan</span>
                    <span class="text-xs font-normal text-slate-400 font-mono">(PP &amp; PPK)</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Keputusan persetujuan dokumen pengadaan dan catatan berita acara rekomendasi penelaahan.</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700">
                {{ $paket->reviuDokumen->count() }} Catatan Reviu
            </span>
        </div>

        @if($paket->reviuDokumen->isEmpty())
            <div class="text-center py-6 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Belum ada catatan reviu atau persetujuan dokumen pengadaan yang tercatat pada paket ini.
            </div>
        @else
            <div class="space-y-3">
                @foreach($paket->reviuDokumen as $rd)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-xs text-[#0969da]">Putaran #{{ $rd->putaran_ke }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $rd->role_reviewer === 'ppk' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ strtoupper($rd->role_reviewer) }}
                                </span>
                                <span class="text-xs font-semibold text-slate-700">&bull; {{ $rd->user->nama ?? '-' }}</span>
                            </div>
                            <x-status-badge :status="$rd->status_reviu" size="sm" />
                        </div>
                        <div class="rounded-lg bg-white p-3 border border-slate-200/80 text-xs text-slate-700 leading-relaxed">
                            <span class="font-bold text-slate-800 block mb-0.5">Catatan / Berita Acara Rekomendasi:</span>
                            {{ $rd->catatan_perbaikan ?: 'Dokumen pengadaan disetujui tanpa catatan perbaikan tambahan.' }}
                        </div>
                        <div class="text-[10px] text-slate-400 text-right">
                            {{ $rd->tanggal_reviu ? \Carbon\Carbon::parse($rd->tanggal_reviu)->format('d/m/Y H:i') : '-' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 7. Riwayat Penugasan & Mutasi PPK Paket Ini --}}
    @if($paket->riwayatPpk && $paket->riwayatPpk->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
            <h3 class="text-sm font-bold text-[#213153] mb-3">Riwayat Pengalihan Tugas PPK (Paket Ini)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase">
                            <th class="px-4 py-2.5">Waktu</th>
                            <th class="px-4 py-2.5">PPK Lama</th>
                            <th class="px-4 py-2.5">PPK Baru</th>
                            <th class="px-4 py-2.5">Ditetapkan Oleh</th>
                            <th class="px-4 py-2.5">Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($paket->riwayatPpk as $rw)
                            <tr>
                                <td class="px-4 py-3 font-mono text-slate-500">
                                    {{ $rw->tanggal_penugasan ? \Carbon\Carbon::parse($rw->tanggal_penugasan)->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $rw->ppkLama->nama ?? 'Awal' }}</td>
                                <td class="px-4 py-3 font-semibold text-emerald-700">{{ $rw->ppkBaru->nama ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $rw->userPenetap->nama ?? ($rw->diubahOleh->nama ?? 'Sistem') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $rw->alasan_pengalihan ?? ($rw->alasan_perubahan ?? '-') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
