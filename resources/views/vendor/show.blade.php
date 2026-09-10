@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ $vendor->nama_perusahaan }}</h1>
            <p class="text-sm text-zinc-600 mt-1">Profil Penyedia / Rekanan Terdaftar</p>
        </div>
        <a href="{{ route('vendor.index') }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Informasi Legalitas & Rekening</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-zinc-500 text-xs block">NPWP:</span><span class="font-mono font-bold">{{ $vendor->npwp ?? '-' }}</span></div>
            <div><span class="text-zinc-500 text-xs block">NIB:</span><span class="font-mono font-bold">{{ $vendor->nib ?? '-' }}</span></div>
            <div class="col-span-2"><span class="text-zinc-500 text-xs block">Alamat:</span><span>{{ $vendor->alamat ?? '-' }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Kontak PIC:</span><span>{{ $vendor->nama_kontak ?? '-' }} ({{ $vendor->telepon ?? '-' }})</span></div>
            <div><span class="text-zinc-500 text-xs block">Rekening Bank:</span><span>{{ $vendor->nama_bank ?? '-' }}: {{ $vendor->nomor_rekening ?? '-' }} a.n {{ $vendor->nama_rekening ?? '-' }}</span></div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-200">
            <h3 class="text-base font-bold text-zinc-900">Riwayat Pengadaan oleh Vendor Ini ({{ $vendor->pengadaans->count() }})</h3>
        </div>
        <div class="divide-y divide-zinc-200">
            @forelse ($vendor->pengadaans as $p)
                <div class="p-4 flex items-center justify-between text-sm">
                    <div>
                        <span class="font-mono font-bold text-xs bg-zinc-100 px-2 py-0.5 rounded">{{ $p->nomor_pengadaan }}</span>
                        <h4 class="font-bold text-zinc-900 mt-1">{{ $p->nama_pengadaan }}</h4>
                        <div class="text-xs text-zinc-500">Kontrak: {{ $p->formatted_nilai_kontrak }} • SPK: {{ $p->nomor_spk ?? '-' }}</div>
                    </div>
                    <a href="{{ route('pengadaan.show', $p) }}" class="px-3 py-1.5 bg-zinc-900 text-white font-bold text-xs rounded">Lihat</a>
                </div>
            @empty
                <div class="p-6 text-center text-zinc-500 text-xs">Belum ada kontrak pengadaan dengan vendor ini.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
