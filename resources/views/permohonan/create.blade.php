@extends('layouts.app')

@section('title', 'Ajukan Permohonan Pengadaan Baru - SIM-PBJ PNJ')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">

    <!-- 1. HEADER HALAMAN & TOMBOL KEMBALI -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('permohonan.index') }}" 
               class="inline-flex size-10 items-center justify-center rounded-xl border border-slate-200/80 bg-white text-slate-600 shadow-xs hover:bg-slate-50 hover:text-[#0969da] transition-all"
               title="Kembali ke Daftar Permohonan">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                    Ajukan Usulan Pengadaan Baru
                </h1>
                <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                    Formulir pendaftaran naskah dinas resmi Srikandi PNJ dengan lampiran KAK dan Spesifikasi Teknis.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-status-badge status="diajukan" size="md" />
        </div>
    </div>

    <!-- 2. CALLOUT NOTICE SRIKANDI (Figma 498:5218) -->
    <div class="rounded-2xl border border-blue-200/80 bg-blue-50/70 p-4 md:p-5 text-xs text-blue-900 shadow-xs">
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded-xl bg-[#0969da] text-white flex items-center justify-center shrink-0 shadow-sm shadow-[#0969da]/20 mt-0.5">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="space-y-1">
                <div class="text-sm font-bold text-[#213153]">Ketentuan Naskah Dinas Resmi Srikandi PNJ</div>
                <p class="text-slate-600 leading-relaxed">
                    Pastikan nomor surat yang didaftarkan sesuai dengan nomor registrasi naskah dinas keluar pada sistem <strong>Srikandi PNJ</strong>. 
                    Seluruh lampiran (Surat Pengantar, KAK, dan Spesifikasi Teknis) dibatasi maksimal <strong>10 MB</strong> per berkas untuk menjaga kinerja arsip digital.
                </p>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 space-y-1">
            <div class="font-bold flex items-center gap-2 text-rose-900">
                <svg class="size-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Periksa kembali data yang dimasukkan:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 3. FORMULIR KARTU UTAMA -->
    <form action="{{ route('permohonan.store') }}" 
          method="POST" 
          enctype="multipart/form-data" 
          x-data="{ nominal: '{{ old('anggaran_diajukan', '') }}' }"
          class="space-y-6">
        @csrf

        <!-- SECTION A: IDENTITAS SURAT DINAS & PENGUSUL -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 md:p-8 shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                <span class="size-7 rounded-lg bg-blue-100 text-[#0969da] flex items-center justify-center font-bold text-xs">1</span>
                <div>
                    <h2 class="text-base font-bold text-[#213153]">Identitas Surat & Unit Pengusul</h2>
                    <p class="text-xs text-slate-500">Nomor registrasi Srikandi dan identitas jurusan/bagian pengusul.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Nomor Surat -->
                <div class="space-y-1.5">
                    <label for="nomor_surat" class="block text-xs font-bold text-slate-700">
                        Nomor Registrasi Surat Srikandi <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           id="nomor_surat" 
                           name="nomor_surat" 
                           value="{{ old('nomor_surat') }}" 
                           required 
                           placeholder="Contoh: B/123/PL3/TIK/2026"
                           class="w-full rounded-xl border @error('nomor_surat') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-slate-50/50 px-4 py-2.5 text-xs font-mono font-medium text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <p class="text-[11px] text-slate-400">Nomor naskah dinas harus unik dan terdaftar di buku agenda keluar unit.</p>
                    @error('nomor_surat')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Unit Pengusul -->
                <div class="space-y-1.5">
                    <label for="kode_unit" class="block text-xs font-bold text-slate-700">
                        Unit Kerja / Jurusan Pengusul <span class="text-rose-500">*</span>
                    </label>
                    <select id="kode_unit" 
                            name="kode_unit" 
                            required
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs font-medium text-slate-800 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                        @foreach($units as $u)
                            <option value="{{ $u->kode_unit }}" {{ (old('kode_unit', Auth::user()->kode_unit) === $u->kode_unit) ? 'selected' : '' }}>
                                [{{ $u->kode_unit }}] {{ $u->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                    @error('kode_unit')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tanggal Pengajuan -->
                <div class="space-y-1.5">
                    <label for="tanggal_pengajuan" class="block text-xs font-bold text-slate-700">
                        Tanggal Pengajuan Usulan <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" 
                           id="tanggal_pengajuan" 
                           name="tanggal_pengajuan" 
                           value="{{ old('tanggal_pengajuan', date('Y-m-d')) }}" 
                           required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs font-medium text-slate-800 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    @error('tanggal_pengajuan')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Anggaran Diajukan with Live Preview -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="anggaran_diajukan" class="block text-xs font-bold text-slate-700">
                            Estimasi Anggaran Usulan (Rp) <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-xs font-bold text-[#0969da] font-mono" 
                              x-text="nominal ? 'Rp ' + Number(nominal).toLocaleString('id-ID') : 'Rp 0'"></span>
                    </div>
                    <input type="number" 
                           id="anggaran_diajukan" 
                           name="anggaran_diajukan" 
                           x-model="nominal"
                           step="0.01" 
                           min="1" 
                           required 
                           placeholder="Contoh: 150000000"
                           class="w-full rounded-xl border @error('anggaran_diajukan') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-slate-50/50 px-4 py-2.5 text-xs font-mono font-medium text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <p class="text-[11px] text-slate-400">Masukkan angka murni tanpa titik atau simbol (contoh: 150000000 untuk 150 juta).</p>
                    @error('anggaran_diajukan')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- SECTION B: DETAIL KEGIATAN & JUSTIFIKASI PENGADAAN -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 md:p-8 shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                <span class="size-7 rounded-lg bg-teal-100 text-[#087F79] flex items-center justify-center font-bold text-xs">2</span>
                <div>
                    <h2 class="text-base font-bold text-[#213153]">Detail Pengadaan &amp; Justifikasi Kebutuhan</h2>
                    <p class="text-xs text-slate-500">Perihal pengadaan barang/jasa dan uraian singkat latar belakang kegiatan.</p>
                </div>
            </div>

            <!-- Perihal / Judul Kegiatan -->
            <div class="space-y-1.5">
                <label for="perihal" class="block text-xs font-bold text-slate-700">
                    Perihal / Judul Kegiatan Pengadaan <span class="text-rose-500">*</span>
                </label>
                <input type="text" 
                       id="perihal" 
                       name="perihal" 
                       value="{{ old('perihal') }}" 
                       required 
                       placeholder="Contoh: Pengadaan Perangkat Server dan Fasilitas Laboratorium Cloud AI"
                       class="w-full rounded-xl border @error('perihal') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-slate-50/50 px-4 py-2.5 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                @error('perihal')
                    <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Tujuan Pengadaan Ringkas -->
            <div class="space-y-1.5">
                <label for="tujuan_pengadaan_ringkas" class="block text-xs font-bold text-slate-700">
                    Uraian Singkat Latar Belakang &amp; Tujuan Justifikasi
                </label>
                <textarea id="tujuan_pengadaan_ringkas" 
                          name="tujuan_pengadaan_ringkas" 
                          rows="3" 
                          placeholder="Jelaskan kebutuhan pengadaan, urgensi capaian IKU/pembelajaran jurusan, dan justifikasi penggunaan anggaran...">{{ old('tujuan_pengadaan_ringkas') }}</textarea>
                <p class="text-[11px] text-slate-400">Deskripsi ini akan ditelaah oleh Staf Bidang II dan Wakil Direktur II saat evaluasi disposisi.</p>
                @error('tujuan_pengadaan_ringkas')
                    <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- SECTION C: UNGGAH BERKAS LAMPIRAN PENDUKUNG -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 md:p-8 shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                <span class="size-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">3</span>
                <div>
                    <h2 class="text-base font-bold text-[#213153]">Dokumen Lampiran Pengadaan (Maks. 10 MB per berkas)</h2>
                    <p class="text-xs text-slate-500">Unggah berkas resmi Srikandi, KAK bermeterai, dan rincian spesifikasi teknis.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                <!-- 1. Berkas Surat Permohonan Srikandi -->
                <div x-data="{ fileName: '' }" class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700">
                        Surat Pengantar Srikandi (PDF)
                    </label>
                    <label class="relative flex flex-col items-center justify-center p-5 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 hover:bg-blue-50/30 hover:border-[#0969da]/50 cursor-pointer transition-all group">
                        <svg class="size-8 text-slate-400 group-hover:text-[#0969da] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <span class="mt-2 text-xs font-semibold text-slate-700 text-center">Pilih Berkas PDF</span>
                        <span class="text-[10px] text-slate-400 mt-0.5" x-text="fileName || 'Maks. 10 MB'"></span>
                        <input type="file" 
                               id="file_surat_permohonan" 
                               name="file_surat_permohonan" 
                               accept=".pdf"
                               @change="fileName = $event.target.files[0]?.name"
                               class="hidden">
                    </label>
                    @error('file_surat_permohonan')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 2. Kerangka Acuan Kerja (KAK) -->
                <div x-data="{ fileName: '' }" class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700">
                        Kerangka Acuan Kerja (KAK) (PDF)
                    </label>
                    <label class="relative flex flex-col items-center justify-center p-5 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 hover:bg-blue-50/30 hover:border-[#0969da]/50 cursor-pointer transition-all group">
                        <svg class="size-8 text-slate-400 group-hover:text-[#0969da] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="mt-2 text-xs font-semibold text-slate-700 text-center">Pilih Berkas KAK</span>
                        <span class="text-[10px] text-slate-400 mt-0.5" x-text="fileName || 'Format PDF &bull; Maks. 10 MB'"></span>
                        <input type="file" 
                               id="file_kak" 
                               name="file_kak" 
                               accept=".pdf"
                               @change="fileName = $event.target.files[0]?.name"
                               class="hidden">
                    </label>
                    @error('file_kak')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 3. Spesifikasi Teknis & Rincian Harga -->
                <div x-data="{ fileName: '' }" class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700">
                        Spesifikasi Teknis &amp; RAB (PDF)
                    </label>
                    <label class="relative flex flex-col items-center justify-center p-5 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 hover:bg-blue-50/30 hover:border-[#0969da]/50 cursor-pointer transition-all group">
                        <svg class="size-8 text-slate-400 group-hover:text-[#0969da] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span class="mt-2 text-xs font-semibold text-slate-700 text-center">Pilih Spesifikasi</span>
                        <span class="text-[10px] text-slate-400 mt-0.5" x-text="fileName || 'Format PDF &bull; Maks. 10 MB'"></span>
                        <input type="file" 
                               id="file_spesifikasi_teknis" 
                               name="file_spesifikasi_teknis" 
                               accept=".pdf"
                               @change="fileName = $event.target.files[0]?.name"
                               class="hidden">
                    </label>
                    @error('file_spesifikasi_teknis')
                        <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>

        <!-- 4. FOOTER ACTIONS -->
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4">
            <a href="{{ route('permohonan.index') }}" 
               class="w-full sm:w-auto text-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
                Batal dan Kembali
            </a>
            <button type="submit" 
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-[#0969da] px-7 py-3 text-xs font-bold text-white shadow-md shadow-[#0969da]/20 hover:bg-blue-700 transition-all">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Kirimkan Usulan Permohonan Pengadaan
            </button>
        </div>

    </form>

</div>
@endsection
