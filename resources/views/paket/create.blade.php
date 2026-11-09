@extends('layouts.app')

@section('title', 'Registrasi Paket Pengadaan Baru & Auto-Routing HPS - SIM-PBJ PNJ')

@section('content')
<div
    x-data="{
        hps: '{{ old('nilai_hps', '') }}',
        namaPaket: '{{ old('nama_paket', '') }}',
        metode: '{{ old('metode_pengadaan', '') }}',
        selectedSurat: '{{ request('selected', old('nomor_surat', '')) }}',
        permohonanMap: {
            @foreach($permohonanDisetujui as $p)
            @php
                $paguVal = (float) ($p->anggaran_diajukan ?? $p->estimasi_total_pagu ?? 0);
                $makObj = $p->anggaranMak ?? ($p->alokasiMak?->anggaranMak ?? null);
                $verifWadir = $p->verifikasiParalel ? $p->verifikasiParalel->where('role_verifikator', 'wadir_2')->first() : null;
                $verifPpk = $p->verifikasiParalel ? $p->verifikasiParalel->where('role_verifikator', 'ppk')->first() : null;
                $verifPerencanaan = $p->verifikasiParalel ? $p->verifikasiParalel->where('role_verifikator', 'perencanaan')->first() : null;
            @endphp
            '{{ $p->nomor_surat }}': {
                nomorSurat: @js($p->nomor_surat),
                pagu: {{ $paguVal }},
                paguFormatted: 'Rp {{ number_format($paguVal, 0, ',', '.') }}',
                perihal: @js($p->perihal ?? $p->judul_pengadaan ?? ''),
                tujuan: @js($p->tujuan_pengadaan_ringkas ?? 'Pengadaan fasilitas operasional sesuai Kerangka Acuan Kerja.'),
                unit: @js($p->unit->nama_unit ?? $p->kode_unit ?? 'Unit Pengusul'),
                tanggalPengajuan: @js($p->tanggal_pengajuan ? date('d M Y', strtotime($p->tanggal_pengajuan)) : '-'),
                filePermohonan: @js($p->file_surat_permohonan ? asset('storage/' . $p->file_surat_permohonan) : route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat])),
                fileKak: @js($p->file_kak ? asset('storage/' . $p->file_kak) : null),
                fileSpek: @js($p->file_spesifikasi_teknis ? asset('storage/' . $p->file_spesifikasi_teknis) : null),
                kodeMak: @js($p->kode_mak ?? ($makObj?->kode_mak ?? '-')),
                namaMak: @js($makObj?->nama_mak ?? 'Mata Anggaran Kegiatan DIPA Institusi'),
                paguMak: 'Rp {{ number_format($makObj?->pagu_mak ?? 0, 0, ',', '.') }}',
                sisaMak: {{ (float) ($makObj?->sisa_pagu_mak ?? 0) }},
                sisaMakFormatted: 'Rp {{ number_format($makObj?->sisa_pagu_mak ?? 0, 0, ',', '.') }}',
                sumberDana: @js($makObj?->sumber_dana ?? 'DIPA PNJ Tahun Anggaran 2026'),
                nomorMemoMak: @js($p->nomor_memo_ketersediaan_mak ?? ($p->nomor_memo_cek_mak ?? 'MEMO-MAK/' . date('Y') . '/' . substr(md5($p->nomor_surat), 0, 6))),
                catatanMemoMak: @js($p->catatan_memo_ketersediaan_mak ?? ($verifPerencanaan?->catatan_alasan ?? 'Ketersediaan pagu MAK telah diverifikasi dan dialokasikan oleh Subbagian Perencanaan.')),
                wadirStatus: @js($verifWadir ? ucwords($verifWadir->status_keputusan) : 'Disetujui'),
                wadirCatatan: @js($verifWadir?->catatan_alasan ?? 'Usulan pengadaan disetujui untuk diteruskan dan dibentuk paket pengadaan oleh PPBJ.'),
                wadirTanggal: @js($verifWadir?->tanggal_keputusan ? date('d M Y H:i', strtotime($verifWadir->tanggal_keputusan)) : date('d M Y')),
                wadirNomorMemo: @js($verifWadir?->nomor_memo ?? $p->nomor_memo_ketersediaan_mak ?? ('MEMO-WADIR2/' . date('Y') . '/' . substr(md5($p->nomor_surat), 0, 6))),
                wadirFileMemo: @js($verifWadir?->file_memo ? asset('storage/' . $verifWadir->file_memo) : ($p->file_memo_ketersediaan_mak ? asset('storage/' . $p->file_memo_ketersediaan_mak) : null)),
                ppkNama: @js($p->ppkAwal?->nama ?? ($verifPpk?->user?->nama ?? 'Pejabat Pembuat Komitmen (PPK)')),
                ppkStatus: @js($verifPpk ? ucwords($verifPpk->status_keputusan) : 'Disetujui'),
                ppkCatatan: @js($verifPpk?->catatan_alasan ?? 'Usulan pengadaan disetujui untuk registrasi paket & penentuan metode belanja.'),
                ppkTanggal: @js($verifPpk?->tanggal_keputusan ? date('d M Y H:i', strtotime($verifPpk->tanggal_keputusan)) : date('d M Y')),
                ppkNomorMemo: @js($verifPpk?->nomor_memo ?? $p->nomor_memo_cek_mak ?? ('MEMO-PPK/' . date('Y') . '/' . substr(md5($p->nomor_surat), 0, 6))),
                ppkFileMemo: @js($verifPpk?->file_memo ? asset('storage/' . $verifPpk->file_memo) : ($p->file_memo_cek_mak ? asset('storage/' . $p->file_memo_cek_mak) : null))
            },
            @endforeach
        },
        onSelectSurat(nomor) {
            this.selectedSurat = nomor;
            if (nomor && this.permohonanMap[nomor]) {
                let item = this.permohonanMap[nomor];
                this.hps = item.pagu;
                if (!this.namaPaket || this.namaPaket.trim() === '') {
                    this.namaPaket = item.perihal ? 'Pengadaan ' + item.perihal : '';
                }
                this.syncMetodeWithRouting();
            }
        },
        routing() {
            let n = parseFloat(this.hps) || 0;
            if (n <= 0) return null;
            if (n <= 50000000) return 'langsung';
            if (n <= 200000000) return 'pp';
            return 'ppk';
        },
        syncMetodeWithRouting() {
            let r = this.routing();
            if (r === 'langsung') {
                this.metode = 'Pengadaan Langsung';
            } else if (r === 'pp') {
                if (this.metode === 'Pengadaan Langsung' || !['SPK', 'Surat Pesanan', 'Surat Kontrak', 'E-Purchasing'].includes(this.metode)) {
                    this.metode = 'SPK';
                }
            } else if (r === 'ppk') {
                if (this.metode === 'Pengadaan Langsung' || !['Tender', 'E-Purchasing', 'Seleksi', 'Penunjukan Langsung'].includes(this.metode)) {
                    this.metode = 'Tender';
                }
            }
        },
        getDeltaMak() {
            if (!this.selectedSurat || !this.permohonanMap[this.selectedSurat]) return 0;
            let n = parseFloat(this.hps) || 0;
            return n - this.permohonanMap[this.selectedSurat].pagu;
        },
        formatRupiah(val) {
            let n = parseFloat(val) || 0;
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
        }
    }"
    x-init="if (selectedSurat) { onSelectSurat(selectedSurat); }"
    class="space-y-6"
>

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="3" status="diajukan" />

    {{-- 2. Card Header & Form --}}
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-100 pb-5 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Registrasi Paket Pengadaan Baru</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Unit Kerja Pengadaan Barang &amp; Jasa (UKPBJ / PPBJ) Politeknik Negeri Jakarta
                </p>
            </div>

            <a
                href="{{ route('paket.index') }}"
                class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shrink-0 inline-flex items-center gap-1.5"
            >
                &larr; Kembali ke Daftar Paket
            </a>
        </div>

        {{-- 3. Dynamic Reactive Routing Banner --}}
        <div class="mb-6 rounded-2xl p-4 transition-all duration-300"
             :class="{
                'bg-slate-50 border border-slate-200 text-slate-600': routing() === null,
                'bg-teal-50 border border-teal-200 text-teal-900': routing() === 'langsung',
                'bg-amber-50 border border-amber-200 text-amber-900': routing() === 'pp',
                'bg-purple-50 border border-purple-200 text-purple-900': routing() === 'ppk'
             }"
        >
            <div class="flex items-start gap-3">
                <div class="size-9 rounded-xl flex items-center justify-center shrink-0"
                     :class="{
                        'bg-slate-200 text-slate-600': routing() === null,
                        'bg-teal-600 text-white': routing() === 'langsung',
                        'bg-amber-600 text-white': routing() === 'pp',
                        'bg-purple-600 text-white': routing() === 'ppk'
                     }"
                >
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Routing HPS:</span>
                        <template x-if="routing() === 'langsung'">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800">
                                &le; Rp 50 Juta &bull; Belanja Langsung
                            </span>
                        </template>
                        <template x-if="routing() === 'pp'">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                &gt; Rp 50 Jt s.d &le; Rp 200 Jt &bull;
                            </span>
                        </template>
                        <template x-if="routing() === 'ppk'">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                &gt; Rp 200 Juta &bull; PPK Mandiri
                            </span>
                        </template>
                        <template x-if="routing() === null">
                            <span class="text-xs text-slate-400 italic">Pilih usulan permohonan atau masukkan nilai HPS untuk melihat jalur routing otomatis</span>
                        </template>
                    </div>

                    <p class="text-xs mt-1 leading-relaxed" x-show="routing() === 'langsung'">
                        <strong>Alur Kerja:</strong> Nilai HPS &le; Rp 50 Juta adalah Belanja Langsung (output Kwitansi sah tanpa Kontrak/SPK/Surat Pesanan). Dropdown metode otomatis terkunci pada <em>Pengadaan Langsung</em>. Dokumen rekanan vendor diinput nanti saat eksekusi belanja langsung.
                    </p>
                    <p class="text-xs mt-1 leading-relaxed" x-show="routing() === 'pp'">
                        <strong>Alur Kerja:</strong> Ditugaskan ke Pejabat Pengadaan (PP) untuk review kelengkapan &amp; negosiasi &rarr; PPK menyetujui dokumen &rarr; PP belanja &rarr; PPBJ mengunggah 1 dokumen perikatan terpilih (SPK / Surat Pesanan / Surat Kontrak) beserta input vendor rekanan.
                    </p>
                    <p class="text-xs mt-1 leading-relaxed" x-show="routing() === 'ppk'">
                        <strong>Alur Kerja:</strong> Ditugaskan ke PPK terpilih untuk penelaahan spesifikasi teknis &rarr; PPK menyetujui &amp; mengeksekusi belanja &rarr; PPBJ mengunggah 1 dokumen perikatan terpilih beserta upload dokumen legalitas vendor. <em>Catatan: Paket &gt; Rp 200 Juta tidak menggunakan memo pengadaan PPBJ.</em>
                    </p>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                <p class="font-bold mb-1">Perhatian: Terjadi kesalahan validasi data form:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 4. Form Registrasi Paket --}}
        <form action="{{ route('paket.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Permohonan Induk --}}
                <div class="md:col-span-2">
                    <label for="nomor_surat" class="block text-xs font-bold text-slate-700 mb-1">
                        Pilih Usulan Permohonan Pengadaan Disetujui <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="nomor_surat"
                        name="nomor_surat"
                        required
                        x-model="selectedSurat"
                        @change="onSelectSurat($event.target.value)"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="">-- Pilih Surat Permohonan Pengadaan Disetujui --</option>
                        @foreach($permohonanDisetujui as $p)
                            <option value="{{ $p->nomor_surat }}" {{ old('nomor_surat', request('selected')) == $p->nomor_surat ? 'selected' : '' }}>
                                [{{ $p->nomor_surat }}] {{ $p->perihal ?? $p->judul_pengadaan }} &bull; Pagu: Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }} ({{ $p->unit->nama_unit ?? $p->kode_unit }}) &bull; MAK: {{ $p->kode_mak }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Dossier Berkas Diterima PPBJ (BPMN Node: Menerima Form Pengadaan, MAK, dan Memo Permohonan) --}}
                    <template x-if="selectedSurat && permohonanMap[selectedSurat]">
                        <div class="mt-4 p-5 rounded-2xl bg-slate-50/80 border border-slate-200 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200/80">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-8 rounded-lg bg-[#0969da] text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#213153]">
                                            Kelengkapan Berkas Usulan Masuk (Form Pengadaan, MAK &amp; Memo Permohonan)
                                        </h3>
                                        <p class="text-[11px] text-slate-500">
                                            Berkas usulan permohonan telah disetujui lengkap oleh Subbagian Perencanaan, Wadir II, dan PPK Penanggung Jawab.
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    Disetujui Lengkap &bull; Siap Registrasi Paket
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                {{-- 1. Form Pengadaan & Dokumen Teknis --}}
                                <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-xs space-y-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="size-6 rounded-md bg-blue-100 text-[#0969da] flex items-center justify-center text-[11px] font-bold">1</span>
                                        <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wide">Form &amp; Teknis</h4>
                                    </div>
                                    <div class="text-xs space-y-1">
                                        <p class="text-slate-500">Nomor: <strong class="text-slate-800 font-mono text-[11px]" x-text="permohonanMap[selectedSurat].nomorSurat"></strong></p>
                                        <p class="text-slate-500">Unit: <strong class="text-slate-800" x-text="permohonanMap[selectedSurat].unit"></strong></p>
                                        <p class="text-slate-500">Tanggal: <span class="text-slate-700" x-text="permohonanMap[selectedSurat].tanggalPengajuan"></span></p>
                                        <p class="text-slate-500">Pagu: <strong class="text-[#0969da] font-mono" x-text="permohonanMap[selectedSurat].paguFormatted"></strong></p>
                                    </div>
                                    <div class="pt-2 border-t border-slate-100 flex flex-col gap-1.5">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Lampiran Fisik:</span>
                                        <a :href="permohonanMap[selectedSurat].filePermohonan" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#0969da] hover:underline">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>Surat Srikandi</span>
                                        </a>
                                        <template x-if="permohonanMap[selectedSurat].fileKak">
                                            <a :href="permohonanMap[selectedSurat].fileKak" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#0969da] hover:underline">
                                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Berkas KAK</span>
                                            </a>
                                        </template>
                                        <template x-if="permohonanMap[selectedSurat].fileSpek">
                                            <a :href="permohonanMap[selectedSurat].fileSpek" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#0969da] hover:underline">
                                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                                <span>Spek Teknis</span>
                                            </a>
                                        </template>
                                    </div>
                                </div>

                                {{-- 2. Penetapan MAK DIPA --}}
                                <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-xs space-y-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="size-6 rounded-md bg-emerald-100 text-emerald-700 flex items-center justify-center text-[11px] font-bold">2</span>
                                        <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wide">Penetapan MAK</h4>
                                    </div>
                                    <div class="text-xs space-y-1">
                                        <p class="text-slate-500">Kode: <strong class="text-emerald-700 font-mono text-[11px]" x-text="permohonanMap[selectedSurat].kodeMak"></strong></p>
                                        <p class="text-slate-500">Uraian: <span class="text-slate-800 font-medium block truncate text-[11px]" :title="permohonanMap[selectedSurat].namaMak" x-text="permohonanMap[selectedSurat].namaMak"></span></p>
                                        <p class="text-slate-500">Pagu MAK: <span class="text-slate-700 font-mono text-[11px]" x-text="permohonanMap[selectedSurat].paguMak"></span></p>
                                        <p class="text-slate-500">Sisa Pagu: <strong class="text-emerald-600 font-mono text-[11px]" x-text="permohonanMap[selectedSurat].sisaMakFormatted"></strong></p>
                                    </div>
                                    <div class="pt-2 border-t border-slate-100">
                                        <span class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                            <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span>MAK Dialokasikan</span>
                                        </span>
                                    </div>
                                </div>

                                {{-- 3. Memo Permohonan Bidang 2 (Wadir II) --}}
                                <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-xs space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span class="size-6 rounded-md bg-purple-100 text-purple-700 flex items-center justify-center text-[11px] font-bold">3</span>
                                            <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wide">Memo Wadir II</h4>
                                        </div>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800" x-text="permohonanMap[selectedSurat].wadirStatus"></span>
                                    </div>
                                    <div class="text-xs space-y-1">
                                        <p class="text-slate-500">No. Memo: <strong class="font-mono text-purple-900 text-[11px]" x-text="permohonanMap[selectedSurat].wadirNomorMemo"></strong></p>
                                        <p class="text-slate-500">Tanggal: <span class="text-slate-700" x-text="permohonanMap[selectedSurat].wadirTanggal"></span></p>
                                        <p class="text-[11px] text-slate-600 italic line-clamp-2" :title="permohonanMap[selectedSurat].wadirCatatan" x-text="'Catatan: ' + permohonanMap[selectedSurat].wadirCatatan"></p>
                                    </div>
                                    <div class="pt-2 border-t border-slate-100">
                                        <template x-if="permohonanMap[selectedSurat].wadirFileMemo">
                                            <a :href="permohonanMap[selectedSurat].wadirFileMemo" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#0969da] hover:underline">
                                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Unduh Berkas Fisik Memo</span>
                                            </a>
                                        </template>
                                        <template x-if="!permohonanMap[selectedSurat].wadirFileMemo">
                                            <span class="text-[10px] text-slate-400 italic">Disposisi digital tercatat</span>
                                        </template>
                                    </div>
                                </div>

                                {{-- 4. Memo Permohonan PPK --}}
                                <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-xs space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span class="size-6 rounded-md bg-blue-100 text-blue-700 flex items-center justify-center text-[11px] font-bold">4</span>
                                            <h4 class="text-xs font-bold text-[#213153] uppercase tracking-wide">Memo PPK</h4>
                                        </div>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800" x-text="permohonanMap[selectedSurat].ppkStatus"></span>
                                    </div>
                                    <div class="text-xs space-y-1">
                                        <p class="text-slate-500">No. Memo: <strong class="font-mono text-blue-900 text-[11px]" x-text="permohonanMap[selectedSurat].ppkNomorMemo"></strong></p>
                                        <p class="text-slate-500">Pejabat: <strong class="text-slate-800 text-[11px]" x-text="permohonanMap[selectedSurat].ppkNama"></strong></p>
                                        <p class="text-[11px] text-slate-600 italic line-clamp-2" :title="permohonanMap[selectedSurat].ppkCatatan" x-text="'Catatan: ' + permohonanMap[selectedSurat].ppkCatatan"></p>
                                    </div>
                                    <div class="pt-2 border-t border-slate-100">
                                        <template x-if="permohonanMap[selectedSurat].ppkFileMemo">
                                            <a :href="permohonanMap[selectedSurat].ppkFileMemo" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#0969da] hover:underline">
                                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Unduh Berkas Fisik Memo</span>
                                            </a>
                                        </template>
                                        <template x-if="!permohonanMap[selectedSurat].ppkFileMemo">
                                            <span class="text-[10px] text-slate-400 italic">Disposisi digital tercatat</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Kode Paket --}}
                <div>
                    <label for="kode_paket" class="block text-xs font-bold text-slate-700 mb-1">
                        Kode Unik Paket Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="kode_paket"
                        name="kode_paket"
                        value="{{ old('kode_paket', 'PKT-PNJ-' . date('Ymd') . '-' . rand(100, 999)) }}"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                {{-- Nama Paket --}}
                <div>
                    <label for="nama_paket" class="block text-xs font-bold text-slate-700 mb-1">
                        Nama Paket Pengadaan Barang/Jasa <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="nama_paket"
                        name="nama_paket"
                        x-model="namaPaket"
                        required
                        placeholder="Contoh: Pengadaan Fasilitas Komputer AI Lab TIK"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                {{-- Jenis Pengadaan --}}
                <div>
                    <label for="jenis_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                        Jenis Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="jenis_pengadaan"
                        name="jenis_pengadaan"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="Barang" {{ old('jenis_pengadaan') == 'Barang' ? 'selected' : '' }}>Barang</option>
                        <option value="Jasa Lainnya" {{ old('jenis_pengadaan') == 'Jasa Lainnya' ? 'selected' : '' }}>Jasa Lainnya</option>
                        <option value="Pekerjaan Konstruksi" {{ old('jenis_pengadaan') == 'Pekerjaan Konstruksi' ? 'selected' : '' }}>Pekerjaan Konstruksi</option>
                        <option value="Jasa Konsultansi" {{ old('jenis_pengadaan') == 'Jasa Konsultansi' ? 'selected' : '' }}>Jasa Konsultansi</option>
                    </select>
                </div>

                {{-- Nilai HPS (Reactive with x-model) --}}
                <div>
                    <label for="nilai_hps" class="block text-xs font-bold text-slate-700 mb-1">
                        Nilai HPS (Harga Perkiraan Sendiri) Rupiah <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-slate-400">
                            Rp
                        </span>
                        <input
                            type="number"
                            id="nilai_hps"
                            name="nilai_hps"
                            x-model="hps"
                            @input="syncMetodeWithRouting()"
                            step="0.01"
                            min="1"
                            required
                            placeholder="75000000"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-xs text-slate-800 font-mono font-bold focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                    </div>
                    <div class="flex items-center justify-between text-[11px] mt-1">
                        <span class="text-slate-400">Nominal terformat:</span>
                        <span class="font-bold font-mono text-[#0969da]" x-text="formatRupiah(hps)">Rp 0</span>
                    </div>

                    {{-- Dynamic Reactive MAK Impact Calculation --}}
                    <template x-if="selectedSurat && permohonanMap[selectedSurat]">
                        <div class="mt-2 text-xs p-2.5 rounded-xl border transition-all"
                             :class="{
                                 'bg-emerald-50 border-emerald-200 text-emerald-800': getDeltaMak() < 0,
                                 'bg-amber-50 border-amber-200 text-amber-800': getDeltaMak() > 0 && getDeltaMak() <= permohonanMap[selectedSurat].sisaMak,
                                 'bg-rose-50 border-rose-200 text-rose-800': getDeltaMak() > permohonanMap[selectedSurat].sisaMak,
                                 'bg-slate-50 border-slate-200 text-slate-600': getDeltaMak() === 0
                             }"
                        >
                            <template x-if="getDeltaMak() === 0">
                                <div class="flex items-center gap-1.5">
                                    <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Pagu HPS sinkron dengan alokasi usulan permohonan awal (<span class="font-bold font-mono" x-text="formatRupiah(permohonanMap[selectedSurat].pagu)"></span>).</span>
                                </div>
                            </template>
                            <template x-if="getDeltaMak() < 0">
                                <div class="flex items-center gap-1.5">
                                    <svg class="size-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                                    <span><strong>Efisiensi Anggaran:</strong> Terjadi penghematan <span class="font-bold font-mono" x-text="formatRupiah(Math.abs(getDeltaMak()))"></span> yang otomatis dikembalikan ke sisa pagu MAK (<span x-text="permohonanMap[selectedSurat].kodeMak"></span>).</span>
                                </div>
                            </template>
                            <template x-if="getDeltaMak() > 0 && getDeltaMak() <= permohonanMap[selectedSurat].sisaMak">
                                <div class="flex items-center gap-1.5">
                                    <svg class="size-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                                    <span><strong>Kenaikan HPS:</strong> Selisih <span class="font-bold font-mono" x-text="formatRupiah(getDeltaMak())"></span> akan dipotong dari saldo MAK (<span x-text="permohonanMap[selectedSurat].kodeMak"></span>). Sisa pagu MAK mencukupi (<span class="font-mono font-bold" x-text="formatRupiah(permohonanMap[selectedSurat].sisaMak)"></span>).</span>
                                </div>
                            </template>
                            <template x-if="getDeltaMak() > permohonanMap[selectedSurat].sisaMak">
                                <div class="flex items-center gap-1.5">
                                    <svg class="size-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span><strong>Defisit Anggaran:</strong> Kenaikan HPS sebesar <span class="font-bold font-mono" x-text="formatRupiah(getDeltaMak())"></span> melebihi sisa pagu MAK (<span x-text="permohonanMap[selectedSurat].kodeMak"></span> tersisa <span class="font-bold font-mono" x-text="formatRupiah(permohonanMap[selectedSurat].sisaMak)"></span>).</span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Metode Pengadaan (Auto-Locking & Filtering) --}}
                <div>
                    <label for="metode_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                        Mekanisme / Metode Pengadaan <span class="text-rose-500">*</span>
                        <template x-if="routing() === 'langsung'">
                            <span class="text-teal-600 font-normal">(Otomatis Terkunci: Belanja Langsung)</span>
                        </template>
                    </label>

                    {{-- Hidden input when disabled to ensure value is submitted --}}
                    <input type="hidden" name="metode_pengadaan" x-bind:value="metode" x-show="routing() === 'langsung'" />

                    <select
                        id="metode_pengadaan"
                        name="metode_pengadaan"
                        x-model="metode"
                        :disabled="routing() === 'langsung'"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 disabled:bg-slate-100 disabled:text-slate-500"
                    >
                        {{-- Opsi Jalur Belanja Langsung (<= 50 Juta) --}}
                        <template x-if="routing() === 'langsung' || routing() === null">
                            <option value="Pengadaan Langsung">Belanja Langsung (&le; Rp 50 Juta &bull; Kwitansi Tanpa Kontrak)</option>
                        </template>

                        {{-- Opsi Jalur PP (> 50 Juta s.d 200 Juta) --}}
                        <template x-if="routing() === 'pp'">
                            <optgroup label="Metode Jalur PP (50 Jt s.d 200 Jt)">
                                <option value="SPK">SPK (Surat Perintah Kerja)</option>
                                <option value="Surat Pesanan">Surat Pesanan (E-Purchasing / E-Katalog)</option>
                                <option value="Surat Kontrak">Surat Kontrak (Non-Tender Rekanan)</option>
                            </optgroup>
                        </template>

                        {{-- Opsi Jalur PPK (> 200 Juta) --}}
                        <template x-if="routing() === 'ppk'">
                            <optgroup label="Metode Jalur PPK (&gt; 200 Jt)">
                                <option value="Tender">Tender Terbuka (SPSE)</option>
                                <option value="E-Purchasing">Non-Tender / E-Purchasing (E-Katalog LKPP)</option>
                                <option value="Seleksi">Seleksi Konsultansi</option>
                                <option value="Penunjukan Langsung">Penunjukan Langsung</option>
                            </optgroup>
                        </template>
                    </select>

                    <p class="text-[11px] text-slate-400 mt-1" x-show="routing() === 'langsung'">
                        Paket &le; Rp 50 Juta otomatis berstatus Belanja Langsung dengan output Kwitansi sah.
                    </p>
                </div>

                {{-- Nomor Memo Pengadaan PPBJ --}}
                <div>
                    <label for="nomor_memo_ppbj" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Memo Pengadaan PPBJ
                        <span class="text-slate-400 font-normal">(Khusus HPS &le; 200 Juta)</span>
                    </label>
                    <input
                        type="text"
                        id="nomor_memo_ppbj"
                        name="nomor_memo_ppbj"
                        value="{{ old('nomor_memo_ppbj', 'MEMO-PPBJ/' . date('Y') . '/' . rand(100, 999)) }}"
                        :disabled="routing() === 'ppk'"
                        :placeholder="routing() === 'ppk' ? 'Tanpa Memo PPBJ (Otomatis Kosong untuk HPS > 200 Juta)' : 'MEMO-PPBJ/...'"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 disabled:bg-slate-100 disabled:text-slate-400"
                    />
                    <p class="text-[11px] text-slate-400 mt-1" x-show="routing() === 'ppk'">
                        Aturan bisnis K2/Q7: Paket &gt; Rp 200 Juta tidak menggunakan memo PPBJ.
                    </p>
                </div>

                {{-- Penugasan PPK (BPMN N30-N36) --}}
                <div>
                    <label for="id_user_ppk" class="block text-xs font-bold text-slate-700 mb-1">
                        Pejabat Pembuat Komitmen (PPK)
                        <span class="text-rose-500">*</span>
                        <span class="text-slate-400 font-normal">(Diwarisi otomatis dari persetujuan awal)</span>
                    </label>
                    <select
                        id="id_user_ppk"
                        name="id_user_ppk"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="">-- Pilih 1 dari 4 Akun PPK Aktif PNJ --</option>
                        @foreach($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}" {{ old('id_user_ppk') == $ppk->id_user ? 'selected' : '' }}>
                                {{ $ppk->nama }} &bull; {{ $ppk->jabatan ?? 'PPK PNJ' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Sistem membatasi penugasan PPK pada akun PPK definitif aktif.
                    </p>
                </div>

                {{-- Penugasan Pejabat Pengadaan (PP) --}}
                <div x-show="routing() === 'pp'">
                    <label for="id_user_pp" class="block text-xs font-bold text-slate-700 mb-1">
                        Pejabat Pengadaan (PP)
                        <span class="text-amber-600 font-semibold">(Jalur 50jt s.d 200jt)</span>
                    </label>
                    <select
                        id="id_user_pp"
                        name="id_user_pp"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="">-- Pilih Pejabat Pengadaan Aktif --</option>
                        @foreach($ppUsers as $pp)
                            <option value="{{ $pp->id_user }}" {{ old('id_user_pp') == $pp->id_user ? 'selected' : '' }}>
                                {{ $pp->nama }} &bull; Pejabat Pengadaan
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Form Submit Actions --}}
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a
                    href="{{ route('paket.index') }}"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm hover:shadow transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Daftarkan &amp; Terbitkan Paket Pengadaan
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
