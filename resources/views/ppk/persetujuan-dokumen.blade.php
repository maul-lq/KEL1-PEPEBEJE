@extends('layouts.app')

@section('title', 'Persetujuan Dokumen Pengadaan (PPK) - SIM-PBJ PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    <!-- 1. HEADER HALAMAN & ROLE PILL -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="rounded-lg bg-teal-100 text-[#087F79] px-2.5 py-1 text-xs font-bold font-mono">BPMN N30 - N36 &bull; Tahap 4</span>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Persetujuan Dokumen Pengadaan (PPK)
                </h1>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Pejabat Pembuat Komitmen (PPK) memeriksa dokumen pengadaan hasil susunan PPBJ dan verifikasi Pejabat Pengadaan (PP). Persetujuan PPK (ACC) menjadi syarat mutlak sebelum PPBJ dapat melaksanakan belanja langsung atau menerbitkan dokumen perikatan (SPK/Kontrak/Surat Pesanan).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 border border-teal-200/80 px-3.5 py-1 text-xs font-bold text-[#087F79]">
                <span class="size-2 rounded-full bg-[#087F79] animate-pulse"></span>
                {{ Auth::user()->nama }} &bull; PPK Penanggung Jawab
            </span>
        </div>
    </div>

    <!-- 2. STEPPER SIKLUS PENGADAAN (TAHAP 4: REVIEW & PERSETUJUAN DOKUMEN) -->
    <x-stepper-8-siklus :currentStep="4" status="reviu_dokumen" />

    <!-- 3. KPI STAT CARDS -->
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card title="Antrean Review Dokumen" 
                     :value="$paketAntrean->count()" 
                     subtitle="Menunggu persetujuan Anda (N30)" 
                     color="amber" />

        <x-stat-card title="Dokumen Telah Disetujui" 
                     :value="$riwayatDisetujui->count()" 
                     subtitle="Diteruskan ke PPBJ (N36)" 
                     color="emerald" />

        <x-stat-card title="Dikembalikan ke PPBJ" 
                     :value="$riwayatDitolak->count()" 
                     subtitle="Perlu susun ulang (N18)" 
                     color="rose" />
    </div>

    <!-- 4. DAFTAR ANTREAN PAKET MENUNGGU PERSETUJUAN PPK -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                <h2 class="text-lg font-bold text-[#213153]">Antrean Dokumen Pengadaan Siap Review</h2>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $paketAntrean->count() }} Paket Siap Keputusan</span>
        </div>

        @if($paketAntrean->isEmpty())
            <div class="rounded-2xl bg-white p-12 text-center shadow-xs border border-slate-200/80">
                <div class="size-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                    <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-base font-bold text-[#213153]">Tidak Ada Antrean Dokumen Pengadaan</h3>
                <p class="mt-1 text-xs text-slate-400 max-w-md mx-auto">
                    Seluruh dokumen pengadaan yang dialokasikan ke akun PPK Anda telah diproses. Paket baru akan muncul setelah PPBJ menyusun paket atau PP menyelesaikan verifikasi teknis.
                </p>
            </div>
        @else
            <div class="space-y-6">
                @foreach($paketAntrean as $pkt)
                    @php
                        $lastPpReviu = $pkt->reviuDokumen->where('role_reviewer', 'pp')->last();
                    @endphp

                    <div class="rounded-2xl bg-white p-6 md:p-7 shadow-xs border border-slate-200/80 space-y-6">
                        
                        <!-- Card Header -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="font-mono text-sm font-bold text-[#087F79]">{{ $pkt->nomor_paket }}</span>
                                    <x-status-badge :status="$pkt->status_paket" size="sm" />
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $pkt->jalur_routing === 'langsung_sd_50jt' ? 'bg-teal-100 text-teal-800' : ($pkt->jalur_routing === 'ppk_diatas_200jt' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800') }}">
                                        {{ strtoupper(str_replace('_', ' ', $pkt->jalur_routing)) }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    No. Surat Usulan: <strong class="text-slate-800 font-mono">{{ $pkt->nomor_surat }}</strong> &bull;
                                    Unit: <strong class="text-slate-800">{{ $pkt->permohonan->unit->nama_unit ?? ($pkt->permohonan->kode_unit ?? '-') }}</strong> &bull;
                                    Pemohon: {{ $pkt->permohonan->user->nama ?? '-' }}
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[11px] text-slate-400 block">Pagu HPS Pengadaan</span>
                                <div class="text-lg font-extrabold text-[#087F79] font-mono">
                                    Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        <!-- Detail Paket & Spesifikasi -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                            
                            <!-- Kolom Kiri: Informasi Paket & Berkas Penunjang -->
                            <div class="lg:col-span-2 space-y-4">
                                @php
                                    $lastPerbaikan = $pkt->reviuDokumen->where('status_reviu', 'perlu_perbaikan')->last();
                                @endphp
                                @if($lastPerbaikan)
                                    <div class="rounded-xl border border-rose-200 bg-rose-50/80 p-4 space-y-1.5">
                                        <div class="flex items-center justify-between text-xs font-bold text-rose-900">
                                            <span class="flex items-center gap-1.5">
                                                <svg class="size-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                Dokumen Telah Disusun Ulang oleh PPBJ (Pasca Penolakan Putaran ke-{{ $lastPerbaikan->putaran_ke }})
                                            </span>
                                            <span class="text-[10px] bg-rose-100 text-rose-800 px-2.5 py-0.5 rounded-full font-mono">
                                                Ditolak: {{ $lastPerbaikan->tanggal_reviu ? date('d/m/Y H:i', strtotime($lastPerbaikan->tanggal_reviu)) : '-' }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-rose-800">
                                            <span class="font-semibold">Catatan Pengembalian Sebelumnya:</span>
                                            <p class="italic bg-white/90 p-2.5 rounded-lg border border-rose-200 mt-1">
                                                "{{ $lastPerbaikan->catatan_perbaikan }}"
                                            </p>
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <h3 class="text-base font-bold text-[#213153] leading-snug">{{ $pkt->nama_paket }}</h3>
                                    <p class="text-xs text-slate-600 mt-1 leading-relaxed bg-slate-50/80 p-3 rounded-xl border border-slate-100">
                                        {{ $pkt->permohonan->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa institusi sesuai Kerangka Acuan Kerja dan persetujuan DIPA kampus.' }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-slate-50/60 p-4 rounded-xl border border-slate-200/60">
                                    <div>
                                        <span class="text-slate-400 block text-[11px]">Jenis Pengadaan:</span>
                                        <span class="font-bold text-slate-800 uppercase">{{ str_replace('_', ' ', $pkt->jenis_pengadaan ?? 'Barang') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[11px]">Mekanisme / Platform:</span>
                                        <span class="font-bold text-slate-800">{{ $pkt->mekanisme_pengadaan ?? $pkt->metode_pengadaan }} ({{ $pkt->platform_pengadaan ?? 'Inaproc/SPSE' }})</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[11px]">Pejabat Pembuat Komitmen:</span>
                                        <span class="font-bold text-[#087F79]">{{ $pkt->ppkUser->nama ?? Auth::user()->nama }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[11px]">Disusun Oleh (PPBJ):</span>
                                        <span class="font-bold text-slate-800">{{ $pkt->ppbjUser->nama ?? 'Unit PPBJ PNJ' }}</span>
                                    </div>
                                </div>

                                <!-- Dokumen Penunjang Pengadaan -->
                                <div class="space-y-2">
                                    <span class="text-xs font-bold text-slate-700 block">Berkas &amp; Dokumen Pengadaan:</span>
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        @if($pkt->file_dokumen_pengadaan)
                                            <a href="{{ asset('storage/' . $pkt->file_dokumen_pengadaan) }}" 
                                               target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-50 border border-blue-200 text-[#0969da] text-xs font-bold hover:bg-blue-100 transition shadow-xs">
                                                <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                Dokumen Pengadaan (PPBJ)
                                            </a>
                                        @endif

                                        @if($pkt->permohonan && $pkt->permohonan->file_kak)
                                            <a href="{{ asset('storage/' . $pkt->permohonan->file_kak) }}" 
                                               target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-purple-50 border border-purple-200 text-purple-700 text-xs font-bold hover:bg-purple-100 transition shadow-xs">
                                                <svg class="size-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                Kerangka Acuan Kerja (KAK)
                                            </a>
                                        @endif

                                        @if($pkt->permohonan && $pkt->permohonan->file_spesifikasi_teknis)
                                            <a href="{{ asset('storage/' . $pkt->permohonan->file_spesifikasi_teknis) }}" 
                                               target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition shadow-xs">
                                                <svg class="size-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                                Spesifikasi Teknis
                                            </a>
                                        @endif

                                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $pkt->nomor_surat]) }}" 
                                           target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-200 transition shadow-xs">
                                            <svg class="size-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            Surat Srikandi (PDF)
                                        </a>

                                        <a href="{{ route('paket.show', $pkt->nomor_paket) }}" 
                                           class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-100 transition">
                                            <span>Detail Paket Lengkap &rarr;</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Box Telaah Khusus Jalur PP (50-200 Juta: BPMN N27 & N31) -->
                                @if(in_array($pkt->jalur_routing, ['pp_50jt_sd_200jt', 'pp_50_sd_200jt']))
                                    <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-blue-900 flex items-center gap-1.5">
                                                <svg class="size-4 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Hasil Verifikasi Dokumen oleh Pejabat Pengadaan (PP) &bull; BPMN N27 &amp; N31
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">Verifikasi Berhasil</span>
                                        </div>
                                        <div class="text-xs text-slate-700 space-y-1">
                                            <div>Pejabat Pengadaan (PP): <strong>{{ $pkt->ppUser->nama ?? ($lastPpReviu->user->nama ?? 'PP Bertugas') }}</strong></div>
                                            <p class="italic bg-white p-2.5 rounded-lg border border-blue-100 text-slate-700 mt-1">
                                                "{{ $lastPpReviu?->catatan_perbaikan ?: ($pkt->catatan_verifikasi_pp ?: 'Spesifikasi teknis dan kewajaran HPS telah diverifikasi oleh PP (BPMN N27: Iya). Berkas diteruskan ke PPK (N31 -> N30).') }}"
                                            </p>
                                        </div>
                                    </div>
                                @elseif($pkt->jalur_routing === 'langsung_sd_50jt')
                                    <div class="rounded-xl border border-teal-200 bg-teal-50/50 p-3 text-xs text-teal-900 flex items-center gap-2">
                                        <svg class="size-4 text-teal-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>Paket Belanja Langsung (&le; Rp 50 Juta): Setelah PPK menyetujui dokumen, PPBJ dapat langsung mengeksekusi pembelian dan mengunggah Kwitansi.</span>
                                    </div>
                                @else
                                    <div class="rounded-xl border border-purple-200 bg-purple-50/50 p-3 text-xs text-purple-900 flex items-center gap-2">
                                        <svg class="size-4 text-purple-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>Paket Tender / Seleksi (&gt; Rp 200 Juta): Pengadaan strategis langsung berada di bawah pengawasan dan persetujuan PPK (N28 &rarr; N30).</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Kolom Kanan: Form Keputusan Persetujuan PPK (BPMN N35 & N36) -->
                            <div class="rounded-xl bg-slate-50 p-5 border border-slate-200 space-y-4"
                                 x-data="{ keputusan: 'disetujui' }">
                                <div class="border-b border-slate-200 pb-3">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                        <svg class="size-4 text-[#087F79]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        Keputusan PPK (BPMN N35)
                                    </h4>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Tentukan persetujuan dokumen pengadaan</p>
                                </div>

                                <form action="{{ route('ppk.persetujuan-dokumen.keputusan') }}" method="POST" class="space-y-4">
                                    @csrf
                                    <input type="hidden" name="nomor_paket" value="{{ $pkt->nomor_paket }}">

                                    <!-- Pilihan Radio Keputusan -->
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                               :class="keputusan === 'disetujui' ? 'border-[#087F79] bg-teal-50/80 shadow-xs' : 'border-slate-200 bg-white hover:border-teal-300'">
                                            <input type="radio" 
                                                   name="keputusan" 
                                                   value="disetujui" 
                                                   x-model="keputusan"
                                                   class="size-4 text-[#087F79] focus:ring-[#087F79] mt-0.5">
                                            <div>
                                                <span class="text-xs font-extrabold text-[#087F79] block">SETUJUI DOKUMEN</span>
                                                <span class="text-[11px] text-slate-500 leading-tight block mt-0.5">
                                                    Dokumen pengadaan disetujui; PPBJ dapat melaksanakan belanja &amp; menerbitkan perikatan.
                                                </span>
                                            </div>
                                        </label>

                                        <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                               :class="keputusan === 'ditolak' ? 'border-rose-500 bg-rose-50/80 shadow-xs' : 'border-slate-200 bg-white hover:border-rose-300'">
                                            <input type="radio" 
                                                   name="keputusan" 
                                                   value="ditolak" 
                                                   x-model="keputusan"
                                                   class="size-4 text-rose-600 focus:ring-rose-500 mt-0.5">
                                            <div>
                                                <span class="text-xs font-extrabold text-rose-800 block">KEMBALIKAN KE PPBJ</span>
                                                <span class="text-[11px] text-slate-500 leading-tight block mt-0.5">
                                                    Dokumen belum layak / perlu perbaikan; PPBJ wajib menyusun ulang dokumen.
                                                </span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Catatan / Pertimbangan Keputusan PPK -->
                                    <div class="space-y-1">
                                        <label for="catatan_{{ $pkt->nomor_paket }}" class="block text-xs font-bold text-slate-700">
                                            Catatan / Pertimbangan PPK
                                            <span x-show="keputusan === 'ditolak'" class="text-rose-600 font-bold">* (Wajib diisi saat menolak)</span>
                                            <span x-show="keputusan === 'disetujui'" class="text-slate-400 font-normal">(Opsional)</span>
                                        </label>
                                        <textarea id="catatan_{{ $pkt->nomor_paket }}" 
                                                  name="catatan_alasan" 
                                                  rows="3" 
                                                  :required="keputusan === 'ditolak'"
                                                  placeholder="Tuliskan catatan teknis atau alasan perbaikan dokumen pengadaan..."
                                                  class="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs text-slate-800 focus:border-[#087F79] focus:ring-2 focus:ring-[#087F79]/20 outline-none"></textarea>
                                    </div>

                                    <div class="pt-1">
                                        <button type="submit" 
                                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold text-white shadow-md transition-all cursor-pointer"
                                                :class="keputusan === 'disetujui' ? 'bg-[#087F79] hover:bg-[#06635e] shadow-[#087F79]/20' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/20'">
                                            <span x-text="keputusan === 'disetujui' ? 'Simpan & Setujui Dokumen (ACC)' : 'Kembalikan Berkas ke PPBJ'"></span>
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>

                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- 5. RIWAYAT DOKUMEN YANG TELAH DIPUTUSKAN OLEH PPK -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start pt-4">
        
        <!-- Riwayat Disetujui -->
        <div class="rounded-2xl bg-white p-6 shadow-xs border border-slate-200/80 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <h3 class="text-sm font-bold text-[#213153]">Riwayat Dokumen Disetujui PPK</h3>
                </div>
                <span class="text-xs font-mono font-bold text-emerald-700">{{ $riwayatDisetujui->count() }} Paket</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-100">
                            <th class="px-3 py-2.5">Nomor Paket</th>
                            <th class="px-3 py-2.5">Nama Paket</th>
                            <th class="px-3 py-2.5">HPS</th>
                            <th class="px-3 py-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($riwayatDisetujui as $rSetuju)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-3 py-2.5 font-mono font-bold text-[#087F79]">{{ $rSetuju->nomor_paket }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="font-semibold">{{ $rSetuju->nama_paket }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $rSetuju->permohonan->unit->nama_unit ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-2.5 font-mono">Rp {{ number_format($rSetuju->nilai_hps, 0, ',', '.') }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <x-status-badge :status="$rSetuju->status_paket" size="sm" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-slate-400 italic">Belum ada riwayat dokumen yang disetujui.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Riwayat Dikembalikan / Perlu Perbaikan -->
        <div class="rounded-2xl bg-white p-6 shadow-xs border border-slate-200/80 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-rose-500"></span>
                    <h3 class="text-sm font-bold text-[#213153]">Riwayat Dokumen Dikembalikan ke PPBJ</h3>
                </div>
                <span class="text-xs font-mono font-bold text-rose-700">{{ $riwayatDitolak->count() }} Paket</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-100">
                            <th class="px-3 py-2.5">Nomor Paket</th>
                            <th class="px-3 py-2.5">Nama Paket</th>
                            <th class="px-3 py-2.5">Catatan Perbaikan PPK</th>
                            <th class="px-3 py-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($riwayatDitolak as $rTolak)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-3 py-2.5 font-mono font-bold text-rose-700">{{ $rTolak->nomor_paket }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="font-semibold">{{ $rTolak->nama_paket }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $rTolak->permohonan->unit->nama_unit ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-[11px] text-slate-600 max-w-xs truncate" title="{{ $rTolak->catatan_verifikasi_pp }}">
                                    {{ $rTolak->catatan_verifikasi_pp ?: '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <x-status-badge status="perlu_perbaikan" size="sm" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-slate-400 italic">Belum ada riwayat dokumen yang dikembalikan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection
