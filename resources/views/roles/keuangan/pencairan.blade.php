@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Pencairan Dana & Bukti Bayar (Keuangan)</h1>
            <p class="text-sm text-zinc-600 mt-1">Pencatatan transaksi perbankan/transfer dan penyelesaian status pengadaan (FR 18.0)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <!-- DETAIL KELENGKAPAN SPP -->
    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-3">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Kelengkapan Surat Perintah Pembayaran (SPP)</h3>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-zinc-500 text-xs block">Nomor SPP:</span><span class="font-mono font-bold">{{ $pengadaan->nomor_spp ?? '-' }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Tanggal SPP:</span><span class="font-bold text-zinc-900">{{ $pengadaan->tanggal_spp ? $pengadaan->tanggal_spp->format('d M Y') : '-' }}</span></div>
            <div class="col-span-2"><span class="text-zinc-500 text-xs block">Nama Pengadaan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Nilai Tagihan Kontrak:</span><span class="font-bold text-base text-zinc-900">{{ $pengadaan->formatted_nilai_kontrak }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Penerima Transfer / Rekening:</span>
                <span class="font-bold text-zinc-900 block">{{ $pengadaan->vendor->nama_perusahaan ?? '-' }}</span>
                <span class="text-xs text-zinc-600">{{ $pengadaan->vendor->nama_bank ?? 'Bank' }}: {{ $pengadaan->vendor->nomor_rekening ?? '-' }} (a.n {{ $pengadaan->vendor->nama_rekening ?? '-' }})</span>
            </div>
            <div><span class="text-zinc-500 text-xs block">Persetujuan Wadir 2:</span><span class="text-xs font-bold text-zinc-900">&#10003; ACC Cair</span></div>
            <div><span class="text-zinc-500 text-xs block">Persetujuan PPK:</span><span class="text-xs font-bold text-zinc-900">&#10003; ACC Pembayaran</span></div>
        </div>
    </div>

    <!-- FORM PENCAIRAN -->
    <form method="POST" action="{{ route('keuangan.store-pencairan', $pengadaan) }}" enctype="multipart/form-data" class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-5">
        @csrf
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Catatan Transaksi Pencairan</h3>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">
                Nomor Bukti Bayar / Referensi Transfer Bank / SP2D <span class="text-zinc-500">*</span>
            </label>
            <input type="text" name="nomor_bukti_bayar" value="{{ old('nomor_bukti_bayar', 'TRF-' . date('Ymd') . '-' . rand(10000, 99999)) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 font-mono text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            <p class="text-xs text-zinc-500 mt-1">Nomor transaksi unik perbankan/CMS atau SP2D KPPN</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Tanggal Realisasi Pembayaran <span class="text-zinc-500">*</span></label>
            <input type="date" name="tanggal_bayar" value="{{ old('tanggal_bayar', date('Y-m-d')) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Unggah Bukti Transfer / Resi Pembayaran (PDF / JPG)</label>
            <input type="file" name="file_bukti_bayar" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm bg-zinc-50">
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Catatan Keuangan</label>
            <textarea name="catatan_keuangan" rows="2" placeholder="Contoh: Pembayaran lunas ditransfer melalui rekening Bank Mandiri / Giro Pos." class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none"></textarea>
        </div>

        <div class="bg-zinc-100 p-4 rounded-lg border border-zinc-300 text-xs text-zinc-700">
            <strong>Konfirmasi Akhir:</strong> Setelah data bukti pembayaran ini disimpan, sistem akan secara otomatis menandai status pengadaan menjadi <strong>SELESAI</strong>.
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3">
            <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Selesaikan Pembayaran Pengadaan &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
