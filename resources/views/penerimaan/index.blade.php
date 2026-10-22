@extends('layouts.app')

@section('title', 'Pemeriksaan Barang & Penerbitan BAST - Unit Perlengkapan PNJ')

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Pemeriksaan Fisik Barang &amp; Penerbitan BAST</h1>
        <p class="text-sm text-slate-600">
            <span class="font-bold text-sky-700">Unit Perlengkapan &amp; Pengelolaan Barang Milik Negara (BMN) Politeknik Negeri Jakarta.</span><br>
            Petugas melakukan inspeksi fisik barang kiriman penyedia, mendokumentasikan foto fisik (minimal 1 foto item terlihat jelas),
            menetapkan klasifikasi Belanja Modal (Aset BMN) atau Belanja Barang (Persediaan), serta mengesahkan Berita Acara Serah Terima (BAST) bertanda tangan digital HMAC SHA-256.
        </p>
    </div>

    {{-- 1. Formulir Pemeriksaan Barang & Penerbitan BAST (FR 14) --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-2 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>Formulir Pemeriksaan Fisik &amp; Pengesahan BAST</span>
            <span class="text-xs font-normal text-slate-500">Kewenangan: Unit Perlengkapan</span>
        </h2>

        <div class="p-3 bg-sky-50 border-l-4 border-sky-600 rounded text-sky-900 text-xs mb-4">
            <strong>Petunjuk Pemeriksaan Fisik (Sesuai Notulensi &amp; Ketentuan BAST):</strong>
            Pastikan seluruh barang yang diserahkan penyedia diperiksa bersama teknisi lab / pemohon. 
            Penetapan <strong>Belanja Modal (BMN)</strong> atau <strong>Belanja Barang (Persediaan)</strong> wajib dipilih sesuai mata anggaran untuk pencatatan inventaris kampus.
        </div>

        @if($errors->any())
            <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-700 rounded text-xs mb-4">
                <p class="font-bold">Mohon perbaiki kesalahan berikut:</p>
                <ul class="list-disc list-inside mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('penerimaan.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="nomor_kontrak_spk" class="block text-xs font-semibold text-slate-700 mb-1">
                        Pilih Kontrak SPK Aktif <span class="text-rose-500">*</span>
                    </label>
                    <select id="nomor_kontrak_spk" name="nomor_kontrak_spk" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Kontrak SPK Hasil Pengadaan --</option>
                        @foreach($kontrakList as $k)
                            <option value="{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}">
                                [{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}] Paket: {{ $k->nomor_paket }} &bull; Nilai: Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="nomor_bast" class="block text-xs font-semibold text-slate-700 mb-1">
                        Nomor Berita Acara Serah Terima (BAST) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nomor_bast" name="nomor_bast" value="BAST/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="tanggal_terima" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Pemeriksaan Fisik <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" id="tanggal_terima" name="tanggal_terima" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="kondisi_barang" class="block text-xs font-semibold text-slate-700 mb-1">
                        Status Keputusan Kondisi Fisik Barang <span class="text-rose-500">*</span>
                    </label>
                    <select id="kondisi_barang" name="kondisi_barang" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="terima">Diterima Lengkap (Sesuai Spesifikasi 100%)</option>
                        <option value="pending">Tertahan / Pending (Belum Lengkap / Perlu Pengujian Lanjutan)</option>
                        <option value="tidak_sesuai">Tidak Sesuai / Rusak (Retur Rekanan)</option>
                    </select>
                </div>

                <div>
                    <label for="kategori_belanja" class="block text-xs font-semibold text-slate-700 mb-1">
                        Penentuan Kategori Belanja (Persediaan / Aset BMN) <span class="text-rose-500">*</span>
                    </label>
                    <select id="kategori_belanja" name="kategori_belanja" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="belanja_barang">Belanja Barang (Dicatat sebagai Persediaan Kampus)</option>
                        <option value="belanja_modal">Belanja Modal (Dicatat sebagai Aset BMN)</option>
                    </select>
                </div>

                <div>
                    <label for="foto_barang" class="block text-xs font-semibold text-slate-700 mb-1">
                        Unggah Foto Dokumentasi Fisik Barang <span class="text-slate-400 font-normal">(Dapat lebih dari 1 file)</span>
                    </label>
                    <input type="file" id="foto_barang" name="foto_barang[]" accept="image/*,.pdf" multiple class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                    <p class="text-[11px] text-slate-500 mt-1">Jumlah foto fleksibel, pastikan kondisi item barang terlihat jelas.</p>
                </div>

                <div>
                    <label for="file_penerimaan_pihak3" class="block text-xs font-semibold text-slate-700 mb-1">
                        Dokumen Penerimaan Pihak Ketiga / Ekspedisi <span class="text-slate-400 font-normal">(Opsional)</span>
                    </label>
                    <input type="file" id="file_penerimaan_pihak3" name="file_penerimaan_pihak3" accept=".pdf,image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                </div>

                <div class="md:col-span-2">
                    <label for="catatan_pemeriksaan" class="block text-xs font-semibold text-slate-700 mb-1">
                        Catatan Hasil Pemeriksaan Fisik
                    </label>
                    <textarea id="catatan_pemeriksaan" name="catatan_pemeriksaan" rows="2" placeholder="Pemeriksaan fisik telah selesai dilakukan, kuantitas dan kelengkapan sesuai dengan kontrak..." class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-6 py-2.5 bg-sky-700 text-white rounded text-xs font-bold hover:bg-sky-800 transition shadow">
                    Sahkan BAST &amp; Tanda Tangan Digital (HMAC SHA-256)
                </button>
            </div>
        </form>
    </div>

    {{-- 2. Daftar Dokumen BAST yang Telah Diterbitkan --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>Daftar Berita Acara Serah Terima (BAST) Terbit</span>
            <span class="text-xs font-normal text-slate-500">Total: {{ $bastList->total() }} BAST</span>
        </h2>

        @if($bastList->isEmpty())
            <div class="text-center py-6 bg-slate-50 rounded border border-dashed border-slate-200">
                <p class="text-sm text-slate-500">Belum ada dokumen BAST yang diterbitkan.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold uppercase tracking-wider">
                            <th class="p-3 border-b">Nomor BAST</th>
                            <th class="p-3 border-b">Nomor Paket</th>
                            <th class="p-3 border-b">Tanggal Terima</th>
                            <th class="p-3 border-b">Status Fisik</th>
                            <th class="p-3 border-b">Kategori Belanja</th>
                            <th class="p-3 border-b">Tanda Tangan Digital (HMAC)</th>
                            <th class="p-3 border-b text-center">Salinan Dokumen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($bastList as $b)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-mono font-bold text-sky-800">{{ $b->nomor_bast }}</td>
                                <td class="p-3 font-mono text-slate-700">{{ $b->nomor_paket }}</td>
                                <td class="p-3 text-slate-600">{{ $b->tanggal_penerimaan ? $b->tanggal_penerimaan->format('d/m/Y') : '-' }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold 
                                        {{ $b->status_fisik === 'terima' ? 'bg-emerald-100 text-emerald-800' : ($b->status_fisik === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                        {{ strtoupper($b->status_fisik) }}
                                    </span>
                                </td>
                                <td class="p-3 font-semibold text-slate-700">
                                    {{ $b->kategori_belanja === 'belanja_modal' ? 'BELANJA MODAL (BMN)' : 'BELANJA BARANG (PERSEDIAAN)' }}
                                </td>
                                <td class="p-3">
                                    <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded font-mono text-slate-700">
                                        {{ substr($b->ttd_digital_bast ?? md5($b->nomor_bast), 0, 18) }}...
                                    </code>
                                </td>
                                <td class="p-3 text-center">
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'bast', 'id' => $b->nomor_bast]) }}" class="inline-block px-3 py-1 bg-slate-700 text-white rounded hover:bg-slate-800 transition font-semibold text-xs">
                                        Unduh BAST (PDF)
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $bastList->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
