{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : View Blade ini rancang untuk halaman Penerimaan & Serah Terima Barang (Unit
              Perlengkapan & BMN) memuat KPI aset barang/modal, formulir inspeksi fisik dengan
              klasifikasi belanja & multi-foto dokumentasi, daftar BAST, dan lightbox pratinjau foto.
--}}
@extends('layouts.app')

@section('title', 'Penerimaan & Serah Terima Barang - Unit Perlengkapan PNJ')

{{--
   Seksi Konten Pemeriksaan Fisik & Penerbitan BAST (`penerimaan.index`).

  Tujuan: mengikat komponen `lightboxViewer` di level halaman dan memisahkan pilihan paket
          siap periksa antara jalur Kontrak (>50jt) serta Belanja Langsung (<=50jt).
  Parameter (Data dari PenerimaanBarangController::index):
    - `$bastList` (LengthAwarePaginator<PenerimaanBarang>), `$kontrakList` (Collection).
  Nilai kembali: Halaman HTML pemeriksaan barang, penerbitan BAST HMAC, dan galeri dokumentasi.
  Contoh pemanggilan: `return view('penerimaan.index', compact(...));`
--}}
@section('content')
<div x-data="lightboxViewer" class="space-y-6">

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="7" status="bast_terbit" />

    {{-- 2. Header --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200">
                        Siklus 7: Serah Terima Barang
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Penerimaan &amp; Serah Terima Barang</h1>
            </div>

            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-50 text-teal-800 border border-teal-200 text-xs font-bold">
                    <span class="size-2 rounded-full bg-teal-600"></span>
                    Peran: Unit Perlengkapan
                </span>
            </div>
        </div>
    </div>

    {{-- 3. Top 4-Card KPI Stat Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Serah Terima Terbit"
            :value="$bastList->total()"
            icon="document"
            color="navy"
            subtitle="Pemeriksaan barang disahkan"
        />

        <x-stat-card
            title="Belanja Modal (Aset Tetap)"
            :value="$bastList->where('kategori_belanja', 'belanja_modal')->count()"
            icon="box"
            color="blue"
            badge="Aset"
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
            subtitle="Kontrak dalam status pengiriman"
        />
    </div>

    {{-- 4. Formulir Pemeriksaan Barang & Serah Terima --}}
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-6">
            <h2 class="text-base font-bold text-[#213153]">Formulir Pemeriksaan &amp; Serah Terima Barang</h2>
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

                <div class="md:col-span-2 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Pilih Paket Pengadaan <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Opsi A: Kontrak (>50jt) --}}
                            <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-2">
                                <span class="text-xs font-bold text-[#213153] block">Opsi A: Paket dengan Kontrak (&gt; Rp 50 Juta)</span>
                                <select id="nomor_kontrak_spk" name="nomor_kontrak_spk" class="w-full h-10 rounded-lg border border-slate-200 text-xs text-slate-800 bg-white px-2 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                                    <option value="">-- Pilih Kontrak Kerja / Pesanan --</option>
                                    @foreach($kontrakList as $k)
                                        <option value="{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}">
                                            [{{ strtoupper($k->tipe_dokumen ?? 'SPK') }}: {{ $k->nomor_spk }}] Paket: {{ $k->nomor_paket }} &bull; Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Opsi B: Belanja Langsung (<=50jt) --}}
                            <div class="p-4 rounded-xl border border-teal-200 bg-teal-50/50 space-y-2">
                                <span class="text-xs font-bold text-[#087F79] block">Opsi B: Belanja Langsung (&le; Rp 50 Juta)</span>
                                <select id="nomor_paket" name="nomor_paket" class="w-full h-10 rounded-lg border border-teal-200 text-xs text-slate-800 bg-white px-2 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                                    <option value="">-- Pilih Paket Belanja Langsung --</option>
                                    @foreach($belanjaLangsungList as $bl)
                                        <option value="{{ $bl->nomor_paket }}">
                                            [{{ $bl->nomor_paket }}] Kwitansi: {{ $bl->nomor_kwitansi_belanja ?? '-' }} &bull; Rp {{ number_format($bl->nilai_hps, 0, ',', '.') }} ({{ $bl->vendor->nama_vendor ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">*Pilih salah satu: Opsi A (&gt; Rp 50 Juta dengan kontrak) atau Opsi B (&le; Rp 50 Juta belanja langsung).</p>
                    </div>
                </div>

                <div>
                    <label for="nomor_bast" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Bukti Serah Terima <span class="text-rose-500">*</span>
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
                        Tanggal Pemeriksaan Barang <span class="text-rose-500">*</span>
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
                    <label for="status_fisik" class="block text-xs font-bold text-slate-700 mb-1">
                        Kondisi Pemeriksaan Barang <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="status_fisik"
                        name="status_fisik"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="terima" {{ old('status_fisik', 'terima') === 'terima' ? 'selected' : '' }}>
                            Terima &mdash; Barang sesuai pesanan, siap dibayar
                        </option>
                        <option value="pending" {{ old('status_fisik') === 'pending' ? 'selected' : '' }}>
                            Pending &mdash; Belum lengkap / perlu perbaikan rekanan
                        </option>
                    </select>
                </div>

                <div>
                    <label for="kategori_belanja" class="block text-xs font-bold text-slate-700 mb-1">
                        Kategori Belanja (Persediaan / Aset) <span class="text-rose-500">*</span>
                    </label>
                    <select id="kategori_belanja" name="kategori_belanja" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="belanja_barang">Belanja Barang (Persediaan Kampus)</option>
                        <option value="belanja_modal">Belanja Modal (Aset Tetap Kampus)</option>
                    </select>
                </div>

                <div>
                    <label for="foto_barang" class="block text-xs font-bold text-slate-700 mb-1">
                        Unggah Foto Barang <span class="text-slate-400 font-normal">(Bisa Lebih dari Satu)</span>
                    </label>
                    <input
                        type="file"
                        id="foto_barang"
                        name="foto_barang[]"
                        accept="image/*,.pdf"
                        multiple
                        class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da] hover:file:bg-blue-100"
                    />
                </div>

                <div>
                    <label for="keterangan_foto" class="block text-xs font-bold text-slate-700 mb-1">
                        Keterangan Foto <span class="text-slate-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="text"
                        id="keterangan_foto"
                        name="keterangan_foto"
                        value="{{ old('keterangan_foto') }}"
                        placeholder="Contoh: Tampak depan item, label nomor seri, &amp; segel kemasan..."
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                <div>
                    <label for="file_bast_signed" class="block text-xs font-bold text-slate-700 mb-1">
                        Unggah Bukti Serah Terima Bertanda Tangan <span class="text-slate-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="file"
                        id="file_bast_signed"
                        name="file_bast_signed"
                        accept=".pdf,image/*"
                        class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da] hover:file:bg-blue-100"
                    />
                    <p class="text-[11px] text-slate-400 mt-1">Jika kosong, sistem membuat dokumen serah terima bertanda tangan digital secara otomatis.</p>
                </div>

                <div class="md:col-span-2">
                    <label for="file_penerimaan_pihak3" class="block text-xs font-bold text-slate-700 mb-1">
                        Surat Jalan / Bukti Pengiriman
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
                        Catatan Pemeriksaan Barang
                    </label>
                    <textarea
                        id="catatan_pemeriksaan"
                        name="catatan_pemeriksaan"
                        rows="2"
                        placeholder="Jumlah dan spesifikasi barang telah sesuai kontrak..."
                        class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    ></textarea>
                </div>

            </div>

            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-700">
                <div class="flex items-center gap-2">
                    <svg class="size-4 text-[#0969da] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span><strong>Waktu Pemeriksaan:</strong> Dicatat otomatis saat serah terima disahkan.</span>
                </div>
                <div class="flex items-center gap-1.5 font-mono text-[11px] text-[#0969da] font-semibold">
                    <svg class="size-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Tanda Tangan Digital Aktif</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-2"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span>Sahkan Serah Terima Barang</span>
                </button>
            </div>
        </form>
    </div>

    {{-- 5. Tabel Daftar Dokumen Serah Terima --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Daftar Dokumen Serah Terima Barang</h3>
                <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ $bastList->count() }} dari {{ $bastList->total() }} total serah terima</p>
            </div>
        </div>

        @if($bastList->isEmpty())
            <div class="text-center py-12 px-4 text-xs text-slate-400">
                Belum ada dokumen serah terima barang.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3.5">Nomor Serah Terima</th>
                            <th class="px-5 py-3.5">Paket Pengadaan</th>
                            <th class="px-5 py-3.5">Tanggal Terima</th>
                            <th class="px-5 py-3.5">Kondisi Fisik</th>
                            <th class="px-5 py-3.5">Kategori Belanja</th>
                            <th class="px-5 py-3.5">Dokumentasi Foto</th>
                            <th class="px-5 py-3.5">Kode Verifikasi</th>
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
                                    <span class="text-[11px] text-slate-400 font-mono mt-0.5 block">{{ $b->nomor_kontrak_spk ?: ($b->paket && $b->paket->nomor_kwitansi_belanja ? 'Kwitansi: ' . $b->paket->nomor_kwitansi_belanja : 'Belanja Langsung') }}</span>
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    <div class="font-medium text-slate-800">{{ $b->tanggal_penerimaan ? \Carbon\Carbon::parse($b->tanggal_penerimaan)->format('d/m/Y') : '-' }}</div>
                                    @if($b->waktu_selesai_inspeksi)
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5" title="Waktu Selesai Pemeriksaan Fisik">
                                            Selesai: {{ \Carbon\Carbon::parse($b->waktu_selesai_inspeksi)->format('H:i') }} WIB
                                        </div>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    <x-status-badge :status="$b->status_fisik" size="sm" />
                                </td>

                                <td class="px-5 py-4">
                                    @if($b->kategori_belanja === 'belanja_modal')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                                            Aset Tetap (Modal)
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
                                                @php $fotoPath = $foto->foto_dokumentasi ?? $foto->file_foto; @endphp
                                                <button
                                                    type="button"
                                                    @click="lightboxImg = '{{ asset('storage/' . $fotoPath) }}'"
                                                    class="size-8 rounded-lg border border-slate-200 overflow-hidden hover:opacity-80 transition shrink-0"
                                                    title="{{ $foto->keterangan_foto ?: 'Pratinjau Foto' }}"
                                                >
                                                    <img src="{{ asset('storage/' . $fotoPath) }}" alt="{{ $foto->keterangan_foto ?: 'Foto Barang' }}" class="size-full object-cover">
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
        x-cloak
        x-transition.opacity
        @click="lightboxImg = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4"
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
