@extends('layouts.app')

@section('title', 'Pemeriksaan Barang & Penerbitan BAST - Unit Perlengkapan PNJ')

@section('content')
<div x-data="{ lightboxImg: null }" class="space-y-6">

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="7" status="bast_terbit" />

    {{-- 2. Header --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200">
                        Siklus 7: Penerimaan &amp; BAST Fisik
                    </span>
                    <span class="text-xs text-slate-400 font-mono">FR 14 &bull; HMAC SHA-256</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Pemeriksaan Fisik Barang &amp; Penerbitan BAST</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Unit Pengelolaan Barang Milik Negara (BMN) dan Unit Perlengkapan Politeknik Negeri Jakarta
                </p>
            </div>

            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-50 text-teal-800 border border-teal-200 text-xs font-bold">
                    <span class="size-2 rounded-full bg-teal-600"></span>
                    Peran: Petugas Perlengkapan / BMN
                </span>
            </div>
        </div>
    </div>

    {{-- 3. Top 4-Card KPI Stat Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total BAST Terbit"
            :value="$bastList->total()"
            icon="document"
            color="navy"
            subtitle="Pemeriksaan fisik disahkan"
        />

        <x-stat-card
            title="Belanja Modal (Aset BMN)"
            :value="$bastList->where('kategori_belanja', 'belanja_modal')->count()"
            icon="box"
            color="blue"
            badge="BMN"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Dicatat sebagai aset tetap kampus"
        />

        <x-stat-card
            title="Belanja Barang (Persediaan)"
            :value="$bastList->where('kategori_belanja', 'belanja_barang')->count()"
            icon="check"
            color="teal"
            badge="Persediaan"
            badgeColor="bg-teal-50 text-teal-700 border-teal-200"
            subtitle="Barang habis pakai / operasional"
        />

        <x-stat-card
            title="Kontrak Siap Diperiksa"
            :value="$kontrakList->count()"
            icon="bell"
            color="amber"
            badge="Antrean"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="SPK dalam status pengiriman"
        />
    </div>

    {{-- 4. Formulir Pemeriksaan Barang & Penerbitan BAST (FR 14) --}}
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-6">
            <h2 class="text-base font-bold text-[#213153]">Formulir Pemeriksaan Fisik &amp; Pengesahan BAST</h2>
            <p class="text-xs text-slate-500 mt-0.5">Petugas melakukan verifikasi fisik item kiriman rekanan, multi-foto bukti, dan pengesahan tanda tangan digital HMAC SHA-256.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                <p class="font-bold mb-1">Periksa kembali input formulir:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('penerimaan.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="md:col-span-2">
                    <label for="nomor_kontrak_spk" class="block text-xs font-bold text-slate-700 mb-1">
                        Pilih Kontrak SPK yang Barang Kirimannya Siap Diperiksa <span class="text-rose-500">*</span>
                    </label>
                    <select id="nomor_kontrak_spk" name="nomor_kontrak_spk" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="">-- Pilih Kontrak SPK Hasil Pengadaan --</option>
                        @foreach($kontrakList as $k)
                            <option value="{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}">
                                [{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}] Paket: {{ $k->nomor_paket }} &bull; Nilai: Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }} ({{ $k->paket->vendor->nama_vendor ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="nomor_bast" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Berita Acara Serah Terima (BAST) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="nomor_bast"
                        name="nomor_bast"
                        value="{{ old('nomor_bast', 'BAST/PNJ/' . date('Y') . '/' . rand(100, 999)) }}"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                <div>
                    <label for="tanggal_terima" class="block text-xs font-bold text-slate-700 mb-1">
                        Tanggal Pemeriksaan Fisik Barang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        id="tanggal_terima"
                        name="tanggal_terima"
                        value="{{ old('tanggal_terima', date('Y-m-d')) }}"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                <div>
                    <label for="kondisi_barang" class="block text-xs font-bold text-slate-700 mb-1">
                        Status Keputusan Kondisi Fisik Barang <span class="text-rose-500">*</span>
                    </label>
                    <select id="kondisi_barang" name="kondisi_barang" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="terima">Diterima Lengkap (Sesuai Spesifikasi 100%)</option>
                        <option value="pending">Tertahan / Pending (Belum Lengkap / Perlu Pengujian)</option>
                        <option value="tidak_sesuai">Tidak Sesuai / Rusak (Retur ke Penyedia)</option>
                    </select>
                </div>

                <div>
                    <label for="kategori_belanja" class="block text-xs font-bold text-slate-700 mb-1">
                        Penetapan Kategori Belanja (Persediaan / Aset BMN) <span class="text-rose-500">*</span>
                    </label>
                    <select id="kategori_belanja" name="kategori_belanja" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="belanja_barang">Belanja Barang (Dicatat sebagai Persediaan Kampus)</option>
                        <option value="belanja_modal">Belanja Modal (Dicatat sebagai Aset Tetap BMN)</option>
                    </select>
                </div>

                <div>
                    <label for="foto_barang" class="block text-xs font-bold text-slate-700 mb-1">
                        Unggah Foto Dokumentasi Fisik Barang <span class="text-slate-400 font-normal">(Multi-Foto / Fleksibel)</span>
                    </label>
                    <input
                        type="file"
                        id="foto_barang"
                        name="foto_barang[]"
                        accept="image/*,.pdf"
                        multiple
                        class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da] hover:file:bg-blue-100"
                    />
                    <p class="text-[11px] text-slate-400 mt-1">Dapat memilih lebih dari satu foto untuk membuktikan kondisi fisik barang.</p>
                </div>

                <div>
                    <label for="file_penerimaan_pihak3" class="block text-xs font-bold text-slate-700 mb-1">
                        Dokumen Penerimaan Pihak Ketiga / Surat Jalan Ekspedisi
                    </label>
                    <input
                        type="file"
                        id="file_penerimaan_pihak3"
                        name="file_penerimaan_pihak3"
                        accept=".pdf,image/*"
                        class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da] hover:file:bg-blue-100"
                    />
                </div>

                <div class="md:col-span-2">
                    <label for="catatan_pemeriksaan" class="block text-xs font-bold text-slate-700 mb-1">
                        Catatan Hasil Pemeriksaan Fisik Petugas
                    </label>
                    <textarea
                        id="catatan_pemeriksaan"
                        name="catatan_pemeriksaan"
                        rows="2"
                        placeholder="Pemeriksaan fisik telah selesai dilakukan bersama teknisi, jumlah dan spesifikasi sesuai dengan SPK..."
                        class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    ></textarea>
                </div>

            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sahkan BAST &amp; Tanda Tangan Digital (HMAC SHA-256)
                </button>
            </div>
        </form>
    </div>

    {{-- 5. Tabel Daftar Dokumen BAST yang Telah Diterbitkan --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Daftar Berita Acara Serah Terima (BAST) Terbit</h3>
                <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ $bastList->count() }} dari {{ $bastList->total() }} total BAST resmi</p>
            </div>
        </div>

        @if($bastList->isEmpty())
            <div class="text-center py-12 px-4 text-xs text-slate-400">
                Belum ada dokumen BAST yang diterbitkan oleh Unit Perlengkapan.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3.5">Nomor BAST</th>
                            <th class="px-5 py-3.5">Paket Pengadaan</th>
                            <th class="px-5 py-3.5">Tanggal Terima</th>
                            <th class="px-5 py-3.5">Kondisi Fisik</th>
                            <th class="px-5 py-3.5">Kategori Belanja</th>
                            <th class="px-5 py-3.5">Dokumentasi Foto</th>
                            <th class="px-5 py-3.5">Token HMAC Digital</th>
                            <th class="px-5 py-3.5 text-center">Salinan PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($bastList as $b)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-5 py-4 font-mono font-bold text-[#0969da]">
                                    {{ $b->nomor_bast }}
                                </td>

                                <td class="px-5 py-4">
                                    <span class="font-mono font-semibold text-slate-800 block">{{ $b->nomor_paket }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono mt-0.5 block">{{ $b->nomor_kontrak_spk }}</span>
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    {{ $b->tanggal_penerimaan ? \Carbon\Carbon::parse($b->tanggal_penerimaan)->format('d/m/Y') : '-' }}
                                </td>

                                <td class="px-5 py-4">
                                    <x-status-badge :status="$b->status_fisik" size="sm" />
                                </td>

                                <td class="px-5 py-4">
                                    @if($b->kategori_belanja === 'belanja_modal')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                                            Aset BMN (Modal)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                            Persediaan (Barang)
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if($b->fotoDokumentasi && $b->fotoDokumentasi->isNotEmpty())
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            @foreach($b->fotoDokumentasi->take(3) as $foto)
                                                <button
                                                    type="button"
                                                    @click="lightboxImg = '{{ asset('storage/' . $foto->file_foto) }}'"
                                                    class="size-8 rounded-lg border border-slate-200 overflow-hidden hover:opacity-80 transition shrink-0"
                                                    title="Pratinjau Foto"
                                                >
                                                    <img src="{{ asset('storage/' . $foto->file_foto) }}" alt="Foto Barang" class="size-full object-cover">
                                                </button>
                                            @endforeach
                                            @if($b->fotoDokumentasi->count() > 3)
                                                <span class="text-[10px] text-slate-400 font-bold">+{{ $b->fotoDokumentasi->count() - 3 }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">Tanpa Foto</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    <code class="text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded font-mono">
                                        {{ substr($b->ttd_digital_bast ?? md5($b->nomor_bast), 0, 16) }}...
                                    </code>
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <a
                                        href="{{ route('dokumen.pdf', ['jenis' => 'bast', 'id' => $b->nomor_bast]) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                    >
                                        <svg class="size-3.5 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                        Unduh PDF
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100">
                {{ $bastList->links() }}
            </div>
        @endif
    </div>

    {{-- Lightbox Modal untuk Thumbnail Foto Barang --}}
    <div
        x-show="lightboxImg !== null"
        x-transition.opacity
        @click="lightboxImg = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4"
        style="display: none;"
    >
        <div @click.stop class="relative max-w-3xl rounded-2xl bg-white p-2 shadow-2xl overflow-hidden">
            <button
                type="button"
                @click="lightboxImg = null"
                class="absolute right-4 top-4 rounded-full bg-slate-900/60 p-1 text-white hover:bg-slate-900"
            >
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <img :src="lightboxImg" alt="Dokumentasi Fisik Barang" class="max-h-[80vh] w-auto rounded-xl object-contain">
        </div>
    </div>

</div>
@endsection
