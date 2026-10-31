@extends('layouts.app')

@section('title', 'Ajukan Permohonan Pengadaan Baru - SIM-PBJ PNJ')

@section('content')
<div class="min-h-screen bg-[#F5F7FC] p-6 lg:p-8" x-data="formPermohonanManager()">

    <!-- HEADER PAGE -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('permohonan.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-[#0969da] mb-2 transition-colors">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Batal dan Kembali
            </a>
            <h1 class="text-2xl md:text-3xl font-extrabold text-[#213153] tracking-tight">Pengajuan Usulan Permohonan Pengadaan Barang & Jasa</h1>
            <p class="text-sm text-slate-500 mt-1">
                Formulir ini digunakan oleh Unit Kerja / Jurusan untuk mendaftarkan usulan pengadaan berbasis surat dinas resmi Srikandi, dilengkapi dengan Kerangka Acuan Kerja (KAK) dan Spesifikasi Teknis.
            </p>
        </div>
    </div>

    <!-- ERROR MESSAGES VALIDASI -->
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <strong class="font-bold block mb-1">Terjadi kesalahan input:</strong>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- MAIN FORM CONTAINER CARD -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        
        <form action="{{ route('permohonan.store') }}" method="POST" enctype="multipart/form-data" class="p-6 lg:p-8 space-y-8">
            @csrf

            <!-- SECTION 1: DATA SURAT & DOKUMEN PENDUKUNG -->
            <div class="space-y-6">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-[#213153]">Data Surat & Dokumen Pendukung Pengadaan</h2>
                    <p class="text-xs text-slate-400">Lengkapi identitas naskah dinas resmi Srikandi dan perihal pengusulan pengadaan.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nomor Surat Resmi Srikandi PNJ -->
                    <div class="space-y-1.5">
                        <label for="nomor_surat" class="block text-sm font-semibold text-[#213153]">
                            Nomor Surat Resmi Srikandi PNJ <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" 
                               id="nomor_surat" 
                               name="nomor_surat" 
                               value="{{ old('nomor_surat') }}" 
                               placeholder="Contoh: B/123/PL3/TIK/2026" 
                               required 
                               class="w-full h-11 px-4 rounded-xl border border-slate-300 text-sm text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                        <p class="text-[11px] text-slate-400 italic">Nomor surat dicatat sesuai identitas registrasi naskah dinas Srikandi dan harus unik.</p>
                        @error('nomor_surat') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Estimasi Nilai Anggaran (anggaran_diajukan) -->
                    <div class="space-y-1.5">
                        <label for="anggaran_diajukan" class="block text-sm font-semibold text-[#213153]">
                            Estimasi Nilai Anggaran yang Diusulkan (Rp) <span class="text-rose-600">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-sm font-bold text-slate-400">Rp</span>
                            <input type="number" 
                                   id="anggaran_diajukan" 
                                   name="anggaran_diajukan" 
                                   value="{{ old('anggaran_diajukan') }}" 
                                   step="0.01" 
                                   min="1" 
                                   placeholder="Contoh: 150000000" 
                                   required 
                                   class="w-full h-11 pl-11 pr-4 rounded-xl border border-slate-300 text-sm text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                        </div>
                        <p class="text-[11px] text-slate-400 italic">Masukkan angka estimasi total tanpa titik atau koma (misal: 150000000 untuk 150 juta).</p>
                        @error('anggaran_diajukan') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Perihal / Judul Pengadaan (perihal) -->
                <div class="space-y-1.5">
                    <label for="perihal" class="block text-sm font-semibold text-[#213153]">
                        Perihal / Judul Pengadaan <span class="text-rose-600">*</span>
                    </label>
                    <input type="text" 
                           id="perihal" 
                           name="perihal" 
                           value="{{ old('perihal') }}" 
                           placeholder="Contoh: Pengadaan Perangkat Server dan Lab Cloud AI" 
                           required 
                           class="w-full h-11 px-4 rounded-xl border border-slate-300 text-sm text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                    @error('perihal') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Tujuan Pengadaan Secara Ringkas (tujuan_pengadaan_ringkas) -->
                <div class="space-y-1.5">
                    <label for="tujuan_pengadaan_ringkas" class="block text-sm font-semibold text-[#213153]">
                        Tujuan Pengadaan Secara Ringkas <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="tujuan_pengadaan_ringkas" 
                              name="tujuan_pengadaan_ringkas" 
                              rows="3" 
                              placeholder="Tuliskan tujuan dan latar belakang pengadaan secara ringkas..." 
                              required 
                              class="w-full p-4 rounded-xl border border-slate-300 text-sm text-[#213153] placeholder-slate-400 focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">{{ old('tujuan_pengadaan_ringkas') }}</textarea>
                    <p class="text-[11px] text-slate-400 italic">Sesuai ketentuan Srikandi dan kebutuhan justifikasi pengadaan institusi.</p>
                    @error('tujuan_pengadaan_ringkas') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Unit Kerja / Jurusan Pengusul (kode_unit dengan Dynamic Loop) -->
                    <div class="space-y-1.5">
                        <label for="kode_unit" class="block text-sm font-semibold text-[#213153]">
                            Unit Kerja / Jurusan Pengusul <span class="text-rose-600">*</span>
                        </label>
                        <select id="kode_unit" 
                                name="kode_unit" 
                                required
                                class="w-full h-11 px-4 rounded-xl border border-slate-300 text-sm text-[#213153] bg-white focus:ring-2 focus:ring-[#0969da]/20 focus:border-[#0969da] outline-none transition-all">
                            @foreach($units as $u)
                                <option value="{{ $u->kode_unit }}" {{ (Auth::user()->kode_unit === $u->kode_unit) ? 'selected' : '' }}>
                                    [{{ $u->kode_unit }}] {{ $u->nama_unit }}
                                </option>
                            @endforeach
                        </select>
                        @error('kode_unit') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Tanggal Pengajuan Usulan (tanggal_pengajuan) -->
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
                        @error('tanggal_pengajuan') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- SECTION 2: UNGGAH BERKAS DOKUMEN -->
            <div class="space-y-6 pt-4 border-t border-slate-100">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-[#213153]">Lampiran Berkas Dokumen (PDF / DOCX / XLSX)</h2>
                    <p class="text-xs text-slate-400">Unggah berkas kelengkapan administrasi permohonan pengadaan Anda.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- File 1: Surat Permohonan Srikandi (file_surat_permohonan) -->
                    <div class="space-y-2">
                        <label for="file_surat_permohonan" class="block text-xs font-bold text-[#213153]">
                            1. Surat Permohonan Srikandi (PDF)
                        </label>
                        <div class="relative border-2 border-dashed border-slate-300 hover:border-[#0969da] rounded-xl p-4 text-center transition-colors bg-slate-50/50">
                            <input type="file" 
                                   id="file_surat_permohonan" 
                                   name="file_surat_permohonan" 
                                   accept=".pdf" 
                                   @change="fileNameSurat = $event.target.files[0] ? $event.target.files[0].name : ''"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <svg class="size-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <span class="text-xs font-semibold text-[#0969da] block" x-text="fileNameSurat ? fileNameSurat : 'Pilih File Surat (PDF)'"></span>
                            <span class="text-[10px] text-slate-400 block mt-1">Format .pdf (Maks. 10 MB). Opsional jika file fisik menyusul.</span>
                        </div>
                        @error('file_surat_permohonan') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- File 2: KAK (file_kak) -->
                    <div class="space-y-2">
                        <label for="file_kak" class="block text-xs font-bold text-[#213153]">
                            2. Kerangka Acuan Kerja / KAK (PDF/DOCX)
                        </label>
                        <div class="relative border-2 border-dashed border-slate-300 hover:border-[#0969da] rounded-xl p-4 text-center transition-colors bg-slate-50/50">
                            <input type="file" 
                                   id="file_kak" 
                                   name="file_kak" 
                                   accept=".pdf,.doc,.docx" 
                                   @change="fileNameKak = $event.target.files[0] ? $event.target.files[0].name : ''"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <svg class="size-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <span class="text-xs font-semibold text-[#0969da] block" x-text="fileNameKak ? fileNameKak : 'Pilih File KAK'"></span>
                            <span class="text-[10px] text-slate-400 block mt-1">Format .pdf, .docx (Maksimal 10 MB)</span>
                        </div>
                        @error('file_kak') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- File 3: Spesifikasi Teknis (file_spesifikasi_teknis) -->
                    <div class="space-y-2">
                        <label for="file_spesifikasi_teknis" class="block text-xs font-bold text-[#213153]">
                            3. Spesifikasi Teknis & Rincian Harga (PDF/DOCX/XLSX)
                        </label>
                        <div class="relative border-2 border-dashed border-slate-300 hover:border-[#0969da] rounded-xl p-4 text-center transition-colors bg-slate-50/50">
                            <input type="file" 
                                   id="file_spesifikasi_teknis" 
                                   name="file_spesifikasi_teknis" 
                                   accept=".pdf,.doc,.docx,.xlsx" 
                                   @change="fileNameSpek = $event.target.files[0] ? $event.target.files[0].name : ''"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <svg class="size-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <span class="text-xs font-semibold text-[#0969da] block" x-text="fileNameSpek ? fileNameSpek : 'Pilih File Spek & RAB'"></span>
                            <span class="text-[10px] text-slate-400 block mt-1">Format .pdf, .docx, .xlsx (Maksimal 10 MB)</span>
                        </div>
                        @error('file_spesifikasi_teknis') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('permohonan.index') }}" class="h-11 px-6 rounded-xl border border-slate-300 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors flex items-center justify-center">
                    Batal dan Kembali
                </a>
                <button type="submit" class="h-11 px-8 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-sm transition-colors shadow-sm shadow-[#0969da]/30 flex items-center justify-center gap-2">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Kirimkan Usulan Permohonan Pengadaan
                </button>
            </div>

        </form>

    </div>

</div>

<script>
    function formPermohonanManager() {
        return {
            fileNameSurat: '',
            fileNameKak: '',
            fileNameSpek: ''
        };
    }
</script>
@endsection