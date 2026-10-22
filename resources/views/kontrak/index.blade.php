@extends('layouts.app')

@section('title', 'Manajemen Penelaahan Dokumen & Kontrak SPK - SIM-PBJ PNJ')

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Penelaahan Dokumen &amp; Penerbitan Kontrak SPK</h1>
        <p class="text-sm text-slate-600">
            @if(Auth::user()->role === 'ppk')
                <span class="font-bold text-purple-700">Peran: Pejabat Pembuat Komitmen (PPK).</span>
                Meninjau dokumen spesifikasi teknis/HPS, mengunci Kode MAK, serta mengeksekusi pembelian barang/jasa pada paket yang ditugaskan kepada akun Anda (<strong>{{ Auth::user()->nama }}</strong>).
                <em>Paket milik akun PPK lain diisolasi demi kepatuhan integritas pengadaan.</em>
            @elseif(Auth::user()->role === 'pp')
                <span class="font-bold text-amber-700">Peran: Pejabat Pengadaan (PP).</span>
                Melakukan verifikasi dokumen teknis, negosiasi harga, dan menyusun Berita Acara hasil pengadaan untuk paket bernilai &gt; Rp 50 Juta s.d &lt; Rp 200 Juta sebelum diteruskan ke PPK.
            @else
                <span class="font-bold text-sky-700">Peran: Unit Pengadaan Barang dan Jasa (PPBJ).</span>
                Menerbitkan Kontrak / Surat Perintah Kerja (SPK), menetapkan jadwal pengingat (Reminder) pengiriman barang, serta mendistribusikan tembusan otomatis ke Perlengkapan dan Keuangan.
            @endif
        </p>
    </div>

    {{-- 1. Penelaahan (Reviu) Dokumen Teknis & Kelayakan HPS (FR 9) --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-2 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>1. Penelaahan (Reviu) Dokumen Teknis &amp; Kelayakan HPS</span>
            <span class="text-xs font-normal text-slate-500">Kewenangan: PP &amp; PPK</span>
        </h2>
        <p class="text-sm text-slate-600 mb-4">
            Pejabat Pengadaan dan PPK menelaah kelengkapan spesifikasi teknis, rancangan kontrak, dan rincian HPS.
            Jika berkas memenuhi syarat, berikan persetujuan (otomatis mengunci MAK saat disetujui PPK). Jika terdapat kekurangan, kembalikan ke PPBJ dengan catatan perbaikan.
        </p>

        @if($paketReviu->isEmpty())
            <div class="text-center py-6 bg-slate-50 rounded border border-dashed border-slate-200">
                <p class="text-sm text-slate-500">Saat ini tidak ada antrean paket pengadaan yang memerlukan reviu dokumen pada akun Anda.</p>
            </div>
        @else
            <form action="{{ route('kontrak.reviu') }}" method="POST" class="space-y-4 mb-6 p-4 bg-slate-50 rounded border border-slate-200">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="nomor_paket_reviu" class="block text-xs font-semibold text-slate-700 mb-1">
                            Pilih Paket Pengadaan yang Akan Ditelaah <span class="text-rose-500">*</span>
                        </label>
                        <select id="nomor_paket_reviu" name="nomor_paket" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                            <option value="">-- Pilih Paket Pengadaan --</option>
                            @foreach($paketReviu as $pktR)
                                <option value="{{ $pktR->nomor_paket }}">
                                    [{{ $pktR->nomor_paket }}] {{ $pktR->nama_paket }} &bull; HPS: Rp {{ number_format($pktR->nilai_hps, 0, ',', '.') }} &bull; Status: {{ strtoupper(str_replace('_', ' ', $pktR->status_paket)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-slate-700 mb-2">Keputusan Hasil Penelaahan <span class="text-rose-500">*</span></span>
                        <div class="space-y-2">
                            <label class="flex items-center text-xs text-slate-800 cursor-pointer">
                                <input type="radio" id="reviu_setuju" name="status_reviu" value="disetujui" checked class="text-sky-600 focus:ring-sky-500 mr-2">
                                <span><strong>Disetujui</strong> - Dokumen teknis dan HPS lengkap, paket siap diterbitkan Kontrak SPK.</span>
                            </label>
                            <label class="flex items-center text-xs text-slate-800 cursor-pointer">
                                <input type="radio" id="reviu_perbaikan" name="status_reviu" value="perlu_perbaikan" class="text-rose-600 focus:ring-rose-500 mr-2">
                                <span><strong>Perlu Perbaikan</strong> - Mengembalikan berkas ke PPBJ disertai rincian catatan revisi.</span>
                            </label>
                        </div>
                    </div>

                    @if(Auth::user()->role === 'pp')
                        <div>
                            <label for="nilai_hasil_negosiasi" class="block text-xs font-semibold text-slate-700 mb-1">
                                Nilai Hasil Negosiasi Harga (Rp) <span class="text-slate-400 font-normal">(Khusus Pejabat Pengadaan)</span>
                            </label>
                            <input type="number" id="nilai_hasil_negosiasi" name="nilai_hasil_negosiasi" step="0.01" min="0" placeholder="Contoh: 142000000" class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono focus:ring-sky-500 focus:border-sky-500">
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <label for="catatan_perbaikan" class="block text-xs font-semibold text-slate-700 mb-1">
                            Catatan Rekomendasi / Rincian Perbaikan <span class="text-slate-400 font-normal">(Wajib jika memilih 'Perlu Perbaikan')</span>
                        </label>
                        <textarea id="catatan_perbaikan" name="catatan_perbaikan" rows="2" placeholder="Cantumkan poin spesifikasi teknis atau rincian HPS yang perlu disesuaikan..." class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500"></textarea>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-5 py-2 bg-slate-800 text-white rounded text-xs font-bold hover:bg-slate-900 transition shadow">
                        Kirim Keputusan Penelaahan Dokumen
                    </button>
                </div>
            </form>

            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Riwayat &amp; Status Telaah Antrean:</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold uppercase tracking-wider">
                            <th class="p-2 border-b">Nomor Paket</th>
                            <th class="p-2 border-b">Nama Paket</th>
                            <th class="p-2 border-b">Nilai HPS</th>
                            <th class="p-2 border-b">Status Paket</th>
                            <th class="p-2 border-b text-center">Putaran</th>
                            <th class="p-2 border-b">Hasil Telaah</th>
                            <th class="p-2 border-b">Catatan Telaah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($paketReviu as $pr)
                            @php
                                $latestReviu = $pr->reviuDokumen->sortByDesc('putaran_ke')->first();
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="p-2 font-mono font-bold text-sky-800">{{ $pr->nomor_paket }}</td>
                                <td class="p-2 text-slate-800">{{ $pr->nama_paket }}</td>
                                <td class="p-2 font-mono text-emerald-700 font-semibold">Rp {{ number_format($pr->nilai_hps, 0, ',', '.') }}</td>
                                <td class="p-2">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                                        {{ strtoupper(str_replace('_', ' ', $pr->status_paket)) }}
                                    </span>
                                </td>
                                <td class="p-2 text-center font-mono">{{ $latestReviu ? 'Ke-'.$latestReviu->putaran_ke : '-' }}</td>
                                <td class="p-2 font-semibold">
                                    @if($latestReviu)
                                        <span class="{{ $latestReviu->status_reviu === 'disetujui' ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ strtoupper(str_replace('_', ' ', $latestReviu->status_reviu)) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Menunggu Telaah</span>
                                    @endif
                                </td>
                                <td class="p-2 text-slate-600">{{ $latestReviu->catatan_perbaikan ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 2. Formulir Penerbitan SPK Baru & Penguncian MAK (FR 11 & NFR 6) --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-2 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>2. Penerbitan Kontrak / Surat Perintah Kerja (SPK)</span>
            <span class="text-xs font-normal text-slate-500">Penerbit Tunggal: PPBJ</span>
        </h2>

        <div class="p-3 bg-amber-50 border-l-4 border-amber-500 rounded text-amber-900 text-xs mb-4">
            <strong>Pemberitahuan Sistem Penguncian Anggaran (FR 11 &amp; NFR 6):</strong>
            Penerbitan dokumen Kontrak SPK merupakan perikatan hukum definitif. Sistem secara otomatis akan <strong>mengunci seluruh data Mata Anggaran Kegiatan (MAK)</strong> yang mendasari paket ini sehingga tidak dapat diubah atau dihapus lagi.
        </div>

        <form action="{{ route('kontrak.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="kode_paket_spk" class="block text-xs font-semibold text-slate-700 mb-1">
                        Pilih Paket Pengadaan (Lolos Telaah) <span class="text-rose-500">*</span>
                    </label>
                    <select id="kode_paket_spk" name="kode_paket" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach($paketList as $pkt)
                            <option value="{{ $pkt->nomor_paket }}">
                                [{{ $pkt->nomor_paket }}] {{ $pkt->nama_paket }} &bull; Nilai HPS: Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }} (Status: {{ $pkt->status_paket }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="id_vendor" class="block text-xs font-semibold text-slate-700 mb-1">
                        Pilih Rekanan Penyedia / Vendor Terdaftar <span class="text-rose-500">*</span>
                    </label>
                    <select id="id_vendor" name="id_vendor" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Rekanan Vendor PNJ --</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id_vendor }}">
                                {{ $v->nama_vendor }} (NPWP: {{ $v->npwp ?? '-' }}) &bull; Bentuk: {{ $v->bentuk_usaha ?? 'CV/PT' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="nomor_kontrak_spk" class="block text-xs font-semibold text-slate-700 mb-1">
                        Nomor Resmi SPK / Kontrak <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nomor_kontrak_spk" name="nomor_kontrak_spk" value="SPK/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="nilai_kontrak" class="block text-xs font-semibold text-slate-700 mb-1">
                        Nilai Kesepakatan Kontrak (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="nilai_kontrak" name="nilai_kontrak" step="0.01" min="1" required placeholder="Contoh: 145000000" class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="tanggal_mulai" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Mulai Kontrak <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="tanggal_selesai" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Jatuh Tempo / Selesai Kontrak <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="file_spk" class="block text-xs font-semibold text-slate-700 mb-1">
                        Unggah Salinan Dokumen Kontrak / SPK (PDF Max 10MB)
                    </label>
                    <input type="file" id="file_spk" name="file_spk" accept="application/pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                </div>

                <div>
                    <label for="catatan_spk" class="block text-xs font-semibold text-slate-700 mb-1">
                        Uraian Pekerjaan / Ketentuan Kontrak
                    </label>
                    <textarea id="catatan_spk" name="catatan_spk" rows="2" placeholder="Pengadaan perangkat lab lengkap dengan garansi resmi dan uji fungsi." class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-6 py-2.5 bg-sky-700 text-white rounded text-xs font-bold hover:bg-sky-800 transition shadow">
                    Terbitkan &amp; Tanda Tangani Kontrak SPK (Kunci MAK)
                </button>
            </div>
        </form>
    </div>

    {{-- 3. Daftar Kontrak SPK yang Telah Diterbitkan --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>3. Daftar Kontrak SPK yang Telah Diterbitkan</span>
            <span class="text-xs font-normal text-slate-500">Total: {{ $kontrakList->total() }} Kontrak</span>
        </h2>

        @if($kontrakList->isEmpty())
            <div class="text-center py-6 bg-slate-50 rounded border border-dashed border-slate-200">
                <p class="text-sm text-slate-500">Belum ada Kontrak SPK yang diterbitkan untuk paket penugasan Anda.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold uppercase tracking-wider">
                            <th class="p-3 border-b">Nomor Kontrak SPK</th>
                            <th class="p-3 border-b">Nomor Paket</th>
                            <th class="p-3 border-b">Penyedia / Vendor</th>
                            <th class="p-3 border-b">Nilai Kontrak</th>
                            <th class="p-3 border-b">Masa Berlaku</th>
                            <th class="p-3 border-b">Status</th>
                            <th class="p-3 border-b text-center">Salinan Dokumen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($kontrakList as $k)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-mono font-bold text-sky-800">{{ $k->nomor_spk }}</td>
                                <td class="p-3 font-mono text-slate-700">{{ $k->nomor_paket }}</td>
                                <td class="p-3 font-semibold text-slate-800">{{ $k->vendor->nama_vendor ?? '-' }}</td>
                                <td class="p-3 font-mono font-bold text-emerald-700">Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}</td>
                                <td class="p-3 text-slate-600">{{ date('d-m-Y', strtotime($k->tanggal_mulai)) }} s.d {{ date('d-m-Y', strtotime($k->tanggal_selesai)) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        {{ strtoupper($k->status_spk ?? 'AKTIF') }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'spk', 'id' => $k->nomor_spk]) }}" class="inline-block px-3 py-1 bg-slate-700 text-white rounded hover:bg-slate-800 transition font-semibold text-xs">
                                        Unduh SPK (PDF)
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $kontrakList->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
