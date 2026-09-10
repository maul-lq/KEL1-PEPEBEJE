@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Dashboard Monitoring Status Pengadaan</h1>
            <p class="text-sm text-zinc-600 mt-1">Pemantauan posisi berkas real-time untuk seluruh 7 Role (MAK, Reviu PPK, Penerimaan Barang, Pencairan)</p>
        </div>
        <div class="text-xs text-zinc-500 bg-zinc-100 px-3 py-2 rounded-lg border border-zinc-300 font-mono">
            FR 7.0 & 19.0 Terpenuhi
        </div>
    </div>

    <!-- FILTER PENCARIAN -->
    <div class="bg-white p-5 rounded-xl border border-zinc-300 shadow-xs">
        <form method="GET" action="{{ route('monitoring') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-4">
            <div class="sm:col-span-5">
                <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Cari Nama / No. Pengadaan / MAK / SPK</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik kata kunci..." 
                    class="w-full px-3.5 py-2.5 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Jalur Anggaran</label>
                <select name="jalur" class="w-full px-3.5 py-2.5 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="">-- Semua Jalur --</option>
                    <option value="pengadaan_langsung" {{ request('jalur') === 'pengadaan_langsung' ? 'selected' : '' }}>Pengadaan Langsung (< Rp50 Jt)</option>
                    <option value="pejabat_pengadaan" {{ request('jalur') === 'pejabat_pengadaan' ? 'selected' : '' }}>Pejabat Pengadaan (Rp50 - 200 Jt)</option>
                    <option value="pejabat_pembuat_komitmen" {{ request('jalur') === 'pejabat_pembuat_komitmen' ? 'selected' : '' }}>PPK (> Rp200 Jt)</option>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Status Posisi</label>
                <select name="status" class="w-full px-3.5 py-2.5 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="">-- Semua Status --</option>
                    <option value="diajukan" {{ request('status') === 'diajukan' ? 'selected' : '' }}>Menunggu Wadir 2</option>
                    <option value="disetujui_wadir2" {{ request('status') === 'disetujui_wadir2' ? 'selected' : '' }}>Penentuan MAK Perencanaan</option>
                    <option value="mak_ditetapkan" {{ request('status') === 'mak_ditetapkan' ? 'selected' : '' }}>Registrasi PPBJ</option>
                    <option value="teregistrasi_ppbj" {{ request('status') === 'teregistrasi_ppbj' ? 'selected' : '' }}>Reviu Dokumen PPK</option>
                    <option value="spk_diterbitkan" {{ request('status') === 'spk_diterbitkan' ? 'selected' : '' }}>Proses Vendor (SPK Terbit)</option>
                    <option value="barang_diterima" {{ request('status') === 'barang_diterima' ? 'selected' : '' }}>Penerimaan Barang Selesai</option>
                    <option value="sp_pembayaran_terbit" {{ request('status') === 'sp_pembayaran_terbit' ? 'selected' : '' }}>SPP Terbit (Pencairan Keuangan)</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai / Lunas</option>
                </select>
            </div>

            <div class="sm:col-span-1 flex items-end">
                <button type="submit" class="w-full py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- TABEL MONITORING LENGKAP -->
    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-900 text-white text-xs uppercase font-bold">
                        <th class="py-3.5 px-4">No. Pengadaan</th>
                        <th class="py-3.5 px-4">Nama Kegiatan & Pemohon</th>
                        <th class="py-3.5 px-4">Jalur & Nilai</th>
                        <th class="py-3.5 px-4">Mata Anggaran (MAK)</th>
                        <th class="py-3.5 px-4">Vendor & SPK</th>
                        <th class="py-3.5 px-4">Posisi Berkas Terkini</th>
                        <th class="py-3.5 px-4 text-center">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse ($pengadaans as $item)
                        <tr class="hover:bg-zinc-50 transition">
                            <td class="py-4 px-4 font-mono font-bold text-xs text-zinc-900 align-top">
                                {{ $item->nomor_pengadaan }}
                                <div class="text-[10px] text-zinc-400 font-sans font-normal mt-0.5">{{ $item->created_at->format('d/m/Y') }}</div>
                            </td>

                            <td class="py-4 px-4 align-top">
                                <div class="font-bold text-zinc-900 text-sm">{{ $item->nama_pengadaan }}</div>
                                <div class="text-xs text-zinc-600 mt-0.5">
                                    Pemohon: <strong>{{ $item->user->name }}</strong> ({{ $item->user->unit_kerja }})
                                </div>
                                @if($item->no_draft_srikandi)
                                    <div class="text-[11px] text-zinc-500 font-mono mt-0.5">Ref Srikandi: {{ $item->no_draft_srikandi }}</div>
                                @endif
                            </td>

                            <td class="py-4 px-4 align-top text-xs">
                                <div class="font-bold text-zinc-900">{{ $item->jalur_label }}</div>
                                <div class="text-zinc-600 mt-1">
                                    Est: <strong>{{ $item->formatted_estimasi_anggaran }}</strong>
                                </div>
                                @if($item->nilai_kontrak)
                                    <div class="text-zinc-900 font-bold mt-0.5">
                                        Kontrak: {{ $item->formatted_nilai_kontrak }}
                                    </div>
                                @endif
                            </td>

                            <td class="py-4 px-4 align-top text-xs">
                                @if($item->nomor_mak)
                                    <span class="font-mono font-bold text-zinc-800 bg-zinc-100 px-2 py-0.5 rounded border border-zinc-200 block truncate max-w-[180px]" title="{{ $item->nomor_mak }}">
                                        {{ $item->nomor_mak }}
                                    </span>
                                    <div class="text-[11px] text-zinc-500 mt-1">Sumber: {{ $item->sumber_dana ?? 'RM' }}</div>
                                @else
                                    <span class="text-zinc-400 italic">Belum ditentukan</span>
                                @endif
                            </td>

                            <td class="py-4 px-4 align-top text-xs">
                                @if($item->vendor)
                                    <div class="font-bold text-zinc-900">{{ $item->vendor->nama_perusahaan }}</div>
                                    <div class="text-[11px] text-zinc-500 mt-0.5">SPK: {{ $item->nomor_spk ?? '-' }}</div>
                                @else
                                    <span class="text-zinc-400 italic">Belum terbit SPK</span>
                                @endif
                            </td>

                            <td class="py-4 px-4 align-top">
                                <x-badge :status="$item->status" :label="$item->status_label" />
                                <div class="text-[11px] text-zinc-500 mt-1.5 font-medium">
                                    Tahap {{ $item->step_index }} / 8
                                </div>
                                @if($item->jenis_belanja)
                                    <div class="text-[10px] font-semibold text-zinc-700 bg-zinc-100 px-1.5 py-0.5 rounded border border-zinc-200 mt-1 inline-block">
                                        {{ $item->jenis_belanja }}
                                    </div>
                                @endif
                                @if($item->nama_penerima)
                                    <div class="text-[10px] text-zinc-600 mt-0.5">
                                        Penerima: {{ $item->nama_penerima }}
                                    </div>
                                @endif
                            </td>

                            <td class="py-4 px-4 text-center align-top">
                                <a href="{{ route('pengadaan.show', $item) }}" class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs rounded shadow-xs">
                                    Lacak
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-zinc-500">
                                Tidak ada data pengadaan yang sesuai dengan filter pencarian.
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
