@extends('layouts.app')

@section('title', 'Verifikasi Anggaran MAK - Subbagian Perencanaan PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-8">

    <!-- 1. HEADER HALAMAN & ROLE PILL (Figma 565:17450) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="rounded-lg bg-teal-100 text-teal-800 px-2.5 py-1 text-xs font-bold font-mono">FR 6.1 - FR 6.3</span>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Verifikasi Anggaran MAK &amp; Penetapan Pagu DIPA
                </h1>
            </div>
            <p class="text-xs md:text-sm text-slate-500 mt-1">
                Subbagian Perencanaan bertugas memeriksa ketersediaan pagu dana DIPA, mengalokasikan Mata Anggaran Kegiatan (MAK), mengunci anggaran MAK, dan memecah usulan (Split MAK).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 border border-teal-200/80 px-3.5 py-1 text-xs font-bold text-teal-800">
                <span class="size-2 rounded-full bg-teal-600 animate-pulse"></span>
                Subbagian Perencanaan PNJ
            </span>
        </div>
    </div>

    <!-- 2. STEPPER SIKLUS PENGADAAN (TAHAP 2: VERIFIKASI PARALEL) -->
    <x-stepper-8-siklus :currentStep="2" status="reviu_paralel" />

    <!-- 3. KPI STAT CARDS -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card title="Total Pagu DIPA Aktif" 
                     :value="'Rp ' . number_format($makList->sum('pagu_anggaran'), 0, ',', '.')" 
                     subtitle="Pagu resmi DIPA tahun ini" 
                     color="blue" />

        <x-stat-card title="Sisa Pagu Bebas" 
                     :value="'Rp ' . number_format($makList->sum('sisa_pagu_mak'), 0, ',', '.')" 
                     subtitle="Siap dialokasikan" 
                     color="teal" />

        <x-stat-card title="Antrean Usulan Pending" 
                     :value="$permohonanAntrean->count()" 
                     subtitle="Menunggu verifikasi MAK" 
                     color="amber" />

        <x-stat-card title="Pagu Ditahan (Hold)" 
                     :value="\App\Models\PermohonanPengadaan::where('status_anggaran', 'hold')->count()" 
                     subtitle="Pagu belum mencukupi" 
                     color="rose" />
    </div>

    <!-- 4. SECTION 1: ANTREAN USULAN PERMOHONAN MENUNGGU ALOKASI MAK -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 md:p-7 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-[#213153]">Antrean Alokasi MAK Usulan Pengadaan</h2>
                <p class="text-xs text-slate-500">Tetapkan rekening MAK DIPA atau tahan (hold) usulan jika pagu belum tersedia.</p>
            </div>
            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-[#0969da] self-start sm:self-auto">
                {{ $permohonanAntrean->count() }} antrean
            </span>
        </div>

        @if($permohonanAntrean->isEmpty())
            <div class="p-8 text-center text-slate-400">
                <svg class="size-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs font-medium">Tidak ada usulan permohonan yang menunggu alokasi MAK saat ini.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach($permohonanAntrean as $p)
                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-5 md:p-6 space-y-4">
                        
                        <!-- Header Baris Usulan -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-200/60">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-bold text-[#0969da]">{{ $p->nomor_surat }}</span>
                                    <x-status-badge :status="$p->status_permohonan ?? $p->status" size="sm" />
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    Unit: <strong class="text-slate-800">{{ $p->unit->nama_unit ?? $p->kode_unit }}</strong> &bull;
                                    Diajukan: {{ $p->tanggal_pengajuan ? date('d/m/Y', strtotime($p->tanggal_pengajuan)) : '-' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] text-slate-400 block">Estimasi Anggaran Usulan</span>
                                <span class="text-base font-extrabold text-[#0969da] font-mono">
                                    Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <!-- Perihal -->
                        <div>
                            <h3 class="text-sm font-bold text-[#213153]">{{ $p->perihal ?? $p->judul_pengadaan }}</h3>
                            <p class="text-xs text-slate-600 mt-1">{{ $p->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa institusi PNJ' }}</p>
                        </div>

                        <!-- Form Alokasi MAK (POST /verifikasi/perencanaan/mak) with Live Pagu Calculation -->
                        <form action="{{ route('verifikasi.perencanaan.mak') }}" 
                              method="POST" 
                              x-data="{
                                  selectedMak: '{{ $p->kode_mak ?? '' }}',
                                  paguSisa: 0,
                                  nominal: {{ (float) ($p->anggaran_diajukan ?? $p->estimasi_total_pagu ?? 0) }},
                                  keputusan: 'setuju',
                                  updateMakInfo(event) {
                                      const opt = event.target.selectedOptions[0];
                                      this.paguSisa = opt ? parseFloat(opt.dataset.sisa || 0) : 0;
                                  }
                              }"
                              class="rounded-xl bg-white p-4 md:p-5 border border-slate-200/80 space-y-4">
                            @csrf
                            <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                <!-- Pilih MAK -->
                                <div class="space-y-1 md:col-span-2">
                                    <label for="kode_mak_{{ $p->nomor_surat }}" class="block text-xs font-bold text-slate-700">
                                        Pilih Mata Anggaran Kegiatan (MAK) DIPA <span class="text-rose-500">*</span>
                                    </label>
                                    <select id="kode_mak_{{ $p->nomor_surat }}" 
                                            name="kode_mak" 
                                            required 
                                            x-model="selectedMak"
                                            @change="updateMakInfo($event)"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-mono font-medium text-slate-800 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                                        <option value="">-- Pilih Rekening MAK DIPA yang Tersedia --</option>
                                        @foreach($makList as $mak)
                                            <option value="{{ $mak->kode_mak }}" 
                                                    data-sisa="{{ (float) $mak->sisa_pagu_mak }}"
                                                    {{ $mak->is_locked ? 'disabled' : '' }}
                                                    {{ ($p->kode_mak === $mak->kode_mak) ? 'selected' : '' }}>
                                                [{{ $mak->kode_mak }}] {{ $mak->uraian_mak }} &bull; Sisa Pagu: Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }} ({{ $mak->sumber_dana }}) {{ $mak->is_locked ? '[TERKUNCI]' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Live Pagu Sufficiency Callout Banner -->
                                <template x-if="selectedMak && paguSisa > 0 && nominal > paguSisa">
                                    <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
                                        <svg class="size-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <div>
                                            <strong class="font-bold">Peringatan Ketercukupan Pagu:</strong>
                                            Nominal usulan (Rp <span x-text="Number(nominal).toLocaleString('id-ID')"></span>) melebihi sisa pagu rekening aktif (Rp <span x-text="Number(paguSisa).toLocaleString('id-ID')"></span>). Disarankan memilih opsi <strong>Tahan Anggaran (Hold)</strong> atau gunakan fitur <strong>Split MAK</strong> di bawah.
                                        </div>
                                    </div>
                                </template>

                                <!-- Nominal Alokasi Anggaran -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label for="nominal_alokasi_{{ $p->nomor_surat }}" class="block text-xs font-bold text-slate-700">
                                            Nominal Alokasi Anggaran (Rp) <span class="text-rose-500">*</span>
                                        </label>
                                        <span class="text-xs font-bold text-[#0969da] font-mono" x-text="nominal ? 'Rp ' + Number(nominal).toLocaleString('id-ID') : 'Rp 0'"></span>
                                    </div>
                                    <input type="number" 
                                           id="nominal_alokasi_{{ $p->nomor_surat }}" 
                                           name="nominal_alokasi" 
                                           x-model="nominal"
                                           step="0.01" 
                                           min="1" 
                                           required 
                                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                                </div>

                                <!-- Keputusan Anggaran Toggle -->
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-700">Keputusan Alokasi Anggaran <span class="text-rose-500">*</span></label>
                                    <div class="grid grid-cols-2 gap-2 pt-0.5">
                                        <label class="flex items-center gap-2 p-2 rounded-xl border cursor-pointer text-xs font-bold transition-all"
                                               :class="keputusan === 'setuju' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'">
                                            <input type="radio" name="keputusan_anggaran" value="setuju" x-model="keputusan" class="text-emerald-600 focus:ring-emerald-500">
                                            <span>Setujui Alokasi MAK</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-2 rounded-xl border cursor-pointer text-xs font-bold transition-all"
                                               :class="keputusan === 'tahan' ? 'border-rose-500 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-600'">
                                            <input type="radio" name="keputusan_anggaran" value="tahan" x-model="keputusan" class="text-rose-600 focus:ring-rose-500">
                                            <span>Tahan Anggaran (Hold)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Catatan Perencanaan -->
                                <div class="space-y-1 md:col-span-2">
                                    <label for="catatan_anggaran_{{ $p->nomor_surat }}" class="block text-xs font-bold text-slate-700">
                                        Catatan Alokasi Anggaran Perencanaan (Opsional)
                                    </label>
                                    <textarea id="catatan_anggaran_{{ $p->nomor_surat }}" 
                                              name="catatan_anggaran" 
                                              rows="2" 
                                              placeholder="Contoh: Menggunakan pagu BOPTN 2026, MAK telah dialokasikan penuh dan siap dieksekusi PPBJ."
                                              class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" 
                                        class="inline-flex items-center gap-2 rounded-xl bg-[#0969da] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-[#0969da]/20 hover:bg-blue-700 transition-all cursor-pointer">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Simpan Verifikasi Anggaran MAK</span>
                                </button>
                            </div>
                        </form>

                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- 5. SECTION 2: FORM PEMOTONGAN / PEMECAHAN (SPLIT MAK) BERDASARKAN POS REKENING (FR 6.3) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 md:p-7 shadow-xs space-y-6"
         x-data="splitMakManager({ 
             permohonanList: {{ json_encode($permohonanAntrean) }}, 
             makList: {{ json_encode($makList) }} 
         })">
        
        <div class="pb-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-2">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-lg bg-indigo-100 text-indigo-800 px-2 py-0.5 text-xs font-bold font-mono">FR 6.3</span>
                    <h2 class="text-lg font-bold text-[#213153]">Pemecahan (Split) Usulan Permohonan Berdasarkan Pos MAK</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Pisahkan pembebanan anggaran usulan tunggal ke beberapa pos rekening MAK DIPA yang berbeda dengan nomor pecahan baru.
                </p>
            </div>
            
            <button type="button" 
                    @click="addRow()" 
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-50 transition-colors cursor-pointer self-start md:self-auto">
                <svg class="size-3.5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Baris Pecahan</span>
            </button>
        </div>

        <form action="{{ route('verifikasi.perencanaan.split') }}" method="POST" class="space-y-5">
            @csrf

            <!-- Pilih Surat Induk & Alasan -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Pilih Surat Usulan Induk yang Akan Dipecah <span class="text-rose-500">*</span></label>
                    <select name="nomor_surat_induk" 
                            x-model="selectedIndukNomor" 
                            @change="onSelectInduk()" 
                            required
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="">-- Pilih Surat Permohonan Induk --</option>
                        @foreach($permohonanAntrean as $p)
                            <option value="{{ $p->nomor_surat }}">
                                [{{ $p->nomor_surat }}] {{ $p->perihal ?? $p->judul_pengadaan }} (Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Alasan Pemecahan Pos MAK (FR 6.3) <span class="text-rose-500">*</span></label>
                    <input type="text" 
                           name="alasan_pemecahan" 
                           required 
                           placeholder="Contoh: Pemisahan belanja modal peralatan lab dan belanja bahan habis pakai..."
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>
            </div>

            <!-- LIVE BUDGET GAUGE METER -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                <div>
                    <span class="text-slate-400 block font-medium">Pagu Usulan Induk:</span>
                    <span class="font-extrabold text-[#213153] text-sm font-mono" 
                          x-text="'Rp ' + Number(parentTotalPagu).toLocaleString('id-ID')"></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Total Teralokasi Pecahan:</span>
                    <span class="font-extrabold text-sm font-mono" 
                          :class="isExceeding ? 'text-rose-600' : 'text-[#0969da]'"
                          x-text="'Rp ' + Number(totalAllocated).toLocaleString('id-ID')"></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Sisa Pagu Usulan Induk:</span>
                    <span class="font-extrabold text-sm font-mono" 
                          :class="remainingPagu < 0 ? 'text-rose-600' : 'text-emerald-700'"
                          x-text="'Rp ' + Number(remainingPagu).toLocaleString('id-ID')"></span>
                </div>
            </div>

            <template x-if="isExceeding">
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
                    <svg class="size-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span><strong>Pagu Melebihi Batas:</strong> Total pagu pecahan tidak boleh melebihi pagu usulan induk. Sesuaikan kembali rincian pecahan.</span>
                </div>
            </template>

            <!-- TABEL DINAMIS BARIS PECAHAN (SUB-BERKAS) -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Rincian Berkas Usulan Pecahan (Minimal 2 Baris)</h3>

                <div class="space-y-3">
                    <template x-for="(row, idx) in subBerkas" :key="idx">
                        <div class="p-4 rounded-xl border border-slate-200 bg-white grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            
                            <div class="md:col-span-3 space-y-1">
                                <label class="text-[11px] font-bold text-slate-600">Nomor Surat Pecahan</label>
                                <input type="text" 
                                       :name="'items[' + idx + '][nomor_surat_pecahan]'" 
                                       x-model="row.nomor_surat_pecahan" 
                                       required 
                                       placeholder="Nomor surat pecahan..."
                                       class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none">
                            </div>

                            <div class="md:col-span-4 space-y-1">
                                <label class="text-[11px] font-bold text-slate-600">Judul / Item Kegiatan Pecahan</label>
                                <input type="text" 
                                       :name="'items[' + idx + '][judul_pengadaan]'" 
                                       x-model="row.judul_pengadaan" 
                                       required 
                                       placeholder="Perihal pecahan..."
                                       class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none">
                            </div>

                            <div class="md:col-span-3 space-y-1">
                                <label class="text-[11px] font-bold text-slate-600">Alokasikan ke Kode MAK</label>
                                <select :name="'items[' + idx + '][kode_mak]'" 
                                        x-model="row.kode_mak" 
                                        required 
                                        class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none">
                                    <option value="">-- Pilih MAK --</option>
                                    <template x-for="mak in makList" :key="mak.kode_mak">
                                        <option :value="mak.kode_mak" 
                                                :disabled="mak.is_locked"
                                                x-text="'[' + mak.kode_mak + '] ' + mak.uraian_mak + ' (Sisa: Rp ' + Number(mak.sisa_pagu_mak).toLocaleString('id-ID') + ')'"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="md:col-span-2 space-y-1 flex items-center gap-2">
                                <div class="flex-1 space-y-1">
                                    <label class="text-[11px] font-bold text-slate-600">Estimasi Pagu (Rp)</label>
                                    <input type="number" 
                                           :name="'items[' + idx + '][estimasi_pagu_pecahan]'" 
                                           x-model="row.estimasi_pagu_pecahan" 
                                           min="1" 
                                           step="0.01" 
                                           required 
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none">
                                </div>

                                <button type="button" 
                                        @click="removeRow(idx)" 
                                        :disabled="subBerkas.length <= 2"
                                        title="Hapus baris pecahan"
                                        class="size-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-600 hover:border-rose-200 disabled:opacity-30 disabled:cursor-not-allowed mt-5 transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                        </div>
                    </template>
                </div>
            </div>

            <!-- Submit Split Button -->
            <div class="flex justify-end pt-3 border-t border-slate-100">
                <button type="submit" 
                        :disabled="!isValid"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-7 py-2.5 text-xs font-bold text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>Proses Pemecahan (Split) Berkas Permohonan</span>
                </button>
            </div>
        </form>

    </div>

    <!-- 6. SECTION 3: KATALOG MASTER REKENING MAK DIPA & MODAL TAMBAH MAK (FR 6.1) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 md:p-7 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-[#213153]">Katalog Master Mata Anggaran Kegiatan (MAK) DIPA PNJ</h2>
                <p class="text-xs text-slate-500">Daftar rekening pagu anggaran institusi beserta status keterkuncian akun.</p>
            </div>

            <button type="button" 
                    @click="$dispatch('open-modal', 'modal-tambah-mak')"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#0969da] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition-all cursor-pointer self-start sm:self-auto">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Rekening MAK DIPA</span>
            </button>
        </div>

        <!-- Tabel Modern MAK DIPA -->
        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-4 py-3">Kode MAK</th>
                        <th class="px-4 py-3">Uraian Rekening Kegiatan</th>
                        <th class="px-4 py-3">Sumber Dana</th>
                        <th class="px-4 py-3 text-center">Tahun</th>
                        <th class="px-4 py-3 text-right">Pagu Awal</th>
                        <th class="px-4 py-3 text-right">Sisa Pagu Bebas</th>
                        <th class="px-4 py-3 text-center">Status Akun</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($makList as $mak)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3 font-mono font-bold text-[#0969da]">{{ $mak->kode_mak }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $mak->uraian_mak }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                                    {{ $mak->sumber_dana }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-slate-600">{{ $mak->tahun_anggaran }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">
                                Rp {{ number_format($mak->pagu_anggaran, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700">
                                Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($mak->is_locked)
                                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-bold text-rose-800">Terkunci</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">Terbuka</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- 7. MODAL TAMBAH REKENING MAK DIPA BARU (FR 6.1) -->
    <x-modal id="modal-tambah-mak" title="Tambah Master Rekening MAK DIPA" subtitle="Pendaftaran pos akun anggaran DIPA baru Kampus PNJ" maxWidth="2xl">
        <form action="{{ route('verifikasi.perencanaan.mak.store') }}" method="POST" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Kode Rekening MAK <span class="text-rose-500">*</span></label>
                    <input type="text" 
                           name="kode_mak" 
                           required 
                           placeholder="Contoh: MAK-TIK-2026-004 atau 023.17.WA.521211"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Sumber Pendanaan DIPA <span class="text-rose-500">*</span></label>
                    <select name="sumber_dana" 
                            required 
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="BOPTN">BOPTN (Bantuan Operasional PTN)</option>
                        <option value="PNBP">PNBP (Pendapatan Negara Bukan Pajak)</option>
                        <option value="Rupiah Murni">Rupiah Murni (APBN RM)</option>
                        <option value="Hibah / Kerjasama">Hibah / Kerjasama</option>
                    </select>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Uraian / Nama Kegiatan MAK <span class="text-rose-500">*</span></label>
                    <input type="text" 
                           name="uraian_mak" 
                           required 
                           placeholder="Contoh: Belanja Bahan Penunjang Laboratorium Komputer dan Cloud AI"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Tahun Anggaran <span class="text-rose-500">*</span></label>
                    <input type="number" 
                           name="tahun_anggaran" 
                           value="{{ date('Y') }}" 
                           min="2020" 
                           max="2035" 
                           required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Pagu Awal Anggaran (Rp) <span class="text-rose-500">*</span></label>
                    <input type="number" 
                           name="pagu_anggaran" 
                           min="1" 
                           step="0.01" 
                           required 
                           placeholder="Contoh: 150000000"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Catatan Rekening (Opsional)</label>
                    <textarea name="catatan_mak" 
                              rows="2" 
                              placeholder="Keterangan alokasi DIPA atau pos penunjang..."
                              class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-tambah-mak')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-[#0969da] px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20 transition-all cursor-pointer">
                    Simpan &amp; Terbitkan Rekening MAK
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection
