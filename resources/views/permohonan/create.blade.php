@extends('layouts.app')

@section('title', 'Ajukan Permohonan Pengadaan Baru - SIM-PBJ PNJ')

@section('content')
<div class="min-h-screen bg-[#F5F7FC] p-4 sm:p-6 lg:p-8" x-data="{ 
    nominal: '{{ old('anggaran_diajukan', '') }}',
    fileNameSurat: '',
    fileNameKak: '',
    fileNameSpek: '',
    isDraggingSurat: false,
    isDraggingKak: false,
    isDraggingSpek: false,
    handleDrop(event, field) {
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;
        const input = document.getElementById(field);
        if (input) {
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}">
    <div class="mx-auto max-w-5xl space-y-6">

        <!-- 1. HEADER HALAMAN, TOMBOL KEMBALI & BADGE STATUS -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3">
                <a href="{{ route('permohonan.index') }}" 
                   class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs hover:shadow-md transition-all duration-200 shrink-0"
                   title="Batal dan Kembali">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span class="hidden sm:inline">Batal dan Kembali</span>
                </a>
                <div>
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-[#213153] tracking-tight">
                        Ajukan Usulan Pengadaan Baru
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Formulir pendaftaran naskah dinas resmi Srikandi PNJ dengan lampiran Kerangka Acuan Kerja (KAK) dan Spesifikasi Teknis.
                    </p>
                </div>
            </div>

            <!-- Status Usulan Default -->
            <div class="flex items-center self-start sm:self-center shrink-0">
                <x-status-badge status="diajukan" size="md" />
            </div>
        </div>

        <!-- 2. CALLOUT NOTICE SRIKANDI (Figma 498:5218) DENGAN STYLE FRONTEND -->
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/80 p-4 md:p-5 text-xs text-blue-900 shadow-xs">
            <div class="flex items-start gap-3.5">
                <div class="size-8 rounded-xl bg-[#0969da] text-white flex items-center justify-center shrink-0 shadow-sm shadow-[#0969da]/20 mt-0.5">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="space-y-1">
                    <div class="text-sm font-bold text-[#213153]">Ketentuan Naskah Dinas Resmi Srikandi PNJ</div>
                    <p class="text-slate-600 leading-relaxed text-xs sm:text-sm">
                        Pastikan nomor surat yang didaftarkan sesuai dengan nomor registrasi naskah dinas keluar pada sistem <strong>Srikandi PNJ</strong>. 
                        Tersedia hingga 3 berkas lampiran utama (maksimal 5 berkas keseluruhan sistem, maksimal <strong>15 MB</strong> per berkas) untuk menjaga kinerja arsip digital.
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. ERROR MESSAGES VALIDASI -->
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-xs sm:text-sm text-rose-800 space-y-1.5 shadow-xs">
                <div class="font-bold flex items-center gap-2 text-rose-900">
                    <svg class="size-4.5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Terjadi kesalahan input data:
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700 pl-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- MAIN FORM CONTAINER CARD (Gaya Frontend-pemula dengan Logika Fullstuck) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            
            <form action="{{ route('permohonan.store') }}" 
                  method="POST" 
                  enctype="multipart/form-data" 
                  class="p-6 lg:p-8 space-y-8">
                @csrf

                <!-- SECTION 1: DATA SURAT & DOKUMEN PENDUKUNG -->
                <div class="space-y-6">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-lg font-bold text-[#213153]">Data Surat &amp; Dokumen Pendukung Pengadaan</h2>
                        <p class="text-xs text-slate-400">Lengkapi identitas naskah dinas resmi Srikandi dan perihal pengusulan pengadaan.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nomor Surat Resmi Srikandi PNJ -->
                        <div class="space-y-1.5">
                            <label for="nomor_surat" class="block text-sm font-semibold text-[#213153]">
                                Nomor Registrasi Surat Srikandi <span class="text-rose-600">*</span>
                            </label>
                            <input type="text" 
                                   id="nomor_surat" 
                                   name="nomor_surat" 
                                   value="{{ old('nomor_surat') }}" 
                                   placeholder="Contoh: B/123/PL3/TIK/2026" 
                                   required 
                                   class="w-full h-11 px-4 rounded-xl border @error('nomor_surat') border-rose-300 ring-2 ring-rose-100 @else border-slate-300 @enderror text-sm font-mono text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                            <p class="text-[11px] text-slate-400 italic">Nomor naskah dinas harus unik dan terdaftar di buku agenda keluar unit.</p>
                            @error('nomor_surat') 
                                <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                            @enderror
                        </div>

                        <!-- Estimasi Nilai Anggaran with Live Preview -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="anggaran_diajukan" class="block text-sm font-semibold text-[#213153]">
                                    Estimasi Nilai Anggaran Usulan (Rp) <span class="text-rose-600">*</span>
                                </label>
                                <span class="text-xs font-bold text-[#0969da] font-mono" 
                                      x-text="nominal ? 'Rp ' + Number(nominal).toLocaleString('id-ID') : 'Rp 0'"></span>
                            </div>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-sm font-bold text-slate-400">Rp</span>
                                <input type="number" 
                                       id="anggaran_diajukan" 
                                       name="anggaran_diajukan" 
                                       x-model="nominal"
                                       value="{{ old('anggaran_diajukan') }}" 
                                       step="0.01" 
                                       min="1" 
                                       placeholder="Contoh: 150000000" 
                                       required 
                                       class="w-full h-11 pl-11 pr-4 rounded-xl border @error('anggaran_diajukan') border-rose-300 ring-2 ring-rose-100 @else border-slate-300 @enderror text-sm font-mono text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                            </div>
                            <p class="text-[11px] text-slate-400 italic">Masukkan angka estimasi total tanpa titik atau koma (contoh: 150000000 untuk 150 juta).</p>
                            @error('anggaran_diajukan') 
                                <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                            @enderror
                        </div>
                    </div>

                    <!-- Perihal / Judul Kegiatan Pengadaan -->
                    <div class="space-y-1.5">
                        <label for="perihal" class="block text-sm font-semibold text-[#213153]">
                            Perihal / Judul Kegiatan Pengadaan <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" 
                               id="perihal" 
                               name="perihal" 
                               value="{{ old('perihal') }}" 
                               placeholder="Contoh: Pengadaan Perangkat Server dan Fasilitas Laboratorium Cloud AI" 
                               required 
                               class="w-full h-11 px-4 rounded-xl border @error('perihal') border-rose-300 ring-2 ring-rose-100 @else border-slate-300 @enderror text-sm text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                        @error('perihal') 
                            <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Uraian Singkat Latar Belakang & Justifikasi -->
                    <div class="space-y-1.5">
                        <label for="tujuan_pengadaan_ringkas" class="block text-sm font-semibold text-[#213153]">
                            Uraian Singkat Latar Belakang &amp; Tujuan Justifikasi
                        </label>
                        <textarea id="tujuan_pengadaan_ringkas" 
                                  name="tujuan_pengadaan_ringkas" 
                                  rows="3" 
                                  placeholder="Jelaskan kebutuhan pengadaan, urgensi capaian IKU/pembelajaran jurusan, dan justifikasi penggunaan anggaran..." 
                                  class="w-full p-4 rounded-xl border border-slate-300 text-sm text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">{{ old('tujuan_pengadaan_ringkas') }}</textarea>
                        <p class="text-[11px] text-slate-400 italic">Deskripsi ini akan ditelaah oleh Staf Bidang II dan Wakil Direktur II saat evaluasi disposisi.</p>
                        @error('tujuan_pengadaan_ringkas') 
                            <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Unit Kerja / Jurusan Pengusul (Otomatis dari Akun) -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-[#213153]">
                                Unit Kerja / Jurusan Pengusul
                            </label>
                            <input type="hidden" name="kode_unit" value="{{ Auth::user()->kode_unit ?? 'TIK' }}">
                            <input type="text" readonly disabled 
                                   value="{{ Auth::user()->unit ? '[' . Auth::user()->kode_unit . '] ' . Auth::user()->unit->nama_unit : (Auth::user()->kode_unit ?? 'Unit Pengusul') }}"
                                   class="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-100 text-sm font-semibold text-slate-700 cursor-not-allowed">
                        </div>

                        <!-- Tanggal Pengajuan Usulan -->
                        <div class="space-y-1.5">
                            <label for="tanggal_pengajuan" class="block text-sm font-semibold text-[#213153]">
                                Tanggal Pengajuan Usulan <span class="text-rose-600">*</span>
                            </label>
                            <input type="date" 
                                   id="tanggal_pengajuan" 
                                   name="tanggal_pengajuan" 
                                   value="{{ old('tanggal_pengajuan', date('Y-m-d')) }}" 
                                   required 
                                   class="w-full h-11 px-4 rounded-xl border border-slate-300 text-sm text-[#213153] focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                            @error('tanggal_pengajuan') 
                                <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: UNGGAH BERKAS DOKUMEN RESMI (PDF Only Srikandi) -->
                <div class="space-y-6 pt-4 border-t border-slate-100">
                    <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                        <div>
                            <h2 class="text-lg font-bold text-[#213153]">Dokumen Lampiran Pengadaan</h2>
                            <p class="text-xs text-slate-400">Unggah berkas resmi surat Srikandi, KAK bermeterai, dan rincian spesifikasi teknis format PDF.</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-[#0969da] border border-blue-200 w-fit">
                            Maksimal 5 file &bull; Maksimal 15 MB
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- File 1: Surat Permohonan Srikandi -->
                        <div class="space-y-2">
                            <label for="file_surat_permohonan" class="block text-xs font-bold text-[#213153]">
                                1. Surat Pengantar Srikandi (PDF)
                            </label>
                            <div @dragover.prevent="isDraggingSurat = true"
                                 @dragleave.prevent="isDraggingSurat = false"
                                 @drop.prevent="isDraggingSurat = false; handleDrop($event, 'file_surat_permohonan')"
                                 :class="isDraggingSurat ? 'border-[#0969da] bg-blue-50/80 scale-[0.99]' : 'border-slate-300 hover:border-[#0969da] bg-slate-50/50'"
                                 class="relative border-2 border-dashed rounded-xl p-4 text-center transition-all">
                                <input type="file" 
                                       id="file_surat_permohonan" 
                                       name="file_surat_permohonan" 
                                       accept=".pdf" 
                                       @change="fileNameSurat = $event.target.files[0] ? $event.target.files[0].name : ''"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <svg class="size-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <span class="text-xs font-semibold text-[#0969da] block" x-text="fileNameSurat ? fileNameSurat : 'Tarik & taruh atau Pilih Surat Pengantar (PDF)'"></span>
                                <span class="text-[10px] text-slate-400 block mt-1">Format PDF &bull; Maksimal 15 MB</span>
                            </div>
                            @error('file_surat_permohonan') 
                                <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                            @enderror
                        </div>

                        <!-- File 2: Kerangka Acuan Kerja (KAK) -->
                        <div class="space-y-2">
                            <label for="file_kak" class="block text-xs font-bold text-[#213153]">
                                2. Kerangka Acuan Kerja / KAK (PDF)
                            </label>
                            <div @dragover.prevent="isDraggingKak = true"
                                 @dragleave.prevent="isDraggingKak = false"
                                 @drop.prevent="isDraggingKak = false; handleDrop($event, 'file_kak')"
                                 :class="isDraggingKak ? 'border-[#0969da] bg-blue-50/80 scale-[0.99]' : 'border-slate-300 hover:border-[#0969da] bg-slate-50/50'"
                                 class="relative border-2 border-dashed rounded-xl p-4 text-center transition-all">
                                <input type="file" 
                                       id="file_kak" 
                                       name="file_kak" 
                                       accept=".pdf" 
                                       @change="fileNameKak = $event.target.files[0] ? $event.target.files[0].name : ''"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <svg class="size-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-xs font-semibold text-[#0969da] block" x-text="fileNameKak ? fileNameKak : 'Tarik & taruh atau Pilih Berkas KAK (PDF)'"></span>
                                <span class="text-[10px] text-slate-400 block mt-1">Format PDF &bull; Maksimal 15 MB</span>
                            </div>
                            @error('file_kak') 
                                <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                            @enderror
                        </div>

                        <!-- File 3: Spesifikasi Teknis & RAB -->
                        <div class="space-y-2">
                            <label for="file_spesifikasi_teknis" class="block text-xs font-bold text-[#213153]">
                                3. Spesifikasi Teknis &amp; RAB (PDF)
                            </label>
                            <div @dragover.prevent="isDraggingSpek = true"
                                 @dragleave.prevent="isDraggingSpek = false"
                                 @drop.prevent="isDraggingSpek = false; handleDrop($event, 'file_spesifikasi_teknis')"
                                 :class="isDraggingSpek ? 'border-[#0969da] bg-blue-50/80 scale-[0.99]' : 'border-slate-300 hover:border-[#0969da] bg-slate-50/50'"
                                 class="relative border-2 border-dashed rounded-xl p-4 text-center transition-all">
                                <input type="file" 
                                       id="file_spesifikasi_teknis" 
                                       name="file_spesifikasi_teknis" 
                                       accept=".pdf" 
                                       @change="fileNameSpek = $event.target.files[0] ? $event.target.files[0].name : ''"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <svg class="size-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <span class="text-xs font-semibold text-[#0969da] block" x-text="fileNameSpek ? fileNameSpek : 'Tarik & taruh atau Pilih Spesifikasi Teknis (PDF)'"></span>
                                <span class="text-[10px] text-slate-400 block mt-1">Format PDF &bull; Maksimal 15 MB</span>
                            </div>
                            @error('file_spesifikasi_teknis') 
                                <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> 
                            @enderror
                        </div>

                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-6 border-t border-slate-100">
                    <a href="{{ route('permohonan.index') }}" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 transition-all duration-200">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Batal dan Kembali</span>
                    </a>
                    <button type="submit" 
                            class="w-full sm:w-auto h-11 px-8 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-sm transition-colors shadow-sm shadow-[#0969da]/30 flex items-center justify-center gap-2">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Kirimkan Usulan Permohonan Pengadaan</span>
                    </button>
                </div>

            </form>

        </div>

    </div>
</div>

@endsection

                <!-- ACTION BUTTONS -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-6 border-t border-slate-100">
                    <a href="{{ route('permohonan.index') }}" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 transition-all duration-200">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Batal dan Kembali</span>
                    </a>
                    <button type="submit" 
                            class="w-full sm:w-auto h-11 px-8 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-sm transition-colors shadow-sm shadow-[#0969da]/30 flex items-center justify-center gap-2">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Kirimkan Usulan Permohonan Pengadaan</span>
                    </button>
                </div>

            </form>

        </div>

    </div>
</div>

@endsection