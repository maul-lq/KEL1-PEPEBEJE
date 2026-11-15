@extends('layouts.app')

@section('title', 'Pembayaran & Pencairan Dana - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6">

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="8" status="spp_terbit" />

    {{-- 2. Header --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Tahap 8: Pembayaran &amp; Pencairan Dana
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Verifikasi Keuangan</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Pembayaran Tagihan &amp; Pencairan Dana</h1>
            </div>

            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-[#213153] border border-slate-200 text-xs font-bold uppercase">
                    Peran: {{ Auth::user()->roleEntity?->label ?? 'Pengguna' }}
                </span>
            </div>
        </div>
    </div>

    {{-- 3. Top 4-Card KPI Stat Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Tagihan Diajukan"
            :value="$pembayaranList->total()"
            icon="document"
            color="navy"
            subtitle="Pengajuan berkas pembayaran"
        />

        <x-stat-card
            title="Memo Menunggu Reviu"
            :value="$memoMenungguReview->count()"
            icon="bell"
            color="amber"
            badge="Tindak Lanjut"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Menunggu persetujuan PPK / Wadir 2"
        />

        <x-stat-card
            title="Perintah Bayar Siap Cair"
            :value="$siapCairKeuangan->count()"
            icon="check"
            color="blue"
            badge="Siap Cair"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Menunggu pencairan"
        />

        <x-stat-card
            title="Total Dana Dicairkan"
            :value="'Rp ' . number_format($transaksiList->sum('nominal_transfer'), 0, ',', '.')"
            icon="wallet"
            color="emerald"
            subtitle="Dana sudah cair"
        />
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-xs text-rose-800 space-y-1">
            <p class="font-bold">Terdapat kesalahan pada pengisian formulir pembayaran:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 4. PANEL PENGAJUAN BERKAS PEMBAYARAN OLEH PPBJ --}}
    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('pembayaran.pengajuan'))
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>1. Pengajuan Berkas Tagihan Pembayaran &amp; Memo Internal</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(Unit Pengadaan)</span>
            </h2>
        </div>

        @if($bastSiapBayar->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Belum ada paket barang diterima yang siap diajukan pembayaran saat ini.
            </div>
        @else
            @php
                $bastJsonData = $bastSiapBayar->mapWithKeys(function($b) {
                    $pkt = $b->paket;
                    $vendorName = $pkt?->vendor?->nama_vendor ?? ($pkt?->kontrakSpk?->vendor?->nama_vendor ?? '-');
                    return [$b->nomor_paket => [
                        'nomor_paket' => $b->nomor_paket,
                        'nama_paket' => $pkt?->nama_paket ?? '-',
                        'nomor_bast' => $b->nomor_bast,
                        'tanggal_penerimaan' => $b->tanggal_penerimaan ? \Carbon\Carbon::parse($b->tanggal_penerimaan)->format('d/m/Y') : '-',
                        'file_bast_signed' => $b->file_bast_signed ? asset('storage/' . $b->file_bast_signed) : null,
                        'foto_dokumentasi' => $b->fotoDokumentasi->map(fn($f) => asset('storage/' . ($f->foto_dokumentasi ?? $f->file_foto)))->all(),
                        'nominal' => (float)($pkt?->kontrakSpk?->nilai_kontrak ?: ($pkt?->nominal_harga_akhir ?: ($pkt?->nilai_hps ?: 0))),
                        'nomor_kwitansi' => $pkt?->nomor_kwitansi_belanja ?: '',
                        'file_kwitansi' => $pkt?->file_kwitansi_belanja ? asset('storage/' . $pkt->file_kwitansi_belanja) : null,
                        'vendor_nama' => $vendorName,
                    ]];
                })->all();

                $pembayaranPengajuanConfig = [
                    'selectedPaket' => old('nomor_paket', request('nomor_paket', '')),
                    'bastMap' => (object) $bastJsonData,
                    'nominal' => old('nominal_pengajuan', ''),
                    'nomorKwitansi' => old('nomor_kwitansi', ''),
                ];
            @endphp

            <div x-data="pembayaranPengajuan" data-config="{{ json_encode($pembayaranPengajuanConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
                <form action="{{ route('pembayaran.pengajuan') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="nomor_paket_fe" class="block text-xs font-bold text-slate-700 mb-1">Pilih Paket Pengadaan (Barang Diterima) <span class="text-rose-500">*</span></label>
                            <select id="nomor_paket_fe" name="nomor_paket" x-model="selectedPaket" @change="onPaketChange()" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                                <option value="">-- Pilih Paket Hasil Penerimaan Barang --</option>
                                @foreach($bastSiapBayar as $b)
                                    <option value="{{ $b->nomor_paket }}">
                                        [{{ $b->nomor_bast }}] {{ $b->paket?->nama_paket ?? $b->nomor_paket }} &bull; Rp {{ number_format((float)($b->paket?->kontrakSpk?->nilai_kontrak ?: ($b->paket?->nominal_harga_akhir ?: ($b->paket?->nilai_hps ?: 0))), 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="nomor_pembayaran_fe" class="block text-xs font-bold text-slate-700 mb-1">Nomor Perintah Bayar <span class="text-rose-500">*</span></label>
                            <input type="text" id="nomor_pembayaran_fe" name="nomor_pembayaran" value="{{ old('nomor_pembayaran', 'SPP/PNJ/' . date('Y') . '/' . rand(100, 999)) }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>
                    </div>

                    {{-- Pratinjau Dokumen BAST & Dokumentasi Barang dari Perlengkapan (BPMN N51 & N52) --}}
                    <div x-show="selectedPaket && bastMap[selectedPaket]" x-cloak class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 space-y-2.5">
                        <div class="flex items-center justify-between text-xs font-bold text-emerald-950">
                            <span class="flex items-center gap-1.5">
                                <svg class="size-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Penerimaan Barang Terverifikasi</span>
                            </span>
                            <template x-if="hasExistingBast">
                                <a :href="existingBastUrl" target="_blank" class="text-[#0969da] hover:underline font-bold inline-flex items-center gap-1">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Lihat Bukti Serah Terima &rarr;</span>
                                </a>
                            </template>
                        </div>
                        <div class="text-[11px] text-emerald-900 grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div>No. Serah Terima: <span class="font-mono font-bold" x-text="bastMap[selectedPaket]?.nomor_bast"></span></div>
                            <div>Tanggal Terima: <span class="font-medium" x-text="bastMap[selectedPaket]?.tanggal_penerimaan"></span></div>
                            <div>Penyedia: <span class="font-bold" x-text="bastMap[selectedPaket]?.vendor_nama"></span></div>
                        </div>
                        <template x-if="fotoList.length > 0">
                            <div class="pt-2 border-t border-emerald-100">
                                <span class="text-[10px] font-bold text-emerald-900 block mb-1">Dokumentasi Foto Fisik Barang (Unit Perlengkapan):</span>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <template x-for="(foto, idx) in fotoList" :key="idx">
                                        <a :href="foto" target="_blank" class="size-10 rounded-lg border border-emerald-200 overflow-hidden hover:opacity-80 transition inline-block shadow-xs">
                                            <img :src="foto" alt="Dokumentasi Barang" class="size-full object-cover">
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="hasExistingKwitansi">
                            <div class="pt-2 border-t border-emerald-100 text-[11px] text-emerald-900 flex items-center justify-between">
                                <span>Kwitansi Belanja Langsung Tersimpan: <strong class="font-mono" x-text="bastMap[selectedPaket]?.nomor_kwitansi"></strong></span>
                                <a :href="existingKwitansiUrl" target="_blank" class="text-[#0969da] hover:underline font-bold inline-flex items-center gap-1">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Lihat File Kwitansi Belanja</span>
                                </a>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="tahap_termin_fe" class="block text-xs font-bold text-slate-700 mb-1">Tahap Pembayaran / Termin <span class="text-rose-500">*</span></label>
                            <input type="number" id="tahap_termin_fe" name="tahap_termin" value="1" min="1" max="12" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>

                        <div>
                            <label for="nominal_pengajuan_fe" class="block text-xs font-bold text-slate-700 mb-1">Nominal Tagihan (Rp) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                                <input type="number" id="nominal_pengajuan_fe" name="nominal_pengajuan" x-model="nominal" step="0.01" min="1" required placeholder="Contoh: 150000000" class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                            </div>
                        </div>

                        <div>
                            <label for="jatuh_tempo_fe" class="block text-xs font-bold text-slate-700 mb-1">Batas Jatuh Tempo Termin <span class="text-rose-500">*</span></label>
                            <input type="date" id="jatuh_tempo_fe" name="jatuh_tempo" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>
                    </div>

                    {{-- Kelengkapan Berkas Tagihan Resmi (Kwitansi & Invoice) --}}
                    <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4 space-y-4">
                        <div>
                            <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Informasi Dokumen Pembayaran (Kwitansi &amp; Invoice Tagihan Rekanan)
                            </h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="nomor_kwitansi" class="block text-xs font-bold text-slate-700 mb-1">Nomor Kwitansi Pembayaran <span class="text-rose-500">*</span></label>
                                <input type="text" id="nomor_kwitansi" name="nomor_kwitansi" x-model="nomorKwitansi" required placeholder="Contoh: KWT/2026/001" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20" />
                            </div>
                            <div>
                                <label for="file_kwitansi" class="block text-xs font-bold text-slate-700 mb-1">
                                    Berkas Kwitansi (PDF/Gambar) <span x-show="!hasExistingKwitansi" class="text-rose-500">*</span>
                                    <span x-show="hasExistingKwitansi" class="text-emerald-600 font-normal text-[11px] ml-1">
                                        (Tersimpan dari belanja langsung)
                                        <a x-show="existingKwitansiUrl" :href="existingKwitansiUrl" target="_blank" class="text-[#0969da] hover:underline ml-0.5">Lihat</a>
                                    </span>
                                </label>
                                <input type="file" id="file_kwitansi" name="file_kwitansi" accept=".pdf,image/*" :required="!hasExistingKwitansi" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]" />
                            </div>
                            <div>
                                <label for="nomor_invoice" class="block text-xs font-bold text-slate-700 mb-1">Nomor Invoice Tagihan Rekanan <span class="text-rose-500">*</span></label>
                                <input type="text" id="nomor_invoice" name="nomor_invoice" value="{{ old('nomor_invoice') }}" required placeholder="Contoh: INV/REKANAN/2026/001" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20" />
                            </div>
                            <div>
                                <label for="file_invoice" class="block text-xs font-bold text-slate-700 mb-1">Unggah Berkas Invoice Tagihan (PDF/Gambar) <span class="text-rose-500">*</span></label>
                                <input type="file" id="file_invoice" name="file_invoice" accept=".pdf,image/*" required class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Dokumen Penunjang Pembayaran (Opsional — PDF/Gambar)</label>
                        <input type="file" name="file_dokumen_penunjang" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                        <p class="text-[11px] text-slate-500 mt-1">Opsional (faktur pajak atau lampiran pendukung).</p>
                    </div>

                    <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5">
                            <span>Kirim Dokumen Pembayaran ke Wadir II &amp; PPK &rarr;</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
    @endif

    {{-- 4.5. PANEL PERBAIKAN BERKAS PEMBAYARAN DITOLAK (BPMN N52: Titik Kembali PPBJ jika ditolak Wadir II N60 / PPK N61) --}}
    @if(isset($pembayaranDitolakList) && $pembayaranDitolakList->isNotEmpty() && (Auth::user()->hasRole('admin') || Auth::user()->hasPermission('pembayaran.pengajuan')))
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-rose-200">
        <div class="border-b border-rose-100 pb-4 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white">
                        Perlu Perbaikan
                    </span>
                    <h2 class="text-base font-bold text-rose-950">Berkas Pembayaran Ditolak (Perlu Perbaikan)</h2>
                </div>
                <p class="text-xs text-rose-700/80 mt-0.5">
                    Berkas dikembalikan oleh Wadir II atau PPK. Unit Pengadaan harap perbaiki dokumen dan ajukan ulang memo pembayaran.
                </p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200 self-start sm:self-auto">
                {{ $pembayaranDitolakList->count() }} Berkas Ditolak
            </span>
        </div>

        <div class="space-y-4">
            @foreach($pembayaranDitolakList as $pDitolak)
                @php
                    $mDitolak = $pDitolak->memoBayar->where('status_memo', 'ditolak')->last();
                @endphp
                <div x-data="pembayaranRejectRow" class="p-4 rounded-xl border border-rose-200 bg-rose-50/40 space-y-3">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-xs text-rose-900">{{ $pDitolak->nomor_pembayaran }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-200 text-rose-800">
                                    Termin {{ $pDitolak->tahap_termin }}
                                </span>
                                <span class="font-mono text-xs text-slate-500">Paket: {{ $pDitolak->nomor_paket }}</span>
                            </div>
                            <div class="text-xs text-slate-700 mt-1">
                                Nominal: <strong>Rp {{ number_format($pDitolak->nominal_pengajuan, 0, ',', '.') }}</strong> &bull; Rekanan: <strong>{{ $pDitolak->paket?->vendor?->nama_vendor ?? '-' }}</strong>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openEdit = !openEdit"
                            class="px-3.5 py-1.5 rounded-xl bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 shadow-sm transition inline-flex items-center gap-1.5 self-start md:self-auto"
                        >
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span x-text="openEdit ? 'Tutup Form' : 'Perbaiki &amp; Ajukan Ulang'"></span>
                        </button>
                    </div>

                    {{-- Alasan Penolakan dari Wadir II atau PPK --}}
                    <div class="p-3 rounded-lg bg-white/90 border border-rose-200 text-xs">
                        <span class="font-bold text-rose-900 block mb-0.5">
                            Alasan Penolakan ({{ $mDitolak ? strtoupper($mDitolak->role_approver) : 'Pemeriksa' }}):
                        </span>
                        <p class="text-rose-800 whitespace-pre-line font-medium">
                            {{ $mDitolak?->catatan_memo ?? ($pDitolak->catatan_reviu_akhir ?? 'Dokumen belum lengkap / terdapat ketidaksesuaian.') }}
                        </p>
                    </div>

                    {{-- Form Perbaikan & Pengajuan Ulang --}}
                    <div x-show="openEdit" x-cloak class="pt-3 border-t border-rose-200 mt-3">
                        <form action="{{ route('pembayaran.update', $pDitolak->nomor_pembayaran) }}" method="POST" enctype="multipart/form-data" class="space-y-4 bg-white p-4 rounded-xl border border-rose-100">
                            @csrf
                            @method('PUT')

                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Form Pembaruan Dokumen Tagihan &amp; Bukti Pembayaran
                            </h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Kwitansi Baru / Revisi</label>
                                    <input type="text" name="nomor_kwitansi" value="{{ $pDitolak->nomor_kwitansi }}" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Unggah Berkas Kwitansi Revisi</label>
                                    <input type="file" name="file_kwitansi" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Invoice Baru / Revisi</label>
                                    <input type="text" name="nomor_invoice" value="{{ $pDitolak->nomor_invoice }}" class="w-full h-10 rounded-xl border border-slate-200 px-3 text-xs font-mono text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Unggah Berkas Invoice Revisi</label>
                                    <input type="file" name="file_invoice" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Unggah Dokumen Kontrak Revisi</label>
                                    <input type="file" name="file_dokumen_perikatan" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Perbaikan Unit Pengadaan</label>
                                <textarea name="catatan_perbaikan_ppbj" rows="2" placeholder="Jelaskan bukti atau berkas yang telah diperbaiki..." class="w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                <button type="button" @click="openEdit = false" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                                    Batal
                                </button>
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                                    Ajukan Ulang ke Wadir II &amp; PPK &rarr;
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 5. PANEL PERSETUJUAN MEMO PEMBAYARAN BERTINGKAT (PPK & WADIR II) --}}
    @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['pembayaran.memo_ppk', 'pembayaran.memo_wadir2']))
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>2. Persetujuan Memo Pembayaran</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(Wadir II &amp; PPK)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Tahap 1 oleh Wadir II; Tahap 2 oleh PPK.</p>
        </div>

        @if($memoMenungguReview->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Belum ada memo pembayaran menunggu persetujuan.
            </div>
        @else
            <div class="space-y-4">
                @foreach($memoMenungguReview as $memo)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        @php
                            $memoPay = $memo->pembayaran;
                            $memoPkt = $memoPay?->paket;
                            $perikatanFile = $memoPkt?->kontrakSpk?->file_spk ?: $memoPay?->file_dokumen_perikatan;
                            $bastFile = $memoPkt?->penerimaanBarang?->sortByDesc('id_penerimaan')?->first()?->file_bast_signed;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-xs text-[#0969da]">{{ $memo->nomor_memo ?: 'Belum Ada No. Memo' }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $memo->role_approver === 'wadir_2' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $memo->role_approver === 'wadir_2' ? 'WADIR II' : 'PPK' }}
                                </span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-800">Paket: {{ $memoPay?->nomor_paket ?? '-' }}</h4>
                            <p class="text-[11px] text-slate-500">
                                Nominal: <strong>Rp {{ number_format($memoPay?->nominal_pengajuan ?? 0, 0, ',', '.') }}</strong> &bull; No. Bayar: <span class="font-mono">{{ $memo->nomor_pembayaran }}</span>
                                @if(!empty($memoPkt?->vendor?->nama_vendor))
                                    &bull; Rekanan: <span class="font-medium text-slate-700">{{ $memoPkt->vendor->nama_vendor }}</span>
                                @endif
                            </p>
                            @if(!empty($memo->file_memo) || !empty($perikatanFile) || !empty($bastFile) || !empty($memoPay?->file_kwitansi) || !empty($memoPay?->file_invoice) || !empty($memoPay?->file_dokumen_penunjang))
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-1 text-[11px]">
                                    @if(!empty($memo->file_memo))
                                        <a href="{{ asset('storage/' . $memo->file_memo) }}" target="_blank" class="inline-flex items-center gap-1 font-bold text-[#0969da] hover:underline">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>Memo Resmi</span>
                                        </a>
                                    @endif
                                    @if(!empty($perikatanFile))
                                        <a href="{{ asset('storage/' . $perikatanFile) }}" target="_blank" class="font-semibold text-[#0969da] hover:underline">Dokumen Kontrak</a>
                                    @endif
                                    @if(!empty($bastFile))
                                        <a href="{{ asset('storage/' . $bastFile) }}" target="_blank" class="font-semibold text-[#0969da] hover:underline">Serah Terima</a>
                                    @endif
                                    @if(!empty($memoPay?->file_kwitansi))
                                        <a href="{{ asset('storage/' . $memoPay->file_kwitansi) }}" target="_blank" class="font-semibold text-[#0969da] hover:underline">Kwitansi{{ $memoPay->nomor_kwitansi ? ' (' . $memoPay->nomor_kwitansi . ')' : '' }}</a>
                                    @endif
                                    @if(!empty($memoPay?->file_invoice))
                                        <a href="{{ asset('storage/' . $memoPay->file_invoice) }}" target="_blank" class="font-semibold text-[#0969da] hover:underline">Invoice{{ $memoPay->nomor_invoice ? ' (' . $memoPay->nomor_invoice . ')' : '' }}</a>
                                    @endif
                                    @if(!empty($memoPay?->file_dokumen_penunjang))
                                        <a href="{{ asset('storage/' . $memoPay->file_dokumen_penunjang) }}" target="_blank" class="font-semibold text-[#0969da] hover:underline">Penunjang</a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <form action="{{ route('pembayaran.memo.approve') }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row flex-wrap items-end sm:items-center gap-2 shrink-0">
                            @csrf
                            <input type="hidden" name="id_memo" value="{{ $memo->id }}">
                            <input type="hidden" name="id_memo_bayar" value="{{ $memo->id }}">
                            
                            <div class="flex flex-col gap-1">
                                <input type="text" 
                                       name="nomor_memo" 
                                       value="{{ $memo->nomor_memo ?? ('MEMO-BAYAR/' . date('Y') . '/' . rand(100, 999)) }}" 
                                       placeholder="Nomor Memo..." 
                                       class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 w-44 font-mono">
                            </div>

                            <div class="flex flex-col gap-1">
                                <input type="file" 
                                       name="file_memo" 
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                       class="h-9 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-600 w-48 file:mr-1 file:py-0.5 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-blue-100 file:text-[#0969da] cursor-pointer">
                            </div>

                            <input type="text" name="catatan" placeholder="Catatan persetujuan..." class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 w-44">

                            <div class="flex items-center gap-1.5">
                                <button type="submit" name="status_memo" value="disetujui" class="h-9 px-3.5 rounded-lg bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700 transition cursor-pointer">
                                    Setujui
                                </button>

                                <button type="submit" name="status_memo" value="ditolak" class="h-9 px-3 rounded-lg bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 transition cursor-pointer">
                                    Tolak
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- 6. PANEL PENERBITAN PERINTAH BAYAR --}}
    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('pembayaran.pengajuan'))
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>3. Penerbitan Perintah Bayar</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(Unit Pengadaan)</span>
            </h2>
        </div>

        @if($siapTerbitkanSpp->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Belum ada berkas yang disetujui untuk penerbitan perintah bayar.
            </div>
        @else
            <div class="space-y-6">
                @foreach($siapTerbitkanSpp as $s)
                    @php
                        $memoWadir = $s->memoBayar->firstWhere('role_approver', 'wadir_2');
                        $memoPpk = $s->memoBayar->firstWhere('role_approver', 'ppk');
                        $sVendor = $s->paket->kontrakSpk->vendor ?? ($s->paket->kontrak->vendor ?? ($s->paket->vendor ?? null));
                        $bastS = $s->paket->penerimaanBarang->first();
                    @endphp
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50/20 p-5 shadow-xs space-y-5">
                        {{-- Ringkasan Paket & Verifikasi Memo --}}
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-indigo-100 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 font-mono">
                                        {{ $s->nomor_pembayaran }}
                                    </span>
                                    <span class="text-xs font-mono text-slate-600">{{ $s->nomor_paket }}</span>
                                </div>
                                <h3 class="text-sm font-bold text-[#213153] mt-1">{{ $s->paket->nama_paket ?? '-' }}</h3>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 mt-1">
                                    <span>Penyedia: <strong class="text-slate-700">{{ $sVendor->nama_vendor ?? 'Penyedia Rekanan' }}</strong></span>
                                    @if($bastS)
                                        <span>Serah Terima: <strong class="text-emerald-700 font-mono">{{ $bastS->nomor_bast }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-left md:text-right">
                                <span class="text-[11px] text-slate-400 block">Nominal Tagihan</span>
                                <span class="text-lg font-mono font-bold text-emerald-600">Rp {{ number_format($s->nominal_pengajuan, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        {{-- Tinjauan 2 Berkas Memo Fisik yang Telah Disetujui --}}
                        <div class="bg-white rounded-xl p-4 border border-slate-200/80 space-y-2">
                            <h4 class="text-xs font-bold text-[#213153] flex items-center gap-1.5 uppercase tracking-wider">
                                <svg class="size-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Memo Persetujuan (Wadir II &amp; PPK)</span>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                                {{-- Memo Wadir 2 --}}
                                <div class="p-3 rounded-lg bg-purple-50/60 border border-purple-100 flex items-center justify-between gap-2">
                                    <div>
                                        <div class="text-xs font-bold text-purple-900 flex items-center gap-1">
                                            <span>1. Memo Wadir II:</span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Telah Disetujui</span>
                                        </div>
                                        <div class="text-[11px] font-mono text-purple-700 mt-0.5">{{ $memoWadir?->nomor_memo ?: 'Memo/Wadir2/Disetujui' }}</div>
                                        @if(!empty($memoWadir?->catatan_reviu))
                                            <div class="text-[10px] text-slate-500 italic mt-0.5">"{{ $memoWadir->catatan_reviu }}"</div>
                                        @endif
                                    </div>
                                    @if(!empty($memoWadir?->file_memo))
                                        <a href="{{ asset('storage/' . $memoWadir->file_memo) }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-[11px] font-bold inline-flex items-center gap-1 shrink-0 transition">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>Unduh Memo Wadir</span>
                                        </a>
                                    @else
                                        <span class="text-[10px] text-slate-400 italic">Disetujui digital</span>
                                    @endif
                                </div>

                                {{-- Memo PPK --}}
                                <div class="p-3 rounded-lg bg-blue-50/60 border border-blue-100 flex items-center justify-between gap-2">
                                    <div>
                                        <div class="text-xs font-bold text-blue-900 flex items-center gap-1">
                                            <span>2. Memo PPK:</span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Telah Disetujui</span>
                                        </div>
                                        <div class="text-[11px] font-mono text-blue-700 mt-0.5">{{ $memoPpk?->nomor_memo ?: 'Memo/PPK/Disetujui' }}</div>
                                        @if(!empty($memoPpk?->catatan_reviu))
                                            <div class="text-[10px] text-slate-500 italic mt-0.5">"{{ $memoPpk->catatan_reviu }}"</div>
                                        @endif
                                    </div>
                                    @if(!empty($memoPpk?->file_memo))
                                        <a href="{{ asset('storage/' . $memoPpk->file_memo) }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold inline-flex items-center gap-1 shrink-0 transition">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>Unduh Memo PPK</span>
                                        </a>
                                    @else
                                        <span class="text-[10px] text-slate-400 italic">Disetujui digital</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Form Input Legalitas Vendor & Penerbitan Perintah Bayar --}}
                        <form action="{{ route('pembayaran.terbitkan-spp') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl p-5 border border-slate-200/80 space-y-4">
                            @csrf
                            <input type="hidden" name="nomor_pembayaran" value="{{ $s->nomor_pembayaran }}">

                            <div class="border-b border-slate-100 pb-2">
                                <h4 class="text-xs font-bold text-[#213153] flex items-center gap-1.5 uppercase tracking-wider">
                                    <svg class="size-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span>Kelengkapan Legalitas Penyedia &amp; Perintah Bayar</span>
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Unggah Berkas Legalitas Penyedia
                                    </label>
                                    <div class="space-y-1">
                                        <input type="file" name="file_legalitas_vendor" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                        @if(!empty($sVendor?->file_dokumen_legalitas))
                                            <div class="text-[10px] text-slate-500">
                                                Berkas saat ini: <a href="{{ asset('storage/' . $sVendor->file_dokumen_legalitas) }}" target="_blank" class="text-indigo-600 font-bold hover:underline">Lihat Dokumen Legalitas Tersimpan</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Unggah Berkas Perintah Bayar (.pdf) <span class="text-slate-400 font-normal">(Opsional)</span>
                                    </label>
                                    <input type="file" name="file_spp" accept=".pdf" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Catatan Unit Pengadaan (Opsional)</label>
                                <input type="text" name="catatan_reviu_akhir" placeholder="Catatan kelengkapan berkas untuk Bagian Keuangan..." class="w-full h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            </div>

                            <div class="flex items-center justify-end pt-2 border-t border-slate-100">
                                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 shadow-sm transition inline-flex items-center gap-2 cursor-pointer">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>Terbitkan Perintah Bayar &amp; Kirim ke Keuangan &rarr;</span>
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- 7. PANEL PENCAIRAN DANA OLEH KEUANGAN --}}
    @if(Auth::user()->hasRole(['admin', 'keuangan']) || Auth::user()->hasPermission('pembayaran.pencairan_sp2d'))
    <div id="panel-pencairan-keuangan" class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80 space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            Pencairan Dana
                        </span>
                        <span class="text-xs text-slate-400 font-mono">Bagian Keuangan</span>
                    </div>
                    <h2 class="text-base font-bold text-[#213153] mt-1 flex items-center gap-2">
                        <span>4. Pencairan Dana &amp; Bukti Transfer</span>
                    </h2>
                </div>
                <div class="text-xs font-semibold text-slate-500">
                    Tahap: <span class="text-emerald-700 font-bold">Penyelesaian Pengadaan</span>
                </div>
            </div>
        </div>

        @if($siapCairKeuangan->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Belum ada tagihan siap cair saat ini.
            </div>
        @else
            {{-- TABEL PAKET PEMBAYARAN SIAP CAIR --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-[#213153] uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Daftar Tagihan Siap Cair
                    </h3>
                    <span class="text-[11px] text-slate-400 font-mono">{{ $siapCairKeuangan->count() }} Paket Siap Cair</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-4 py-3">No. Bayar &amp; Paket</th>
                                <th class="px-4 py-3">Dokumen Pembayaran</th>
                                <th class="px-4 py-3">Legalitas Penyedia</th>
                                <th class="px-4 py-3">Memo Persetujuan</th>
                                <th class="px-4 py-3">Nominal Siap Cair</th>
                                <th class="px-4 py-3 text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($siapCairKeuangan as $sc)
                                @php
                                    $scVendor = $sc->paket->kontrakSpk->vendor ?? ($sc->paket->kontrak->vendor ?? ($sc->paket->vendor ?? null));
                                    $memoWadirSc = $sc->memoBayar->firstWhere('role_approver', 'wadir_2');
                                    $memoPpkSc = $sc->memoBayar->firstWhere('role_approver', 'ppk');
                                    $bastSc = $sc->paket->penerimaanBarang->first();
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3.5 align-top">
                                        <div class="font-mono font-bold text-[#0969da]">{{ $sc->nomor_pembayaran }}</div>
                                        <div class="font-mono text-slate-700 text-[11px] mt-0.5">{{ $sc->nomor_paket }}</div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">{{ $sc->paket->nama_paket ?? '-' }}</div>
                                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            Perintah Bayar: {{ $sc->tanggal_perintah_bayar ? \Carbon\Carbon::parse($sc->tanggal_perintah_bayar)->format('d/m/Y') : '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 align-top space-y-1">
                                        {{-- Berkas Perintah Bayar --}}
                                        @if(!empty($sc->file_spp))
                                            <div>
                                                <a href="{{ asset('storage/' . $sc->file_spp) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#0969da] hover:underline">
                                                    <svg class="size-3 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                                    <span>Surat Perintah Bayar</span>
                                                </a>
                                            </div>
                                        @else
                                            <div class="text-[11px] text-slate-600 font-medium">No. Sistem: {{ $sc->nomor_pembayaran }}</div>
                                        @endif

                                        {{-- Kwitansi, Invoice, Perikatan & Penunjang --}}
                                        <div class="text-[10px] text-slate-600 space-y-0.5">
                                            <div>Kwitansi: <span class="font-mono font-semibold">{{ $sc->nomor_kwitansi ?: '-' }}</span>
                                                @if(!empty($sc->file_kwitansi))
                                                    <a href="{{ asset('storage/' . $sc->file_kwitansi) }}" target="_blank" class="text-[#0969da] hover:underline font-bold ml-1">(File)</a>
                                                @endif
                                            </div>
                                            <div>Invoice: <span class="font-mono font-semibold">{{ $sc->nomor_invoice ?: '-' }}</span>
                                                @if(!empty($sc->file_invoice))
                                                    <a href="{{ asset('storage/' . $sc->file_invoice) }}" target="_blank" class="text-[#0969da] hover:underline font-bold ml-1">(File)</a>
                                                @endif
                                            </div>
                                            @php
                                                $scPerikatanFile = $sc->paket?->kontrakSpk?->file_spk ?: $sc->file_dokumen_perikatan;
                                            @endphp
                                            @if(!empty($scPerikatanFile))
                                                <div>Perikatan: <a href="{{ asset('storage/' . $scPerikatanFile) }}" target="_blank" class="text-[#0969da] hover:underline font-bold">(File)</a></div>
                                            @endif
                                            @if(!empty($sc->file_dokumen_penunjang))
                                                <div>Penunjang: <a href="{{ asset('storage/' . $sc->file_dokumen_penunjang) }}" target="_blank" class="text-[#0969da] hover:underline font-bold">(File)</a></div>
                                            @endif
                                        </div>

                                        {{-- Dokumen Serah Terima --}}
                                        @if($bastSc)
                                            <div class="text-[10px] text-emerald-700 font-semibold pt-0.5">
                                                Serah Terima: {{ $bastSc->nomor_bast }}
                                                @if(!empty($bastSc->file_bast_signed))
                                                    <a href="{{ asset('storage/' . $bastSc->file_bast_signed) }}" target="_blank" class="text-[#0969da] hover:underline font-bold ml-1">(Bukti Fisik)</a>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 align-top space-y-1">
                                        <div class="font-bold text-slate-800">{{ $scVendor->nama_vendor ?? 'Penyedia Rekanan' }}</div>
                                        @if(!empty($scVendor?->file_dokumen_legalitas))
                                            <div>
                                                <a href="{{ asset('storage/' . $scVendor->file_dokumen_legalitas) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-[#0969da] hover:underline mt-0.5">
                                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                    <span>Berkas Legalitas Vendor</span>
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 align-top space-y-1">
                                        {{-- Memo Wadir 2 --}}
                                        <div class="p-1.5 rounded-lg bg-purple-50/70 border border-purple-100 text-[10px]">
                                            <div class="font-bold text-purple-900">1. Memo Wadir II (Level 1)</div>
                                            <div class="font-mono text-purple-700 truncate">{{ $memoWadirSc?->nomor_memo ?: 'Telah Disetujui' }}</div>
                                            @if(!empty($memoWadirSc?->file_memo))
                                                <a href="{{ asset('storage/' . $memoWadirSc->file_memo) }}" target="_blank" class="text-[#0969da] font-bold hover:underline inline-flex items-center gap-0.5 mt-0.5">
                                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    <span>Berkas Memo Fisik</span>
                                                </a>
                                            @endif
                                        </div>

                                        {{-- Memo PPK --}}
                                        <div class="p-1.5 rounded-lg bg-blue-50/70 border border-blue-100 text-[10px]">
                                            <div class="font-bold text-blue-900">2. Memo PPK (Level 2)</div>
                                            <div class="font-mono text-blue-700 truncate">{{ $memoPpkSc?->nomor_memo ?: 'Telah Disetujui' }}</div>
                                            @if(!empty($memoPpkSc?->file_memo))
                                                <a href="{{ asset('storage/' . $memoPpkSc->file_memo) }}" target="_blank" class="text-[#0969da] font-bold hover:underline inline-flex items-center gap-0.5 mt-0.5">
                                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    <span>Berkas Memo Fisik</span>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 align-top font-mono font-bold text-emerald-600 text-sm whitespace-nowrap">
                                        Rp {{ number_format($sc->nominal_pengajuan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3.5 align-top text-center">
                                        <button
                                            type="button"
                                            data-select-spp="{{ $sc->nomor_pembayaran }}"
                                            data-select-nominal="{{ $sc->nominal_pengajuan }}"
                                            class="px-3 py-1.5 rounded-lg bg-[#0969da] text-[11px] font-bold text-white hover:bg-blue-700 shadow-sm transition whitespace-nowrap"
                                        >
                                            Pilih &amp; Eksekusi &darr;
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- FORM EKSEKUSI PEMBAYARAN & INPUT BUKTI PEMBAYARAN --}}
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/30 p-5 space-y-4">
                <div class="border-b border-emerald-100 pb-3">
                    <h3 class="text-xs font-bold text-emerald-950 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="size-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Pencairan Dana &amp; Bukti Transfer
                    </h3>
                    <p class="text-[11px] text-emerald-800 mt-0.5">
                        Transfer dana ke rekening penyedia, isi nomor transaksi bank, dan unggah bukti transfer.
                    </p>
                </div>

                <form id="form-pencairan-sp2d" action="{{ route('pembayaran.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="nomor_pembayaran_cair" class="block text-xs font-bold text-slate-700 mb-1">Pilih Perintah Bayar Siap Cair <span class="text-rose-500">*</span></label>
                            <select id="nomor_pembayaran_cair" name="nomor_pembayaran" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                                <option value="">-- Pilih Perintah Bayar --</option>
                                @foreach($siapCairKeuangan as $sc)
                                    <option
                                        value="{{ $sc->nomor_pembayaran }}"
                                        data-nominal="{{ $sc->nominal_pengajuan }}"
                                    >
                                        [{{ $sc->nomor_pembayaran }}] Paket: {{ $sc->nomor_paket }} &bull; Rp {{ number_format($sc->nominal_pengajuan, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="nomor_transaksi" class="block text-xs font-bold text-slate-700 mb-1">Nomor Transaksi Bank <span class="text-rose-500">*</span></label>
                            <input type="text" id="nomor_transaksi" name="nomor_transaksi" value="SP2D/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="tanggal_cair" class="block text-xs font-bold text-slate-700 mb-1">Tanggal Cair <span class="text-rose-500">*</span></label>
                            <input type="date" id="tanggal_cair" name="tanggal_cair" value="{{ date('Y-m-d') }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>

                        <div>
                            <label for="nominal_cair" class="block text-xs font-bold text-slate-700 mb-1">Jumlah Dicairkan (Rp) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                                <input type="number" id="nominal_cair" name="nominal_cair" step="0.01" min="1" required placeholder="Contoh: 150000000" class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                            </div>
                        </div>

                        <div>
                            <label for="bank_tujuan" class="block text-xs font-bold text-slate-700 mb-1">Bank Penyalur <span class="text-rose-500">*</span></label>
                            <input type="text" id="bank_tujuan" name="bank_tujuan" value="Bank Mandiri KCP PNJ" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>
                    </div>

                    <div>
                        <label for="file_bukti_transfer" class="block text-xs font-bold text-slate-700 mb-1">Unggah Bukti Transfer Bank (PDF/JPG, Maks 10MB) <span class="text-rose-500">*</span></label>
                        <input type="file" id="file_bukti_transfer" name="file_bukti_transfer" required accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800">
                    </div>

                    <div>
                        <label for="keterangan_cair" class="block text-xs font-bold text-slate-700 mb-1">Catatan Pencairan (Opsional)</label>
                        <textarea id="keterangan_cair" name="keterangan_cair" rows="2" placeholder="Catatan pembayaran termin atau pelunasan..." class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                    </div>

                    <div class="flex items-center justify-end pt-3 border-t border-emerald-100">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700 shadow-sm transition inline-flex items-center gap-2">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Cairkan Dana &amp; Selesaikan Pembayaran</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
    @endif

    {{-- 8. RIWAYAT PENCAIRAN DANA & BUKTI TRANSFER --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Riwayat Pencairan Dana</h3>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $transaksiList->total() }} Transaksi</span>
        </div>

        @if($transaksiList->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400">Belum ada riwayat pencairan dana.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3">Nomor Transaksi Bank</th>
                            <th class="px-5 py-3">Nomor Perintah Bayar</th>
                            <th class="px-5 py-3">Paket Pengadaan</th>
                            <th class="px-5 py-3">Tanggal Cair</th>
                            <th class="px-5 py-3">Nominal Cair</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-center">Unduh Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($transaksiList as $t)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-5 py-3.5 font-mono font-bold text-[#0969da]">{{ $t->nomor_transaksi_bank }}</td>
                                <td class="px-5 py-3.5 font-mono">{{ $t->nomor_pembayaran }}</td>
                                <td class="px-5 py-3.5">{{ $t->pembayaran->nomor_paket ?? '-' }}</td>
                                <td class="px-5 py-3.5">{{ $t->tanggal_transfer ? \Carbon\Carbon::parse($t->tanggal_transfer)->format('d/m/Y') : '-' }}</td>
                                <td class="px-5 py-3.5 font-mono font-bold text-emerald-600">Rp {{ number_format($t->nominal_transfer, 0, ',', '.') }}</td>
                                <td class="px-5 py-3.5">
                                    <x-status-badge status="sp2d_lunas" size="sm" />
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <a
                                        href="{{ route('dokumen.pdf', ['jenis' => 'transfer', 'id' => $t->nomor_transaksi_bank]) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        <svg class="size-3.5 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                        PDF Bukti
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100">
                {{ $transaksiList->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
