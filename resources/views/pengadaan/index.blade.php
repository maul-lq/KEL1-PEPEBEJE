@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Daftar Pengadaan Barang & Jasa</h1>
            <p class="text-sm text-zinc-600 mt-1">Kelola dan pantau seluruh permohonan pengadaan barang & jasa kampus</p>
        </div>
        <div>
            @if(Auth::user()->isRole('user', 'ppbj'))
                <a href="{{ route('pengadaan.create') }}" class="px-5 py-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-xs transition inline-flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Ajukan Permohonan Baru</span>
                </a>
            @endif
        </div>
    </div>

    <!-- FILTER & PENCARIAN -->
    <div class="bg-white p-5 rounded-xl border border-zinc-300 shadow-xs">
        <form method="GET" action="{{ route('pengadaan.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-4">
            <div class="sm:col-span-6">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama pengadaan, nomor surat, nomor pengadaan..." 
                    class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>

            <div class="sm:col-span-4">
                <select name="status" class="w-full px-3.5 py-2.5 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="">-- Semua Status --</option>
                    @foreach ($statuses as $code => $label)
                        <option value="{{ $code }}" {{ request('status') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" class="w-full py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg">
                    Cari
                </button>
            </div>
        </form>
    </div>

    <!-- LIST PENGADAAN -->
    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-100 text-zinc-700 font-bold border-b border-zinc-300 text-xs uppercase">
                        <th class="py-3 px-4">No. Pengadaan</th>
                        <th class="py-3 px-4">Nama Pengadaan & Keperluan</th>
                        <th class="py-3 px-4">Pemohon</th>
                        <th class="py-3 px-4">Jalur & Estimasi</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse ($pengadaans as $pengadaan)
                        <tr class="hover:bg-zinc-50 transition">
                            <td class="py-4 px-4 font-mono font-bold text-xs text-zinc-900 align-top">
                                {{ $pengadaan->nomor_pengadaan }}
                                <div class="text-[11px] text-zinc-500 font-sans font-normal mt-0.5">{{ $pengadaan->created_at->format('d M Y') }}</div>
                            </td>

                            <td class="py-4 px-4 align-top">
                                <div class="font-bold text-zinc-900 text-base">{{ $pengadaan->nama_pengadaan }}</div>
                                <div class="text-xs text-zinc-500 mt-0.5">
                                    Jenis: <strong>{{ $pengadaan->jenis_pengadaan }}</strong> • Surat: {{ $pengadaan->nomor_surat_user ?? '-' }}
                                </div>
                                @if($pengadaan->no_draft_srikandi)
                                    <div class="text-[11px] text-zinc-500 font-mono mt-0.5">Ref Srikandi: {{ $pengadaan->no_draft_srikandi }}</div>
                                @endif
                            </td>

                            <td class="py-4 px-4 align-top text-xs">
                                <div class="font-bold text-zinc-900">{{ $pengadaan->user->name }}</div>
                                <div class="text-zinc-500">{{ $pengadaan->user->unit_kerja }}</div>
                            </td>

                            <td class="py-4 px-4 align-top text-xs">
                                <div class="font-medium text-zinc-700">{{ $pengadaan->jalur_label }}</div>
                                <div class="font-bold text-zinc-900 mt-0.5">{{ $pengadaan->formatted_estimasi_anggaran }}</div>
                            </td>

                            <td class="py-4 px-4 align-top">
                                <x-badge :status="$pengadaan->status" :label="$pengadaan->status_label" />
                            </td>

                            <td class="py-4 px-4 text-center align-top">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-3.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs rounded">
                                        Lihat
                                    </a>
                                    <a href="{{ route('pengadaan.cetak', $pengadaan) }}" target="_blank" class="px-2.5 py-1.5 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 font-bold text-xs rounded" title="Cetak Berkas">
                                        &#128438;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-zinc-500">
                                Tidak ada data pengadaan yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-zinc-200">
            {{ $pengadaans->links() }}
        </div>
    </div>

</div>
@endsection
