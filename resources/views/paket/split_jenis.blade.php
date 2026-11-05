@extends('layouts.app')

@section('title', 'Pemecahan Jenis Pengadaan (Split Jenis) - SIM-PBJ PNJ')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                        Feature 24 &bull; FR 7.2
                    </span>
                </div>
                <h1 class="text-xl font-bold text-[#213153] mt-1">Pemecahan Jenis Pengadaan (Split Jenis)</h1>
                <p class="text-xs text-slate-500 mt-0.5">Memecah usulan pengadaan berdasarkan jenis pengadaan (Barang / Jasa / Konstruksi)</p>
            </div>
            <a href="{{ route('paket.index') }}" class="px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                &larr; Kembali
            </a>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('paket.split-jenis') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="nomor_surat_induk" class="block text-xs font-bold text-slate-700 mb-1">
                    Surat Permohonan Induk Disetujui <span class="text-rose-500">*</span>
                </label>
                <select id="nomor_surat_induk" name="nomor_surat_induk" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    <option value="">-- Pilih Berkas Permohonan Induk --</option>
                    @foreach($permohonanDisetujui as $p)
                        <option value="{{ $p->nomor_surat }}" {{ old('nomor_surat_induk') == $p->nomor_surat ? 'selected' : '' }}>
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} (Pagu: Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nomor_surat_baru" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Surat Baru Hasil Split <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nomor_surat_baru" name="nomor_surat_baru" value="{{ old('nomor_surat_baru') }}" required placeholder="Contoh: 123/PNJ/2026.PBJ.1" class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div>
                    <label for="jenis_pengadaan_split" class="block text-xs font-bold text-slate-700 mb-1">
                        Jenis Pengadaan Pecahan <span class="text-rose-500">*</span>
                    </label>
                    <select id="jenis_pengadaan_split" name="jenis_pengadaan_split" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="barang">Pengadaan Barang</option>
                        <option value="jasa">Pengadaan Jasa Lainnya</option>
                        <option value="konstruksi">Pekerjaan Konstruksi</option>
                        <option value="jasa_konsultansi">Jasa Konsultansi</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="judul_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                    Judul Pengadaan Hasil Pemecahan <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="judul_pengadaan" name="judul_pengadaan" value="{{ old('judul_pengadaan') }}" required placeholder="Contoh: Pengadaan Komputer Server AI (Pecahan Barang)" class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <div>
                <label for="estimasi_total_pagu" class="block text-xs font-bold text-slate-700 mb-1">
                    Nominal Pagu Pecahan (Rupiah) <span class="text-rose-500">*</span>
                </label>
                <input type="number" id="estimasi_total_pagu" name="estimasi_total_pagu" value="{{ old('estimasi_total_pagu') }}" step="0.01" min="1" required placeholder="Contoh: 45000000" class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <div>
                <label for="alasan_pemecahan" class="block text-xs font-bold text-slate-700 mb-1">
                    Alasan Pemecahan Jenis Pengadaan <span class="text-rose-500">*</span>
                </label>
                <textarea id="alasan_pemecahan" name="alasan_pemecahan" rows="3" required placeholder="Jelaskan kebutuhan pemecahan berkas..." class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">{{ old('alasan_pemecahan') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('paket.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                    Proses Pemecahan Berkas
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
