@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Pengajuan Dokumen Pembayaran (PPBJ)</h1>
            <p class="text-sm text-zinc-600 mt-1">Unggah berkas penagihan setelah proses penerimaan barang selesai (FR 15.0)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-3">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Status Pemeriksaan Barang</h3>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-zinc-500 text-xs block">Status Barang:</span><span class="font-bold text-zinc-900">Barang Telah Diterima ({{ $pengadaan->status_penerimaan }})</span></div>
            <div><span class="text-zinc-500 text-xs block">Penerima BMN:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_penerima ?? '-' }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Nilai Tagihan Kontrak:</span><span class="font-bold text-base text-zinc-900">{{ $pengadaan->formatted_nilai_kontrak }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Penyedia:</span><span class="font-bold text-zinc-900">{{ $pengadaan->vendor->nama_perusahaan ?? '-' }}</span></div>
        </div>
    </div>

    <form method="POST" action="{{ route('ppbj.store-ajukan-pembayaran', $pengadaan) }}" enctype="multipart/form-data" class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-5">
        @csrf
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Kelengkapan Dokumen Tagihan</h3>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Unggah Bundel Dokumen Pembayaran (Faktur, Kuitansi, BAST) <span class="text-zinc-500">*</span></label>
            <input type="file" name="file_dokumen_pembayaran" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm bg-zinc-50">
            <p class="text-xs text-zinc-500 mt-1">Format PDF/JPG, maks 10MB.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Catatan Pengajuan</label>
            <textarea name="catatan_pengajuan_pembayaran" rows="3" placeholder="Contoh: Seluruh persyaratan BAST fisik, kuitansi bermaterai, dan faktur pajak telah diverifikasi lengkap." class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none"></textarea>
        </div>

        <div class="bg-zinc-100 p-4 rounded-lg border border-zinc-300 text-xs text-zinc-700">
            <strong>Alur Selanjutnya:</strong> Dokumen pembayaran akan diteruskan ke PPK untuk memo persetujuan, kemudian ke Wadir 2 untuk persetujuan akhir sebelum penerbitan SPP.
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3">
            <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Ajukan ke PPK & Wadir 2 &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
