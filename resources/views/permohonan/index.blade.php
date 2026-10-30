@extends('layouts.app')

@section('title', 'Tracking & Permohonan Pengadaan - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<div x-data="trackingManager()" class="space-y-6">

    <!-- 1. HEADER HALAMAN & TOMBOL PERMOHONAN BARU -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                Tracking Pengajuan
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detail pelacakan 8 siklus.
            </p>
        </div>

        @if(Auth::user()->hasRole('user') || Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['permohonan.create']))
            <button type="button" 
                    @click="openCreateModal()" 
                    class="inline-flex items-center justify-center gap-2 shrink-0 rounded-xl bg-[#0969da] px-5 py-3 text-sm font-bold text-white shadow-md shadow-[#0969da]/20 hover:bg-blue-700 transition-all cursor-pointer">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Permohonan Baru
            </button>
        @endif
    </div>

    <!-- 2. SECTION FILTER & PENCARIAN -->
    <form method="GET" action="{{ route('permohonan.index') }}" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            
            <!-- Input Cari -->
            <div class="lg:col-span-4 space-y-1">
                <label for="search-input" class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Pencarian</label>
                <input type="search" 
                       id="search-input"
                       name="q" 
                       value="{{ request('q') }}"
                       placeholder="Cari permohonan / no. surat..." 
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
            </div>

            <!-- Filter Status -->
            <div class="lg:col-span-3 space-y-1">
                <label for="status-filter" class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</label>
                <select id="status-filter" name="status" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <option value="">Semua Status</option>
                    <option value="diajukan" {{ request('status') == 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="reviu_paralel" {{ request('status') == 'reviu_paralel' ? 'selected' : '' }}>Reviu Paralel (B2/MAK)</option>
                    <option value="disetujui" {{ request('status') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="perlu_revisi" {{ request('status') == 'perlu_revisi' ? 'selected' : '' }}>Perlu Revisi</option>
                    <option value="ditolak" {{ request('status') == 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            <!-- Filter Unit -->
            <div class="lg:col-span-3 space-y-1">
                <label for="unit-filter" class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Unit / Jurusan</label>
                <select id="unit-filter" name="unit" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <option value="">Semua Unit</option>
                    @foreach($units as $u)
                        <option value="{{ $u->kode_unit }}" {{ request('unit') == $u->kode_unit ? 'selected' : '' }}>
                            {{ $u->nama_unit }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tombol Cari & Reset -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0969da] py-2 px-3 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition-all cursor-pointer">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Cari
                </button>
                <a href="{{ route('permohonan.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white py-2 px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                    Reset
                </a>
            </div>

        </div>
    </form>

    <!-- 3. MAIN CONTENT: SPLIT VIEW (Daftar Pengajuan Kiri & Tracking Panel Kanan) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- KIRI (5 COLS): DAFTAR PENGAJUAN -->
        <div class="lg:col-span-5 rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <h2 class="font-bold text-base text-[#213153]">Daftar Pengajuan</h2>
                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-[#0969da]">
                        {{ $permohonanList->total() ?? $permohonanList->count() }} pengajuan
                    </span>
                </div>
            </div>

            <div class="divide-y divide-slate-100 max-h-[680px] overflow-y-auto">
                @forelse($permohonanList as $item)
                    <div @click="selectPermohonan({{ json_encode($item) }})" 
                         :class="selectedItem && selectedItem.nomor_surat === '{{ $item->nomor_surat }}' ? 'bg-blue-50/70 border-l-4 border-l-[#0969da]' : 'hover:bg-slate-50/80'"
                         class="p-4 cursor-pointer transition-colors space-y-2">
                        
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs font-bold font-mono text-[#0969da]">{{ $item->nomor_surat }}</span>
                            <x-status-badge :status="$item->status_permohonan ?? $item->status" size="sm" />
                        </div>

                        <h3 class="text-sm font-bold text-[#213153] line-clamp-2 leading-snug">
                            {{ $item->perihal ?? $item->judul_pengadaan }}
                        </h3>

                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                            <span>{{ $item->unit->nama_unit ?? $item->kode_unit }}</span>
                            <span class="font-bold text-[#213153] font-mono">
                                Rp {{ number_format($item->anggaran_diajukan ?? $item->estimasi_total_pagu, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400">
                        <svg class="size-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-xs font-medium">Belum ada berkas usulan permohonan pengadaan.</p>
                    </div>
                @endforelse
            </div>

            @if($permohonanList->hasPages())
                <div class="p-3 border-t border-slate-100 bg-slate-50/50">
                    {{ $permohonanList->links() }}
                </div>
            @endif
        </div>

        <!-- KANAN (7 COLS): TRACKING PANEL & AKSI CEPAT -->
        <div class="lg:col-span-7 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs min-h-[500px]">
            
            <!-- Keadaan Kosong (Belum Memilih) -->
            <template x-if="!selectedItem">
                <div class="flex flex-col items-center justify-center h-full min-h-[440px] text-center p-8">
                    <div class="size-16 rounded-2xl bg-blue-50 text-[#0969da] flex items-center justify-center mb-4 shadow-sm shadow-blue-500/10">
                        <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-[#213153]">Pilih Pengajuan untuk Melihat Tracking</h3>
                    <p class="text-xs text-slate-400 max-w-sm mt-1.5 leading-relaxed">
                        Klik salah satu pengajuan di panel sebelah kiri untuk memantau siklus pengadaan, riwayat disposisi, dan dokumen lampiran secara real-time.
                    </p>
                </div>
            </template>

            <!-- Keadaan Berkas Dipilih -->
            <template x-if="selectedItem">
                <div class="space-y-6">

                    <!-- Header Kartu Terpilih & Aksi -->
                    <div class="space-y-3 pb-4 border-b border-slate-100">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-bold font-mono text-[#0969da]" x-text="selectedItem.nomor_surat"></span>
                            <span class="text-xs font-bold uppercase px-2.5 py-0.5 rounded-full"
                                  :class="{
                                      'bg-blue-50 text-[#0969da]': ['diajukan'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase()),
                                      'bg-amber-50 text-amber-700': ['reviu_paralel', 'pending_anggaran', 'hold'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase()),
                                      'bg-emerald-50 text-emerald-700': ['disetujui', 'selesai'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase()),
                                      'bg-rose-50 text-rose-700': ['ditolak', 'revisi', 'perlu_revisi'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())
                                  }"
                                  x-text="(selectedItem.status_permohonan || selectedItem.status || 'Diajukan').replace('_', ' ')"></span>
                        </div>

                        <h3 class="text-lg font-extrabold text-[#213153] leading-snug" x-text="selectedItem.perihal || selectedItem.judul_pengadaan"></h3>
                        <p class="text-xs text-slate-500" x-text="'Unit Pengusul: ' + (selectedItem.unit ? selectedItem.unit.nama_unit : (selectedItem.kode_unit || 'Utama'))"></p>

                        <!-- Action Bar Banner -->
                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.id)" 
                               class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors">
                                <span>Detail Penuh</span>
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>

                            <a :href="'/dokumen/pdf/permohonan/' + (selectedItem.nomor_surat || selectedItem.id)" 
                               target="_blank"
                               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                <svg class="size-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span>Unduh PDF</span>
                            </a>

                            <!-- Tombol Ajukan Revisi jika Ditolak/Revisi -->
                            <template x-if="['ditolak', 'revisi', 'perlu_revisi'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
                                <button type="button" 
                                        @click="openRevisiModal(selectedItem)"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-2 text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Ajukan Revisi</span>
                                </button>
                            </template>

                            <!-- Tombol Hapus/Batalkan jika Draft/Diajukan/Ditolak -->
                            <template x-if="['draft', 'diajukan', 'ditolak'].includes((selectedItem.status_permohonan || selectedItem.status || '').toLowerCase())">
                                <button type="button" 
                                        @click="openHapusModal(selectedItem)"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 px-3.5 py-2 text-xs font-bold transition-colors cursor-pointer">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <span>Batalkan</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- STEPPER 8 SIKLUS PENGADAAN -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Tracking 8 Siklus Pengadaan</h4>
                            <span class="text-xs font-bold text-[#0969da]" x-text="'Tahap ' + getStepNumber(selectedItem.status_permohonan || selectedItem.status) + ' dari 8'"></span>
                        </div>
                        
                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 py-2">
                            <template x-for="step in 8" :key="step">
                                <div class="flex flex-col items-center gap-1.5">
                                    <div :class="getStepColor(step)" 
                                         class="size-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-xs">
                                        <span x-text="step"></span>
                                    </div>
                                    <span class="text-[9px] font-semibold text-center text-slate-500 leading-tight" x-text="getStepTitle(step)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- METADATA & ESTIMASI ANGGARAN RINGKAS -->
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block font-medium">Estimasi Pagu:</span>
                            <span class="font-bold text-[#0969da] text-sm font-mono" x-text="selectedItem.estimasi_total_pagu || selectedItem.anggaran_diajukan ? 'Rp ' + Number(selectedItem.estimasi_total_pagu || selectedItem.anggaran_diajukan).toLocaleString('id-ID') : 'Rp -'"></span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block font-medium">Tanggal Pengajuan:</span>
                            <span class="font-bold text-slate-700" x-text="selectedItem.tanggal_pengajuan ? String(selectedItem.tanggal_pengajuan).substring(0, 10) : '-'"></span>
                        </div>
                    </div>

                    <!-- RIWAYAT & CATATAN TELAAH -->
                    <div class="space-y-3 pt-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Riwayat Berkas &amp; Catatan</h4>
                        
                        <div class="space-y-2.5 max-h-56 overflow-y-auto pr-1">
                            <template x-if="selectedItem.alasan_revisi">
                                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                                    <div class="size-2.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></div>
                                    <div class="text-xs space-y-0.5">
                                        <div class="font-bold text-amber-900">Catatan Revisi / Penolakan</div>
                                        <div class="text-amber-800" x-text="selectedItem.alasan_revisi"></div>
                                    </div>
                                </div>
                            </template>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-3">
                                <div class="size-2.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></div>
                                <div class="text-xs space-y-0.5">
                                    <div class="font-bold text-[#213153]">Usulan Pengadaan Didaftarkan</div>
                                    <div class="text-slate-500">Berkas usulan resmi Srikandi dicatat oleh unit pengusul.</div>
                                    <div class="text-[10px] text-slate-400" x-text="selectedItem.tanggal_pengajuan"></div>
                                </div>
                            </div>
                            
                            <template x-if="selectedItem.tujuan_pengadaan_ringkas">
                                <div class="p-3.5 rounded-xl bg-blue-50/50 border border-blue-100 flex items-start gap-3">
                                    <div class="size-2.5 rounded-full bg-[#0969da] mt-1.5 shrink-0"></div>
                                    <div class="text-xs space-y-0.5">
                                        <div class="font-bold text-[#213153]">Uraian Ringkas Tujuan</div>
                                        <div class="text-slate-600" x-text="selectedItem.tujuan_pengadaan_ringkas"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </template>

        </div>

    </div>

    <!-- 4. MODAL INTERAKTIF 1: BUAT USULAN PERMOHONAN BARU (x-modal) -->
    <x-modal id="modal-create-permohonan" title="Buat Usulan Permohonan Pengadaan" subtitle="Pendaftaran berkas naskah dinas Srikandi baru" maxWidth="3xl">
        <form action="{{ route('permohonan.store') }}" 
              method="POST" 
              enctype="multipart/form-data" 
              x-data="{ nominalModal: '' }"
              class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Nomor Surat Srikandi <span class="text-rose-500">*</span></label>
                    <input type="text" name="nomor_surat" required placeholder="Contoh: B/123/PL3/TIK/2026" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Unit Pengusul <span class="text-rose-500">*</span></label>
                    <select name="kode_unit" required 
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                        @foreach($units as $u)
                            <option value="{{ $u->kode_unit }}" {{ Auth::user()->kode_unit === $u->kode_unit ? 'selected' : '' }}>
                                [{{ $u->kode_unit }}] {{ $u->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Perihal / Judul Kegiatan <span class="text-rose-500">*</span></label>
                    <input type="text" name="perihal" required placeholder="Contoh: Pengadaan Fasilitas Laboratorium Cloud AI" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-700">Estimasi Pagu Anggaran (Rp) <span class="text-rose-500">*</span></label>
                        <span class="text-xs font-bold text-[#0969da] font-mono" x-text="nominalModal ? 'Rp ' + Number(nominalModal).toLocaleString('id-ID') : 'Rp 0'"></span>
                    </div>
                    <input type="number" name="anggaran_diajukan" x-model="nominalModal" step="0.01" min="1" required placeholder="Contoh: 150000000" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Tanggal Usulan <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_pengajuan" value="{{ date('Y-m-d') }}" required 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Uraian Ringkas Tujuan</label>
                    <textarea name="tujuan_pengadaan_ringkas" rows="2" placeholder="Uraian singkat justifikasi pengadaan..." 
                              class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-slate-700">Unggah Berkas Lampiran (KAK / Spesifikasi - PDF Maks. 10 MB)</label>
                    <input type="file" name="file_surat_permohonan" accept=".pdf" 
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-600 focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-create-permohonan')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-[#0969da] px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20 transition-all cursor-pointer">
                    Kirim Usulan Pengadaan
                </button>
            </div>
        </form>
    </x-modal>

    <!-- 5. MODAL INTERAKTIF 2: REVISI USULAN PERMOHONAN (Figma 509:1651 & PANDUAN_INTEGRASI §2.2) -->
    <x-modal id="modal-revisi-permohonan" title="Revisi Usulan Pengadaan" subtitle="Perbaiki usulan berdasarkan catatan telaah verifikator (Figma 509:1651)" maxWidth="2xl">
        <form action="{{ route('permohonan.revisi') }}" 
              method="POST" 
              enctype="multipart/form-data" 
              class="space-y-4">
            @csrf
            
            <input type="hidden" name="nomor_surat_lama" :value="revisiTarget?.nomor_surat">

            <!-- Catatan Alasan Penolakan -->
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-center justify-between text-xs font-bold text-amber-900 mb-1">
                    <span>Catatan Telaah / Alasan Penolakan:</span>
                    <span class="font-mono text-[11px]" x-text="'Revisi ke-' + ((revisiTarget?.revisi_ke || 0) + 1)"></span>
                </div>
                <p class="text-xs text-amber-800 leading-relaxed" 
                   x-text="revisiTarget?.alasan_revisi || revisiTarget?.catatan || 'Diperlukan penyesuaian berkas KAK atau estimasi pagu anggaran usulan.'"></p>
            </div>

            <!-- Nomor Surat Baru -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Nomor Surat Revisi Baru Srikandi <span class="text-rose-500">*</span></label>
                <input type="text" 
                       name="nomor_surat_baru" 
                       :value="(revisiTarget?.nomor_surat ? revisiTarget.nomor_surat + '-REV' : '')"
                       required 
                       placeholder="Contoh: B/123-REV/PL3/TIK/2026"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <!-- Perihal Baru -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Perihal / Judul Kegiatan <span class="text-rose-500">*</span></label>
                <input type="text" 
                       name="perihal" 
                       :value="revisiTarget?.perihal || revisiTarget?.judul_pengadaan || ''"
                       required 
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <!-- Penyesuaian Anggaran -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Penyesuaian Anggaran Diajukan (Rp) <span class="text-rose-500">*</span></label>
                <input type="number" 
                       name="anggaran_diajukan" 
                       :value="revisiTarget?.anggaran_diajukan || revisiTarget?.estimasi_total_pagu || ''"
                       step="0.01" 
                       min="1" 
                       required 
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <!-- Keterangan Revisi -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Uraian Perbaikan Berkas <span class="text-rose-500">*</span></label>
                <textarea name="keterangan_revisi" 
                          rows="3" 
                          required 
                          placeholder="Jelaskan penyesuaian yang telah dilakukan pada KAK atau spesifikasi harga..."
                          class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
            </div>

            <!-- Unggah KAK Baru -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700">Unggah KAK Baru / Dokumen Pendukung (PDF Maks. 10 MB)</label>
                <input type="file" 
                       name="file_kak" 
                       accept=".pdf"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-600 focus:outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-revisi-permohonan')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 text-xs font-bold shadow-md shadow-amber-600/20 transition-all cursor-pointer">
                    Kirim Dokumen Revisi
                </button>
            </div>
        </form>
    </x-modal>

    <!-- 6. MODAL INTERAKTIF 3: KONFIRMASI HAPUS / BATALKAN PENGAJUAN (Figma 498:4950) -->
    <x-modal id="modal-delete-permohonan" title="Konfirmasi Pembatalan Pengajuan" maxWidth="md">
        <form :action="'/permohonan/' + (hapusTarget?.nomor_surat || '')" 
              method="POST" 
              class="space-y-4">
            @csrf
            @method('DELETE')

            <div class="text-center py-2">
                <div class="size-14 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-3 shadow-xs">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <h4 class="text-base font-bold text-slate-800">Batalkan Pengajuan Permohonan?</h4>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    Anda akan membatalkan dan menghapus berkas usulan <strong class="text-slate-800 font-mono" x-text="hapusTarget?.nomor_surat"></strong>. Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" 
                        @click="$dispatch('close-modal', 'modal-delete-permohonan')" 
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    Batal
                </button>
                <button type="submit" 
                        class="rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 text-xs font-bold shadow-md shadow-rose-600/20 transition-all cursor-pointer">
                    Ya, Batalkan Pengajuan
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection