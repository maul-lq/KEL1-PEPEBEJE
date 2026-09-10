@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Registrasi Pengadaan (PPBJ)</h1>
            <p class="text-sm text-zinc-600 mt-1">Mencatat memo resmi Wadir 2 dan mengarahkan jalur pengadaan (FR 7.0 & 8.0)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-3">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Informasi Berkas</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-zinc-500 text-xs block">Nomor Pengadaan:</span><span class="font-mono font-bold">{{ $pengadaan->nomor_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Mata Anggaran (MAK):</span><span class="font-mono font-bold">{{ $pengadaan->nomor_mak ?? '-' }}</span></div>
            <div class="col-span-2"><span class="text-zinc-500 text-xs block">Nama Pengadaan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Pagu Anggaran Disetujui:</span><span class="font-bold text-base text-zinc-900">{{ $pengadaan->formatted_pagu_anggaran }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Rekomendasi Jalur:</span><span class="font-bold text-zinc-900">{{ $pengadaan->jalur_label }}</span></div>
        </div>
    </div>

    <form method="POST" action="{{ route('ppbj.store-register', $pengadaan) }}" class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-5">
        @csrf
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Formulir Pencatatan Memo & Metode</h3>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Nomor Register Memo PPBJ <span class="text-zinc-500">*</span></label>
            <input type="text" name="nomor_memo_ppbj" value="{{ old('nomor_memo_ppbj', 'MEMO-PPBJ/' . date('Y/m/') . sprintf('%03d', rand(10, 99))) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 font-mono text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            <p class="text-xs text-zinc-500 mt-1">Nomor register internal buku kendali PBJ</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Metode Pengadaan <span class="text-zinc-500">*</span></label>
            <select name="metode_pengadaan" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                <option value="E-Katalog LKPP">E-Katalog LKPP (katalog.inaproc.id)</option>
                <option value="Pembelian Langsung">Pembelian Langsung (< Rp50 Juta)</option>
                <option value="Non-Tender / Pengadaan Langsung">Non-Tender / Pengadaan Langsung</option>
                <option value="Tender / Seleksi">Tender / Seleksi Terbuka</option>
            </select>
        </div>

        <div class="bg-zinc-100 p-4 rounded-lg border border-zinc-300 text-xs text-zinc-700 space-y-1">
            <div class="font-bold">Arah Jalur Dokumen (FR 8.0):</div>
            <div>Berkas ini secara otomatis dialirkan ke <strong>{{ $pengadaan->jalur_label }}</strong> untuk reviu spesifikasi teknis dan HPS sebelum penerbitan SPK.</div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3">
            <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Simpan & Teruskan ke PPK/PP &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
