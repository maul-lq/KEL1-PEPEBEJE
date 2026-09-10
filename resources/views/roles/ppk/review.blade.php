@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Reviu Dokumen Pengadaan (PPK / PP)</h1>
            <p class="text-sm text-zinc-600 mt-1">Memeriksa kelayakan dokumen spesifikasi teknis dan HPS yang dibuat PPBJ (FR 9.0)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Rangkuman Dokumen Pengadaan</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-zinc-500 text-xs block">Nomor Pengadaan:</span><span class="font-mono font-bold">{{ $pengadaan->nomor_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Jalur Routing:</span><span class="font-bold text-zinc-900">{{ $pengadaan->jalur_label }}</span></div>
            <div class="col-span-2"><span class="text-zinc-500 text-xs block">Nama Kegiatan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Pagu Anggaran (MAK):</span><span class="font-bold text-base text-zinc-900">{{ $pengadaan->formatted_pagu_anggaran }} ({{ $pengadaan->nomor_mak ?? '-' }})</span></div>
            <div><span class="text-zinc-500 text-xs block">Metode Pengadaan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->metode_pengadaan ?? '-' }}</span></div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-6">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Keputusan Reviu PPK / PP</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('ppk.approve', $pengadaan) }}" class="space-y-4 p-5 rounded-xl border-2 border-zinc-900 bg-zinc-50">
                @csrf
                <div class="flex items-center space-x-2"><span class="w-3 h-3 rounded-full bg-zinc-900"></span><h4 class="font-extrabold text-base text-zinc-900">Setujui Dokumen</h4></div>
                <p class="text-xs text-zinc-600">Dokumen disetujui. PPBJ dapat melanjutkan ke proses penerbitan Surat Kontrak / SPK.</p>
                <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase mb-1">Catatan Persetujuan</label>
                    <textarea name="catatan_ppk" rows="2" placeholder="Contoh: Dokumen spesifikasi teknis dan HPS pembanding telah sesuai." class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-sm bg-white"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-sm cursor-pointer">
                    Setujui & Siap Terbitkan SPK &rarr;
                </button>
            </form>

            <form method="POST" action="{{ route('ppk.revisi', $pengadaan) }}" class="space-y-4 p-5 rounded-xl border border-zinc-300 bg-zinc-50">
                @csrf
                <div class="flex items-center space-x-2"><span class="w-3 h-3 rounded-full bg-zinc-400"></span><h4 class="font-extrabold text-base text-zinc-800">Minta Perbaikan ke PPBJ</h4></div>
                <p class="text-xs text-zinc-600">Dokumen dikembalikan ke Koordinator PPBJ beserta catatan perbaikan yang harus disempurnakan.</p>
                <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase mb-1">Catatan Perbaikan <span class="text-zinc-500">*</span></label>
                    <textarea name="catatan_ppk" rows="2" required placeholder="Contoh: Lampirkan minimal 2 data pembanding harga pasar/e-katalog terbaru." class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-sm bg-white"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-zinc-200 hover:bg-zinc-300 text-zinc-900 font-bold text-sm rounded-lg border border-zinc-400 cursor-pointer">
                    Kembalikan ke PPBJ
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
