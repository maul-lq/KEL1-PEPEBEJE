@extends('layouts.app')

@section('title', 'Permohonan Saya - SIM-PBJ')

@section('content')
<div x-data="{ modalOpen: false }" class="mx-auto max-w-[1750px]">

    {{-- HEADER & TOMBOL PERMOHONAN BARU --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-[32px] font-normal text-[#689EC2]">Permohonan Saya</h1>
        <button
            type="button"
            @click="modalOpen = true"
            class="h-10 rounded-[10px] bg-gradient-to-r from-[#C0D4DE] to-[#DDF4FF] px-[22px] text-base text-[#1E3156] shadow-[0_0_5px_rgba(0,0,0,0.25)] hover:brightness-95 transition-all"
        >
            + Permohonan Baru
        </button>
    </div>

    {{-- KARTU STATISTIK --}}
    <div class="mx-auto mt-10 grid max-w-[1650px] gap-6 md:grid-cols-3 md:gap-[8%]">
        <div class="relative h-[147px] rounded-[20px] bg-[#689EC2] p-6 text-white shadow-[inset_0_-4px_4px_#BAE5FF]">
            <div class="text-2xl font-normal">Permohonan berlangsung</div>
            <div class="absolute bottom-0 right-6 text-[64px] leading-[80px]">
                {{ $permohonanList->whereIn('status', ['diajukan', 'reviu_paralel', 'diproses'])->count() }}
            </div>
        </div>
        <div class="relative h-[147px] rounded-[20px] bg-[#689EC2] p-6 text-white shadow-[inset_0_-4px_4px_#BAE5FF]">
            <div class="text-2xl font-normal">Menunggu aksi saya</div>
            <div class="absolute bottom-0 right-6 text-[64px] leading-[80px]">
                {{ $permohonanList->where('status', 'diajukan')->count() }}
            </div>
        </div>
        <div class="relative h-[147px] rounded-[20px] bg-[#689EC2] p-6 text-white shadow-[inset_0_-4px_4px_#BAE5FF]">
            <div class="text-2xl font-normal">Selesai Bulan ini</div>
            <div class="absolute bottom-0 right-6 text-[64px] leading-[80px]">
                {{ $permohonanList->where('status', 'selesai')->count() }}
            </div>
        </div>
    </div>

    {{-- FILTER & PENCARIAN --}}
    <form action="{{ route('permohonan.index') }}" method="GET" class="mx-auto mt-[45px] grid max-w-[1650px] gap-6 md:grid-cols-3 md:gap-[8%]">
        <label class="block">
            <span class="mb-2 block text-2xl text-[#689EC2]">Cari</span>
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="No. Surat atau Judul..."
                class="h-12 w-full rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
            />
        </label>

        <label class="block">
            <span class="mb-2 block text-2xl text-[#689EC2]">Status</span>
            <div class="relative">
                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="h-12 w-full appearance-none rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
                >
                    <option value="">Semua</option>
                    <option value="diajukan" {{ request('status') == 'diajukan' ? 'selected' : '' }}>Menunggu aksi saya</option>
                    <option value="ditahan" {{ request('status') == 'ditahan' ? 'selected' : '' }}>Ditahan</option>
                    <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-black" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </div>
        </label>

        <div class="flex items-end gap-2">
            <label class="block flex-1">
                <span class="mb-2 block text-2xl text-[#689EC2]">Jurusan/unit/bidang</span>
                <select
                    name="unit"
                    onchange="this.form.submit()"
                    class="h-12 w-full appearance-none rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
                >
                    <option value="">Semua Unit</option>
                    @foreach($units as $u)
                        <option value="{{ $u->kode_unit }}" {{ request('unit') == $u->kode_unit ? 'selected' : '' }}>
                            {{ $u->nama_unit }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button
                type="submit"
                class="h-12 w-[91px] shrink-0 rounded-[10px] border border-[#1B5B7E] bg-[#BAE5FF] text-2xl text-black hover:brightness-95 transition-colors"
            >
                Cari
            </button>
        </div>
    </form>

    {{-- TABEL PERMOHONAN --}}
    <div class="mx-auto mt-10 max-w-[1650px] overflow-x-auto rounded-[20px] bg-white/40 px-6 pb-6 pt-6 shadow-sm">
        <table class="w-full min-w-[720px] text-left">
            <thead>
                <tr class="border-b border-[#689EC2] text-2xl text-[#1E3156]">
                    <th class="pb-2 pr-4 font-normal">No. Surat</th>
                    <th class="pb-2 pr-4 font-normal">Judul</th>
                    <th class="pb-2 pr-4 font-normal">Posisi</th>
                    <th class="pb-2 font-normal">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-sm text-[#1E3156] divide-y divide-slate-200/50">
                @forelse ($permohonanList as $item)
                    <tr class="h-[62px] align-middle hover:bg-white/40 transition-colors">
                        <td class="pr-4 font-medium">{{ $item->nomor_surat }}</td>
                        <td class="pr-4">
                            <div class="font-normal text-base">{{ $item->perihal }}</div>
                            <div class="text-xs text-slate-500">{{ $item->tanggal_pengajuan ? \Carbon\Carbon::parse($item->tanggal_pengajuan)->format('d/m/Y') : '-' }}</div>
                        </td>
                        <td class="pr-4">{{ ucfirst(str_replace('_', ' ', $item->status_permohonan ?? $item->status)) }}</td>
                        <td>
                            <a href="{{ route('permohonan.show', $item->nomor_surat) }}" class="text-[#1E3156] font-medium hover:underline hover:text-[#0088E8]">
                                Detail &amp; Lacak
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-lg text-[#689EC2]">
                            Tidak ada permohonan ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $permohonanList->links() }}
        </div>
    </div>

    {{-- MODAL PERMOHONAN BARU --}}
    <div
        x-show="modalOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/75 p-4"
        style="display: none;"
    >
        <form
            action="{{ route('permohonan.store') }}"
            method="POST"
            enctype="multipart/form-data"
            @click.away="modalOpen = false"
            class="relative w-full max-w-[981px] rounded-[20px] border-[10px] border-[#BAE5FF] bg-white bg-cover bg-center px-6 pb-8 pt-8 text-black sm:px-[47px] shadow-2xl"
            style="background-image: url('https://api.builder.io/api/v1/image/assets/TEMP/7bedcce2fdfe84f9c60cde0cd45be101bc8606a6?width=1962');"
        >
            @csrf
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h2 class="text-[32px] font-normal">Permohonan Baru</h2>
                <button
                    type="button"
                    aria-label="Tutup"
                    @click="modalOpen = false"
                    class="text-[#F00] hover:scale-110 transition-transform"
                >
                    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 grid gap-x-[59px] gap-y-3 sm:grid-cols-2">
                <label class="block">
                    <span class="block text-2xl mb-1">No. surat Srikandi</span>
                    <input
                        type="text"
                        name="nomor_surat"
                        required
                        class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                    />
                </label>
                <label class="block">
                    <span class="block text-2xl mb-1">Asal / unit pengusul</span>
                    <select
                        name="kode_unit"
                        required
                        class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                    >
                        @foreach($units as $u)
                            <option value="{{ $u->kode_unit }}" {{ Auth::user()->kode_unit == $u->kode_unit ? 'selected' : '' }}>
                                {{ $u->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="block sm:col-span-2">
                    <span class="block text-2xl mb-1">Judul permohonan</span>
                    <input
                        type="text"
                        name="perihal"
                        required
                        class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                    />
                </label>
                <label class="block sm:col-span-2">
                    <span class="block text-2xl mb-1">Estimasi Anggaran (Rp)</span>
                    <input
                        type="number"
                        name="anggaran_diajukan"
                        required
                        min="1"
                        class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                    />
                    <input type="hidden" name="tanggal_pengajuan" value="{{ date('Y-m-d') }}">
                </label>
                <div class="sm:col-span-2" x-data="{ fileName: '' }">
                    <span class="block text-2xl mb-1">Upload dokumen KAK / Spesifikasi (PDF)</span>
                    <label class="flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-[10px] border border-black bg-white hover:bg-slate-50 transition-colors">
                        <svg class="size-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span x-text="fileName ? fileName : 'Pilih File Surat / KAK...'" class="text-base text-gray-700"></span>
                        <input
                            type="file"
                            name="file_surat_permohonan"
                            accept="application/pdf"
                            @change="fileName = $event.target.files[0]?.name"
                            class="sr-only"
                        />
                    </label>
                </div>
            </div>

            <div class="mt-12 flex justify-center">
                <button
                    type="submit"
                    class="h-11 w-[183px] rounded-[10px] border border-[#1E3156] bg-[#AFDEFF] text-2xl text-[#1E3156] font-medium hover:brightness-95 transition-all"
                >
                    Kirim
                </button>
            </div>
        </form>
    </div>

</div>
@endsection