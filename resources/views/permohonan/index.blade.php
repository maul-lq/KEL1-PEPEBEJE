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
                Pantau status pengajuan pengadaan Anda secara real-time. Pilih salah satu pengajuan untuk melihat detailnya.
            </p>
        </div>

        @if(Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create']))
            <button type="button" 
                    @click="modalCreateOpen = true" 
                    class="inline-flex items-center justify-center gap-2 shrink-0 rounded-xl bg-[#0969da] px-5 py-3 text-sm font-bold text-white shadow-md shadow-[#0969da]/20 hover:bg-blue-700 transition-all">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Permohonan Baru
            </button>
        @endif
    </div>

    <!-- 2. SECTION FILTER & PENCARIAN (Sesuai Layout Figma) -->
    <form method="GET" action="{{ route('permohonan.index') }}" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
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
                    <option value="diproses" {{ request('status') == 'diproses' ? 'selected' : '' }}>Diproses</option>
                    <option value="disetujui" {{ request('status') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="ditolak" {{ request('status') == 'ditolak' ? 'selected' : '' }}>Ditolak / Ditahan</option>
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
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0969da] py-2 px-3 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition-all">
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
        <div class="lg:col-span-5 rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
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
                         :class="selectedItem?.id === {{ $item->id }} || selectedItem?.nomor_surat === '{{ $item->nomor_surat }}' ? 'bg-blue-50/60 border-l-4 border-l-[#0969da]' : 'hover:bg-slate-50/80'"
                         class="p-4 cursor-pointer transition-all flex items-start justify-between gap-3">
                        
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-[#0969da] font-mono">{{ $item->nomor_surat }}</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-[11px] font-medium text-slate-400">
                                    {{ $item->tanggal_pengajuan ? \Carbon\Carbon::parse($item->tanggal_pengajuan)->format('d M Y') : '-' }}
                                </span>
                            </div>
                            <h3 class="text-xs font-bold text-[#213153] leading-snug line-clamp-2">
                                {{ $item->perihal ?? $item->judul_pengadaan }}
                            </h3>
                            <div class="text-[11px] text-slate-500 truncate">
                                {{ $item->unit->nama_unit ?? $item->kode_unit }}
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-1 shrink-0">
                            @php
                                $st = strtolower($item->status_permohonan ?? $item->status ?? 'diajukan');
                            @endphp
                            @if(in_array($st, ['disetujui', 'selesai']))
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                    {{ ucfirst($st) }}
                                </span>
                            @elseif(in_array($st, ['ditolak', 'ditahan', 'revisi']))
                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                                    {{ ucfirst($st) }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                    {{ ucfirst(str_replace('_', ' ', $st)) }}
                                </span>
                            @endif
                            
                            <svg class="size-4 text-slate-300 mt-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>

                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400">
                        <svg class="size-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-xs font-medium">Belum ada permohonan pengadaan ditemukan.</p>
                    </div>
                @endforelse
            </div>

            @if(method_exists($permohonanList, 'links'))
                <div class="p-3 border-t border-slate-100 bg-slate-50/30">
                    {{ $permohonanList->links() }}
                </div>
            @endif
        </div>

        <!-- KANAN (7 COLS): TRACKING & DETAIL PANEL (FRAME 1, 2, 4 FIGMA) -->
        <div class="lg:col-span-7 rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 min-h-[500px]">
            
            <!-- FRAME 1: STATE KOSONG (Belum Ada Item Diklik) -->
            <template x-if="!selectedItem">
                <div class="flex flex-col items-center justify-center min-h-[440px] text-center p-6">
                    <div class="size-20 rounded-full bg-blue-50 flex items-center justify-center text-[#0969da] mb-4">
                        <svg class="size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-[#213153]">Pilih Pengajuan untuk Melihat Detail</h3>
                    <p class="text-xs text-slate-400 max-w-sm mt-1">
                        Klik salah satu item daftar pengajuan di sebelah kiri untuk melihat alur progres 8 siklus pengadaan secara real-time.
                    </p>
                </div>
            </template>

            <!-- FRAME 2 & 4: STATE TRACKING (Item Diklik) -->
            <template x-if="selectedItem">
                <div class="space-y-6">
                    
                    <!-- Header Info Pengajuan Diklik -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <span class="text-xs font-bold font-mono text-[#0969da]" x-text="selectedItem.nomor_surat"></span>
                            <h3 class="text-lg font-extrabold text-[#213153] leading-snug" x-text="selectedItem.perihal || selectedItem.judul_pengadaan"></h3>
                            <p class="text-xs text-slate-500 mt-0.5" x-text="'Unit: ' + (selectedItem.unit ? selectedItem.unit.nama_unit : (selectedItem.kode_unit || 'Utama'))"></p>
                        </div>
                        
                        <a :href="'/permohonan/' + (selectedItem.nomor_surat || selectedItem.id)" 
                           class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-[#213153] hover:bg-slate-200 transition-colors shrink-0">
                            <span>Detail Penuh</span>
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>

                    <!-- STEPPER 8 SIKLUS PENGADAAN (Figma Timeline Tracker) -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Tracking 8 Siklus Pengadaan</h4>
                        
                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 py-2">
                            <template x-for="step in 8" :key="step">
                                <div class="flex flex-col items-center gap-1.5">
                                    <div :class="getStepColor(step)" 
                                         class="size-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-sm">
                                        <span x-text="step"></span>
                                    </div>
                                    <span class="text-[9px] font-semibold text-center text-slate-500 leading-tight" x-text="getStepTitle(step)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- METADATA & ESTMASI ANGGARAN RINGKAS -->
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block font-medium">Estimasi Pagu:</span>
                            <span class="font-bold text-[#0969da] text-sm" x-text="selectedItem.estimasi_total_pagu || selectedItem.anggaran_diajukan ? 'Rp ' + Number(selectedItem.estimasi_total_pagu || selectedItem.anggaran_diajukan).toLocaleString('id-ID') : 'Rp -'"></span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block font-medium">Status Pengajuan:</span>
                            <span class="font-bold text-slate-700 uppercase" x-text="selectedItem.status_permohonan || selectedItem.status || 'Diajukan'"></span>
                        </div>
                    </div>

                    <!-- RIWAYAT AKTIVITAS & CATATAN -->
                    <div class="space-y-3 pt-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Riwayat Berkas & Catatan</h4>
                        
                        <div class="space-y-2.5 max-h-52 overflow-y-auto pr-1">
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

    <!-- 4. MODAL POPUP: PERMOHONAN BARU (FORM INTEGRATED) -->
    <div x-show="modalCreateOpen" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        
        <div @click.away="modalCreateOpen = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto space-y-5">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-extrabold text-[#213153]">Buat Permohonan Pengadaan Baru</h3>
                <button @click="modalCreateOpen = false" type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('permohonan.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">No. Surat Srikandi <span class="text-rose-500">*</span></label>
                        <input type="text" name="nomor_surat" required placeholder="Contoh: B/123/PL3/TIK/2026" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                    </div>
                    
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Asal / Unit Pengusul <span class="text-rose-500">*</span></label>
                        <select name="kode_unit" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                            @foreach($units as $u)
                                <option value="{{ $u->kode_unit }}" {{ Auth::user()->kode_unit == $u->kode_unit ? 'selected' : '' }}>
                                    [{{ $u->kode_unit }}] {{ $u->nama_unit }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-slate-700">Judul / Perihal Permohonan <span class="text-rose-500">*</span></label>
                    <input type="text" name="perihal" required placeholder="Contoh: Pengadaan Perangkat Lab AI dan Server" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-slate-700">Tujuan Pengadaan Ringkas</label>
                    <textarea name="tujuan_pengadaan_ringkas" rows="2" placeholder="Uraikan tujuan pengadaan..." class="w-full rounded-xl border border-slate-200 p-3 text-xs focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Estimasi Anggaran (Rp) <span class="text-rose-500">*</span></label>
                        <input type="number" name="anggaran_diajukan" required min="1" placeholder="Contoh: 150000000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                        <input type="hidden" name="tanggal_pengajuan" value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Dokumen KAK / Spesifikasi (PDF)</label>
                        <input type="file" name="file_surat_permohonan" accept="application/pdf" class="w-full rounded-xl border border-slate-200 px-2 py-1.5 text-xs text-slate-600">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button @click="modalCreateOpen = false" type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit" class="rounded-xl bg-[#0969da] px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-md shadow-[#0969da]/20">
                        Kirim Usulan
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>

<!-- SCRIPT ALPINE.JS MANAGEMENT -->
<script>
    function trackingManager() {
        return {
            selectedItem: null,
            modalCreateOpen: false,

            selectPermohonan(item) {
                this.selectedItem = item;
            },

            getStepTitle(step) {
                const titles = [
                    'Permohonan',
                    'Penelaahan',
                    'Verifikasi MAK',
                    'Pembuatan Paket',
                    'Penetapan HPS',
                    'Pembuatan SPK',
                    'Penerimaan',
                    'Pembayaran'
                ];
                return titles[step - 1] || 'Siklus';
            },

            getStepColor(step) {
                if (!this.selectedItem) return 'bg-slate-100 text-slate-400';
                
                const st = (this.selectedItem.status_permohonan || this.selectedItem.status || '').toLowerCase();
                if (st === 'disetujui' || st === 'selesai') {
                    return 'bg-emerald-500 text-white shadow-emerald-500/20';
                } else if (step <= 2) {
                    return 'bg-[#0969da] text-white shadow-blue-500/20';
                } else {
                    return 'bg-slate-100 text-slate-400';
                }
            }
        };
    }
</script>
@endsection