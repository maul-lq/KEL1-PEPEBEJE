@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Data Rekanan & Legalitas Vendor</h1>
            <p class="text-sm text-zinc-600 mt-1">Pengelolaan data penyedia barang/jasa dan dokumen legalitas (FR 10.0)</p>
        </div>
        <a href="{{ route('vendor.create') }}" class="px-5 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs inline-flex items-center space-x-2">
            <span>+ Tambah Vendor Baru</span>
        </a>
    </div>

    <!-- LIST VENDOR -->
    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-100 text-zinc-700 font-bold border-b border-zinc-300 text-xs uppercase">
                        <th class="py-3 px-4">Nama Perusahaan</th>
                        <th class="py-3 px-4">NPWP & NIB</th>
                        <th class="py-3 px-4">Kontak Person</th>
                        <th class="py-3 px-4">Rekening Bank</th>
                        <th class="py-3 px-4">Total Kontrak</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse ($vendors as $v)
                        <tr class="hover:bg-zinc-50">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-zinc-900 text-base">{{ $v->nama_perusahaan }}</div>
                                <div class="text-xs text-zinc-500">{{ $v->alamat ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono">
                                <div>NPWP: {{ $v->npwp ?? '-' }}</div>
                                <div class="text-zinc-500">NIB: {{ $v->nib ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div class="font-bold text-zinc-800">{{ $v->nama_kontak ?? '-' }}</div>
                                <div class="text-zinc-500">{{ $v->telepon ?? '-' }} • {{ $v->email ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div class="font-bold text-zinc-800">{{ $v->nama_bank ?? '-' }}</div>
                                <div class="font-mono text-zinc-600">{{ $v->nomor_rekening ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-bold text-zinc-900">
                                {{ $v->pengadaans_count }} Pengadaan
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="{{ route('vendor.show', $v) }}" class="px-3.5 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 font-bold text-xs rounded">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-zinc-500 text-sm">Belum ada data rekanan vendor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
