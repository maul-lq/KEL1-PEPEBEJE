@extends('layouts.app')

@section('title', 'Detail Paket Pengadaan ' . ($paket->kode_paket ?? $paket->nomor_paket) . ' - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6">

    @php
        $stepMap = [
            'terdaftar' => 3,
            'reviu_dokumen' => 4,
            'perlu_perbaikan' => 4,
            'reviu_dokumen_disetujui' => 4,
            'spk_terbit' => 5,
            'selesai' => 8,
        ];
        $currentStep = $stepMap[$paket->status_paket] ?? 3;
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

            <div class="flex items-center gap-2.5 shrink-0">
                <a
                    href="{{ route('paket.index') }}"
                    class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    &larr; Kembali
                </a>

                <a
                    href="{{ route('kontrak.index') }}"
                    class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <span>Modul Kontrak / SPK</span>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
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

    {{-- 4. Berkas Usulan Permohonan Induk --}}
    @if($paket->permohonan)
        <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
            <h3 class="text-sm font-bold text-[#213153] mb-3">Usulan Permohonan Pengadaan Terkait</h3>
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                <div>
                    <span class="font-mono font-bold text-[#0969da]">{{ $paket->permohonan->nomor_surat }}</span>
                    <h4 class="font-bold text-slate-800 text-sm mt-0.5">{{ $paket->permohonan->perihal }}</h4>
                    <p class="text-slate-500 mt-0.5">Unit: {{ $paket->permohonan->unit->nama_unit ?? $paket->permohonan->kode_unit }} &bull; Estimasi Pagu: Rp {{ number_format($paket->permohonan->anggaran_diajukan ?? $paket->permohonan->estimasi_total_pagu, 0, ',', '.') }}</p>
                </div>
                <a
                    href="{{ route('permohonan.show', $paket->permohonan->nomor_surat) }}"
                    class="px-3.5 py-1.5 rounded-lg border border-slate-200 bg-white font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs text-xs shrink-0 inline-flex items-center gap-1"
                >
                    Lihat Berkas Permohonan &rarr;
                </a>
            </div>
        </div>
    @endif

    {{-- 5. Dokumen Turunan Siklus Berikutnya (SPK, BAST, Pembayaran) --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <h3 class="text-sm font-bold text-[#213153] mb-4">Dokumen Rangkaian Siklus Pengadaan</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Dokumen SPK --}}
            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tahap 5: Kontrak SPK</span>
                    <h4 class="text-sm font-bold text-slate-800 mt-1">
                        {{ $paket->kontrakSpk->nomor_spk ?? 'Belum Diterbitkan' }}
                    </h4>
                    @if($paket->kontrakSpk)
                        <p class="text-xs text-slate-500 mt-1">Nilai Kontrak: Rp {{ number_format($paket->kontrakSpk->nilai_kontrak, 0, ',', '.') }}</p>
                    @else
                        <p class="text-xs text-slate-400 mt-1">Menunggu penelaahan reviu teknis atau penerbitan SPK.</p>
                    @endif
                </div>
                <div class="mt-4 pt-3 border-t border-slate-200/60">
                    <a href="{{ route('kontrak.index') }}" class="text-xs font-bold text-[#0969da] hover:underline">
                        Buka Modul SPK &rarr;
                    </a>
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

    {{-- 6. Riwayat Penugasan & Mutasi PPK Paket Ini --}}
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
