@extends('layouts.app')

@section('title', 'Persetujuan Awal Usulan PPK - SIM-PBJ PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    <!-- 1. HEADER HALAMAN & ROLE PILL -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Persetujuan Awal Usulan Pengadaan (PPK)
                </h1>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Pejabat Pembuat Komitmen (PPK) terpilih meninjau ketersediaan MAK DIPA kampus dan memberikan persetujuan awal (ACC) bersama Wakil Direktur II sebelum berkas usulan masuk ke tahap pengadaan di PPBJ.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 border border-teal-200/80 px-3.5 py-1 text-xs font-bold text-[#087F79]">
                <span class="size-2 rounded-full bg-[#087F79] animate-pulse"></span>
                {{ Auth::user()->nama }} &bull; PPK Terpilih
            </span>
        </div>
    </div>

    <!-- 2. STEPPER SIKLUS PENGADAAN (TAHAP 2: VERIFIKASI BERANTAI) -->
    <x-stepper-8-siklus :currentStep="2" status="reviu_paralel" />

    <!-- 3. KPI STAT CARDS -->
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card title="Antrean Persetujuan PPK" 
                     :value="$permohonanAntrean->count()" 
                     subtitle="Menunggu persetujuan Anda" 
                     color="teal" />

        <x-stat-card title="Telah Disetujui PPK" 
                     :value="\App\Models\VerifikasiParalel::where('role_verifikator', 'ppk')->where('id_user', Auth::id())->where('status_keputusan', 'disetujui')->count()" 
                     subtitle="Disetujui akun ini" 
                     color="emerald" />

        <x-stat-card title="Ditolak / Dikembalikan" 
                     :value="\App\Models\VerifikasiParalel::where('role_verifikator', 'ppk')->where('id_user', Auth::id())->where('status_keputusan', 'ditolak')->count()" 
                     subtitle="Usulan tidak dilanjutkan" 
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
                Saat ini tidak ada permohonan pengadaan yang menunggu keputusan persetujuan awal pada akun PPK Anda.
            </p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($permohonanAntrean as $p)
                @php
                    $vpWadir = $p->verifikasiParalel->where('role_verifikator', 'wadir_2')->first();
                    $vpPerencanaan = $p->verifikasiParalel->where('role_verifikator', 'perencanaan')->first();
                    $vpPpk = $p->verifikasiParalel->where('role_verifikator', 'ppk')->where('id_user', Auth::id())->first();
                @endphp

                <div class="rounded-2xl bg-white p-6 md:p-7 shadow-xs border border-slate-200/80 space-y-5">
                    
                    <!-- Card Header -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-[#087F79]">{{ $p->nomor_surat }}</span>
                                <x-status-badge :status="$p->status_permohonan ?? $p->status" size="sm" />
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                Unit Pengusul: <strong class="text-slate-800">{{ $p->unit->nama_unit ?? $p->kode_unit }}</strong> ({{ $p->kode_unit }})
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 block">Tanggal Usulan: {{ $p->tanggal_pengajuan ? date('d/m/Y', strtotime($p->tanggal_pengajuan)) : '-' }}</span>
                            <div class="text-base font-extrabold text-[#087F79] font-mono">
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

                    <!-- Box Status Ketersediaan MAK & Status Disposisi Wadir 2 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        
                        <!-- Box Ketersediaan MAK Perencanaan -->
                        <div class="rounded-xl bg-teal-50/70 p-3.5 border border-teal-200/70 space-y-1.5">
                            <div class="font-bold text-[#087F79] flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4 text-[#087F79]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Memo Ketersediaan MAK (Perencanaan):
                                </span>
                                <span class="font-mono text-[11px] font-bold text-teal-800">{{ $p->nomor_memo_ketersediaan_mak ?? '-' }}</span>
                            </div>
                            <div class="text-slate-700 leading-relaxed space-y-0.5">
                                <div>Pos Rekening MAK: <strong class="font-mono text-[#0969da]">{{ $p->kode_mak ?? '-' }}</strong> ({{ $p->anggaranMak->uraian_mak ?? 'DIPA PNJ' }})</div>
                                @if($p->catatan_memo_ketersediaan_mak)
                                    <div class="text-slate-600 italic">&ldquo;{{ $p->catatan_memo_ketersediaan_mak }}&rdquo;</div>
                                @endif
                            </div>
                        </div>

                        <!-- Box Status Persetujuan Wadir 2 -->
                        <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-200/70 space-y-1.5">
                            <div class="font-bold text-slate-800 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Status Otoritas Wadir II:
                                </span>
                                @if($vpWadir && $vpWadir->status_keputusan === 'disetujui')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">Telah Disetujui (ACC)</span>
                                @elseif($vpWadir && $vpWadir->status_keputusan === 'ditolak')
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">Ditolak Wadir II</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Menunggu ACC Wadir II</span>
                                @endif
                            </div>
                            <p class="text-slate-600 leading-relaxed text-[11px]">
                                Berkas otomatis dilanjutkan ke PPBJ setelah Wakil Direktur II dan PPK terpilih sama-sama memberikan persetujuan penuh.
                            </p>
                        </div>
                    </div>

                    <!-- Lampiran Quick Links -->
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" 
                           target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                            <svg class="size-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <span>Lihat Naskah Dinas Srikandi (PDF)</span>
                        </a>

                        <a href="{{ route('permohonan.show', $p->nomor_surat) }}" 
                           class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                            <span>Detail Penuh &amp; Silsilah &rarr;</span>
                        </a>
                    </div>

                    <!-- FORM KEPUTUSAN AWAL PPK TERPILIH -->
                    <form action="{{ route('verifikasi.ppk.keputusan') }}" 
                          method="POST" 
                          enctype="multipart/form-data"
                          x-data="{ 
                              keputusan: 'disetujui', 
                              get isMandatory() { return this.keputusan === 'ditolak'; } 
                          }"
                          class="rounded-xl bg-slate-50/70 p-5 md:p-6 border border-slate-200/80 space-y-4">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                <svg class="size-4 text-[#087F79]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Keputusan Persetujuan Awal PPK
                            </h3>
                            <span class="text-[11px] text-slate-400">Pilih tindakan verifikasi usulan</span>
                        </div>

                        <!-- 2-Way Radio Decision Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            
                            <!-- 1. SETUJUI USULAN (ACC PPK) -->
                            <label class="flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all"
                                   :class="keputusan === 'disetujui' ? 'border-[#087F79] bg-teal-50/60 shadow-xs' : 'border-slate-200 bg-white hover:border-teal-300'">
                                <div class="flex items-center gap-2.5">
                                    <input type="radio" 
                                           name="keputusan" 
                                           value="disetujui" 
                                           x-model="keputusan"
                                           class="size-4 text-[#087F79] focus:ring-[#087F79]">
                                    <span class="text-xs font-extrabold text-[#087F79]">SETUJUI USULAN (ACC PPK)</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">
                                    Setujui usulan pengadaan atas alokasi MAK Perencanaan untuk dilanjutkan ke PPBJ bersama persetujuan Wadir II.
                                </p>
                            </label>

                            <!-- 2. TOLAK / MINTA REVISI -->
                            <label class="flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all"
                                   :class="keputusan === 'ditolak' ? 'border-rose-500 bg-rose-50/60 shadow-xs' : 'border-slate-200 bg-white hover:border-rose-300'">
                                <div class="flex items-center gap-2.5">
                                    <input type="radio" 
                                           name="keputusan" 
                                           value="ditolak" 
                                           x-model="keputusan"
                                           class="size-4 text-rose-600 focus:ring-rose-500">
                                    <span class="text-xs font-extrabold text-rose-800">TOLAK / MINTA REVISI</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">
                                    Kembalikan usulan ke unit pemohon disertai alasan penolakan tertulis. <strong>Catatan alasan wajib diisi</strong>.
                                </p>
                            </label>

                        </div>

                        <!-- Kelengkapan Berkas Fisik Memo Persetujuan Usulan PPK (BPMN Fase 1) -->
                        <div x-show="keputusan === 'disetujui'" class="p-4 rounded-xl bg-teal-50/60 border border-teal-200/80 space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-teal-200/80 text-teal-900 px-2 py-0.5 text-[10px] font-bold font-mono">BPMN PPK</span>
                                <h4 class="text-xs font-bold text-teal-950">Memo Persetujuan Usulan Awal PPK</h4>
                            </div>
                            <p class="text-[11px] text-slate-600">
                                Bersama Wadir II, persetujuan awal PPK wajib menyertakan nomor memo dan file fisik dokumen memo resmi.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                        Nomor Memo Persetujuan PPK
                                    </label>
                                    <input type="text" 
                                           name="nomor_memo" 
                                           value="{{ 'MEMO-PPK/' . date('Y') . '/' . substr(md5($p->nomor_surat . Auth::id()), 0, 6) }}"
                                           placeholder="Contoh: 041/PL3.PPK/PG/{{ date('Y') }}"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                        Upload File Fisik Dokumen Memo <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="file" 
                                           name="file_memo" 
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-100 file:text-teal-800 hover:file:bg-teal-200 cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <!-- Catatan / Alasan Keputusan PPK -->
                        <div class="space-y-1.5">
                            <label for="catatan_alasan_{{ $p->nomor_surat }}" class="block text-xs font-bold text-slate-700">
                                Catatan / Pertimbangan Persetujuan PPK 
                                <span x-show="!isMandatory" class="text-slate-400 font-normal">(Opsional untuk Persetujuan)</span>
                                <span x-show="isMandatory" class="text-rose-600 font-bold">(Wajib Diisi saat Menolak)</span>
                            </label>
                            <textarea id="catatan_alasan_{{ $p->nomor_surat }}" 
                                      name="catatan_alasan" 
                                      rows="3"
                                      :required="isMandatory"
                                      placeholder="Tuliskan catatan teknis pengadaan atau alasan penolakan usulan..."
                                      class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 placeholder-slate-400 focus:border-[#087F79] focus:outline-none focus:ring-2 focus:ring-[#087F79]/20"></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-2">
                            <button type="submit" 
                                    class="inline-flex items-center gap-2 rounded-xl bg-[#087F79] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-[#087F79]/20 hover:bg-[#06635e] transition-all cursor-pointer">
                                <span>Simpan Keputusan Persetujuan Awal PPK</span>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </div>
                    </form>

                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
