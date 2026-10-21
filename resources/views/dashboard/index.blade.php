@extends('layouts.app')

@section('title', 'Permohonan Saya - SIM-PBJ')

@section('content')
<div x-data="{ modalOpen: false }" class="space-y-8">

    {{-- HEADER KONTEN & TOMBOL TAMBAH --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-normal text-[#689EC2]">
            Permohonan Saya
        </h1>
        <button
            type="button"
            @click="modalOpen = true"
            class="h-10 rounded-[10px] bg-gradient-to-r from-[#C0D4DE] to-[#DDF4FF] px-[22px] text-base text-[#1E3156] shadow-[0_0_5px_rgba(0,0,0,0.25)] hover:brightness-95 transition-all"
        >
            + Permohonan Baru
        </button>
    </div>

    {{-- CARD STATISTIK --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="relative flex h-[147px] flex-col rounded-[20px] bg-[#689EC2] p-6 text-white shadow-[inset_0_-4px_4px_#BAE5FF]">
            <span class="text-2xl font-normal">Permohonan berlangsung</span>
            <span class="absolute bottom-4 right-6 text-[64px] leading-none">3</span>
        </div>
        <div class="relative flex h-[147px] flex-col rounded-[20px] bg-[#689EC2] p-6 text-white shadow-[inset_0_-4px_4px_#BAE5FF]">
            <span class="text-2xl font-normal">Menunggu aksi saya</span>
            <span class="absolute bottom-4 right-6 text-[64px] leading-none">3</span>
        </div>
        <div class="relative flex h-[147px] flex-col rounded-[20px] bg-[#689EC2] p-6 text-white shadow-[inset_0_-4px_4px_#BAE5FF]">
            <span class="text-2xl font-normal">Selesai Bulan ini</span>
            <span class="absolute bottom-4 right-6 text-[64px] leading-none">3</span>
        </div>
    </div>

    {{-- FORM FILTER & PENCARIAN --}}
    <form action="{{ route('permohonan.index') }}" method="GET" class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <label class="block">
            <span class="mb-2 block text-2xl text-[#689EC2]">Cari</span>
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="No. Surat atau Judul..."
                class="h-12 w-full rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
            />
        </label>

        <label class="block">
            <span class="mb-2 block text-2xl text-[#689EC2]">Status</span>
            <div class="relative">
                <select
                    name="status"
                    class="h-12 w-full appearance-none rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
                >
                    <option value="">Semua</option>
                    <option value="menunggu" {{ request('status') == 'menunggu' ? 'selected' : '' }}>Menunggu aksi saya</option>
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
                <input
                    type="text"
                    name="unit"
                    value="{{ request('unit') }}"
                    class="h-12 w-full rounded-[10px] border border-[#1B5B7E] bg-white px-4 text-lg text-black outline-none focus:ring-2 focus:ring-[#689EC2]"
                />
            </label>
            <button
                type="submit"
                class="h-12 w-[91px] rounded-[10px] border border-[#1B5B7E] bg-[#BAE5FF] text-2xl text-black transition-colors hover:bg-[#9cc7de]"
            >
                Cari
            </button>
        </div>
    </form>

    {{-- TABEL DAFTAR PERMOHONAN --}}
    <section class="overflow-x-auto rounded-[20px] bg-white/50 p-6 shadow-sm">
        <table class="w-full min-w-[720px] text-left">
            <thead>
                <tr class="border-b border-[#689EC2] text-2xl text-[#1E3156]">
                    <th class="pb-3 font-normal">No. Surat</th>
                    <th class="pb-3 font-normal">Judul</th>
                    <th class="pb-3 font-normal">Posisi</th>
                    <th class="pb-3 font-normal">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/60 text-sm text-[#1E3156]">
                {{-- Contoh Iterasi Data (Menggunakan variabel $permohonan dari Controller jika ada) --}}
                @forelse ($permohonan ?? range(1, 6) as $item)
                    <tr class="h-[62px] align-middle hover:bg-white/40 transition-colors">
                        <td class="pr-4 font-medium">123/456/789/0</td>
                        <td class="pr-4">
                            <div class="font-normal text-base">Pembelian Kipas 1,8T</div>
                            <div class="text-xs text-slate-500">26/08/2025</div>
                        </td>
                        <td class="pr-4">2/8 Persetujuan Bidang 2</td>
                        <td>
                            <a href="{{ route('permohonan.show', $item->id ?? 1) }}" class="text-[#1E3156] underline hover:text-[#3B82F6]">
                                Detail &amp; Lacak
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-lg text-[#689EC2]">
                            Tidak ada permohonan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- MODAL POP-UP (PERMOHONAN BARU) --}}
    <div
        x-show="modalOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 overflow-y-auto"
        style="display: none;"
    >
        <div
            @click.away="modalOpen = false"
            class="relative w-full max-w-[981px] rounded-[20px] border-[10px] border-[#BAE5FF] bg-white px-6 pb-8 pt-8 text-black sm:px-[47px] shadow-2xl"
            style="background-image: url('https://api.builder.io/api/v1/image/assets/TEMP/7bedcce2fdfe84f9c60cde0cd45be101bc8606a6?width=1962'); background-size: cover; background-position: center;"
        >
            {{-- Header Modal --}}
            <div class="flex items-center justify-between border-b pb-4 border-slate-200">
                <h2 class="text-[32px] font-normal text-black">Permohonan Baru</h2>
                <button
                    type="button"
                    @click="modalOpen = false"
                    aria-label="Tutup"
                    class="text-red-600 hover:text-red-800 transition-colors"
                >
                    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            {{-- Form Modal --}}
            <form action="{{ route('permohonan.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf

                <div class="grid grid-cols-1 gap-x-[59px] gap-y-4 sm:grid-cols-2">
                    <!-- No. Surat Srikandi -->
                    <label class="block">
                        <span class="block text-2xl mb-1">No. surat Srikandi</span>
                        <input
                            type="text"
                            name="no_surat"
                            required
                            class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                        />
                    </label>

                    <!-- Asal / Unit Pengusul -->
                    <label class="block">
                        <span class="block text-2xl mb-1">Asal / unit pengusul</span>
                        <input
                            type="text"
                            name="unit_pengusul"
                            value="{{ Auth::user()->unit->nama_unit ?? Auth::user()->kode_unit }}"
                            required
                            class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                        />
                    </label>

                    <!-- Judul Permohonan -->
                    <label class="block sm:col-span-2">
                        <span class="block text-2xl mb-1">Judul permohonan</span>
                        <input
                            type="text"
                            name="judul"
                            required
                            class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                        />
                    </label>

                    <!-- Tujuan / Kebutuhan -->
                    <label class="block sm:col-span-2">
                        <span class="block text-2xl mb-1">Tujuan / kebutuhan</span>
                        <input
                            type="text"
                            name="tujuan"
                            required
                            class="h-11 w-full rounded-[10px] border border-black bg-white px-3 text-base outline-none focus:ring-2 focus:ring-[#689EC2]"
                        />
                    </label>

                    <!-- Upload Dokumen -->
                    <div class="sm:col-span-2" x-data="{ fileName: '' }">
                        <span class="block text-2xl mb-1">Upload dokumen</span>
                        <label class="flex h-12 w-full cursor-pointer items-center justify-center gap-3 rounded-[10px] border border-black bg-white px-4 hover:bg-slate-50 transition-colors">
                            <svg class="size-7 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span x-text="fileName ? fileName : 'Pilih file dokumen...'" class="text-base text-gray-700 font-medium"></span>
                            <input
                                type="file"
                                name="dokumen"
                                required
                                @change="fileName = $event.target.files[0]?.name"
                                class="sr-only"
                            />
                        </label>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="pt-8 flex justify-center">
                    <button
                        type="submit"
                        class="h-11 w-[183px] rounded-[10px] border border-[#1E3156] bg-[#AFDEFF] text-2xl hover:brightness-95 transition-all"
                    >
                        Kirim
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection