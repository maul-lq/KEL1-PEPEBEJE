@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Tinjauan Permohonan Pengadaan (Wadir 2)</h1>
            <p class="text-sm text-zinc-600 mt-1">Disposisi dan keputusan persetujuan pimpinan untuk pengadaan barang & jasa</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Data Permohonan Pengadaan</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="text-zinc-500 block text-xs">No. Pengadaan:</span><span class="font-mono font-bold text-zinc-900">{{ $pengadaan->nomor_pengadaan }}</span></div>
            <div><span class="text-zinc-500 block text-xs">Pemohon / Unit:</span><span class="font-bold text-zinc-900">{{ $pengadaan->user->name }} ({{ $pengadaan->user->unit_kerja }})</span></div>
            <div><span class="text-zinc-500 block text-xs">Nama Kegiatan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</span></div>
            <div><span class="text-zinc-500 block text-xs">Estimasi Anggaran:</span><span class="font-bold text-zinc-900 text-base">{{ $pengadaan->formatted_estimasi_anggaran }}</span></div>
            <div><span class="text-zinc-500 block text-xs">Surat & Draft Srikandi:</span><span class="text-zinc-800">{{ $pengadaan->nomor_surat_user ?? '-' }} (Srikandi: {{ $pengadaan->no_draft_srikandi ?? '-' }})</span></div>
            <div><span class="text-zinc-500 block text-xs">Lampiran Surat:</span>
                @if($pengadaan->file_surat_permohonan)
                    <a href="{{ asset('storage/' . $pengadaan->file_surat_permohonan) }}" target="_blank" class="text-xs font-bold text-zinc-900 underline">Unduh Surat &darr;</a>
                @else
                    <span class="text-xs text-zinc-400">Tidak ada</span>
                @endif
            </div>
        </div>
        @if($pengadaan->latar_belakang)
            <div class="pt-2 border-t border-zinc-100">
                <span class="text-zinc-500 block text-xs mb-1">Latar Belakang / Urgensi:</span>
                <p class="text-sm text-zinc-700 bg-zinc-50 p-3 rounded-lg border border-zinc-200">{{ $pengadaan->latar_belakang }}</p>
            </div>
        @endif
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-6">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Keputusan Disposisi Wadir 2</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('wadir2.approve', $pengadaan) }}" class="space-y-4 p-5 rounded-xl border-2 border-zinc-900 bg-zinc-50">
                @csrf
                <div class="flex items-center space-x-2"><span class="w-3 h-3 rounded-full bg-zinc-900"></span><h4 class="font-extrabold text-base text-zinc-900">Opsi A: Setujui Permohonan</h4></div>
                <p class="text-xs text-zinc-600">Berkas otomatis diteruskan ke Bagian Perencanaan untuk pengisian MAK dan ke PPBJ untuk registrasi.</p>
                <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase mb-1">Catatan Persetujuan (Opsional)</label>
                    <textarea name="catatan_wadir2" rows="2" placeholder="Contoh: Disetujui untuk diproses sesuai pagu anggaran." class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-sm bg-white"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-sm cursor-pointer">
                    Setujui & Teruskan ke Perencanaan &rarr;
                </button>
            </form>

            <form method="POST" action="{{ route('wadir2.reject', $pengadaan) }}" class="space-y-4 p-5 rounded-xl border border-zinc-300 bg-zinc-50">
                @csrf
                <div class="flex items-center space-x-2"><span class="w-3 h-3 rounded-full bg-zinc-400"></span><h4 class="font-extrabold text-base text-zinc-800">Opsi B: Tolak Permohonan</h4></div>
                <p class="text-xs text-zinc-600">Permohonan akan ditolak dan dikembalikan ke pemohon beserta alasan penolakan.</p>
                <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase mb-1">Alasan Penolakan <span class="text-zinc-500">*</span></label>
                    <textarea name="catatan_wadir2" rows="2" required placeholder="Contoh: Belum dialokasikan dalam rencana kerja atau tidak memenuhi kriteria urgensi." class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-sm bg-white"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-zinc-200 hover:bg-zinc-300 text-zinc-900 font-bold text-sm rounded-lg border border-zinc-400 cursor-pointer">
                    Tolak Permohonan
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
