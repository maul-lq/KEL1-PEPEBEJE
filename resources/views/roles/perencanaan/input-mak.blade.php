@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Penetapan MAK & Pagu Anggaran</h1>
            <p class="text-sm text-zinc-600 mt-1">Bagian Perencanaan: Menentukan nomor MAK dan pagu anggaran (Persiapan integrasi SIGAP)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-3">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Informasi Permohonan</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-zinc-500 text-xs block">Nomor Pengadaan:</span><span class="font-mono font-bold">{{ $pengadaan->nomor_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Pemohon:</span><span class="font-bold">{{ $pengadaan->user->name }} ({{ $pengadaan->user->unit_kerja }})</span></div>
            <div class="col-span-2"><span class="text-zinc-500 text-xs block">Kegiatan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Estimasi Permohonan User:</span><span class="font-bold text-base text-zinc-900">{{ $pengadaan->formatted_estimasi_anggaran }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Disposisi Wadir 2:</span><span class="font-bold text-xs bg-zinc-100 px-2 py-1 rounded border border-zinc-300">Disetujui</span></div>
        </div>
    </div>

    <form method="POST" action="{{ route('perencanaan.store-mak', $pengadaan) }}" class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-5">
        @csrf
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Formulir Alokasi MAK (FR 6.0)</h3>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Nomor Mata Anggaran Kegiatan (MAK) <span class="text-zinc-500">*</span></label>
            <input type="text" name="nomor_mak" value="{{ old('nomor_mak', $pengadaan->nomor_mak) }}" required placeholder="Contoh: 2026.024.BHP.05.521811.TIK" class="w-full px-4 py-3 rounded-lg border border-zinc-300 font-mono text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            <p class="text-xs text-zinc-500 mt-1">Struktur MAK sesuai standar DIPA / aplikasi SIGAP kampus</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Pagu Anggaran Disetujui (Rp) <span class="text-zinc-500">*</span></label>
                <input type="number" name="pagu_anggaran" min="0" value="{{ old('pagu_anggaran', $pengadaan->pagu_anggaran ?? $pengadaan->estimasi_anggaran) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base font-bold focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Sumber Dana <span class="text-zinc-500">*</span></label>
                <select name="sumber_dana" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="RM" {{ old('sumber_dana', $pengadaan->sumber_dana) === 'RM' ? 'selected' : '' }}>Rupiah Murni (RM)</option>
                    <option value="PNBP" {{ old('sumber_dana', $pengadaan->sumber_dana) === 'PNBP' ? 'selected' : '' }}>PNBP</option>
                    <option value="BOPTN" {{ old('sumber_dana', $pengadaan->sumber_dana) === 'BOPTN' ? 'selected' : '' }}>BOPTN</option>
                    <option value="Kerjasama" {{ old('sumber_dana', $pengadaan->sumber_dana) === 'Kerjasama' ? 'selected' : '' }}>Kerjasama / Hibah</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Tanggal Penetapan MAK <span class="text-zinc-500">*</span></label>
            <input type="date" name="tanggal_mak" value="{{ old('tanggal_mak', date('Y-m-d')) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Catatan Tambahan Perencanaan</label>
            <textarea name="catatan_perencanaan" rows="2" placeholder="Catatan alokasi atau ketersediaan dana..." class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">{{ old('catatan_perencanaan') }}</textarea>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3">
            <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Tetapkan MAK & Teruskan ke PPBJ &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
